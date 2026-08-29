<?php

namespace App\Authentication;

use RuntimeException;

final class LoopbackResetMailer
{
    /** @var array<string, array{request_id: string}> */
    private array $messages = [];

    public function send(ResetDelivery $delivery): void
    {
        if (
            ! str_ends_with($delivery->recipient(), '@example.invalid')
            || preg_match('/^[0-9a-f]{64}$/D', $delivery->token()) !== 1
        ) {
            throw new RuntimeException('Loopback reset delivery rejected.');
        }

        $idempotencyKey = $delivery->idempotencyKey();

        if (isset($this->messages[$idempotencyKey])) {
            return;
        }

        (new ResetEmailRenderer())->render($delivery);
        $this->messages[$idempotencyKey] = ['request_id' => $delivery->requestId()];
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function lastIdempotencyKey(): string
    {
        return array_key_last($this->messages) ?? '';
    }

    /** @return array{messageCount: int, idempotencyKeys: list<string>} */
    public function __debugInfo(): array
    {
        return [
            'messageCount'    => $this->count(),
            'idempotencyKeys' => array_keys($this->messages),
        ];
    }
}
