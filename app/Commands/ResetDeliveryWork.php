<?php

namespace App\Commands;

use App\Authentication\AtomicRateLimiter;
use App\Authentication\LoopbackResetMailer;
use App\Authentication\ResetDeliveryIntentStore;
use App\Authentication\ResetDeliveryWorker;
use Closure;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;
use Throwable;

final class ResetDeliveryWork extends BaseCommand
{
    protected $group = 'Samsonite CI4';

    protected $name = 'reset:delivery-work';

    protected $description = 'Processes queued password-reset delivery through local loopback transport.';

    protected $usage = 'reset:delivery-work --transport loopback [--limit 10]';

    protected $options = [
        '--transport' => 'Required. Only loopback is available in this scaffold.',
        '--limit'     => 'Maximum intents to inspect (1-100, default 10).',
    ];

    public function run(array $params): int
    {
        $limit = filter_var($params['limit'] ?? 10, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);

        if (($params['transport'] ?? null) !== 'loopback' || $limit === false) {
            CLI::error('Use --transport loopback and --limit 1-100.');

            return EXIT_ERROR;
        }

        if (ENVIRONMENT === 'production') {
            CLI::error('Loopback reset delivery is disabled in production.');

            return EXIT_ERROR;
        }

        try {
            $now      = new DateTimeImmutable('now');
            $store    = new ResetDeliveryIntentStore(db_connect(), service('encrypter'));
            $released = $store->releaseStale($now->modify('-15 minutes'), $now);
            $ratePruned = (new AtomicRateLimiter(db_connect()))->pruneExpired($now);
            $mailer   = new LoopbackResetMailer();
            $worker   = new ResetDeliveryWorker($store);
            $sent     = 0;
            $retried  = 0;
            $idle     = 0;

            for ($attempt = 0; $attempt < $limit; $attempt++) {
                $result = $worker->runNext($now, Closure::fromCallable([$mailer, 'send']));

                if ($result === 'idle') {
                    $idle = 1;
                    break;
                }

                $result === 'sent' ? $sent++ : $retried++;
            }

            CLI::write(
                "reset_delivery sent={$sent} retry={$retried} idle={$idle} released={$released} rate_pruned={$ratePruned}",
            );

            return $retried === 0 ? EXIT_SUCCESS : EXIT_ERROR;
        } catch (Throwable $exception) {
            log_message('error', 'Reset delivery worker unavailable: {exception}', [
                'exception' => $exception::class,
            ]);
            CLI::error('Reset delivery worker unavailable.');

            return EXIT_ERROR;
        }
    }
}
