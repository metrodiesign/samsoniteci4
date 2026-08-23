<?php

namespace App\Authentication;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class ResetTokenFactory
{
    private const BYTE_LENGTH = 32;

    private const REQUEST_ID_BYTE_LENGTH = 16;

    private const TTL = '+30 minutes';

    private Closure $randomBytes;

    public function __construct(?Closure $randomBytes = null)
    {
        $this->randomBytes = $randomBytes ?? static fn (int $length): string => random_bytes($length);
    }

    public function issue(DateTimeImmutable $now): IssuedResetToken
    {
        $bytes = ($this->randomBytes)(self::BYTE_LENGTH);

        if (strlen($bytes) !== self::BYTE_LENGTH) {
            throw new RuntimeException('Reset token generator must return 32 bytes.');
        }

        $requestIdBytes = ($this->randomBytes)(self::REQUEST_ID_BYTE_LENGTH);

        if (strlen($requestIdBytes) !== self::REQUEST_ID_BYTE_LENGTH) {
            throw new RuntimeException('Reset request ID generator must return 16 bytes.');
        }

        $createdAt = $now->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $token     = bin2hex($bytes);

        return new IssuedResetToken(
            $token,
            hash('sha256', $token),
            bin2hex($requestIdBytes),
            $createdAt,
            $createdAt->modify(self::TTL),
        );
    }

    public function hashCandidate(string $token): ?string
    {
        if (preg_match('/^[0-9a-f]{64}$/D', $token) !== 1) {
            return null;
        }

        return hash('sha256', $token);
    }
}
