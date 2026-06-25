<?php
$cfg = require __DIR__ . '/config.php';
// FastCGI/PHP-FPM-rekin PHP_AUTH_USER hutsa egon daiteke; Authorization header-etik atera
if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] === '') {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Basic\s+(.+)$/i', $h, $m)) {
        [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']] =
            array_pad(explode(':', base64_decode($m[1]), 2), 2, '');
    }
}
$u = $_SERVER['PHP_AUTH_USER'] ?? '';
$p = $_SERVER['PHP_AUTH_PW'] ?? '';
if (!hash_equals($cfg['auth_user'], $u) || !hash_equals($cfg['auth_pass'], $p)) {
    header('WWW-Authenticate: Basic realm="Aramaixo Porra Admin"');
    http_response_code(401);
    echo 'Autentifikazioa behar da.';
    exit;
}
include __DIR__ . '/index.html';
