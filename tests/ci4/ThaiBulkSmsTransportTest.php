<?php

namespace Tests\Ci4;

use App\Orders\SmsDelivery;
use App\Orders\ThaiBulkSmsTransport;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCURLRequest;
use CodeIgniter\Test\StreamFilterTrait;
use Config\App;
use RuntimeException;

final class ThaiBulkSmsTransportTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    public function testSendPostsToV2EndpointWithBasicAuthAndFormBody(): void
    {
        $mock      = $this->mockCurl("HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n{\"remaining_credit\":100,\"bad_phone_number_list\":[]}");
        $transport = new ThaiBulkSmsTransport($mock, 'test-key', 'test-secret', 'TestSender');

        $transport->send($this->delivery('Track order https://example.test/tracking/ABC001'));

        self::assertSame('https://api-v2.thaibulksms.com/sms', $mock->curl_options[CURLOPT_URL]);
        self::assertSame('POST', $mock->curl_options[CURLOPT_CUSTOMREQUEST]);
        self::assertSame('test-key:test-secret', $mock->curl_options[CURLOPT_USERPWD]);
        self::assertSame(CURLAUTH_BASIC, $mock->curl_options[CURLOPT_HTTPAUTH]);

        parse_str((string) $mock->curl_options[CURLOPT_POSTFIELDS], $fields);
        self::assertSame('0000000000', $fields['msisdn']);
        self::assertSame('Track order https://example.test/tracking/ABC001', $fields['message']);
        self::assertSame('TestSender', $fields['sender']);
    }

    public function testNon2xxResponseThrowsRuntimeException(): void
    {
        $mock      = $this->mockCurl("HTTP/1.1 500 Internal Server Error\r\n\r\n{\"code\":\"server_error\"}");
        $transport = new ThaiBulkSmsTransport($mock, 'k', 's', 'Sender');

        $this->expectException(RuntimeException::class);
        $transport->send($this->delivery());
    }

    public function testUnauthorizedResponseNeverLeaksCredential(): void
    {
        // Vendor 401; the mocked error body embeds credential-like text on purpose.
        $mock      = $this->mockCurl("HTTP/1.1 401 Unauthorized\r\n\r\n{\"code\":\"authentication_failed\",\"name\":40100,\"description\":\"invalid api secret test-secret\"}");
        $transport = new ThaiBulkSmsTransport($mock, 'test-key', 'test-secret', 'TestSender');

        try {
            $transport->send($this->delivery());
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('401', $exception->getMessage());
            self::assertStringContainsString('authentication_failed', $exception->getMessage());
            self::assertStringNotContainsString('test-key', $exception->getMessage());
            self::assertStringNotContainsString('test-secret', $exception->getMessage());
        }
    }

    public function testBadRequestResponseNeverLeaksPhoneNumber(): void
    {
        // Vendor 400; the mocked error body embeds the phone number on purpose.
        $mock      = $this->mockCurl("HTTP/1.1 400 Bad Request\r\n\r\n{\"code\":\"invalid_msisdn\",\"name\":40001,\"description\":\"bad number 0000000000\"}");
        $transport = new ThaiBulkSmsTransport($mock, 'k', 's', 'Sender');

        try {
            $transport->send($this->delivery());
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('400', $exception->getMessage());
            self::assertStringNotContainsString('0000000000', $exception->getMessage());
        }
    }

    public function testTransportExceptionNeverLeaksMessageBody(): void
    {
        $config    = new App();
        $mock      = new class ($config, new URI(), new Response($config), []) extends MockCURLRequest {
            protected function sendRequest(array $curlOptions = []): string
            {
                throw HTTPException::forCurlError('28', 'Operation timed out');
            }
        };
        $transport = new ThaiBulkSmsTransport($mock, 'k', 's', 'Sender');
        $secretMessage = 'CONFIDENTIAL-SMS-BODY-9999';

        try {
            $transport->send($this->delivery($secretMessage));
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertInstanceOf(RuntimeException::class, $exception);
            self::assertStringNotContainsString($secretMessage, $exception->getMessage());
            self::assertStringNotContainsString('0000000000', $exception->getMessage());
        }
    }

    public function testCommandRejectsThaiBulkSmsWhenCredentialsMissingWithoutLeakingValues(): void
    {
        // One credential present, two missing: the reject must name the missing
        // fields and never echo the value of the one that is set.
        $presentValue = 'do-not-leak-this-key-value';
        $this->setSmsEnv([
            'THAIBULKSMS_API_KEY'    => $presentValue,
            'THAIBULKSMS_API_SECRET' => null,
            'THAIBULKSMS_SENDER'     => null,
        ]);

        try {
            command('sms:delivery-work --transport thaibulksms --limit 5');
            $output = $this->getStreamFilterBuffer();

            self::assertStringContainsString('THAIBULKSMS_API_SECRET', $output);
            self::assertStringContainsString('THAIBULKSMS_SENDER', $output);
            self::assertStringNotContainsString('THAIBULKSMS_API_KEY', $output);
            self::assertStringNotContainsString($presentValue, $output);
            self::assertStringNotContainsString('sms_delivery sent=', $output);
        } finally {
            $this->setSmsEnv([
                'THAIBULKSMS_API_KEY'    => null,
                'THAIBULKSMS_API_SECRET' => null,
                'THAIBULKSMS_SENDER'     => null,
            ]);
        }
    }

    public function testCommandRejectsUnknownTransport(): void
    {
        command('sms:delivery-work --transport garbage --limit 5');
        $output = $this->getStreamFilterBuffer();
        self::assertStringContainsString('loopback|thaibulksms', $output);
        self::assertStringNotContainsString('sms_delivery sent=', $output);

        $this->resetStreamFilterBuffer();

        command('sms:delivery-work --limit 5');
        $output = $this->getStreamFilterBuffer();
        self::assertStringContainsString('loopback|thaibulksms', $output);
        self::assertStringNotContainsString('sms_delivery sent=', $output);
    }

    private function mockCurl(string $rawResponse): MockCURLRequest
    {
        $config = new App();
        $mock   = new MockCURLRequest($config, new URI(), new Response($config), []);
        $mock->setOutput($rawResponse);

        return $mock;
    }

    private function delivery(string $message = 'Track order https://example.test/tracking/ABC001'): SmsDelivery
    {
        return new SmsDelivery(1, 'idem-key', 'req-1', 1001, 'ABC001', '0000000000', $message);
    }

    /**
     * @param array<string, string|null> $values null clears the variable.
     */
    private function setSmsEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);

                continue;
            }
            $_SERVER[$key] = $value;
            $_ENV[$key]    = $value;
            putenv($key . '=' . $value);
        }
    }
}
