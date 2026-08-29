<?php

// Deterministic local parity runtime: suppress the debug toolbar without enabling
// production-only HTTPS/cookie policy on the loopback HTTP harness.
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
defined('CI_DEBUG') || define('CI_DEBUG', false);
