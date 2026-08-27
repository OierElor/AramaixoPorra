<?php
/**
 * Aramaixo Porra — PROBA-SISTEMA LOKALA (router-a `php -S`-rentzat).
 *
 * Webgunea LOKALEAN exekutatzeko, DATU ERREALEKIN, ezer publiko egin gabe.
 * Abiarazi: ./proba.sh   (ez exekutatu fitxategi hau zuzenean)
 *
 * ZERGATIK: MySQL-era EZIN da lokaletik konektatu (docs/garapena.md), baina
 * `api/q.php` endpoint publikoa da eta SELECT soilik onartzen du. Beraz kontsultak
 * zuzeneko API publikora birbidaltzen dira: kodea lokala, datuak errealak.
 *
 * SEGURTASUNA (produkzioa EZIN da hondatu hemendik):
 *  - Idazketa-endpoint publikoak BLOKEATUTA: api/porra.php eta api/proposal.php
 *    log-a idazten dute ETA EMAIL ERREALA bidaltzen dute.
 *  - Admin APIa: GET (irakurketa) soilik birbidaltzen da; POST/PUT/DELETE → 403.
 *  - `api/q.php`-k zerbitzarian SELECT soilik onartzen du → ezin du ezer idatzi.
 *  - `127.0.0.1`-era lotuta soilik (ikus proba.sh), ez sarera.
 *  - Zerbitzarira igoz gero INERTE da: `cli-server` SAPI-a bakarrik onartzen du,
 *    eta `dev/.htaccess`-ek webetik sarbidea ukatzen du.
 */

// ── SAPI-babesa: garapeneko zerbitzari integratutik KANPO ez du ezer egiten ──
if (PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit('Garapen-fitxategia (proba lokala). Ez dago erabilgarri.');
}

const PROD  = 'https://aramaixoporra.eus';
const DENBORA_MUGA = 10;   // segundo

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri  = rawurldecode($uri);
$meth = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$root = dirname(__DIR__);

// ─── Laguntzaileak ──────────────────────────────────────────────────────────

function json_erantzun($datuak, $kodea = 200) {
    http_response_code($kodea);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datuak, JSON_UNESCAPED_UNICODE);
    exit;
}

function debekatu($zergatik) {
    json_erantzun(['error' => 'PROBA: ' . $zergatik], 403);
}

/**
 * HTTP eskaera bat zuzeneko webgunera. `curl` luzapena EZ da behar
 * (lokalean ez dago instalatuta): stream-testuinguru bat erabiltzen da.
 * Itzultzen du: [gorputza, http_kodea, content_type].
 */
function urrunera($url, $metodoa = 'GET', $gorputza = null, $goiburuak = []) {
    $lerroak = [];
    foreach ($goiburuak as $izena => $balioa) $lerroak[] = "$izena: $balioa";

    $aukerak = [
        'http' => [
            'method'        => $metodoa,
            'header'        => implode("\r\n", $lerroak),
            'timeout'       => DENBORA_MUGA,
            'ignore_errors' => true,   // 4xx/5xx ere irakurri, ez jaurti
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ];
    if ($gorputza !== null) $aukerak['http']['content'] = $gorputza;

    $ctx  = stream_context_create($aukerak);
    $eran = @file_get_contents($url, false, $ctx);

    if ($eran === false) {
        return [null, 0, null];   // saretik ezin izan da lortu
    }

    // Egoera-kodea eta content-type erantzun-goiburuetatik atera.
    $kodea = 0; $mota = null;
    foreach ($http_response_header ?? [] as $g) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $g, $m)) $kodea = (int)$m[1];
        if (stripos($g, 'Content-Type:') === 0) $mota = trim(substr($g, 13));
    }
    return [$eran, $kodea, $mota];
}

/** Zuzeneko webgunera birbidali eta erantzuna bere horretan itzuli. */
function proxy($bidea, $metodoa, $gorputza = null, $goiburuak = []) {
    [$eran, $kodea, $mota] = urrunera(PROD . $bidea, $metodoa, $gorputza, $goiburuak);

    if ($eran === null) {
        json_erantzun([
            'error' => 'PROBA: ezin izan da zuzeneko webgunera konektatu (' . PROD . '). '
                     . 'Internet-konexioa egiaztatu.',
        ], 502);
    }
    http_response_code($kodea ?: 200);
    header('Content-Type: ' . ($mota ?: 'application/json; charset=utf-8'));
    header('Cache-Control: no-store');
    echo $eran;
    exit;
}

/** Eskaeraren gorputza (POST JSON). */
function sarrera_gorputza() {
    return file_get_contents('php://input');
}

/** PROBA bereizgarria: HTML-ean txertatzen da, lokala eta produkzioa ez nahasteko. */
function banner_html() {
    return '<div id="proba-banner" style="position:fixed;right:10px;bottom:10px;z-index:2147483647;'
         . 'pointer-events:none;background:rgba(20,20,20,.86);color:#ffd54f;font:600 11px/1.4 '
         . 'system-ui,sans-serif;padding:6px 10px;border-radius:6px;border:1px solid #ffd54f;'
         . 'letter-spacing:.02em;box-shadow:0 2px 10px rgba(0,0,0,.35)">'
         . '🔧 PROBA · datu errealak · idazketak blokeatuta</div>';
}

/** Banner-a `</body>` aurretik txertatu (azken agerraldian). */
function bannerra_txertatu($html) {
    $pos = strripos($html, '</body>');
    if ($pos === false) return $html . banner_html();
    return substr($html, 0, $pos) . banner_html() . substr($html, $pos);
}

/** Fitxategi lokala zerbitzatu, cache GABE (beti azken kode lokala). */
function fitxategia_zerbitzatu($abs) {
    $luz = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $motak = [
        'html' => 'text/html; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg'  => 'image/svg+xml',
    ];
    header('Cache-Control: no-store, must-revalidate');
    header('Content-Type: ' . ($motak[$luz] ?? 'application/octet-stream'));

    $edukia = file_get_contents($abs);
    if ($luz === 'html') $edukia = bannerra_txertatu($edukia);
    echo $edukia;
    exit;
}

// ─── 1 · Produkzioko .htaccess debekuen isla ────────────────────────────────
// `php -S`-k ez du .htaccess irakurtzen: eskuz errepikatu behar da, bestela
// lokalak produkzioak baino GEHIAGO erakutsiko luke.
if (preg_match('#^/api/(db-read|tresna-katalogoa)\.php$#', $uri)) {
    debekatu('barneko fitxategia (produkzioan ere blokeatuta)');
}
if (preg_match('#^/admin/(config|config\.example)\.php$#', $uri) || preg_match('#\.log$#', $uri)) {
    debekatu('fitxategi babestua (produkzioan ere blokeatuta)');
}
if ($uri === '/dev/router.php' || strpos($uri, '/dev/') === 0) {
    debekatu('garapen-fitxategia');
}

// ─── 2 · Idazketa-endpoint publikoak: BLOKEATUTA ────────────────────────────
// Hauek log-a IDAZTEN dute eta EMAIL ERREALA bidaltzen dute produkzioan.
// Lokaletik joz gero, benetako aurre-porra / zuzenketa bat sortuko litzateke.
if ($uri === '/api/porra.php') {
    debekatu('aurre-porrak blokeatuta daude proban (emaila + log erreala idatziko luke)');
}
if ($uri === '/api/proposal.php') {
    debekatu('zuzenketak blokeatuta daude proban (emaila + log erreala idatziko luke)');
}

// ─── 3 · Irakurketa-APIak: zuzenera birbidali (datu errealak) ───────────────
if ($uri === '/api/q.php') {
    if ($meth !== 'POST') debekatu('q.php POST bakarrik onartzen du');
    // Zerbitzariak SELECT/WITH soilik onartzen du → idazketa ezinezkoa.
    proxy('/api/q.php', 'POST', sarrera_gorputza(), [
        'Content-Type' => 'application/json',
        'Accept'       => 'application/json',
    ]);
}

if ($uri === '/api/ezarpenak.php') {
    // Lokalean `admin/ezarpenak.json` ez dago → proxy gabe lehenetsiak itzuliko lituzke
    // eta lokala ez litzateke produkzioaren berdina (karpeta-mapa, tresnen ikusgaitasuna).
    proxy('/api/ezarpenak.php', 'GET', null, ['Accept' => 'application/json']);
}

// ─── 4 · Admin APIa: IRAKURKETA soilik, zuzenera birbidalita ────────────────
// `php -S`-k ez du mod_rewrite: admin/.htaccess-eko `^api/(.*)$ → api.php?_path=$1`
// biraketa eskuz egin behar da, eta URL bera zuzenean deitu.
if (preg_match('#^/admin/api/(.*)$#', $uri, $m)) {
    if ($meth !== 'GET') {
        debekatu('idazketak blokeatuta daude (irakurketa hutsa). '
               . 'Aldaketa hau zerbitzarian probatu behar da.');
    }
    $cfg = @include $root . '/admin/config.php';
    if (!is_array($cfg) || !isset($cfg['auth_user'], $cfg['auth_pass'])) {
        json_erantzun(['error' => 'PROBA: admin/config.php falta da edo ez du auth_user/auth_pass.'], 500);
    }
    $galdera = $_SERVER['QUERY_STRING'] ?? '';
    $helburu = '/admin/api/' . $m[1] . ($galdera !== '' ? '?' . $galdera : '');
    proxy($helburu, 'GET', null, [
        // Kredentzialak EZ dira inoiz logeatzen ez diskoan idazten.
        'Authorization' => 'Basic ' . base64_encode($cfg['auth_user'] . ':' . $cfg['auth_pass']),
        'Accept'        => 'application/json',
    ]);
}

// ─── 5 · Admin panela: LOKALEAN exekutatu (zure index.html ikusteko) ────────
if ($uri === '/admin' || $uri === '/admin/' || $uri === '/admin/index.php') {
    // `index.php`-k Basic Auth egiten du eta gero `index.html` barneratzen du.
    // Irteera atzeman banner-a txertatzeko.
    ob_start();
    include $root . '/admin/index.php';
    $html = ob_get_clean();
    header('Cache-Control: no-store');
    if (stripos($html, '</body>') !== false) $html = bannerra_txertatu($html);
    echo $html;
    exit;
}

// ─── 6 · Gainerakoa: fitxategi lokala ───────────────────────────────────────
$abs = realpath($root . $uri);

// Bide-zeharkatzea saihestu (errepotik kanpo ezer ez).
if ($abs !== false && strpos($abs, $root) !== 0) {
    debekatu('bide baliogabea');
}

// Karpeta bat bada, index.html bilatu (php -S-ren DirectoryIndex ordez).
if ($abs !== false && is_dir($abs)) {
    foreach (['index.html', 'index.php'] as $aukera) {
        if (is_file($abs . '/' . $aukera)) { $abs = $abs . '/' . $aukera; break; }
    }
}

if ($abs !== false && is_file($abs)) {
    $luz = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    // HTML/JS/CSS: guk zerbitzatu (cache gabe + banner-a). Gainerakoa (irudiak,
    // PDFak…): `false` itzuli → zerbitzari integratuak MIME-mota zuzenarekin.
    if (in_array($luz, ['html', 'js', 'css'], true)) fitxategia_zerbitzatu($abs);
    return false;
}

return false;   // 404 zerbitzari integratuari
