<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

final class ParityError extends BaseCommand
{
    protected $group = 'parity';
    protected $name = 'parity:error';
    protected $description = 'Trigger parity-only CLI exception rendering.';

    public function run(array $params): void
    {
        if (ENVIRONMENT !== 'parity' || getenv('PARITY_ERROR_TRIGGER') !== 'enabled') {
            CLI::error('Parity error trigger is unavailable.');
            return;
        }
        $kind = $params[0] ?? 'exception';
        if ($kind === '404') {
            throw PageNotFoundException::forPageNotFound('Synthetic parity CLI 404.');
        }
        if ($kind === 'db') {
            db_connect()->query('SELECT * FROM __parity_missing_table');
        }
        if ($kind === 'php') {
            trigger_error('Synthetic parity CLI PHP error.', E_USER_WARNING);
        }
        throw new RuntimeException('Synthetic parity CLI ' . $kind . ' error.');
    }
}
