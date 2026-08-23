<?php

namespace App\Authentication;

use CodeIgniter\Database\BaseConnection;
use Throwable;

final class ResetAuditLog
{
    private const TABLE = 'ci4_password_reset_audit';

    public function __construct(private BaseConnection $db)
    {
    }

    public function record(string $event, ?string $identity, ?string $clientIp): void
    {
        try {
            $this->db->table(self::TABLE)->insert([
                'event'         => $event,
                'identity_hash' => $this->hashIdentity($identity),
                'client_ip'     => $clientIp,
                'occurred_at'   => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'Password reset audit write failed: {exception}', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function hashIdentity(?string $identity): ?string
    {
        if ($identity === null) {
            return null;
        }

        return hash('sha256', strtolower(trim($identity)));
    }
}
