<?php

namespace App\Imports;

use App\Orders\OrderSequence;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Encryption\EncrypterInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ImportWorkflow
{
    private const HEADERS = ['order_id', 'customer_name', 'telephone', 'updated_at', 'status', 'repair_started_at', 'repair_price', 'warranty', 'number_cmg'];

    public function __construct(private BaseConnection $db, private EncrypterInterface $encrypter)
    {
    }

    /** @return array{batch_id: string, accepted: int, rejected: int, rows: list<array{row: int, accepted: bool, error: string|null}>} */
    public function preview(string $kind, int $ownerId, ?int $ownerBranch, string $path): array
    {
        if (! in_array($kind, ['status', 'price', 'new-order'], true) || $ownerId < 1 || $ownerBranch === null) {
            throw new InvalidArgumentException('Invalid import owner.');
        }
        $rows = (new XlsxReader())->rows($path);
        $headers = array_map(static fn (string $value): string => strtolower(trim($value)), array_shift($rows));
        if ($headers !== self::HEADERS || $rows === []) {
            throw new InvalidArgumentException('Invalid import headers or empty workbook.');
        }
        $batchId = bin2hex(random_bytes(16));
        $preview = [];
        $stored = [];
        $accepted = 0;
        foreach ($rows as $offset => $cells) {
            $payload = array_combine(self::HEADERS, array_map('trim', $cells));
            if (! is_array($payload)) {
                throw new RuntimeException('Unable to map import row.');
            }
            [$valid, $error, $normalized] = $this->validateRow($kind, $ownerBranch, $payload);
            $accepted += $valid ? 1 : 0;
            $rowNumber = $offset + 2;
            $preview[] = ['row' => $rowNumber, 'accepted' => $valid, 'error' => $error];
            $stored[] = [
                'batch_id' => $batchId, 'row_number' => $rowNumber, 'accepted' => $valid ? 1 : 0,
                'error_code' => $error,
                'payload_ciphertext' => base64_encode($this->encrypter->encrypt(json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))),
            ];
        }
        $timestamp = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            if (! $this->db->table('ci4_import_batches')->insert([
                'batch_id' => $batchId, 'kind' => $kind, 'owner_user_id' => $ownerId,
                'owner_branch_id' => $ownerBranch, 'state' => 'previewed', 'file_sha256' => hash_file('sha256', $path),
                'row_count' => count($rows), 'accepted_count' => $accepted, 'rejected_count' => count($rows) - $accepted,
                'created_at' => $timestamp,
            ]) || ! $this->db->table('ci4_import_rows')->insertBatch($stored)
                || ! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Unable to store import preview.');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ['batch_id' => $batchId, 'accepted' => $accepted, 'rejected' => count($rows) - $accepted, 'rows' => $preview];
    }

    public function confirm(string $kind, string $batchId, int $ownerId, ?int $ownerBranch): string
    {
        if (! in_array($kind, ['status', 'price', 'new-order'], true)
            || preg_match('/\A[a-f0-9]{32}\z/D', $batchId) !== 1 || $ownerId < 1 || $ownerBranch === null) {
            return 'invalid';
        }
        $owned = static fn ($query) => $query
            ->where('batch_id', $batchId)->where('kind', $kind)
            ->where('owner_user_id', $ownerId)->where('owner_branch_id', $ownerBranch);
        $batch = $owned($this->db->table('ci4_import_batches'))->get()->getRowArray();
        if ($batch === null) {
            return 'not_found';
        }
        if ($batch['state'] === 'confirmed') {
            return 'replayed';
        }
        if ($batch['state'] !== 'previewed') {
            return 'conflict';
        }

        $timestamp = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $claimed = $owned($this->db->table('ci4_import_batches'))
                ->where('state', 'previewed')->update(['state' => 'confirming']);
            if (! $claimed || $this->db->affectedRows() !== 1) {
                $this->db->transRollback();
                $state = $owned($this->db->table('ci4_import_batches'))->get()->getRow('state');

                return $state === 'confirmed' ? 'replayed' : 'conflict';
            }
            $rows = $this->db->table('ci4_import_rows')
                ->where('batch_id', $batchId)->where('accepted', 1)->orderBy('row_number', 'ASC')->get()->getResultArray();
            foreach ($rows as $row) {
                $payload = $this->decrypt((string) $row['payload_ciphertext']);
                match ($kind) {
                    'status' => $this->confirmStatus($payload, $ownerId, $ownerBranch, $timestamp),
                    'price' => $this->confirmPrice($payload, $ownerBranch),
                    'new-order' => $this->confirmNewOrder($payload, $ownerId, $ownerBranch, $timestamp),
                };
            }
            $confirmed = $owned($this->db->table('ci4_import_batches'))
                ->where('state', 'confirming')->update(['state' => 'confirmed', 'confirmed_at' => $timestamp]);
            if (! $confirmed || $this->db->affectedRows() !== 1
                || ! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Unable to confirm import batch.');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return 'confirmed';
    }

    /** @param array<string, string> $payload @return array{bool, string|null, array<string, mixed>} */
    private function validateRow(string $kind, int $branchId, array $payload): array
    {
        $price = str_replace(',', '', $payload['repair_price']);
        if (! is_numeric($price) || (float) $price < 0 || (float) $price > 9_999_999.99
            || preg_match('/\A[0-9]{10,20}\z/D', $payload['telephone']) !== 1
            || $this->date($payload['updated_at']) === null || $this->date($payload['repair_started_at']) === null) {
            return [false, 'invalid_row', $payload];
        }
        $status = $this->db->table('tracking_status')
            ->where('description_en', $payload['status'])->get()->getRowArray();
        if ($status === null) {
            return [false, 'unknown_status', $payload];
        }
        $normalized = [
            ...$payload, 'repair_price' => number_format((float) $price, 2, '.', ''),
            'updated_at' => $this->date($payload['updated_at']),
            'repair_started_at' => $this->date($payload['repair_started_at']),
            'tracking_status_id' => (int) $status['status_id'], 'success' => (int) $status['success'],
            'branch_id' => $branchId,
        ];
        if ($kind === 'new-order') {
            if (preg_match('#\A[^/]{1,10}/[A-Za-z0-9._-]{1,100}\z#D', $payload['order_id']) !== 1
                || $payload['customer_name'] === '' || mb_strlen($payload['customer_name']) > 250
                || $this->db->table('request_order')->where('orderIDShow', $payload['order_id'])->countAllResults() !== 0) {
                return [false, 'invalid_new_order', $normalized];
            }

            return [true, null, $normalized];
        }
        $query = $this->db->table('request_order')->select('request_id, trackID, action_status')
            ->where('branchID', $branchId);
        if ($kind === 'price') {
            $query->where('number_cmg', $payload['number_cmg']);
        } else {
            $query->where('orderIDShow', $payload['order_id'])->where('customerTel', $payload['telephone']);
        }
        $order = $query->get()->getRowArray();
        if ($order === null || ($kind === 'status' && ! in_array((int) $order['action_status'], [2, 3], true))
            || ($kind === 'price' && (float) $price <= 0)) {
            return [false, 'order_not_eligible', $normalized];
        }

        return [true, null, [
            ...$normalized, 'request_id' => (int) $order['request_id'], 'track_id' => (string) $order['trackID'],
            'original_action_status' => (int) $order['action_status'],
        ]];
    }

    /** @return array<string, mixed> */
    private function decrypt(string $ciphertext): array
    {
        $decoded = base64_decode($ciphertext, true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid import row ciphertext.');
        }
        $payload = json_decode($this->encrypter->decrypt($decoded), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw new RuntimeException('Invalid import row payload.');
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function confirmStatus(array $payload, int $ownerId, int $branchId, string $timestamp): void
    {
        $this->assertPayload($payload, $branchId);
        $success = (int) $payload['success'] === 1;
        $updates = [
            'RepairPrice' => $payload['repair_price'], 'date_update_status' => $payload['updated_at'],
            'action_status' => $success ? 4 : 3, 'waranty_cmg' => $payload['warranty'],
            'number_cmg' => $payload['number_cmg'],
        ];
        if ($success) {
            $updates['date_repair'] = $payload['repair_started_at'];
            $updates['date_repair_complete'] = $payload['updated_at'];
        } elseif (strtoupper((string) $payload['warranty']) === 'OUT') {
            $updates['date_repair'] = $payload['repair_started_at'];
            $updates['date_repair_waranty'] = $payload['updated_at'];
        }
        $updated = $this->db->table('request_order')
            ->where('request_id', $payload['request_id'])->where('trackID', $payload['track_id'])
            ->where('branchID', $branchId)->where('action_status', $payload['original_action_status'])->update($updates);
        if (! $updated || $this->db->affectedRows() !== 1
            || ! $this->db->table('status_log')->insert([
                'order_id' => $payload['track_id'], 'action_id' => null,
                'update_id' => $payload['tracking_status_id'], 'cdate' => $timestamp,
            ])
            || ! $this->db->table('uploadstaus')->insert([
                'tracking_id' => str_replace('/', '', (string) $payload['order_id']),
                'Listname' => $payload['customer_name'], 'Telephone' => $payload['telephone'],
                'updatetime' => $payload['updated_at'], 'startdate' => $payload['repair_started_at'],
                'Userstatus' => $payload['status'], 'tracking_status' => $payload['tracking_status_id'],
                'cdate' => substr($timestamp, 0, 10), 'user_id' => $ownerId,
                'RepairPrice' => $payload['repair_price'], 'waranty_cmg' => $payload['warranty'],
                'number_cmg' => $payload['number_cmg'],
            ])) {
            throw new RuntimeException('Unable to confirm status import row.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function confirmPrice(array $payload, int $branchId): void
    {
        $this->assertPayload($payload, $branchId);
        $updated = $this->db->table('request_order')
            ->where('request_id', $payload['request_id'])->where('trackID', $payload['track_id'])
            ->where('branchID', $branchId)->where('number_cmg', $payload['number_cmg'])
            ->update(['RepairPrice' => $payload['repair_price']]);
        if (! $updated || $this->db->affectedRows() !== 1) {
            throw new RuntimeException('Unable to confirm price import row.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function confirmNewOrder(array $payload, int $ownerId, int $branchId, string $timestamp): void
    {
        $this->assertPayload($payload, $branchId, false);
        if ($this->db->table('request_order')->where('orderIDShow', $payload['order_id'])->countAllResults() !== 0) {
            throw new RuntimeException('New order import changed after preview.');
        }
        $branch = $this->db->table('branch')->select('branch_type')->where('branch_id', $branchId)->get()->getRowArray();
        $parts = explode('/', (string) $payload['order_id'], 2);
        if ($branch === null || count($parts) !== 2 || $parts[1] === '') {
            throw new RuntimeException('Invalid new order import branch.');
        }
        $now = new DateTimeImmutable('now');
        $trackId = (new OrderSequence($this->db))->next($now);
        $action = (int) $payload['success'] === 1 ? 4 : 2;
        $order = [
            'requestDate' => $payload['updated_at'], 'trackID' => $trackId, 'numberID' => $parts[1],
            'orderID' => str_replace('/', '', (string) $payload['order_id']), 'orderIDShow' => $payload['order_id'],
            'customerFullname' => $payload['customer_name'], 'customerTel' => $payload['telephone'],
            'branchID' => $branchId, 'branch_type_id' => (int) $branch['branch_type'], 'UserID' => $ownerId,
            'date_create' => $timestamp, 'date_repair' => $payload['repair_started_at'],
            'date_update_status' => $payload['updated_at'], 'action_status' => $action,
            'RepairPrice' => $payload['repair_price'], 'waranty_cmg' => $payload['warranty'],
            'number_cmg' => $payload['number_cmg'], 'create_by_user' => (string) $ownerId,
        ];
        if ($action === 4) {
            $order['date_repair_complete'] = $payload['updated_at'];
        }
        if (! $this->db->table('request_order')->insert($order)
            || ! $this->db->table('status_log')->insert([
                'order_id' => $trackId, 'action_id' => $action, 'update_id' => null, 'cdate' => $timestamp,
            ])) {
            throw new RuntimeException('Unable to confirm new order import row.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertPayload(array $payload, int $branchId, bool $existing = true): void
    {
        $required = ['order_id', 'customer_name', 'telephone', 'updated_at', 'status', 'repair_started_at', 'repair_price', 'warranty', 'number_cmg', 'tracking_status_id', 'success', 'branch_id'];
        if ($existing) {
            array_push($required, 'request_id', 'track_id', 'original_action_status');
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $payload)) {
                throw new RuntimeException('Incomplete import row payload.');
            }
        }
        if ((int) $payload['branch_id'] !== $branchId || ! is_string($payload['repair_price'])
            || ! is_numeric($payload['repair_price']) || ! is_string($payload['updated_at'])
            || ! is_string($payload['repair_started_at']) || ! in_array((int) $payload['success'], [0, 1], true)
            || ($existing && ((int) $payload['request_id'] < 1 || ! is_string($payload['track_id'])))) {
            throw new RuntimeException('Invalid import row payload.');
        }
    }

    private function date(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!d/m/Y', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('d/m/Y') === $value ? $date->format('Y-m-d 00:00:00') : null;
    }
}
