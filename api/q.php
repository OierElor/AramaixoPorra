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

// ───── KONFIGURAZIOA — bete zure datuekin ───────────────────────────────────
$DB_HOST = 'localhost';
$DB_NAME = '6437239_aramaixoporra';
$DB_USER = 'Erabiltzaile';   // SELECT-soilik baimenarekin, gomendatua
$DB_PASS = 'erabiltzailePH';
// ────────────────────────────────────────────────────────────────────────────

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
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,   // LIMIT ? eta zenbaki-mota natiboak
        ]
    );
    // Komatxo bikoitzak identifikadore gisa (kontsultek SQLite estiloa darabilte)
    $pdo->exec("SET SESSION sql_mode='ANSI_QUOTES'");

    $stmt = $pdo->prepare($sql);
    foreach ($params as $i => $v) {
        if (is_int($v))        $stmt->bindValue($i + 1, $v, PDO::PARAM_INT);
        elseif (is_null($v))   $stmt->bindValue($i + 1, null, PDO::PARAM_NULL);
        elseif (is_bool($v))   $stmt->bindValue($i + 1, $v ? 1 : 0, PDO::PARAM_INT);
        else                   $stmt->bindValue($i + 1, (string) $v);
    }
    $stmt->execute();
    echo json_encode($stmt->fetchAll());
} catch (Throwable $e) {
    fail('DB errorea: ' . $e->getMessage(), 500);
}
