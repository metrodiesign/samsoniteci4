<?php

namespace Tests\Ci4;

use App\Authentication\ResetDelivery;
use App\Authentication\ResetEmailRenderer;
use CodeIgniter\Test\CIUnitTestCase;

final class ResetEmailRendererTest extends CIUnitTestCase
{
    public function testRendersStandaloneCi3StyleResetDocumentWithCanonicalLinkOnly(): void
    {
        $token = str_repeat('a', 64);
        $delivery = new ResetDelivery(
            901,
            str_repeat('b', 64),
            'request-901',
            'recipient@example.invalid',
            $token,
        );

        $body = (new ResetEmailRenderer())->render($delivery);

        $body = (string) preg_replace('/<!-- DEBUG-VIEW (?:START|ENDED) \d+ [^-]+-->/', '', $body);

        self::assertStringStartsWith('<!DOCTYPE html>', ltrim($body));
        self::assertStringContainsString('<title>Reset Your Password</title>', $body);
        self::assertStringContainsString('Just one more step.', $body);
        self::assertStringContainsString('Reset Password Link', $body);
        self::assertStringContainsString(
            'href="http://example.invalid/reset-password?token=' . $token . '"',
            html_entity_decode($body, ENT_QUOTES, 'UTF-8'),
        );
        self::assertStringNotContainsString('recipient@example.invalid', $body);
        self::assertStringNotContainsString('request-901', $body);
    }
}
