<?php

namespace App\Commands;

use App\Orders\LoopbackSmsTransport;
use App\Orders\SmsDeliveryIntentStore;
use App\Orders\SmsDeliveryWorker;
use Closure;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class SmsDeliveryWork extends BaseCommand
{
    protected $group = 'Samsonite CI4';
    protected $name = 'sms:delivery-work';
    protected $description = 'Processes queued SMS delivery through local loopback transport.';
    protected $usage = 'sms:delivery-work --transport loopback [--limit 10]';
    protected $options = [
        '--transport' => 'Required. Only loopback is available.',
        '--limit' => 'Maximum intents to inspect (1-100, default 10).',
    ];

    public function run(array $params): int
    {
        $limit = filter_var($params['limit'] ?? 10, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);
        if (($params['transport'] ?? null) !== 'loopback' || $limit === false || ENVIRONMENT === 'production') {
            CLI::error('Use non-production --transport loopback and --limit 1-100.');

            return EXIT_ERROR;
        }
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $store = new SmsDeliveryIntentStore(db_connect(), service('encrypter'));
            $released = $store->releaseStale($now->modify('-15 minutes'), $now);
            $transport = new LoopbackSmsTransport();
            $worker = new SmsDeliveryWorker($store);
            $sent = 0;
            $retry = 0;
            $idle = 0;
            for ($attempt = 0; $attempt < $limit; $attempt++) {
                $result = $worker->runNext($now, Closure::fromCallable([$transport, 'send']));
                if ($result === 'idle') {
                    $idle = 1;
                    break;
                }
                $result === 'sent' ? $sent++ : $retry++;
            }
            CLI::write("sms_delivery sent={$sent} retry={$retry} idle={$idle} released={$released}");

            return $retry === 0 ? EXIT_SUCCESS : EXIT_ERROR;
        } catch (Throwable $exception) {
            log_message('error', 'SMS delivery worker unavailable: {exception}', ['exception' => $exception::class]);
            CLI::error('SMS delivery worker unavailable.');

            return EXIT_ERROR;
        }
    }
}
