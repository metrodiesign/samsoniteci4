<?php

namespace App\Orders;

use CodeIgniter\HTTP\CURLRequest;
use RuntimeException;
use Throwable;

final class ThaiBulkSmsTransport
{
    private const ENDPOINT        = 'https://api-v2.thaibulksms.com/sms';
    private const CONNECT_TIMEOUT = 15;
    private const TIMEOUT         = 30;

    public function __construct(
        private CURLRequest $http,
        private string $apiKey,
        private string $apiSecret,
        private string $sender,
    ) {
    }

    public function send(SmsDelivery $delivery): void
    {
        try {
            $response = $this->http->post(self::ENDPOINT, [
                'auth'        => [$this->apiKey, $this->apiSecret, 'basic'],
                'form_params' => [
                    'msisdn'  => $delivery->telephone(),
                    'message' => $delivery->message(),
                    'sender'  => $this->sender,
                ],
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout'         => self::TIMEOUT,
                'http_errors'     => false,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('ThaiBulkSMS SMS transport request failed.', 0, $exception);
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $body = json_decode((string) $response->getBody(), true);
            $code = is_array($body) && isset($body['code']) && is_scalar($body['code'])
                ? (string) $body['code']
                : 'unknown';

            throw new RuntimeException(sprintf('ThaiBulkSMS SMS send failed (HTTP %d, error code %s).', $status, $code));
        }
    }
}
