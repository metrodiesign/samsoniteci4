<?php

namespace App\Contact;

use Closure;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class ContactDeliveryWorker
{
    public function __construct(private ContactDeliveryIntentStore $store)
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
            if (! $this->store->markRetry(
                $delivery->intentId(),
                'provider_failure',
                $now->modify('+5 minutes'),
                $now,
            )) {
                throw new RuntimeException('Unable to schedule contact delivery retry.');
            }

            return 'retry';
        }
        if (! $this->store->markSent($delivery->intentId(), $now)) {
            throw new RuntimeException('Unable to mark contact delivery sent.');
        }

        return 'sent';
    }
}
