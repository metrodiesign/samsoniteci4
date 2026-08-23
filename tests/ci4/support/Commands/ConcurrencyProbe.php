<?php

namespace App\Commands;

use App\Authentication\AtomicRateLimiter;
use App\Authentication\ResetDeliveryIntentStore;
use App\Authentication\ResetTokenFactory;
use App\Authentication\ResetTokenStore;
use App\Imports\ImportWorkflow;
use App\Orders\OrderCreationWorkflow;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class ConcurrencyProbe extends BaseCommand
{
    private const SCOPE = 'ci4-concurrency-check';

    private const USER_ID = 9002;

    protected $group = 'Tests';

    protected $name = 'concurrency:probe';

    protected $description = 'Runs isolated MariaDB concurrency assertions.';

    protected $usage = 'concurrency:probe <seed|consume|verify|order-create|order-verify|import-seed|import-confirm|import-verify|import-isolation-seed|import-isolation-confirm|import-isolation-verify>';

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
        if (! in_array($slot, [1, 2], true)) {
            throw new RuntimeException('Invalid order probe slot.');
        }
        $this->waitForBarrier();
        $trackId = (new OrderCreationWorkflow(db_connect(), service('encrypter')))->create(
            self::USER_ID,
            2,
            1,
            [
                'submission_id' => str_repeat($slot === 1 ? 'a' : 'b', 32),
                'number_id' => '30' . $slot,
                'order_id' => 'ORDER-30' . $slot,
                'book_id' => 'WPA',
                'customer_name' => 'CONCURRENT ' . $slot,
                'customer_tel' => '0000000000',
                'customer_email' => 'concurrent-' . $slot . '@example.invalid',
                'type_id' => '1',
                'brand_id' => '1',
                'branch_id' => '1',
                'note' => 'Concurrency probe',
            ],
            null,
        );
        CLI::write('ORDER_CREATED ' . $trackId);

        return EXIT_SUCCESS;
    }

    private function verifyOrders(): int
    {
        $db = db_connect();
        $orders = $db->table('request_order')->select('trackID')->orderBy('trackID', 'ASC')->get()->getResultArray();
        $tracks = array_column($orders, 'trackID');
        $logs = $db->table('status_log')->countAllResults();
        $intents = $db->table('ci4_delivery_intents')->where('kind', 'sms')->countAllResults();
        $period = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('ym');
        $expected = ["G{$period}0042", "G{$period}0043", "G{$period}0044"];
        if ($tracks !== $expected || count(array_unique($tracks)) !== 3 || $logs !== 2 || $intents !== 2) {
            throw new RuntimeException('Order concurrency invariant failed.');
        }
        foreach ($tracks as $track) {
            if (! is_string($track) || preg_match('/\AG[0-9]{8}\z/D', $track) !== 1) {
                throw new RuntimeException('Invalid concurrent tracking ID.');
            }
        }
        CLI::write('ORDER_VERIFIED unique=3 orders=3 logs=2 sms=2 baseline=0042 allocated=0043,0044');

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
        $timestamp = gmdate('Y-m-d H:i:s');
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
            $timestamp = gmdate('Y-m-d H:i:s');
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

    private function factory(): ResetTokenFactory
    {
        return new ResetTokenFactory(static fn (int $length): string => str_repeat("\x23", $length));
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
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
