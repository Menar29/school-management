<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
$GLOBALS['config'] = require ROOT . '/config.php';
date_default_timezone_set($GLOBALS['config']['timezone']);
mb_internal_encoding('UTF-8');

require ROOT . '/src/Db.php';
require ROOT . '/src/helpers.php';
require ROOT . '/src/Totp.php';
require ROOT . '/src/Perm.php';
require ROOT . '/src/Auth.php';
require ROOT . '/src/Stock.php';
require ROOT . '/src/Caisse.php';
require ROOT . '/src/Compteurs.php';
require ROOT . '/src/Reappro.php';

function config(string $key, $default = null)
{
    $v = $GLOBALS['config'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return $default;
        $v = $v[$k];
    }
    return $v;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('pharma_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => (bool) config('https'),
        'samesite' => 'Lax',
    ]);
    session_start();
}
