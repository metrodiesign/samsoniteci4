<?php

namespace App\Authentication;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

final class LegacyUserImporter
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function import(): int
    {
        if (! $this->db->tableExists('tbl_users') || ! $this->db->tableExists('tbl_roles')) {
            return 0;
        }

        $rows = $this->db->table('tbl_users users')
            ->select([
                'users.userId',
                'users.email',
                'users.username',
                'users.password',
                'users.name',
                'users.group_id',
                'users.roleId',
                'users.branch_id',
                'users.isDeleted',
                'roles.role',
            ])
            ->join('tbl_roles roles', 'roles.roleId = users.roleId')
            ->orderBy('users.userId', 'ASC')
            ->get()
            ->getResultArray();
        $store = new ShadowUserStore($this->db);

        $this->db->transBegin();
        try {
            foreach ($rows as $row) {
                $store->synchronizeLegacyUser(
                    (int) $row['userId'],
                    (string) $row['email'],
                    (string) $row['password'],
                    (int) $row['roleId'],
                    $row['branch_id'] === null ? null : (int) $row['branch_id'],
                    (string) $row['username'],
                    (string) $row['name'],
                    (int) $row['group_id'],
                    (string) $row['role'],
                    (int) $row['isDeleted'] === 0,
                );
            }
            if (! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit legacy user import.');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }

        return count($rows);
    }
}
