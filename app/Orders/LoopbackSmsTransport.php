<?php

namespace App\Orders;

use RuntimeException;

final class LoopbackSmsTransport
{
    /** @var array<string, true> */
    private array $sent = [];
    private ?string $lastKey = null;

    public function send(SmsDelivery $delivery): void
    {
        if (ENVIRONMENT === 'production') {
            throw new RuntimeException('Loopback SMS transport is disabled in production.');
        }
        $this->lastKey = $delivery->idempotencyKey();
        $this->sent[$this->lastKey] = true;
    }

    public function count(): int
    {
        return count($this->sent);
    }

    public function lastIdempotencyKey(): ?string
    {
        return $this->lastKey;
    }

    /** @return array{count:int,lastKey:string|null} */
    public function __debugInfo(): array
    {
        return ['count' => $this->count(), 'lastKey' => $this->lastKey];
    }
}
