<?php

declare(strict_types=1);

$uri = urldecode(
    parse_url('https://codeigniter.com' . ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '',
);

$_SERVER['SCRIPT_NAME'] = '/index.php';
$path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . ltrim($uri, '/');
$mutableLegacyBackground = preg_match(
    '#\A/uploads/web/(?:track_laptop|track_mobile|trackstatus_laptop|trackstatus_mobile|contact_laptop|contact_mobile)\.png\z#D',
    $uri,
) === 1;

if (! $mutableLegacyBackground && $uri !== '/' && (is_file($path) || is_dir($path))) {
    return false;
}

unset($uri, $path, $mutableLegacyBackground);

require $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'index.php';
