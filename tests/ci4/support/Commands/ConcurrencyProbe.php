<?php

namespace App\Commands;

use App\Authentication\AtomicRateLimiter;
use App\Authentication\ResetDeliveryIntentStore;
use App\Authentication\ResetTokenFactory;
use App\Authentication\ResetTokenStore;
use App\Database\Migrations\AddUniqueOrderBusinessKey;
use App\Imports\ImportWorkflow;
use App\Orders\OrderCreationWorkflow;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use DomainException;
use RuntimeException;

final class ConcurrencyProbe extends BaseCommand
{
    private const SCOPE = 'ci4-concurrency-check';

    private const USER_ID = 9002;

    protected $group = 'Tests';

    protected $name = 'concurrency:probe';

    protected $description = 'Runs isolated MariaDB concurrency assertions.';

    protected $usage = 'concurrency:probe <seed|consume|verify|order-create|order-verify|order-migration-verify|order-migration-cycle|order-migration-duplicate|import-seed|import-confirm|import-verify|import-isolation-seed|import-isolation-confirm|import-isolation-verify>';

    protected $arguments = [
        'mode' => 'seed, consume or verify',
    ];

    public function run(array $params): int
    {
        return match ($params[0] ?? '') {
            'seed'    => $this->seed(),
            'consume' => $this->consume(),
            'verify'  => $this->verify(),
            'order-create' => $this->createOrder(),
            'order-verify' => $this->verifyOrders(),
            'order-migration-verify' => $this->verifyOrderMigration(),
            'order-migration-cycle' => $this->cycleOrderMigration(),
            'order-migration-duplicate' => $this->verifyOrderMigrationDuplicatePreflight(),
            'import-seed' => $this->seedImport(),
            'import-confirm' => $this->confirmImport(),
            'import-verify' => $this->verifyImport(),
            'import-isolation-seed' => $this->seedImportIsolation(),
            'import-isolation-confirm' => $this->confirmImportIsolation(),
            'import-isolation-verify' => $this->verifyImportIsolation(),
            default   => EXIT_ERROR,
        };
    }

    private function seed(): int
    {
        $factory = $this->factory();
        $issued  = $factory->issue($this->now());
        (new ResetTokenStore(db_connect(), $factory))->issue(
            self::USER_ID,
            $issued,
            static function ($db) use ($issued): void {
                (new ResetDeliveryIntentStore($db, service('encrypter')))->enqueue(
                    self::USER_ID,
                    'concurrency@example.invalid',
                    $issued,
                );
            },
        );
        CLI::write('SEEDED');

        return EXIT_SUCCESS;
    }

    private function consume(): int
    {
        $this->waitForBarrier();
        $now = $this->now();
        $tokenWon = (new ResetTokenStore(db_connect()))->consume(
            self::USER_ID,
            str_repeat('23', 32),
            $now,
            static function (): void {
            },
        );
        $rateAllowed = (new AtomicRateLimiter(db_connect()))->consume(
            self::SCOPE,
            1,
            900,
            $now,
        ) === null;
        CLI::write(($tokenWon ? 'TOKEN_WIN' : 'TOKEN_DENY') . ' ' . ($rateAllowed ? 'RATE_ALLOW' : 'RATE_DENY'));

        return EXIT_SUCCESS;
    }

    private function verify(): int
    {
        $db = db_connect();
        $consumed = $db->table('ci4_password_reset_tokens')
            ->where('user_id', self::USER_ID)
            ->where('consumed_at !=', null)
            ->countAllResults();
        $rateCount = (int) ($db->table('ci4_rate_limit_buckets')
            ->select('request_count')
            ->get()
            ->getRow('request_count') ?? 0);

        if ($consumed !== 1 || $rateCount !== 2) {
            throw new RuntimeException('Concurrency invariant failed.');
        }

        CLI::write('VERIFIED token_consumed=1 rate_count=2');

        return EXIT_SUCCESS;
    }

    private function createOrder(): int
    {
        $slot = filter_var(getenv('PROBE_SLOT'), FILTER_VALIDATE_INT);
        $scenario = getenv('PROBE_SCENARIO');
        if (! in_array($slot, [1, 2], true) || ! in_array($scenario, ['business', 'submission', 'distinct'], true)) {
            throw new RuntimeException('Invalid order probe input.');
        }
        $number = match ($scenario) {
            'business' => '301',
            'submission' => '30' . ($slot + 1),
            'distinct' => '30' . ($slot + 3),
        };
        $submission = match ($scenario) {
            'business' => str_repeat($slot === 1 ? 'a' : 'b', 32),
            'submission' => str_repeat('c', 32),
            'distinct' => str_repeat($slot === 1 ? 'd' : 'e', 32),
        };
        $telephone = match ($scenario) {
            'business' => '1111111111',
            'submission' => '2222222222',
            'distinct' => '3333333333',
        };
        $this->waitForBarrier();
        try {
            (new OrderCreationWorkflow(db_connect(), service('encrypter')))->create(
                self::USER_ID,
                2,
                1,
                [
                    'submission_id' => $submission,
                    'number_id' => $number,
                    'order_id' => 'IGNORED-' . $slot,
                    'book_id' => '1',
                    'customer_name' => strtoupper($scenario) . ' ' . $slot,
                    'customer_tel' => $telephone,
                    'customer_email' => $scenario . '-' . $slot . '@example.invalid',
                    'type_id' => '1',
                    'brand_id' => '1',
                    'branch_id' => '1',
                    'note' => 'Concurrency probe',
                    'detail_sku_name' => 'PROBE BAG',
                    'create_by_user' => 'PROBE RECEIVER',
                    'condition' => ['1'],
                    'estimateprice' => ['1'],
                    'fixed' => ['1'],
                ],
                [],
            );
            CLI::write('ORDER_' . strtoupper($scenario) . '_CREATED');
        } catch (DomainException) {
            CLI::write('ORDER_' . strtoupper($scenario) . '_DUPLICATE');
        }

        return EXIT_SUCCESS;
    }

    private function verifyOrders(): int
    {
        $scenario = getenv('PROBE_SCENARIO');
        $contract = match ($scenario) {
            'business' => ['1111111111', 1, ['ABC/301']],
            'submission' => ['2222222222', 1, ['ABC/302', 'ABC/303']],
            'distinct' => ['3333333333', 2, ['ABC/304', 'ABC/305']],
            default => throw new RuntimeException('Invalid order verification scenario.'),
        };
        [$telephone, $expectedCount, $allowedKeys] = $contract;
        $db = db_connect();
        $orders = $db->table('request_order')
            ->select('request_id, trackID, bookID, numberID, orderID, orderIDShow')
            ->where('customerTel', $telephone)
            ->orderBy('request_id', 'ASC')
            ->get()
            ->getResultArray();
        $tracks = array_column($orders, 'trackID');
        $requestIds = array_map('intval', array_column($orders, 'request_id'));
        $logs = $tracks === [] ? 0 : $db->table('status_log')->whereIn('order_id', $tracks)->countAllResults();
        $intents = $requestIds === [] ? 0 : $db->table('ci4_delivery_intents')
            ->where('kind', 'sms')->whereIn('user_id', $requestIds)->countAllResults();
        if (count($orders) !== $expectedCount || count(array_unique($tracks)) !== $expectedCount
            || $logs !== $expectedCount || $intents !== $expectedCount) {
            throw new RuntimeException('Order concurrency state count failed.');
        }
        foreach ($orders as $order) {
            if ((string) $order['bookID'] !== '1'
                || (string) $order['orderID'] !== 'ABC' . (string) $order['numberID']
                || ! in_array((string) $order['orderIDShow'], $allowedKeys, true)
                || ! is_string($order['trackID'])
                || preg_match('/\AWPA[0-9]{8}\z/D', $order['trackID']) !== 1) {
                throw new RuntimeException('Order concurrency canonical identifier failed.');
            }
        }
        CLI::write('ORDER_' . strtoupper($scenario) . "_VERIFIED orders={$expectedCount} logs={$expectedCount} sms={$expectedCount}");

        return EXIT_SUCCESS;
    }

    private function verifyOrderMigration(): int
    {
        $index = db_connect()->getIndexData('request_order')['uq_request_order_order_show_tel'] ?? null;
        if ($index === null || $index->type !== 'UNIQUE' || $index->fields !== ['orderIDShow', 'customerTel']) {
            throw new RuntimeException('Order business key migration invariant failed.');
        }
        CLI::write('ORDER_MIGRATION_VERIFIED');

        return EXIT_SUCCESS;
    }

    private function cycleOrderMigration(): int
    {
        $db = db_connect();
        $before = $db->table('request_order')->countAllResults();
        $migration = $this->orderMigration($db);
        $migration->down();
        if (array_key_exists('uq_request_order_order_show_tel', $db->getIndexData('request_order'))
            || $db->table('request_order')->countAllResults() !== $before) {
            throw new RuntimeException('Order migration down invariant failed.');
        }
        $migration->up();
        if (! array_key_exists('uq_request_order_order_show_tel', $db->getIndexData('request_order'))
            || $db->table('request_order')->countAllResults() !== $before) {
            throw new RuntimeException('Order migration up invariant failed.');
        }
        CLI::write('ORDER_MIGRATION_CYCLED rows=' . $before);

        return EXIT_SUCCESS;
    }

    private function verifyOrderMigrationDuplicatePreflight(): int
    {
        $db = db_connect();
        $migration = $this->orderMigration($db);
        $migration->down();
        $before = $db->table('request_order')->countAllResults();
        foreach ([1, 2] as $slot) {
            if (! $db->table('request_order')->insert([
                'requestDate' => '2026-08-26 00:00:00', 'trackID' => 'WPA2608999' . $slot,
                'bookID' => '1', 'numberID' => '999', 'orderID' => 'ABC999', 'orderIDShow' => 'ABC/999',
                'customerFullname' => 'MIGRATION DUPLICATE ' . $slot, 'customerTel' => '9999999999',
                'branchID' => 1, 'branch_type_id' => 1, 'UserID' => self::USER_ID, 'action_status' => 1,
            ])) {
                throw new RuntimeException('Unable to seed migration duplicate proof.');
            }
        }
        try {
            $migration->up();
            throw new RuntimeException('Migration accepted duplicate business keys.');
        } catch (RuntimeException $exception) {
            if (! str_contains($exception->getMessage(), 'aborted before DDL')) {
                throw $exception;
            }
        }
        if (array_key_exists('uq_request_order_order_show_tel', $db->getIndexData('request_order'))
            || $db->table('request_order')->countAllResults() !== $before + 2) {
            throw new RuntimeException('Migration duplicate preflight changed data or DDL.');
        }
        CLI::write('ORDER_MIGRATION_DUPLICATE_ABORTED rows=' . ($before + 2));

        return EXIT_SUCCESS;
    }

    private function seedImport(): int
    {
        $db = db_connect();
        $order = $db->table('request_order')->select('request_id, trackID, orderIDShow, customerFullname, customerTel')
            ->where('request_id', 1)->get()->getRowArray();
        if ($order === null || ! $db->table('request_order')->where('request_id', 1)->update([
            'number_cmg' => 'IMPORT-1', 'RepairPrice' => '100.00',
        ])) {
            throw new RuntimeException('Unable to seed import order.');
        }
        $payload = [
            'order_id' => $order['orderIDShow'], 'customer_name' => $order['customerFullname'],
            'telephone' => $order['customerTel'], 'updated_at' => '2026-08-22 00:00:00',
            'status' => 'SUCCESS', 'repair_started_at' => '2026-08-20 00:00:00',
            'repair_price' => '222.00', 'warranty' => 'IN', 'number_cmg' => 'IMPORT-1',
            'tracking_status_id' => 1, 'success' => 1, 'branch_id' => 1,
            'request_id' => (int) $order['request_id'], 'track_id' => $order['trackID'],
            'original_action_status' => 1,
        ];
        $batch = str_repeat('c', 32);
        $timestamp = date('Y-m-d H:i:s');
        if (! $db->table('ci4_import_batches')->insert([
            'batch_id' => $batch, 'kind' => 'price', 'owner_user_id' => self::USER_ID,
            'owner_branch_id' => 1, 'state' => 'previewed', 'file_sha256' => str_repeat('d', 64),
            'row_count' => 1, 'accepted_count' => 1, 'rejected_count' => 0, 'created_at' => $timestamp,
        ]) || ! $db->table('ci4_import_rows')->insert([
            'batch_id' => $batch, 'row_number' => 2, 'accepted' => 1, 'error_code' => null,
            'payload_ciphertext' => base64_encode(service('encrypter')->encrypt(json_encode($payload, JSON_THROW_ON_ERROR))),
        ])) {
            throw new RuntimeException('Unable to seed import batch.');
        }
        CLI::write('IMPORT_SEEDED');

        return EXIT_SUCCESS;
    }

    private function confirmImport(): int
    {
        $this->waitForBarrier();
        $result = (new ImportWorkflow(db_connect(), service('encrypter')))->confirm(
            'price',
            str_repeat('c', 32),
            self::USER_ID,
            1,
        );
        if (! in_array($result, ['confirmed', 'replayed'], true)) {
            throw new RuntimeException('Import concurrency result failed: ' . $result);
        }
        CLI::write('IMPORT_' . strtoupper($result));

        return EXIT_SUCCESS;
    }

    private function verifyImport(): int
    {
        $db = db_connect();
        $price = (float) $db->table('request_order')->where('request_id', 1)->get()->getRow('RepairPrice');
        $state = $db->table('ci4_import_batches')->where('batch_id', str_repeat('c', 32))->get()->getRow('state');
        if ($price !== 222.0 || $state !== 'confirmed' || $db->table('ci4_import_rows')->countAllResults() !== 1) {
            throw new RuntimeException('Import concurrency invariant failed.');
        }
        CLI::write('IMPORT_VERIFIED confirmed=1 rows=1 price=222.00');

        return EXIT_SUCCESS;
    }

    private function seedImportIsolation(): int
    {
        $db = db_connect();
        if (! $db->table('branch')->insert([
            'branch_id' => 2, 'branch_type' => 2, 'default_suffix' => 'WPB',
        ]) || ! $db->table('request_order')->where('request_id', 1)->update([
            'number_cmg' => 'ISOLATION-A', 'RepairPrice' => '100.00',
        ]) || ! $db->table('request_order')->insert([
            'request_id' => 9004, 'requestDate' => '2026-08-22 00:00:00',
            'trackID' => 'WP00C-IMPORT-B', 'numberID' => 'ISOLATION-B',
            'orderIDShow' => 'WPB/ISOLATION-B', 'customerFullname' => 'ISOLATION B',
            'customerTel' => '0000000000', 'branchID' => 2, 'branch_type_id' => 2,
            'action_status' => 1, 'number_cmg' => 'ISOLATION-B', 'RepairPrice' => '100.00',
        ])) {
            throw new RuntimeException('Unable to seed import isolation orders.');
        }
        $orders = [
            [str_repeat('d', 32), self::USER_ID, 1, 1, 'ISOLATION-A', '111.00'],
            [str_repeat('e', 32), 9003, 2, 9004, 'ISOLATION-B', '222.00'],
        ];
        foreach ($orders as [$batch, $owner, $branch, $requestId, $numberCmg, $price]) {
            $order = $db->table('request_order')
                ->select('request_id, trackID, orderIDShow, customerFullname, customerTel')
                ->where('request_id', $requestId)->get()->getRowArray();
            if ($order === null) {
                throw new RuntimeException('Missing import isolation order.');
            }
            $payload = [
                'order_id' => $order['orderIDShow'], 'customer_name' => $order['customerFullname'],
                'telephone' => $order['customerTel'], 'updated_at' => '2026-08-22 00:00:00',
                'status' => 'SUCCESS', 'repair_started_at' => '2026-08-20 00:00:00',
                'repair_price' => $price, 'warranty' => 'IN', 'number_cmg' => $numberCmg,
                'tracking_status_id' => 1, 'success' => 1, 'branch_id' => $branch,
                'request_id' => (int) $order['request_id'], 'track_id' => $order['trackID'],
                'original_action_status' => 1,
            ];
            $timestamp = date('Y-m-d H:i:s');
            if (! $db->table('ci4_import_batches')->insert([
                'batch_id' => $batch, 'kind' => 'price', 'owner_user_id' => $owner,
                'owner_branch_id' => $branch, 'state' => 'previewed', 'file_sha256' => str_repeat('f', 64),
                'row_count' => 1, 'accepted_count' => 1, 'rejected_count' => 0, 'created_at' => $timestamp,
            ]) || ! $db->table('ci4_import_rows')->insert([
                'batch_id' => $batch, 'row_number' => 2, 'accepted' => 1, 'error_code' => null,
                'payload_ciphertext' => base64_encode(service('encrypter')->encrypt(json_encode($payload, JSON_THROW_ON_ERROR))),
            ])) {
                throw new RuntimeException('Unable to seed import isolation batch.');
            }
        }
        CLI::write('IMPORT_ISOLATION_SEEDED owners=2 branches=2 batches=2');

        return EXIT_SUCCESS;
    }

    private function confirmImportIsolation(): int
    {
        $slot = filter_var(getenv('PROBE_SLOT'), FILTER_VALIDATE_INT);
        if (! in_array($slot, [1, 2], true)) {
            throw new RuntimeException('Invalid import isolation slot.');
        }
        $this->waitForBarrier();
        $result = (new ImportWorkflow(db_connect(), service('encrypter')))->confirm(
            'price',
            str_repeat($slot === 1 ? 'd' : 'e', 32),
            $slot === 1 ? self::USER_ID : 9003,
            $slot,
        );
        if ($result !== 'confirmed') {
            throw new RuntimeException('Import isolation result failed: ' . $result);
        }
        CLI::write('IMPORT_ISOLATION_' . ($slot === 1 ? 'A' : 'B') . '_CONFIRMED');

        return EXIT_SUCCESS;
    }

    private function verifyImportIsolation(): int
    {
        $db = db_connect();
        $priceA = (float) $db->table('request_order')->where('request_id', 1)->get()->getRow('RepairPrice');
        $priceB = (float) $db->table('request_order')->where('request_id', 9004)->get()->getRow('RepairPrice');
        $states = $db->table('ci4_import_batches')
            ->select('batch_id, owner_user_id, owner_branch_id, state')
            ->whereIn('batch_id', [str_repeat('d', 32), str_repeat('e', 32)])
            ->orderBy('batch_id', 'ASC')->get()->getResultArray();
        if ($priceA !== 111.0 || $priceB !== 222.0 || count($states) !== 2
            || (int) $states[0]['owner_user_id'] !== self::USER_ID || (int) $states[0]['owner_branch_id'] !== 1
            || (int) $states[1]['owner_user_id'] !== 9003 || (int) $states[1]['owner_branch_id'] !== 2
            || $states[0]['state'] !== 'confirmed' || $states[1]['state'] !== 'confirmed') {
            throw new RuntimeException('Import isolation invariant failed.');
        }
        CLI::write('IMPORT_ISOLATION_VERIFIED owners=2 branches=2 batches=2 prices=111.00,222.00');

        return EXIT_SUCCESS;
    }

    private function orderMigration(BaseConnection $db): AddUniqueOrderBusinessKey
    {
        require_once APPPATH . 'Database/Migrations/2026-08-26-090000_AddUniqueOrderBusinessKey.php';

        return new AddUniqueOrderBusinessKey(Database::forge($db));
    }

    private function factory(): ResetTokenFactory
    {
        return new ResetTokenFactory(static fn (int $length): string => str_repeat("\x23", $length));
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }

    private function waitForBarrier(): void
    {
        $barrier = filter_var(getenv('PROBE_START_EPOCH'), FILTER_VALIDATE_INT);

        if ($barrier === false) {
            throw new RuntimeException('Missing concurrency barrier.');
        }

        while (time() < $barrier) {
            usleep(10_000);
        }
    }
}
