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
    private const BUSINESS_KEY_CONSTRAINT = 'uq_request_order_order_show_tel';

    /** @var list<string> */
    private const SUBMISSION_CONSTRAINTS = ['uq_delivery_intents_request', 'uq_delivery_intents_idempotency'];

    public function __construct(private BaseConnection $db, private EncrypterInterface $encrypter)
    {
    }

    /** @param array<string, mixed> $input @param list<string> $imageNames */
    public function create(int $actorId, int $actorRole, ?int $actorBranch, array $input, array $imageNames): string
    {
        $submissionId = is_string($input['submission_id'] ?? null) ? $input['submission_id'] : '';
        if (preg_match('/\A[a-f0-9]{32}\z/D', $submissionId) !== 1 || $actorId < 1) {
            throw new InvalidArgumentException('Invalid order submission.');
        }
        $values = $this->normalize($actorRole, $actorBranch, $input, $imageNames);
        $suffix = (string) $values['trackSuffix'];
        $bookId = (int) $values['canonicalBookId'];
        unset($values['trackSuffix'], $values['canonicalBookId']);

        $now = new DateTimeImmutable('now');
        $timestamp = $now->format('Y-m-d H:i:s');
        $this->db->resetTransStatus();
        $this->db->transBegin();
        try {
            $book = $this->db->table('book')
                ->select('book_id, book_detail')
                ->where('book_id', $bookId)
                ->where('branch_id', $values['branchID'])
                ->where('status', 1)
                ->get()
                ->getRowArray();
            $bookDetail = trim((string) ($book['book_detail'] ?? ''));
            if ($book === null || $bookDetail === '' || mb_strlen($bookDetail) > 3) {
                throw new InvalidArgumentException('Invalid order book.');
            }
            $values['bookID'] = (string) (int) $book['book_id'];
            $values['orderID'] = $bookDetail . $values['numberID'];
            $values['orderIDShow'] = $bookDetail . '/' . $values['numberID'];

            if ($this->db->table('ci4_delivery_intents')->where('request_id', $submissionId)->countAllResults() !== 0
                || $this->db->table('request_order')->where('orderIDShow', $values['orderIDShow'])
                    ->where('customerTel', $values['customerTel'])->countAllResults() !== 0) {
                throw new DomainException('Duplicate order submission.');
            }

            $trackId = (new OrderSequence($this->db))->next($now, $suffix);
            if (! $this->db->table('request_order')->insert([
                ...$values,
                'trackID' => $trackId,
                'UserID' => $actorId,
                'requestDate' => $now->format('Y-m-d 00:00:00'),
                'action_status' => 1,
            ])) {
                $this->throwWriteFailure('Unable to insert order for tracking ID ' . $trackId . '.');
            }
            $orderId = (int) $this->db->insertID();
            if ($orderId < 1 || ! $this->db->table('status_log')->insert([
                'order_id' => $trackId, 'action_id' => 1, 'update_id' => null, 'cdate' => $timestamp,
            ])) {
                $this->throwWriteFailure('Unable to insert order status.');
            }
            if (! (new SmsDeliveryIntentStore($this->db, $this->encrypter))->enqueue(
                $orderId, $trackId, (string) $values['customerTel'],
                OrderSmsMessages::created($trackId), $submissionId, $now,
            )) {
                $error = $this->db->error();
                $errorCode = (int) ($error['code'] ?? 0);
                $errorMessage = (string) ($error['message'] ?? '');
                if ($this->isDuplicateConstraint($errorCode, $errorMessage)
                    || ($errorCode === 0 && $errorMessage === '')) {
                    throw new DomainException('Duplicate order submission.');
                }
                throw new RuntimeException('Unable to store SMS intent.');
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Unable to store SMS intent.');
            }

            return $trackId;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            if (! $exception instanceof DomainException
                && $this->isDuplicateConstraint((int) $exception->getCode(), $exception->getMessage())) {
                throw new DomainException('Duplicate order submission.', 0, $exception);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input @param list<string> $imageNames @return array<string, int|string|null> */
    private function normalize(int $actorRole, ?int $actorBranch, array $input, array $imageNames): array
    {
        foreach ($imageNames as $imageName) {
            if (preg_match('/\A[a-f0-9]{32}\.png\z/D', $imageName) !== 1) {
                throw new InvalidArgumentException('Invalid order image.');
            }
        }
        if (! is_string($input['number_id'] ?? null) || ! is_string($input['book_id'] ?? null)) {
            throw new InvalidArgumentException('Invalid order field.');
        }
        foreach (['customer_name', 'customer_tel', 'customer_email', 'note', 'detail_sku_name', 'create_by_user'] as $field) {
            if (! is_string($input[$field] ?? null)) {
                throw new InvalidArgumentException('Invalid order field.');
            }
            $input[$field] = trim($input[$field]);
        }
        foreach (['customer_tel2', 'condition_other', 'estimateprice_other', 'fixed_other', 'detail_equipment', 'detail_number_waranty'] as $field) {
            $value = $input[$field] ?? '';
            if (! is_string($value)) {
                throw new InvalidArgumentException('Invalid order field.');
            }
            $input[$field] = trim($value);
        }
        $typeId = $this->positiveInteger($input['type_id'] ?? null);
        $brandId = $this->positiveInteger($input['brand_id'] ?? null);
        $bookId = $this->positiveInteger($input['book_id']);
        $requestedBranch = $this->positiveInteger($input['branch_id'] ?? null);
        $branchId = $actorRole === 1 ? $requestedBranch : $actorBranch;
        $warantyRaw = $input['waranty_type'] ?? '0';
        $warantyType = in_array($warantyRaw, ['0', '1'], true) ? (int) $warantyRaw : null;
        $numberWaranty = $warantyType === 1 ? $input['detail_number_waranty'] : '';
        $datePurchase = $this->purchaseDate($input['detail_date_purchase'] ?? '');
        $conditionIds = $this->catalogueIds($input['condition'] ?? null, 'condition', 'condition_id');
        $estimatePriceIds = $this->catalogueIds($input['estimateprice'] ?? null, 'estimateprice', 'estimateprice_id');
        $fixedIds = $this->catalogueIds($input['fixed'] ?? null, 'fixed', 'fixed_id');
        if (! in_array($actorRole, [1, 2, 3], true) || $branchId === null || $branchId < 1
            || ($actorRole !== 1 && $requestedBranch !== null && $requestedBranch !== $actorBranch)
            || $typeId === null || $brandId === null || $bookId === null || $warantyType === null
            || preg_match('/\A[0-9]{1,96}\z/D', $input['number_id']) !== 1
            || $input['customer_name'] === '' || mb_strlen($input['customer_name']) > 250
            || preg_match('/\A[0-9]{10,20}\z/D', $input['customer_tel']) !== 1
            || ($input['customer_tel2'] !== '' && preg_match('/\A[0-9]{1,20}\z/D', $input['customer_tel2']) !== 1)
            || ($input['customer_email'] !== '' && (strlen($input['customer_email']) > 100 || filter_var($input['customer_email'], FILTER_VALIDATE_EMAIL) === false))
            || $input['detail_sku_name'] === '' || mb_strlen($input['detail_sku_name']) > 100
            || mb_strlen($numberWaranty) > 100
            || mb_strlen($input['condition_other']) > 250
            || mb_strlen($input['estimateprice_other']) > 250
            || mb_strlen($input['fixed_other']) > 250
            || mb_strlen($input['detail_equipment']) > 4000
            || $input['create_by_user'] === '' || mb_strlen($input['create_by_user']) > 250
            || mb_strlen($input['note']) > 4000
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
            'canonicalBookId' => $bookId, 'numberID' => $input['number_id'],
            'customerFullname' => $input['customer_name'], 'customerTel' => $input['customer_tel'],
            'customerTel2' => $input['customer_tel2'],
            'customerEmail' => strtolower($input['customer_email']), 'detailTypeId' => $typeId,
            'detailBrandId' => $brandId, 'detailAgent' => ($input['detail_agent'] ?? null) === '1' ? 1 : 0,
            'detailSKUName' => $input['detail_sku_name'], 'warantyType' => $warantyType,
            'detailNumberWaranty' => $numberWaranty, 'detailDatePurchase' => $datePurchase,
            'detailCondition' => $conditionIds, 'detailConditionOther' => $input['condition_other'],
            'detailEstimatePrice' => $estimatePriceIds, 'detailEstimatePriceOther' => $input['estimateprice_other'],
            'detailFixed' => $fixedIds, 'detailFixedOther' => $input['fixed_other'],
            'detailEquipment' => $input['detail_equipment'], 'create_by_user' => $input['create_by_user'],
            'detailNote' => $input['note'], 'detailImage' => $imageNames === [] ? null : implode('|', $imageNames),
            'branchID' => $branchId, 'branch_type_id' => (int) $branch['branch_type'],
            'trackSuffix' => $suffix,
        ];
    }

    /**
     * Empty stays the legacy zero date; otherwise parse dd/mm/yyyy strictly (round-trip guards the
     * PHP overflow that turns 31/02 into 03/03) and store Y-m-d 00:00:00.
     */
    private function purchaseDate(mixed $raw): string
    {
        if (! is_string($raw)) {
            throw new InvalidArgumentException('Invalid order purchase date.');
        }
        $raw = trim($raw);
        if ($raw === '') {
            return '0000-00-00 00:00:00';
        }
        $date = DateTimeImmutable::createFromFormat('!d/m/Y', $raw);
        if ($date === false || $date->format('d/m/Y') !== $raw) {
            throw new InvalidArgumentException('Invalid order purchase date.');
        }

        return $date->format('Y-m-d 00:00:00');
    }

    /**
     * Require at least one id, all present in the catalogue table, no duplicates (whereIn matches
     * distinct rows, so its count only equals the raw id count when every id is real and unique);
     * join in submission order for the print view, capped at the column width.
     */
    private function catalogueIds(mixed $raw, string $table, string $idColumn): string
    {
        if (! is_array($raw) || $raw === []) {
            throw new InvalidArgumentException('Invalid order catalogue.');
        }
        $ids = [];
        foreach ($raw as $value) {
            if (! is_string($value) || preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) {
                throw new InvalidArgumentException('Invalid order catalogue.');
            }
            $ids[] = $value;
        }
        if ($this->db->table($table)->whereIn($idColumn, $ids)->countAllResults() !== count($ids)) {
            throw new InvalidArgumentException('Invalid order catalogue.');
        }
        $joined = implode('|', $ids);
        if (strlen($joined) > 250) {
            throw new InvalidArgumentException('Invalid order catalogue.');
        }

        return $joined;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) {
            return null;
        }
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? null : (int) $validated;
    }

    private function throwWriteFailure(string $message): never
    {
        $error = $this->db->error();
        if ($this->isDuplicateConstraint((int) ($error['code'] ?? 0), (string) ($error['message'] ?? ''))) {
            throw new DomainException('Duplicate order submission.');
        }
        throw new RuntimeException($message);
    }

    private function isDuplicateConstraint(int $code, string $message): bool
    {
        if ($code === 1062) {
            foreach ([self::BUSINESS_KEY_CONSTRAINT, ...self::SUBMISSION_CONSTRAINTS] as $constraint) {
                if (str_contains($message, $constraint)) {
                    return true;
                }
            }

            return false;
        }
        if ($code !== 19) {
            return false;
        }
        $orderTable = $this->db->prefixTable('request_order');
        $intentTable = $this->db->prefixTable('ci4_delivery_intents');

        return str_contains($message, "UNIQUE constraint failed: {$orderTable}.orderIDShow, {$orderTable}.customerTel")
            || str_contains($message, "UNIQUE constraint failed: {$intentTable}.request_id")
            || str_contains($message, "UNIQUE constraint failed: {$intentTable}.idempotency_key");
    }
}
