<?php
$cfg = require __DIR__ . '/config.php';
$u = $_SERVER['PHP_AUTH_USER'] ?? '';
$p = $_SERVER['PHP_AUTH_PW'] ?? '';
if (!hash_equals($cfg['auth_user'], $u) || !hash_equals($cfg['auth_pass'], $p)) {
    header('WWW-Authenticate: Basic realm="Aramaixo Porra Admin"');
    http_response_code(401);
    echo 'Autentifikazioa behar da.';
    exit;
}
include __DIR__ . '/index.html';
