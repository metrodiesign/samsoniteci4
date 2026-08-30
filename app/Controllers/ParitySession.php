<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

final class ParitySession extends BaseController
{
    public function admin(): RedirectResponse
    {
        return $this->bootstrap('wp00c-admin', 1);
    }

    public function branch(): RedirectResponse
    {
        return $this->bootstrap('wp00c-a', 2);
    }

    private function bootstrap(string $username, int $expectedRole): RedirectResponse
    {
        if (! defined('ENVIRONMENT') || ENVIRONMENT !== 'parity' || getenv('PARITY_SESSION_BOOTSTRAP') !== 'enabled') {
            throw new RuntimeException('Parity session flow is unavailable.');
        }
        $row = db_connect()->table('ci4_users')
            ->select('id, display_name, group_id, role_id, branch_id, role_text, session_version')
            ->where('username', $username)
            ->where('is_active', 1)
            ->get()
            ->getRowArray();
        if ($row === null || (int) $row['role_id'] !== $expectedRole) {
            throw new RuntimeException('Synthetic parity user is unavailable.');
        }
        $session = service('session');
        $session->regenerate(true);
        $session->set([
            'userId' => (int) $row['id'], 'role' => (int) $row['role_id'],
            'GroupID' => (int) $row['group_id'],
            'BranchID' => $row['branch_id'] === null ? null : (int) $row['branch_id'],
            'roleText' => (string) $row['role_text'], 'name' => (string) $row['display_name'],
            'lastLogin' => '2026-08-30 09:00:00', 'isLoggedIn' => true,
            'sessionVersion' => (int) $row['session_version'],
        ]);

        return redirect()->to('/dashboard');
    }
}
