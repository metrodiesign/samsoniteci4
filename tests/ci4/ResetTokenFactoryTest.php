<?php

namespace Tests\Ci4;

use App\Authentication\ResetTokenFactory;
use CodeIgniter\Test\CIUnitTestCase;
use DateTimeImmutable;
use RuntimeException;

final class ResetTokenFactoryTest extends CIUnitTestCase
{
    public function testIssueCreatesThirtyMinuteHashedToken(): void
    {
        $factory = new ResetTokenFactory(
            static fn (int $length): string => str_repeat("\x01", $length),
        );

        $issued = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));

        self::assertSame(
            '0101010101010101010101010101010101010101010101010101010101010101',
            $issued->token(),
        );
        self::assertSame(
            '58badd9b455145b487ad24c7f259cc437cc4ffd0784716845249321fb5961cfc',
            $issued->hash(),
        );
        self::assertSame('01010101010101010101010101010101', $issued->requestId());
        self::assertSame('2026-08-22T09:00:00+00:00', $issued->createdAt()->format(DATE_ATOM));
        self::assertSame('2026-08-22T09:30:00+00:00', $issued->expiresAt()->format(DATE_ATOM));
        self::assertNotSame($issued->token(), $issued->hash());
    }

    public function testHashCandidateRejectsMalformedToken(): void
    {
        $factory = new ResetTokenFactory();
        $token   = '0101010101010101010101010101010101010101010101010101010101010101';

        self::assertSame(
            '58badd9b455145b487ad24c7f259cc437cc4ffd0784716845249321fb5961cfc',
            $factory->hashCandidate($token),
        );
        self::assertNull($factory->hashCandidate('short'));
        self::assertNull($factory->hashCandidate('A' . substr($token, 1)));
        self::assertNull($factory->hashCandidate($token . '00'));
    }

    public function testIssueFailsClosedWhenRandomSourceReturnsWrongLength(): void
    {
        $this->expectException(RuntimeException::class);

        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat('x', $length - 1));
        $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
    }

    public function testIssuedTokenDoesNotExposeSecretsWhenSerializedOrDumped(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x08", $length));
        $issued  = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));

        $json = json_encode($issued, JSON_THROW_ON_ERROR);
        ob_start();
        var_dump($issued);
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString($issued->token(), $json);
        self::assertStringNotContainsString($issued->hash(), $json);
        self::assertStringNotContainsString($issued->token(), $dump);
        self::assertStringNotContainsString($issued->hash(), $dump);
        self::assertStringContainsString('[REDACTED]', $dump);
    }
}
