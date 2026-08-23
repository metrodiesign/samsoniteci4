<?php

namespace Tests\Ci4;

use App\Authentication\AtomicRateLimiter;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use DateTimeImmutable;

final class AtomicRateLimiterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    public function testSharedFixedWindowAllowsCapacityThenReturnsRetryAfter(): void
    {
        $limiter = new AtomicRateLimiter($this->db);
        $now     = new DateTimeImmutable('2026-08-22 10:00:00 UTC');

        self::assertNull($limiter->consume('reset-request:synthetic', 2, 900, $now));
        self::assertNull($limiter->consume('reset-request:synthetic', 2, 900, $now));
        self::assertSame(900, $limiter->consume('reset-request:synthetic', 2, 900, $now));

        $row = $this->db->table('ci4_rate_limit_buckets')->get()->getRowArray();
        self::assertSame(3, (int) $row['request_count']);
        self::assertNotSame('reset-request:synthetic', $row['bucket_key']);

        self::assertNull($limiter->consume('reset-request:synthetic', 2, 900, $now->modify('+15 minutes')));
        self::assertSame(1, $this->db->table('ci4_rate_limit_buckets')->countAllResults());
        self::assertSame(1, (int) $this->db->table('ci4_rate_limit_buckets')
            ->select('request_count')
            ->get()
            ->getRow('request_count'));
        self::assertSame(1, $limiter->pruneExpired($now->modify('+31 minutes')));
        self::assertSame(0, $this->db->table('ci4_rate_limit_buckets')->countAllResults());
    }
}
