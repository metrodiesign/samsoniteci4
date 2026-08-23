<?php

namespace App\Authentication;

final class ResetDelivery
{
    public function __construct(
        private int $intentId,
        private string $idempotencyKey,
        private string $requestId,
        private string $recipient,
        private string $token,
    ) {
    }

    public function intentId(): int
    {
        return $this->intentId;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function recipient(): string
    {
        return $this->recipient;
    }

    public function token(): string
    {
        return $this->token;
    }

    /** @return array<string, int|string> */
    public function __debugInfo(): array
    {
        return [
            'intentId'      => $this->intentId,
            'idempotencyKey' => $this->idempotencyKey,
            'requestId'     => $this->requestId,
            'recipient'     => '[redacted]',
            'token'         => '[redacted]',
        ];
    }
}
