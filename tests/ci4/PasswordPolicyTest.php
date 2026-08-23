<?php

namespace Tests\Ci4;

use App\Authentication\PasswordPolicy;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

final class PasswordPolicyTest extends CIUnitTestCase
{
    public function testAcceptsTwelveToOneHundredTwentyEightCharacters(): void
    {
        $policy = new PasswordPolicy();

        self::assertFalse($policy->accepts(str_repeat('a', 11)));
        self::assertTrue($policy->accepts(str_repeat('a', 12)));
        self::assertTrue($policy->accepts(str_repeat('ก', 128)));
        self::assertFalse($policy->accepts(str_repeat('ก', 129)));
    }

    public function testHashesAcceptedPassphraseWithoutBcryptTruncation(): void
    {
        $policy     = new PasswordPolicy();
        $passphrase = str_repeat('a', 80) . 'tail-one';
        $hash       = $policy->hash($passphrase);

        self::assertSame('argon2id', password_get_info($hash)['algoName']);
        self::assertTrue(password_verify($passphrase, $hash));
        self::assertFalse(password_verify(str_repeat('a', 80) . 'tail-two', $hash));
    }

    public function testRejectsHashingPasswordOutsidePolicy(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PasswordPolicy())->hash('too short');
    }
}
