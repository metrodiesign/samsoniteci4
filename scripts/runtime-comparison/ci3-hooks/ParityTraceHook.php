<?php

final class ParityTraceHook
{
    public function bootstrapSession(): void
    {
        $this->registerShutdownTrace();
        $profile = $_GET['parity_session'] ?? '';
        if (getenv('PARITY_SESSION_BOOTSTRAP') !== 'enabled' || ! in_array($profile, array('admin', 'branch'), TRUE)) {
            return;
        }
        $username = $profile === 'admin' ? 'wp00c-admin' : 'wp00c-a';
        $expectedRole = $profile === 'admin' ? 1 : 2;
        $CI =& get_instance();
        $row = $CI->db->select('users.userId, users.roleId, users.group_id, users.branch_id, users.name, roles.role')
            ->from('tbl_users users')
            ->join('tbl_roles roles', 'roles.roleId = users.roleId', 'left')
            ->where('users.username', $username)
            ->where('users.isDeleted', 0)
            ->get()
            ->row();
        if ($row === null || (int) $row->roleId !== $expectedRole) {
            show_error('Synthetic parity user is unavailable.', 503);
        }
        $CI->session->set_userdata(array(
            'userId' => (int) $row->userId, 'role' => (int) $row->roleId,
            'GroupID' => (int) $row->group_id,
            'BranchID' => $row->branch_id === null ? null : (int) $row->branch_id,
            'roleText' => (string) $row->role, 'name' => (string) $row->name,
            'lastLogin' => '2026-08-30 09:00:00', 'isLoggedIn' => TRUE,
        ));
        redirect('/dashboard');
        exit;
    }

    private function registerShutdownTrace(): void
    {
        $requestId = isset($_SERVER['HTTP_X_PARITY_REQUEST_ID'])
            ? $_SERVER['HTTP_X_PARITY_REQUEST_ID']
            : (isset($_GET['parity_request_id']) ? $_GET['parity_request_id'] : getenv('PARITY_REQUEST_ID'));
        if (! is_string($requestId) || preg_match('/\A[a-zA-Z0-9_-]{8,64}\z/D', $requestId) !== 1) {
            return;
        }
        register_shutdown_function(static function () use ($requestId): void {
            $root = str_replace('\\', '/', APPPATH . 'views/');
            $templates = array();
            foreach (get_included_files() as $file) {
                $normalized = str_replace('\\', '/', $file);
                if (strpos($normalized, $root) === 0) {
                    $templates[] = substr($normalized, strlen($root));
                }
            }
            file_put_contents('/tmp/ci3-parity-template-trace.jsonl', json_encode(array(
                'request_id' => $requestId, 'timestamp' => gmdate(DATE_ATOM),
                'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'CLI',
                'path' => isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : 'CLI',
                'status' => http_response_code(), 'templates' => array_values(array_unique($templates)),
            )) . "\n", FILE_APPEND | LOCK_EX);
        });
    }

    public function display(): void
    {
        $requestId = isset($_SERVER['HTTP_X_PARITY_REQUEST_ID'])
            ? $_SERVER['HTTP_X_PARITY_REQUEST_ID']
            : (isset($_GET['parity_request_id']) ? $_GET['parity_request_id'] : getenv('PARITY_REQUEST_ID'));
        if (is_string($requestId) && preg_match('/\A[a-zA-Z0-9_-]{8,64}\z/D', $requestId) === 1) {
            $root = str_replace('\\', '/', APPPATH . 'views/');
            $templates = array();
            foreach (get_included_files() as $file) {
                $normalized = str_replace('\\', '/', $file);
                if (strpos($normalized, $root) === 0) {
                    $templates[] = substr($normalized, strlen($root));
                }
            }
            $record = array(
                'request_id' => $requestId,
                'timestamp' => gmdate(DATE_ATOM),
                'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'CLI',
                'path' => isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : 'CLI',
                'status' => http_response_code(),
                'templates' => array_values(array_unique($templates)),
            );
            file_put_contents('/tmp/ci3-parity-template-trace.jsonl', json_encode($record) . "\n", FILE_APPEND | LOCK_EX);
        }

        $CI =& get_instance();
        $CI->output->_display();
    }
}
