<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Container override of application/config/database.php.
|
| The upstream file is gitignored in the CI3 repo and ships with literal placeholders
| ('DB_HOST', 'DB_USER', ...), so it can never connect as-is. This copy reads the values
| from the container environment instead of hardcoding them.
|
| Differences from upstream that are deliberate:
|   char_set  utf8   -> utf8mb4              the target database is utf8mb4
|   dbcollat  utf8_general_ci -> utf8mb4_general_ci
|   pconnect  TRUE   -> FALSE                persistent handles outlive a failing request
|                                            and make rehearsal failures hard to read
*/

$active_group  = 'default';
$active_record = TRUE;

$db['default']['hostname'] = getenv('CI3_DB_HOST') ?: 'db';
$db['default']['username'] = getenv('CI3_DB_USER') ?: '';
$db['default']['password'] = getenv('CI3_DB_PASSWORD') ?: '';
$db['default']['database'] = getenv('CI3_DB_NAME') ?: '';
$db['default']['dbdriver'] = 'mysqli';
$db['default']['port']     = (int) (getenv('CI3_DB_PORT') ?: 3306);

$db['default']['dbprefix'] = '';
$db['default']['pconnect'] = FALSE;
$db['default']['db_debug'] = TRUE;
$db['default']['cache_on'] = FALSE;
$db['default']['cachedir'] = '';
$db['default']['char_set'] = 'utf8mb4';
$db['default']['dbcollat'] = 'utf8mb4_general_ci';
$db['default']['swap_pre'] = '';
$db['default']['autoinit'] = TRUE;
$db['default']['stricton'] = FALSE;
