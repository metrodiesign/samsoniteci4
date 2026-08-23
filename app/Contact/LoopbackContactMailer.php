<?php

namespace App\Contact;

use RuntimeException;

final class LoopbackContactMailer
{
    /** @var array<string, true> */
    private array $messages = [];

    public function send(ContactDelivery $delivery): void
    {
        if (
            ! str_ends_with($delivery->recipient(), '@example.invalid')
            || ! str_ends_with($delivery->replyTo(), '@example.invalid')
        ) {
            throw new RuntimeException('Loopback contact delivery rejected.');
        }
        $this->messages[$delivery->idempotencyKey()] = true;
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
