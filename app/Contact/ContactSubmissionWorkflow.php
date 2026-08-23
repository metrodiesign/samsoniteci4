<?php

namespace App\Contact;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Encryption\EncrypterInterface;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ContactSubmissionWorkflow
{
    public function __construct(
        private BaseConnection $db,
        private EncrypterInterface $encrypter,
        private string $recipient,
    ) {
    }

    /** @param array{fullname: string, email: string, phone: string, detail: string} $contact */
    public function submit(string $submissionId, array $contact, ?DateTimeImmutable $now = null): string
    {
        $contact = array_map('trim', $contact);
        if (
            preg_match('/^[0-9a-f]{32}$/D', $submissionId) !== 1
            || $contact['fullname'] === ''
            || mb_strlen($contact['fullname']) > 128
            || filter_var($contact['email'], FILTER_VALIDATE_EMAIL) === false
            || strlen($contact['email']) > 128
            || preg_match('/^[0-9+ -]{7,32}$/D', $contact['phone']) !== 1
            || $contact['detail'] === ''
            || mb_strlen($contact['detail']) > 4000
            || filter_var($this->recipient, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new InvalidArgumentException('Invalid contact submission.');
        }
        if ($this->db->table('ci4_delivery_intents')
            ->where('kind', 'contact')
            ->where('request_id', $submissionId)
            ->countAllResults() !== 0
        ) {
            return 'duplicate';
        }

        $timestamp = ($now ?? new DateTimeImmutable('now'))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            if (! $this->db->table('contact')->insert([
                ...$contact,
                'samsoniteid' => null,
                'cdate'       => $timestamp,
            ])) {
                throw new RuntimeException('Unable to store contact.');
            }
            $contactId = (int) $this->db->insertID();
            $payload = json_encode([
                'contact_id' => $contactId,
                'recipient'  => strtolower($this->recipient),
                ...$contact,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (! $this->db->table('ci4_delivery_intents')->insert([
                'idempotency_key'   => hash('sha256', "contact\0" . $submissionId),
                'kind'              => 'contact',
                'user_id'           => $contactId,
                'request_id'        => $submissionId,
                'payload_ciphertext' => base64_encode($this->encrypter->encrypt($payload)),
                'status'            => 'pending',
                'attempt_count'     => 0,
                'available_at'      => $timestamp,
                'created_at'        => $timestamp,
                'updated_at'        => $timestamp,
            ])) {
                throw new RuntimeException('Unable to store contact delivery intent.');
            }
            if (! $this->db->transStatus()) {
                throw new RuntimeException('Unable to complete contact transaction.');
            }
            $this->db->transCommit();

            return 'created';
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }
}
