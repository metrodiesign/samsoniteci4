<?php

namespace App\Commands;

use App\Orders\LoopbackSmsTransport;
use App\Orders\SmsDeliveryIntentStore;
use App\Orders\SmsDeliveryWorker;
use App\Orders\ThaiBulkSmsTransport;
use Closure;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;
use Throwable;

final class SmsDeliveryWork extends BaseCommand
{
    protected $group = 'Samsonite CI4';
    protected $name = 'sms:delivery-work';
    protected $description = 'Processes queued SMS delivery through the loopback or ThaiBulkSMS transport.';
    protected $usage = 'sms:delivery-work --transport loopback|thaibulksms [--limit 10]';
    protected $options = [
        '--transport' => 'Required. loopback (non-production) or thaibulksms.',
        '--limit' => 'Maximum intents to inspect (1-100, default 10).',
    ];

    public function run(array $params): int
    {
        $limit = filter_var($params['limit'] ?? 10, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);
        $transport = $params['transport'] ?? null;
        if (! in_array($transport, ['loopback', 'thaibulksms'], true) || $limit === false) {
            CLI::error('Use --transport loopback|thaibulksms and --limit 1-100.');

            return EXIT_ERROR;
        }
        if ($transport === 'loopback' && ENVIRONMENT === 'production') {
            CLI::error('Loopback transport is not available in production.');

            return EXIT_ERROR;
        }

        if ($transport === 'loopback') {
            $smsTransport = new LoopbackSmsTransport();
        } else {
            $apiKey    = (string) env('THAIBULKSMS_API_KEY', '');
            $apiSecret = (string) env('THAIBULKSMS_API_SECRET', '');
            $sender    = (string) env('THAIBULKSMS_SENDER', '');
            $missing   = [];
            if ($apiKey === '') {
                $missing[] = 'THAIBULKSMS_API_KEY';
            }
            if ($apiSecret === '') {
                $missing[] = 'THAIBULKSMS_API_SECRET';
            }
            if ($sender === '') {
                $missing[] = 'THAIBULKSMS_SENDER';
            }
            if ($missing !== []) {
                CLI::error('Missing SMS credential(s): ' . implode(', ', $missing));

                return EXIT_ERROR;
            }
            $smsTransport = new ThaiBulkSmsTransport(service('curlrequest'), $apiKey, $apiSecret, $sender);
        }

        try {
            $now = new DateTimeImmutable('now');
            $store = new SmsDeliveryIntentStore(db_connect(), service('encrypter'));
            $released = $store->releaseStale($now->modify('-15 minutes'), $now);
            $worker = new SmsDeliveryWorker($store);
            $sent = 0;
            $retry = 0;
            $idle = 0;
            for ($attempt = 0; $attempt < $limit; $attempt++) {
                $result = $worker->runNext($now, Closure::fromCallable([$smsTransport, 'send']));
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
