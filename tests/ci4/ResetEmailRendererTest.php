<?php

namespace Tests\Ci4;

use App\Authentication\ResetDelivery;
use App\Authentication\ResetEmailRenderer;
use CodeIgniter\Test\CIUnitTestCase;

final class ResetEmailRendererTest extends CIUnitTestCase
{
    public function testRendersCi3XhtmlStructureWithCanonicalResetButton(): void
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
        $body = html_entity_decode($body, ENT_QUOTES, 'UTF-8');

        self::assertStringStartsWith(
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
            ltrim($body),
        );
        $this->assertOrdered($body, [
            '<html xmlns="http://www.w3.org/1999/xhtml">',
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />',
            '<title>Reset Your Password</title>',
            '<table style="width:100%;border-spacing:0" cellpadding="0" cellspacing="0">',
            '<th style="border-top:solid 5px #f56400;font-weight:normal;text-align:center;background:#ffffff;border-bottom:solid 1px #e3e5e1">',
            '<table style="width:100%;max-width:596px;border-spacing:0;margin:0 auto" cellpadding="0" cellspacing="0" align="center">',
            '<table style="margin:0%;width:100%;border-spacing:0;table-layout:fixed" cellpadding="0" cellspacing="0">',
            '<cite style="text-align:center;display:block;font-style:normal">',
            'Just one more step.',
            '<dl style="list-style-type:none;padding:0;overflow:hidden;margin:0">',
            '<a href="http://example.invalid/" title="CodeInsect" style="display:inline-block" target="_blank">CodeInsect</a>',
            '<tr><th style="background:#f5f5f1;height:28px"></th></tr>',
            '<th style="background:#f5f5f1;font-weight:normal;text-align:left">',
            '<div style="min-height:28px"></div>',
            '<div style="padding:24px 3.6% 24px;background:#fff;border:1px solid #e3e5e1">',
            '<table cellpadding="0" cellspacing="0" style="width:100%;margin:0;padding:0">',
            '<td align="center">',
            '<b>Hi, Customer</b>! <span class="il">Please reset your password using the secure link below.</span>',
            '<div style="min-height:20px"></div>',
            'href="http://example.invalid/reset-password?token=' . $token . '"',
            '<span class="il"> Reset Password Link </span>',
            '<div style="min-height:28px"></div>',
            '<div class="yj6qo"></div>',
            '<div class="adL"></div>',
        ]);
        self::assertSame(3, substr_count($body, '<tr><th style="background:#f5f5f1;height:28px"></th></tr>'));
    }

    public function testDoesNotDiscloseDeliveryMetadataOrLegacyResetLocator(): void
    {
        foreach ([
            [987654321, str_repeat('b', 64), str_repeat('c', 32), 'first-marker@example.invalid', str_repeat('d', 64)],
            [123456789, str_repeat('e', 64), str_repeat('f', 32), 'second+marker@example.invalid', str_repeat('0', 64)],
        ] as [$intentId, $idempotencyKey, $requestId, $recipient, $token]) {
            $body = (new ResetEmailRenderer())->render(new ResetDelivery(
                $intentId,
                $idempotencyKey,
                $requestId,
                $recipient,
                $token,
            ));

            $decoded = html_entity_decode($body, ENT_QUOTES, 'UTF-8');
            self::assertSame(1, substr_count($decoded, $token));
            self::assertStringContainsString(
                'href="http://example.invalid/reset-password?token=' . $token . '"',
                $decoded,
            );
            foreach ([(string) $intentId, $idempotencyKey, $requestId, $recipient] as $marker) {
                self::assertStringNotContainsString($marker, $decoded);
            }
            self::assertStringNotContainsString('/resetPasswordConfirmUser/', $decoded);
            self::assertStringNotContainsString('reset-password?token=' . $token . '&', $decoded);
            self::assertStringNotContainsString(rawurlencode($recipient), $decoded);
        }
    }

    /** @param list<string> $needles */
    private function assertOrdered(string $body, array $needles): void
    {
        $position = -1;

        foreach ($needles as $needle) {
            $next = strpos($body, $needle, $position + 1);
            self::assertNotFalse($next, $needle);
            self::assertGreaterThan($position, $next, $needle);
            $position = $next;
        }
    }
}
