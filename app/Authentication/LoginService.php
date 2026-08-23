<?php

namespace App\Authentication;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class LoginService
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** @return array<string, int|string|bool|null>|null */
    public function authenticate(
        string $identifier,
        string $password,
        string $ipAddress,
        string $agentString,
    ): ?array {
        $row = $this->db->table('ci4_users')
            ->select([
                'id',
                'password_hash',
                'display_name',
                'group_id',
                'role_id',
                'branch_id',
                'role_text',
                'session_version',
            ])
            ->where('username', trim($identifier))
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        if (
            $row === null
            || ! is_string($row['password_hash'] ?? null)
            || ! password_verify($password, $row['password_hash'])
        ) {
            return null;
        }

        $userId  = (int) $row['id'];
        $lastRow = $this->db->table('tbl_last_login')
            ->select('createdDtm')
            ->where('userId', $userId)
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();
        $lastLogin = is_string($lastRow['createdDtm'] ?? null)
            ? $lastRow['createdDtm']
            : date('Y-m-d H:i:s');
        $session = [
            'userId'         => $userId,
            'role'           => (int) $row['role_id'],
            'GroupID'        => (int) $row['group_id'],
            'BranchID'       => $row['branch_id'] === null ? null : (int) $row['branch_id'],
            'roleText'       => (string) $row['role_text'],
            'name'           => (string) $row['display_name'],
            'lastLogin'      => $lastLogin,
            'isLoggedIn'     => true,
            'sessionVersion' => (int) $row['session_version'],
        ];
        $historySession = $session;
        unset(
            $historySession['userId'],
            $historySession['isLoggedIn'],
            $historySession['lastLogin'],
            $historySession['sessionVersion'],
        );
        $inserted = $this->db->table('tbl_last_login')->insert([
            'userId'      => $userId,
            'sessionData' => json_encode($historySession, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'machineIp'   => substr($ipAddress, 0, 1024),
            'userAgent'   => 'Browser',
            'agentString' => substr($agentString, 0, 1024),
            'platform'    => 'Unknown',
            'createdDtm'  => date('Y-m-d H:i:s'),
        ]);

        if (! $inserted) {
            throw new RuntimeException('Unable to record login.');
        }

        return $session;
    }
}
