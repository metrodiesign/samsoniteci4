<?php

namespace App\Orders;

use Closure;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class SmsDeliveryWorker
{
    public function __construct(private SmsDeliveryIntentStore $store)
    {
    }

    public function runNext(DateTimeImmutable $now, Closure $send): string
    {
        $delivery = $this->store->reserveNext($now);
        if ($delivery === null) {
            return 'idle';
        }
        try {
            $send($delivery);
        } catch (Throwable) {
            if (! $this->store->markRetry($delivery->intentId(), 'provider_failure', $now->modify('+5 minutes'), $now)) {
                throw new RuntimeException('Unable to schedule SMS delivery retry.');
            }

            return 'retry';
        }
        if (! $this->store->markSent($delivery->intentId(), $now)) {
            throw new RuntimeException('Unable to mark SMS delivery sent.');
        }

        return 'sent';
    }
}
