<?php

namespace App\Contact;

final class ContactDelivery
{
    public function __construct(
        private int $intentId,
        private string $idempotencyKey,
        private string $requestId,
        private string $recipient,
        private string $replyTo,
        private string $fullname,
        private string $phone,
        private string $detail,
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

    public function replyTo(): string
    {
        return $this->replyTo;
    }

    public function fullname(): string
    {
        return $this->fullname;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function detail(): string
    {
        return $this->detail;
    }

    /** @return array<string, int|string> */
    public function __debugInfo(): array
    {
        return [
            'intentId'      => $this->intentId,
            'idempotencyKey' => $this->idempotencyKey,
            'requestId'     => $this->requestId,
            'recipient'     => '[redacted]',
            'replyTo'       => '[redacted]',
            'fullname'      => '[redacted]',
            'phone'         => '[redacted]',
            'detail'        => '[redacted]',
        ];
    }
}
