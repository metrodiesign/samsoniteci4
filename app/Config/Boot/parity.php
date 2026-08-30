<?php

declare(strict_types=1);

// Test-only runtime trace seam. Records included view paths, never response data.
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
defined('SHOW_DEBUG_BACKTRACE') || define('SHOW_DEBUG_BACKTRACE', false);
defined('BASEPATH') || define('BASEPATH', SYSTEMPATH);
defined('CI_DEBUG') || define('CI_DEBUG', false);

$requestId = $_SERVER['HTTP_X_PARITY_REQUEST_ID'] ?? $_GET['parity_request_id'] ?? getenv('PARITY_REQUEST_ID') ?: '';
if (is_string($requestId) && preg_match('/\A[a-zA-Z0-9_-]{8,64}\z/D', $requestId) === 1) {
    register_shutdown_function(static function () use ($requestId): void {
        $viewRoot = str_replace('\\', '/', APPPATH . 'Views/');
        $templates = [];
        foreach (get_included_files() as $file) {
            $normalized = str_replace('\\', '/', $file);
            if (str_starts_with($normalized, $viewRoot)) {
                $templates[] = substr($normalized, strlen($viewRoot));
            }
        }
        $record = [
            'request_id' => $requestId,
            'timestamp' => gmdate(DATE_ATOM),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'path' => parse_url($_SERVER['REQUEST_URI'] ?? 'CLI', PHP_URL_PATH),
            'status' => http_response_code(),
            'templates' => array_values(array_unique($templates)),
        ];
        file_put_contents(WRITEPATH . 'parity-template-trace.jsonl', json_encode($record, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    });
}
