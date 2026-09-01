<?php

namespace App\Orders;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Encryption\EncrypterInterface;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class OrderTransitionWorkflow
{
    public function __construct(private BaseConnection $db, private ?EncrypterInterface $encrypter)
    {
    }

    public function transition(
        int $actorRole,
        ?int $actorBranch,
        mixed $rawIds,
        string $mode,
        mixed $rawValue,
        mixed $rawLogisticsDetail = null,
    ): string
    {
        $ids = $this->ids($rawIds);
        $value = is_string($rawValue) && preg_match('/\A[1-9][0-9]*\z/D', $rawValue) === 1 ? (int) $rawValue : null;
        if ($ids === null || $value === null || ! in_array($mode, ['provider', 'status', 'deliver'], true)) {
            return 'invalid';
        }
        $isCustomProvider = $mode === 'provider' && $value === 9999;
        if ($isCustomProvider && $rawLogisticsDetail !== null && ! is_string($rawLogisticsDetail)) {
            return 'invalid';
        }
        $logisticsDetail = $isCustomProvider && is_string($rawLogisticsDetail) ? $rawLogisticsDetail : '';
        if ($mode === 'provider' && ! $isCustomProvider
            && $this->db->table('provider')->where('provider_id', $value)->countAllResults() !== 1) {
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
            // Branch users lose manual control of the TRANSPORTING (2) / STATUS REPAIR (3) queues:
            // manual transitions (all manual transition endpoints), the queue 2/3 listings
            // (/orders?status=2|3) and soft-delete are closed to them, so deny before the state check
            // to answer 403 not 409. This is not an app-wide invariant:
            // the Excel status import (app/Imports/ImportWorkflow.php) still lets branches move orders
            // through those states on purpose, matching the CI3 UPLOAD STATUS menu.
            if ($actorBranch !== null && ($from === 2 || $from === 3)) {
                return 'forbidden';
            }
            $allowed = match ($mode) {
                'provider' => $from === 1,
                'status' => ($from === 2 && in_array($value, [3, 4], true)) || ($from === 3 && $value === 4) || ($from === 5 && $value === 7),
                'deliver' => $from === 4 && $value === 5,
            };
            if (! $allowed) {
                return 'conflict';
            }
        }

        $now = new DateTimeImmutable('now');
        $timestamp = $now->format('Y-m-d H:i:s');
        $isComplete = $mode === 'status' && $value === 7;
        $needsSmsStore = $mode === 'deliver' || $isComplete;
        $store = $needsSmsStore && $this->encrypter !== null
            ? new SmsDeliveryIntentStore($this->db, $this->encrypter)
            : null;
        $this->db->transBegin();
        try {
            foreach ($rows as $row) {
                $target = $mode === 'provider' ? 2 : $value;
                $updates = ['action_status' => $target, 'date_update_status' => $timestamp];
                if ($mode === 'provider') {
                    $updates['provider_id'] = $isCustomProvider ? 0 : $value;
                    $updates['date_create'] = $timestamp;
                    if ($isCustomProvider) {
                        $updates['logistics_etc_detail'] = $logisticsDetail;
                    }
                } elseif ($mode === 'deliver') {
                    $updates['date_deliver'] = $timestamp;
                } elseif ($isComplete) {
                    $updates['date_complete'] = $timestamp;
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
                if ($mode === 'deliver') {
                    $store?->enqueue(
                        (int) $row['request_id'], (string) $row['trackID'], (string) $row['customerTel'],
                        OrderSmsMessages::returned((string) $row['trackID']),
                        md5('sms-return:' . (string) $row['request_id']), $now,
                    );
                } elseif ($isComplete) {
                    $store?->enqueue(
                        (int) $row['request_id'], (string) $row['trackID'], (string) $row['customerTel'],
                        OrderSmsMessages::completed((string) $row['trackID']),
                        md5('sms-complete:' . (string) $row['request_id']), $now,
                    );
                }
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
