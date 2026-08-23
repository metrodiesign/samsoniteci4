<?php

namespace App\Authentication;

use InvalidArgumentException;
use RuntimeException;

final class PasswordPolicy
{
    private const MIN_CHARACTERS = 12;

    private const MAX_CHARACTERS = 128;

    public function accepts(string $password): bool
    {
        if (! mb_check_encoding($password, 'UTF-8')) {
            return false;
        }

        $length = mb_strlen($password, 'UTF-8');

        return $length >= self::MIN_CHARACTERS && $length <= self::MAX_CHARACTERS;
    }

    public function hash(string $password): string
    {
        if (! $this->accepts($password)) {
            throw new InvalidArgumentException('Password must contain 12 to 128 UTF-8 characters.');
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID);

        if (! is_string($hash)) {
            throw new RuntimeException('Unable to hash password.');
        }

        return $hash;
    }
}
