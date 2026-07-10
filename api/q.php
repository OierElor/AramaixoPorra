<?php
/**
 * Aramaixo Porra — kontsulta-API segurua (SELECT soilik).
 * Bezeroak (db-loader.js) JSON bidez bidaltzen du { sql, params } eta JSON itzultzen da.
 *
 * SEGURTASUNA:
 *  - SELECT/WITH kontsultak soilik; idazketa-eragiketak debekatuta.
 *  - Kontsulta bakarra (puntu eta komarik ez).
 *  - Prestatutako adierazpenak (parametroak lotuta).
 *  - GOMENDIOA: sortu SELECT-soilik baimena duen MySQL erabiltzaile bat.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db-read.php';   // api_pdo() / api_select() — kredentzialak hor daude

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST bakarrik', 405);

$req = json_decode(file_get_contents('php://input'), true);
if (!is_array($req) || !isset($req['sql'])) fail('sql falta da');

$sql = trim($req['sql']);
$params = (isset($req['params']) && is_array($req['params'])) ? $req['params'] : [];

// Segurtasun-iragazkiak
if (!preg_match('/^\s*(SELECT|WITH)\b/i', $sql)) fail('SELECT/WITH kontsultak soilik onartzen dira');
if (strpos($sql, ';') !== false) fail('Kontsulta bakarra onartzen da');
if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE|REPLACE|GRANT|REVOKE|RENAME|OUTFILE|DUMPFILE|LOAD_FILE)\b/i', $sql)) {
    fail('Eragiketa debekatua');
}

try {
    echo json_encode(api_select($sql, $params));
} catch (Throwable $e) {
    fail('DB errorea: ' . $e->getMessage(), 500);
}
