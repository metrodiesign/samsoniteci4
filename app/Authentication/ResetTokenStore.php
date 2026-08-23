<?php

namespace App\Authentication;

use CodeIgniter\Database\BaseConnection;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ResetTokenStore
{
    private const PURPOSE = 'password_reset';

    private const TABLE = 'ci4_password_reset_tokens';

    private ResetTokenFactory $tokens;

    public function __construct(
        private BaseConnection $db,
        ?ResetTokenFactory $tokens = null,
    ) {
        $this->tokens = $tokens ?? new ResetTokenFactory();
    }

    public function issue(int $userId, IssuedResetToken $issued, ?Closure $onIssue = null): void
    {
        $this->assertIssuable($userId, $issued);
        $createdAt = $this->timestamp($issued->createdAt());

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start reset token transaction.');
        }

        try {
            $revoked = $this->db->table(self::TABLE)
                ->where('user_id', $userId)
                ->where('purpose', self::PURPOSE)
                ->where('consumed_at', null)
                ->where('revoked_at', null)
                ->update(['revoked_at' => $createdAt]);

            if (! $revoked) {
                throw new RuntimeException('Unable to revoke prior reset tokens.');
            }

            $inserted = $this->db->table(self::TABLE)->insert([
                'user_id'    => $userId,
                'purpose'    => self::PURPOSE,
                'request_id' => $issued->requestId(),
                'token_hash' => $issued->hash(),
                'created_at' => $createdAt,
                'expires_at' => $this->timestamp($issued->expiresAt()),
            ]);

            if (! $inserted) {
                throw new RuntimeException('Unable to persist reset token.');
            }

            if ($onIssue !== null) {
                $onIssue($this->db);
            }

            if (! $this->db->transCommit()) {
                throw new RuntimeException('Unable to persist reset token.');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    public function isValid(int $userId, string $token, DateTimeImmutable $now): bool
    {
        $hash = $this->tokens->hashCandidate($token);

        if ($userId < 1 || $hash === null) {
            return false;
        }

        return $this->db->table(self::TABLE)
            ->where('user_id', $userId)
            ->where('purpose', self::PURPOSE)
            ->where('token_hash', $hash)
            ->where('consumed_at', null)
            ->where('revoked_at', null)
            ->where('expires_at >', $this->timestamp($now))
            ->countAllResults() === 1;
    }

    public function consume(
        int $userId,
        string $token,
        DateTimeImmutable $now,
        Closure $onConsume,
    ): bool {
        $hash = $this->tokens->hashCandidate($token);

        if ($userId < 1 || $hash === null) {
            return false;
        }

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start reset token transaction.');
        }

        try {
            $timestamp = $this->timestamp($now);
            $this->db->table(self::TABLE)
                ->where('user_id', $userId)
                ->where('purpose', self::PURPOSE)
                ->where('token_hash', $hash)
                ->where('consumed_at', null)
                ->where('revoked_at', null)
                ->where('expires_at >', $timestamp)
                ->update(['consumed_at' => $timestamp]);

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $onConsume($this->db);

            if (! $this->db->transCommit()) {
                throw new RuntimeException('Unable to consume reset token.');
            }

            return true;
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    private function assertIssuable(int $userId, IssuedResetToken $issued): void
    {
        if (
            $userId < 1
            || preg_match('/^[0-9a-f]{32}$/D', $issued->requestId()) !== 1
            || ! hash_equals($issued->hash(), $this->tokens->hashCandidate($issued->token()) ?? '')
            || $issued->expiresAt()->getTimestamp() - $issued->createdAt()->getTimestamp() !== 1800
        ) {
            throw new InvalidArgumentException('Invalid reset token issue.');
        }
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }
}
