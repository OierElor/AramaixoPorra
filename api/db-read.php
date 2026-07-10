<?php
/**
 * Aramaixo Porra — datu-base konexio partekatua, IRAKURKETA SOILIK.
 *
 * `api/q.php` eta `api/porra.php`-k erabiltzen dute, kredentzialak leku bakarrean
 * egon daitezen. Fitxategi honek funtzioak bakarrik definitzen ditu: ez du irteerarik
 * sortzen eta ez du eskaerarik prozesatzen. Zuzeneko HTTP sarbidea `api/.htaccess`-ek
 * blokeatzen du.
 *
 * SEGURTASUNA: erabiltzaileak SELECT-soilik baimena izan beharko luke MySQL-en.
 */

// ───── KONFIGURAZIOA — bete zure datuekin ───────────────────────────────────
const API_DB_HOST = 'PMYSQL104.dns-servicio.com';
const API_DB_NAME = '6437239_aramaixoporra';
const API_DB_USER = 'Erabiltzaile';   // SELECT-soilik baimenarekin, gomendatua
const API_DB_PASS = 'erabiltzailePH';
// ────────────────────────────────────────────────────────────────────────────

/** PDO konexioa (eskaera bakoitzeko bat). */
function api_pdo() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $pdo = new PDO(
        'mysql:host=' . API_DB_HOST . ';dbname=' . API_DB_NAME . ';charset=utf8mb4',
        API_DB_USER, API_DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,   // LIMIT ? eta zenbaki-mota natiboak
        ]
    );
    // Komatxo bikoitzak identifikadore gisa (kontsultek SQLite estiloa darabilte)
    $pdo->exec("SET SESSION sql_mode='ANSI_QUOTES'");
    return $pdo;
}

/** SELECT bat exekutatu eta errenkada guztiak itzuli. */
function api_select($sql, array $params = []) {
    $stmt = api_pdo()->prepare($sql);
    foreach ($params as $i => $v) {
        if (is_int($v))        $stmt->bindValue($i + 1, $v, PDO::PARAM_INT);
        elseif (is_null($v))   $stmt->bindValue($i + 1, null, PDO::PARAM_NULL);
        elseif (is_bool($v))   $stmt->bindValue($i + 1, $v ? 1 : 0, PDO::PARAM_INT);
        else                   $stmt->bindValue($i + 1, (string) $v);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}
