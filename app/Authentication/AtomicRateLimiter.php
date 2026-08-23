<?php

namespace App\Authentication;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

final class AtomicRateLimiter
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function consume(
        string $scope,
        int $capacity,
        int $windowSeconds,
        DateTimeImmutable $now,
    ): ?int {
        if ($scope === '' || $capacity < 1 || $windowSeconds < 1) {
            throw new InvalidArgumentException('Rate-limit scope, capacity and window must be positive.');
        }

        $timestamp   = $now->getTimestamp();
        $window      = intdiv($timestamp, $windowSeconds);
        $expiresAt   = ($window + 1) * $windowSeconds;
        $bucketKey   = hash('sha256', $scope);
        $requestCount = $this->increment(
            $bucketKey,
            $window,
            date('Y-m-d H:i:s', $expiresAt),
            $now->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'),
        );

        return $requestCount <= $capacity ? null : max(1, $expiresAt - $timestamp);
    }

    public function pruneExpired(DateTimeImmutable $before): int
    {
        $deleted = $this->db->table('ci4_rate_limit_buckets')
            ->where('expires_at <', $before->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'))
            ->delete();

        if (! $deleted) {
            throw new RuntimeException('Unable to prune expired rate-limit buckets.');
        }

        return $this->db->affectedRows();
    }

    private function increment(string $bucketKey, int $window, string $expiresAt, string $createdAt): int
    {
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('ci4_rate_limit_buckets'));

        if ($this->db->getPlatform() === 'SQLite3') {
            $result = $this->db->query(
                "INSERT INTO {$table} (bucket_key, window_id, request_count, expires_at, created_at)
                 VALUES (?, ?, 1, ?, ?)
                 ON CONFLICT(bucket_key) DO UPDATE
                 SET request_count = CASE
                         WHEN window_id = excluded.window_id THEN request_count + 1
                         ELSE 1
                     END,
                     window_id = excluded.window_id,
                     expires_at = excluded.expires_at,
                     created_at = excluded.created_at",
                [$bucketKey, $window, $expiresAt, $createdAt],
            );
            $row = $result === true
                ? $this->db->table('ci4_rate_limit_buckets')
                    ->select('request_count')
                    ->where('bucket_key', $bucketKey)
                    ->get()
                    ->getRowArray()
                : null;

            if (! is_array($row) || ! isset($row['request_count'])) {
                throw new RuntimeException('Atomic SQLite rate-limit increment failed.');
            }

            return (int) $row['request_count'];
        }

        if ($this->db->getPlatform() === 'MySQLi') {
            $result = $this->db->query(
                "INSERT INTO {$table} (bucket_key, window_id, request_count, expires_at, created_at)
                 VALUES (?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     request_count = LAST_INSERT_ID(IF(window_id = VALUES(window_id), request_count + 1, 1)),
                     window_id = VALUES(window_id),
                     expires_at = VALUES(expires_at),
                     created_at = VALUES(created_at)",
                [$bucketKey, $window, $expiresAt, $createdAt],
            );

            if ($result !== true) {
                throw new RuntimeException('Atomic MariaDB rate-limit increment failed.');
            }

            return $this->db->affectedRows() === 1 ? 1 : (int) $this->db->insertID();
        }

        throw new RuntimeException('Unsupported rate-limit database driver.');
    }
}
