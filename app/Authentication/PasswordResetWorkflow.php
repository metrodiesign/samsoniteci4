<?php

namespace App\Authentication;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use RuntimeException;

final class PasswordResetWorkflow
{
    private ShadowUserStore $users;

    private ResetTokenStore $tokens;

    public function __construct(BaseConnection $db, ?ResetTokenFactory $tokenFactory = null)
    {
        $this->users  = new ShadowUserStore($db);
        $this->tokens = new ResetTokenStore($db, $tokenFactory);
    }

    public function reset(
        string $email,
        string $token,
        string $password,
        DateTimeImmutable $now,
    ): bool {
        $passwordPolicy = new PasswordPolicy();

        if (! $passwordPolicy->accepts($password)) {
            return false;
        }

        $userId = $this->users->findActiveIdByEmail($email);

        if ($userId === null) {
            return false;
        }

        return $this->tokens->consume(
            $userId,
            $token,
            $now,
            static function (BaseConnection $db) use ($userId, $password, $passwordPolicy): void {
                $passwordHash = $passwordPolicy->hash($password);

                if (! (new ShadowUserStore($db))->replacePasswordAndRevokeSessions($userId, $passwordHash)) {
                    throw new RuntimeException('Unable to reset shadow user password.');
                }
            },
        );
    }
}
