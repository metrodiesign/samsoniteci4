<?php

namespace App\Authentication;

use DateTimeImmutable;

final readonly class IssuedResetToken
{
    public function __construct(
        private string $token,
        private string $hash,
        private string $requestId,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $expiresAt,
    ) {
    }

    public function token(): string
    {
        return $this->token;
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'token'     => '[REDACTED]',
            'hash'      => '[REDACTED]',
            'requestId' => $this->requestId,
            'createdAt' => $this->createdAt,
            'expiresAt' => $this->expiresAt,
        ];
    }
}
