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

    /** @return array{batch_id: string, accepted: int, rejected: int, rows: list<array{row: int, accepted: bool, error: string|null, data: array<string, mixed>}>} */
    public function preview(
        string $kind,
        int $ownerId,
        ?int $ownerBranch,
        string $path,
        string $extension,
        bool $legacyContract = false,
    ): array
    {
        if (! in_array($kind, ['status', 'price', 'new-order'], true) || $ownerId < 1
            || ($kind === 'new-order' && $ownerBranch === null && ! $legacyContract)) {
            throw new InvalidArgumentException('Invalid import owner.');
        }
        $rows = ($extension === 'xls' ? new XlsReader() : new XlsxReader())->rows($path);
        $headers = array_map(static fn (string $value): string => strtolower(trim($value)), array_shift($rows));
        if ($legacyContract && $kind === 'new-order') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $cells): bool => trim((string) ($cells[0] ?? '')) !== '',
            ));
        }
        if ($headers !== self::HEADERS || $rows === []) {
            throw new InvalidArgumentException('Invalid import headers or empty workbook.');
        }
        $batchId = bin2hex(random_bytes(16));
        $preview = [];
        $stored = [];
        $accepted = 0;
        foreach ($rows as $offset => $cells) {
            $rawPayload = array_combine(self::HEADERS, $cells);
            $payload = array_combine(self::HEADERS, array_map('trim', $cells));
            if (! is_array($payload)) {
                throw new RuntimeException('Unable to map import row.');
            }
            [$valid, $error, $normalized] = $this->validateRow(
                $kind,
                $ownerBranch,
                $payload,
                $legacyContract,
            );
            if ($legacyContract && $kind === 'new-order') {
                $normalized['_legacy_customer_name'] = $rawPayload['customer_name'];
                if (mb_strlen($rawPayload['customer_name']) > 250) {
                    $valid = false;
                    $error = 'invalid_new_order';
                }
            }
            $accepted += $valid ? 1 : 0;
            $rowNumber = $offset + 2;
            $displayPayload = $legacyContract ? $rawPayload : $payload;
            if ($legacyContract) {
                $displayPayload['warranty'] = trim($displayPayload['warranty']);
                $displayPayload['number_cmg'] = trim($displayPayload['number_cmg']);
            }
            $preview[] = [
                'row' => $rowNumber, 'accepted' => $valid, 'error' => $error,
                'data' => [...$displayPayload, '_track_id' => $normalized['track_id'] ?? '',
                    '_action_status' => $normalized['original_action_status'] ?? 0],
            ];
            $stored[] = [
                'batch_id' => $batchId, 'row_number' => $rowNumber, 'accepted' => $valid ? 1 : 0,
                'error_code' => $error,
                'payload_ciphertext' => base64_encode($this->encrypter->encrypt(json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))),
            ];
        }
        $fileSha = hash_file('sha256', $path);
        if ($fileSha === false) {
            throw new RuntimeException('Unable to hash import file.');
        }
        $timestamp = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            if (! $this->db->table('ci4_import_batches')->insert([
                'batch_id' => $batchId, 'kind' => $kind, 'owner_user_id' => $ownerId,
                'owner_branch_id' => $ownerBranch, 'state' => 'previewed', 'file_sha256' => $fileSha,
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
        $this->storeSourceFile($path, $fileSha, $extension);

        return ['batch_id' => $batchId, 'accepted' => $accepted, 'rejected' => count($rows) - $accepted, 'rows' => $preview];
    }

    public function confirm(string $kind, string $batchId, int $ownerId, ?int $ownerBranch): string
    {
        if (! in_array($kind, ['status', 'price', 'new-order'], true)
            || preg_match('/\A[a-f0-9]{32}\z/D', $batchId) !== 1 || $ownerId < 1) {
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
        if ((int) $batch['accepted_count'] < 1) {
            return 'invalid';
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
                $payloadBranch = is_numeric($payload['branch_id'] ?? null) ? (int) $payload['branch_id'] : 0;
                if ($payloadBranch < 1 || ($ownerBranch !== null && $payloadBranch !== $ownerBranch)) {
                    throw new RuntimeException('Import row branch changed after preview.');
                }
                $effectiveBranch = $ownerBranch ?? $payloadBranch;
                match ($kind) {
                    'status' => $this->confirmStatus($payload, $ownerId, $effectiveBranch, $timestamp),
                    'price' => $this->confirmPrice($payload, $effectiveBranch),
                    'new-order' => $this->confirmNewOrder($payload, $ownerId, $effectiveBranch, $timestamp),
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

    private function storeSourceFile(string $path, string $sha, string $extension): void
    {
        $directory = WRITEPATH . 'uploads/imports';
        $target = $directory . '/' . $sha . '.' . $extension;
        if (is_file($target)) {
            return;
        }
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('Import file storage unavailable.');
        }
        if (! copy($path, $target)) {
            throw new RuntimeException('Import file storage unavailable.');
        }
        chmod($target, 0640);
    }

    /** @param array<string, string> $payload @return array{bool, string|null, array<string, mixed>} */
    private function validateRow(
        string $kind,
        ?int $branchId,
        array $payload,
        bool $legacyContract,
    ): array
    {
        $price = str_replace(',', '', $payload['repair_price']);
        if ($kind === 'price' && $legacyContract) {
            return $this->validatePriceRow($branchId, $payload, $price, true);
        }
        if (! is_numeric($price) || (float) $price < 0 || (float) $price > 9_999_999.99
            || preg_match('/\A[0-9]{10,20}\z/D', $payload['telephone']) !== 1
            || $this->date($payload['updated_at']) === null || $this->date($payload['repair_started_at']) === null) {
            return [false, 'invalid_row', $payload];
        }
        if ($kind === 'new-order' && $legacyContract) {
            $statusMatches = $this->db->table('tracking_status')
                ->like('description_en', $payload['status'], 'both')
                ->limit(2)
                ->get()
                ->getResultArray();
            $status = count($statusMatches) === 1 ? $statusMatches[0] : null;
        } else {
            $status = $this->db->table('tracking_status')
                ->where('description_en', $payload['status'])->get()->getRowArray();
        }
        if ($status === null) {
            return [false, 'unknown_status', $payload];
        }
        $normalized = [
            ...$payload, 'repair_price' => number_format((float) $price, 2, '.', ''),
            'updated_at' => $this->date($payload['updated_at']),
            'repair_started_at' => $this->date($payload['repair_started_at']),
            'tracking_status_id' => (int) $status['status_id'], 'success' => (int) $status['success'],
            'branch_id' => $branchId, '_legacy_contract' => $legacyContract,
            '_legacy_updated_at' => $payload['updated_at'],
            '_legacy_repair_started_at' => $payload['repair_started_at'],
        ];
        if ($kind === 'new-order') {
            if (preg_match('#\A[^/]{1,10}/[A-Za-z0-9._-]{1,100}\z#D', $payload['order_id']) !== 1
                || $payload['customer_name'] === '' || mb_strlen($payload['customer_name']) > 250
                || $this->db->table('request_order')->where('orderIDShow', $payload['order_id'])->countAllResults() !== 0) {
                return [false, 'invalid_new_order', $normalized];
            }
            if ($branchId === null) {
                $derivedBranch = $legacyContract
                    ? $this->db->table('branch')->select('branch_id')->where('branch_id', 90)->get()->getRow('branch_id')
                    : null;
                if (! is_numeric($derivedBranch)) {
                    $prefix = explode('/', $payload['order_id'], 2)[0];
                    $derivedBranch = $this->db->table('branch')
                        ->select('branch_id')->where('default_suffix', $prefix)->get()->getRow('branch_id');
                }
                if (! is_numeric($derivedBranch) || (int) $derivedBranch < 1) {
                    return [false, 'invalid_new_order_branch', $normalized];
                }
                $normalized['branch_id'] = (int) $derivedBranch;
            }

            return [true, null, $normalized];
        }
        $query = $this->db->table('request_order')->select('request_id, trackID, action_status, branchID');
        if ($branchId !== null) {
            $query->where('branchID', $branchId);
        }
        if ($kind === 'price') {
            $query->where('number_cmg', $payload['number_cmg']);
        } else {
            $query->where('orderIDShow', $payload['order_id'])->where('customerTel', $payload['telephone']);
        }
        $order = $query->get()->getRowArray();
        if ($order === null) {
            return [false, 'order_not_eligible', $normalized];
        }
        $normalized = [
            ...$normalized, 'request_id' => (int) $order['request_id'], 'track_id' => (string) $order['trackID'],
            'original_action_status' => (int) $order['action_status'], 'branch_id' => (int) $order['branchID'],
        ];
        if (($kind === 'status' && ! in_array((int) $order['action_status'], [2, 3], true))
            || ($kind === 'price' && (float) $price <= 0)) {
            return [false, 'order_not_eligible', $normalized];
        }

        return [true, null, $normalized];
    }

    /** @param array<string, string> $payload @return array{bool, string|null, array<string, mixed>} */
    private function validatePriceRow(
        ?int $branchId,
        array $payload,
        string $price,
        bool $legacyContract,
    ): array {
        $normalized = [
            ...$payload,
            'repair_price' => is_numeric($price) ? number_format((float) $price, 2, '.', '') : $price,
            'branch_id' => $branchId,
            'tracking_status_id' => 0,
            'success' => 0,
            '_legacy_contract' => $legacyContract,
            '_legacy_updated_at' => $payload['updated_at'],
            '_legacy_repair_started_at' => $payload['repair_started_at'],
        ];
        if (! is_numeric($price) || (float) $price <= 0 || (float) $price > 9_999_999.99
            || $payload['number_cmg'] === '') {
            return [false, 'invalid_row', $normalized];
        }

        $query = $this->db->table('request_order')
            ->select('request_id, trackID, action_status, branchID')
            ->where('number_cmg', $payload['number_cmg']);
        if ($branchId !== null) {
            $query->where('branchID', $branchId);
        }
        $order = $query->get()->getRowArray();
        if ($order === null) {
            return [false, 'order_not_eligible', $normalized];
        }

        return [true, null, [
            ...$normalized,
            'request_id' => (int) $order['request_id'],
            'track_id' => (string) $order['trackID'],
            'original_action_status' => (int) $order['action_status'],
            'branch_id' => (int) $order['branchID'],
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
        $legacyContract = ($payload['_legacy_contract'] ?? false) === true;
        if (! $updated || $this->db->affectedRows() !== 1
            || ! $this->db->table('status_log')->insert([
                'order_id' => $payload['track_id'], 'action_id' => null,
                'update_id' => $payload['tracking_status_id'], 'cdate' => $timestamp,
            ])
            || ! $this->db->table('uploadstaus')->insert([
                'tracking_id' => str_replace('/', '', (string) $payload['order_id']),
                'Listname' => $payload['customer_name'], 'Telephone' => $payload['telephone'],
                'updatetime' => $legacyContract ? $payload['_legacy_updated_at'] : $payload['updated_at'],
                'startdate' => $legacyContract
                    ? $payload['_legacy_repair_started_at']
                    : $payload['repair_started_at'],
                'Userstatus' => $payload['status'], 'tracking_status' => $payload['tracking_status_id'],
                'cdate' => substr($timestamp, 0, 10), 'user_id' => $legacyContract ? 0 : $ownerId,
                'RepairPrice' => $payload['repair_price'], 'waranty_cmg' => $payload['warranty'],
                'number_cmg' => $payload['number_cmg'],
            ])) {
            throw new RuntimeException('Unable to confirm status import row.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function confirmPrice(array $payload, int $branchId): void
    {
        foreach (['request_id', 'track_id', 'branch_id', 'number_cmg', 'repair_price'] as $field) {
            if (! array_key_exists($field, $payload)) {
                throw new RuntimeException('Incomplete price import row payload.');
            }
        }
        if ((int) $payload['request_id'] < 1 || ! is_string($payload['track_id'])
            || (int) $payload['branch_id'] !== $branchId || ! is_string($payload['repair_price'])
            || ! is_numeric($payload['repair_price'])) {
            throw new RuntimeException('Invalid price import row payload.');
        }
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
        $branch = $this->db->table('branch')->select('branch_type, default_suffix')->where('branch_id', $branchId)->get()->getRowArray();
        $parts = explode('/', (string) $payload['order_id'], 2);
        if ($branch === null || count($parts) !== 2 || $parts[1] === '') {
            throw new RuntimeException('Invalid new order import branch.');
        }
        $legacyContract = ($payload['_legacy_contract'] ?? false) === true;
        $suffix = $legacyContract ? 'G' : trim((string) ($branch['default_suffix'] ?? ''));
        if ($suffix === '' || strlen($suffix) > 10) {
            throw new RuntimeException('Invalid new order import branch.');
        }
        $now = new DateTimeImmutable('now');
        $trackId = (new OrderSequence($this->db))->next($now, $suffix);
        $action = (int) $payload['success'] === 1 ? 4 : 2;
        $customerName = $legacyContract && is_string($payload['_legacy_customer_name'] ?? null)
            ? $payload['_legacy_customer_name']
            : $payload['customer_name'];
        $order = [
            'requestDate' => $payload['updated_at'], 'trackID' => $trackId, 'numberID' => $parts[1],
            'orderID' => str_replace('/', '', (string) $payload['order_id']), 'orderIDShow' => $payload['order_id'],
            'customerFullname' => $customerName, 'customerTel' => $payload['telephone'],
            'branchID' => $branchId, 'branch_type_id' => (int) $branch['branch_type'],
            'date_create' => $timestamp, 'date_repair' => $payload['repair_started_at'],
            'action_status' => $action, 'RepairPrice' => $payload['repair_price'],
            'waranty_cmg' => $payload['warranty'],
            'number_cmg' => $legacyContract ? $parts[0] : $payload['number_cmg'],
            'create_by_user' => $legacyContract ? $customerName : (string) $ownerId,
        ];
        if ($legacyContract) {
            $order['detailDatePurchase'] = $timestamp;
        } else {
            $order['UserID'] = $ownerId;
            $order['date_update_status'] = $payload['updated_at'];
        }
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
