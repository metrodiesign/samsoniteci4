<?php

namespace App\Orders;

final class SmsDelivery
{
    public function __construct(
        private int $intentId,
        private string $idempotencyKey,
        private string $requestId,
        private int $orderId,
        private string $trackId,
        private string $telephone,
        private string $message,
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

    public function orderId(): int
    {
        return $this->orderId;
    }

    public function trackId(): string
    {
        return $this->trackId;
    }

    public function telephone(): string
    {
        return $this->telephone;
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return array<string, int|string> */
    public function __debugInfo(): array
    {
        return [
            'intentId' => $this->intentId, 'idempotencyKey' => $this->idempotencyKey,
            'requestId' => $this->requestId, 'orderId' => $this->orderId,
            'trackId' => $this->trackId, 'telephone' => '[redacted]', 'message' => '[redacted]',
        ];
    }
}
