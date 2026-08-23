<?php

namespace App\Orders;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Encryption\EncrypterInterface;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class OrderCreationWorkflow
{
    public function __construct(private BaseConnection $db, private EncrypterInterface $encrypter)
    {
    }

    /** @param array<string, mixed> $input */
    public function create(int $actorId, int $actorRole, ?int $actorBranch, array $input, ?string $imageName): string
    {
        $values = $this->normalize($actorRole, $actorBranch, $input, $imageName);
        $suffix = (string) $values['trackSuffix'];
        unset($values['trackSuffix']);
        $submissionId = is_string($input['submission_id'] ?? null) ? $input['submission_id'] : '';
        if (preg_match('/\A[a-f0-9]{32}\z/D', $submissionId) !== 1 || $actorId < 1) {
            throw new InvalidArgumentException('Invalid order submission.');
        }
        if ($this->db->table('ci4_delivery_intents')->where('request_id', $submissionId)->countAllResults() !== 0
            || $this->db->table('request_order')->where('branchID', $values['branchID'])->where('numberID', $values['numberID'])->countAllResults() !== 0) {
            throw new DomainException('Duplicate order submission.');
        }

        $now = new DateTimeImmutable('now');
        $timestamp = $now->format('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $trackId = (new OrderSequence($this->db))->next($now, $suffix);
            if (! $this->db->table('request_order')->insert([
                ...$values,
                'trackID' => $trackId,
                'orderIDShow' => $values['orderIDShow'],
                'UserID' => $actorId,
                'requestDate' => $now->format('Y-m-d 00:00:00'),
                'action_status' => 1,
                'create_by_user' => (string) $actorId,
            ])) {
                throw new RuntimeException('Unable to insert order for tracking ID ' . $trackId . '.');
            }
            $orderId = (int) $this->db->insertID();
            if ($orderId < 1 || ! $this->db->table('status_log')->insert([
                'order_id' => $trackId, 'action_id' => 1, 'update_id' => null, 'cdate' => $timestamp,
            ])) {
                throw new RuntimeException('Unable to insert order status.');
            }
            $payload = json_encode([
                'order_id' => $orderId,
                'track_id' => $trackId,
                'telephone' => $values['customerTel'],
                'message' => 'Track repair: /tracking/' . $trackId,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (! $this->db->table('ci4_delivery_intents')->insert([
                'idempotency_key' => hash('sha256', "sms\0" . $submissionId),
                'kind' => 'sms', 'user_id' => $orderId, 'request_id' => $submissionId,
                'payload_ciphertext' => base64_encode($this->encrypter->encrypt($payload)),
                'status' => 'pending', 'attempt_count' => 0, 'available_at' => $timestamp,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]) || ! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Unable to store SMS intent.');
            }

            return $trackId;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input @return array<string, int|string|null> */
    private function normalize(int $actorRole, ?int $actorBranch, array $input, ?string $imageName): array
    {
        foreach (['number_id', 'order_id', 'book_id', 'customer_name', 'customer_tel', 'customer_email', 'note'] as $field) {
            if (! is_string($input[$field] ?? null)) {
                throw new InvalidArgumentException('Invalid order field.');
            }
            $input[$field] = trim($input[$field]);
        }
        $typeId = $this->positiveInteger($input['type_id'] ?? null);
        $brandId = $this->positiveInteger($input['brand_id'] ?? null);
        $requestedBranch = $this->positiveInteger($input['branch_id'] ?? null);
        $branchId = $actorRole === 1 ? $requestedBranch : $actorBranch;
        if ($branchId === null || ($actorRole !== 1 && $requestedBranch !== null && $requestedBranch !== $actorBranch)
            || $typeId === null || $brandId === null
            || $input['number_id'] === '' || strlen($input['number_id']) > 100
            || $input['order_id'] === '' || strlen($input['order_id']) > 100
            || $input['book_id'] === '' || strlen($input['book_id']) > 100
            || $input['customer_name'] === '' || mb_strlen($input['customer_name']) > 250
            || preg_match('/\A[0-9]{10,20}\z/D', $input['customer_tel']) !== 1
            || ($input['customer_email'] !== '' && (strlen($input['customer_email']) > 100 || filter_var($input['customer_email'], FILTER_VALIDATE_EMAIL) === false))
            || mb_strlen($input['note']) > 4000
            || ($imageName !== null && preg_match('/\A[a-f0-9]{32}\.png\z/D', $imageName) !== 1)
            || $this->db->table('type')->where('type_id', $typeId)->countAllResults() !== 1
            || $this->db->table('brand')->where('brand_id', $brandId)->countAllResults() !== 1) {
            throw new InvalidArgumentException('Invalid order.');
        }
        $branch = $this->db->table('branch')->select('branch_type, default_suffix')->where('branch_id', $branchId)->get()->getRowArray();
        if ($branch === null) {
            throw new InvalidArgumentException('Invalid order branch.');
        }
        $suffix = trim((string) ($branch['default_suffix'] ?? ''));
        if ($suffix === '' || strlen($suffix) > 10) {
            throw new InvalidArgumentException('Invalid order branch suffix.');
        }

        return [
            'numberID' => $input['number_id'], 'orderID' => $input['order_id'],
            'orderIDShow' => $suffix . '/' . $input['number_id'], 'bookID' => $input['book_id'],
            'customerFullname' => $input['customer_name'], 'customerTel' => $input['customer_tel'],
            'customerEmail' => strtolower($input['customer_email']), 'detailTypeId' => $typeId,
            'detailBrandId' => $brandId, 'detailNote' => $input['note'], 'detailImage' => $imageName,
            'branchID' => $branchId, 'branch_type_id' => (int) $branch['branch_type'],
            'trackSuffix' => $suffix,
        ];
    }

    private function positiveInteger(mixed $value): ?int
    {
        return is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value) === 1 ? (int) $value : null;
    }
}
