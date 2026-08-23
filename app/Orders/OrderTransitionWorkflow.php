<?php

namespace App\Orders;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Encryption\EncrypterInterface;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class OrderTransitionWorkflow
{
    public function __construct(private BaseConnection $db, private EncrypterInterface $encrypter)
    {
    }

    public function transition(int $actorRole, ?int $actorBranch, mixed $rawIds, string $mode, mixed $rawValue): string
    {
        $ids = $this->ids($rawIds);
        $value = is_string($rawValue) && preg_match('/\A[1-9][0-9]*\z/D', $rawValue) === 1 ? (int) $rawValue : null;
        if ($ids === null || $value === null || ! in_array($mode, ['provider', 'status', 'deliver'], true)) {
            return 'invalid';
        }
        if ($mode === 'provider' && $this->db->table('provider')->where('provider_id', $value)->countAllResults() !== 1) {
            return 'invalid';
        }
        $query = $this->db->table('request_order')
            ->select('request_id, trackID, action_status, branchID, customerTel')
            ->whereIn('request_id', $ids);
        if ($actorRole !== 1) {
            $query->where('branchID', $actorBranch);
        }
        $rows = $query->get()->getResultArray();
        if (count($rows) !== count($ids)) {
            return 'not_found';
        }
        foreach ($rows as $row) {
            $from = (int) $row['action_status'];
            $allowed = match ($mode) {
                'provider' => $from === 1,
                'status' => ($from === 2 && in_array($value, [3, 4], true)) || ($from === 3 && $value === 4),
                'deliver' => $from === 4 && $value === 5,
            };
            if (! $allowed) {
                return 'conflict';
            }
        }

        $now = new DateTimeImmutable('now');
        $timestamp = $now->format('Y-m-d H:i:s');
        $store = $mode === 'deliver' ? new SmsDeliveryIntentStore($this->db, $this->encrypter) : null;
        $this->db->transBegin();
        try {
            foreach ($rows as $row) {
                $target = $mode === 'provider' ? 2 : $value;
                $updates = ['action_status' => $target, 'date_update_status' => $timestamp];
                if ($mode === 'provider') {
                    $updates['provider_id'] = $value;
                    $updates['date_create'] = $timestamp;
                } elseif ($mode === 'deliver') {
                    $updates['date_deliver'] = $timestamp;
                }
                $updated = $this->db->table('request_order')
                    ->where('request_id', $row['request_id'])
                    ->where('action_status', $row['action_status'])
                    ->update($updates);
                if (! $updated || $this->db->affectedRows() !== 1) {
                    throw new RuntimeException('Order changed concurrently.');
                }
                if (! $this->db->table('status_log')->insert([
                    'order_id' => $row['trackID'], 'action_id' => $target, 'update_id' => null, 'cdate' => $timestamp,
                ])) {
                    throw new RuntimeException('Unable to log order transition.');
                }
                $store?->enqueue(
                    (int) $row['request_id'], (string) $row['trackID'], (string) $row['customerTel'],
                    OrderSmsMessages::returned((string) $row['trackID']),
                    md5('sms-return:' . (string) $row['request_id']), $now,
                );
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit order transition.');
            }
        } catch (Throwable) {
            $this->db->transRollback();

            return 'failed';
        }

        return 'updated';
    }

    /** @return list<int>|null */
    private function ids(mixed $raw): ?array
    {
        if (! is_array($raw) || $raw === [] || count($raw) > 100) {
            return null;
        }
        $ids = [];
        foreach ($raw as $value) {
            if (! is_string($value) || preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) {
                return null;
            }
            $ids[(int) $value] = (int) $value;
        }

        return count($ids) === count($raw) ? array_values($ids) : null;
    }
}
