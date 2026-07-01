<?php
declare(strict_types=1);

// Bootstrap: config, errors, session, db. Included by every entry point.

const APP_ROOT = __DIR__ . '/..';
$config = require APP_ROOT . '/config.php';

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', $config['password'] === 'change-me-please' ? '1' : '0');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require APP_ROOT . '/app/db.php';
require APP_ROOT . '/app/helpers.php';
require APP_ROOT . '/app/actions.php';

db_init($config['db_path']);
