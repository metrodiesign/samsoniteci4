#!/usr/bin/env php
<?php

declare(strict_types=1);

// Isolated compatibility runtime for one pinned CI3 caller scenario. It deliberately
// renders one requested template only; partial/email/export/error scenarios are never
// substituted with an unrelated browser URL.

if ($argc !== 4 || ! in_array($argv[1], ['ci3', 'ci4'], true)) {
    fwrite(STDERR, "usage: render-strict-presentation-scenario.php ci3|ci4 TEMPLATE OUTPUT\n");
    exit(2);
}

const BASEPATH = __DIR__ . '/../vendor/codeigniter4/framework/system/';
const APPPATH = __DIR__ . '/../app/';
const ENVIRONMENT = 'production';
const SHOW_DEBUG_BACKTRACE = false;
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
if (! defined('BASEPATH')) {
    define('BASEPATH', BASEPATH);
}

function base_url(string $path = ''): string { return 'http://127.0.0.1:18405/' . ltrim($path, '/'); }
function site_url(string $path = ''): string { return base_url($path); }
function set_value(string $field, mixed $default = ''): string { return is_scalar($default) ? (string) $default : ''; }
function validation_errors(string $prefix = '', string $suffix = ''): string { return ''; }
function form_open(string $action = '', array $attributes = []): string { return '<form action="' . htmlspecialchars(base_url($action), ENT_QUOTES) . '" method="post">'; }
function form_close(): string { return '</form>'; }
function csrf_field(): string { return '<input type="hidden" name="csrf_test_name" value="scenario-token">'; }
function esc(mixed $value, string $context = 'html'): string {
    if (! is_scalar($value) && $value !== null) return '';
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

final class ScenarioNull implements IteratorAggregate, Countable, Stringable
{
    public function __get(string $name): mixed { return ''; }
    public function __call(string $name, array $arguments): mixed { return ''; }
    public function getIterator(): Traversable { return new ArrayIterator([]); }
    public function count(): int { return 0; }
    public function __toString(): string { return ''; }
}

final class ScenarioLoader { public function helper(mixed $helper): void {} }
final class ScenarioSession {
    public function userdata(string $key): mixed { return match ($key) { 'GroupID' => 1, 'role' => 1, default => '' }; }
    public function flashdata(string $key): mixed { return null; }
}
final class ScenarioPagination { public function create_links(): string { return ''; } }
final class ScenarioContext
{
    public ScenarioLoader $load;
    public ScenarioSession $session;
    public ScenarioPagination $pagination;
    public ScenarioNull $request_order_model;
    public ScenarioNull $user_model;
    public function __construct() {
        $this->load = new ScenarioLoader();
        $this->session = new ScenarioSession();
        $this->pagination = new ScenarioPagination();
        $this->request_order_model = new ScenarioNull();
        $this->user_model = new ScenarioNull();
    }
}

function fixtureVariables(string $source): array
{
    $tokens = token_get_all($source);
    $variables = [];
    foreach ($tokens as $token) {
        if (is_array($token) && $token[0] === T_VARIABLE && $token[1] !== '$this') {
            $variables[substr($token[1], 1)] = 0;
        }
    }
    foreach (array_keys($variables) as $name) {
        if (preg_match('/(Records|Info|list|types|groups|ratings|jobs|rows|Status|Brand|Condition|Estimateprice|Fixed|Producttype|Providers|Branchs|branchs)$/i', $name) === 1) {
            $variables[$name] = [];
        }
    }
    return array_replace($variables, [
        'heading' => 'Scenario heading', 'message' => 'Scenario message',
        'severity' => 1, 'filepath' => '/scenario/caller.php', 'line' => 1,
        'exception' => new RuntimeException('Scenario exception'),
        'data' => ['name' => 'Scenario Customer', 'message' => 'Scenario message', 'reset_link' => 'http://127.0.0.1/reset/scenario'],
        'userInfo' => (object) ['name' => 'Scenario User', 'email' => 'scenario@example.invalid'],
        'sheet_data' => [], 'ratings' => [], 'jobs' => [], 'branchs' => [],
        'pageTitle' => 'Scenario page', 'name' => 'Scenario User', 'role_text' => 'Admin',
        'last_login' => '01/01/2026 00:00:00', 'response' => '[]',
        'GroupID' => 1, 'BranchID' => null, 'page' => 0, 'searchText' => '',
        'sdate' => '01/01/2026', 'edate' => '31/01/2026',
    ]);
}

$template = $argv[2];
if (preg_match('/\A[a-zA-Z0-9_\/-]+\.php\z/D', $template) !== 1) {
    fwrite(STDERR, "invalid template\n"); exit(2);
}
$ci4Root = realpath(__DIR__ . '/..');
$ci3Root = realpath(__DIR__ . '/../../samsoniteci3');
$path = $argv[1] === 'ci3'
    ? $ci3Root . '/application/views/' . $template
    : $ci4Root . '/app/Views/ci3/' . $template;
if (! is_file($path)) { fwrite(STDERR, "missing template: {$path}\n"); exit(2); }
$source = (string) file_get_contents($path);
$variables = fixtureVariables($source);
$context = new ScenarioContext();
$render = function (string $__path, array $__variables): string {
    extract($__variables, EXTR_SKIP);
    ob_start();
    try { include $__path; return (string) ob_get_clean(); }
    catch (Throwable $error) { ob_end_clean(); throw $error; }
};
try {
    $output = $render->call($context, $path, $variables);
    if (file_put_contents($argv[3], $output) === false) throw new RuntimeException('cannot write output');
} catch (Throwable $error) {
    fwrite(STDERR, $template . ': ' . $error::class . ': ' . $error->getMessage() . "\n"); exit(1);
}
