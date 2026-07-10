<?php
/**
 * Aramaixo Porra — aurre-porrak jaso (publikoa).
 *
 * Porralariek beren porra AURRETIK bidal dezakete, baina:
 *  - Adminak ireki dituen txapelketetan BAKARRIK (Txapelketak.Porra_Irekita = 1).
 *  - Porra EZ da datu-basean gordetzen: emaila + admin/aurre-porrak.log.
 *  - Adminak berrikusi eta gero sartzen ditu apustuak (admin panela → Aurre-porrak).
 *
 * SEGURTASUNA: datu-basea IRAKURTZEKO soilik erabiltzen da (txapelketa irekita dagoen
 * eta dortsalak startlist-ean dauden egiaztatzeko). Ez da idazketarik egiten.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db-read.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST bakarrik', 405);

$LOG_FILE = __DIR__ . '/../admin/aurre-porrak.log';
$EMAIL_TO = 'harremana@aramaixoporra.eus';
$SEP = "\n=====\n";
$ABISUA = 'Gogoratu: aurre-porra hau ez da behin betikoa. Anboto tabernara joan behar duzu porra ofizialki uztera.';

/**
 * Eremu bat lerro BAKARRERA murriztu: kontrol-karaktereak eta lerro-jauziak kendu.
 * Ezinbestekoa da: bestela ezizen batek log-eko goiburua, "DORTSALAK:" lerroa edo
 * "\n=====\n" bereizlea faltsutu litzake.
 */
function lerro_bakarra($s, $max) {
    $s = (string) $s;
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);   // lerro-jauziak barne
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return mb_substr($s, 0, $max, 'UTF-8');
}

$req = json_decode(file_get_contents('php://input'), true);
if (!is_array($req)) fail('JSON baliogabea');

// Honeypot: botek eremu ezkutua betetzen dute → isilik onartu (ez gorde).
if (!empty($req['hp'])) { echo json_encode(['ok' => true, 'abisua' => $ABISUA]); exit; }

// ───── Eremuak ──────────────────────────────────────────────────────────────
$txap_id = filter_var($req['txapelketa_id'] ?? null, FILTER_VALIDATE_INT);
if ($txap_id === false || $txap_id <= 0) fail('Txapelketa baliogabea');

// Goiburuko eremua da eta '|' bereizlea darabil: ordezkatu, zutabeak ez nahasteko.
$ezizena = str_replace('|', '/', lerro_bakarra($req['ezizena'] ?? '', 60));
if ($ezizena === '') fail('Ezizena behar da');

$harremana = lerro_bakarra($req['harremana'] ?? '', 120);
$oharrak   = lerro_bakarra($req['oharrak'] ?? '', 500);

if (!isset($req['dortsalak']) || !is_array($req['dortsalak'])) fail('Dortsalak behar dira');
$dortsalak = [];
foreach ($req['dortsalak'] as $d) {
    $di = filter_var($d, FILTER_VALIDATE_INT);
    if ($di === false) fail('Dortsal baliogabea: ' . lerro_bakarra($d, 20));
    $dortsalak[] = $di;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '?';

// ───── Anti-spam arina: IP beretik azken orduan > 10 sarrera → 429 ──────────
if (is_file($LOG_FILE)) {
    $content = file_get_contents($LOG_FILE);
    if ($content !== false) {
        $cutoff = time() - 3600;
        $recent = 0;
        foreach (explode($SEP, $content) as $block) {
            if (preg_match('/^\[(.*?)\].*IP:\s*' . preg_quote($ip, '/') . '\s*$/m', $block, $m)) {
                $ts = strtotime($m[1]);
                if ($ts !== false && $ts >= $cutoff) $recent++;
            }
        }
        if ($recent >= 10) fail('Gehiegizko eskaerak. Saiatu geroago.', 429);
    }
}

// ───── Datu-basea: txapelketa irekita dago? Zenbat apustu? ──────────────────
try {
    $rows = api_select(
        'SELECT Izena, Porra_Irekita, Apustu_Kopurua FROM "Txapelketak" WHERE Txapelketa_ID = ?',
        [$txap_id]);
} catch (Throwable $e) {
    fail('DB errorea: ' . $e->getMessage(), 500);
}

// Existitzen ez dena eta itxita dagoena berdin tratatu (ez da informaziorik iragazten).
if (!$rows || (int) $rows[0]['Porra_Irekita'] !== 1) {
    fail('Txapelketa honetan ez da aurre-porrarik onartzen.', 403);
}
$txap_izena = (string) $rows[0]['Izena'];
$kop = (int) ($rows[0]['Apustu_Kopurua'] ?? 0);
if ($kop <= 0) $kop = 15;

if (count($dortsalak) !== $kop) {
    fail('Zehazki ' . $kop . ' txirrindulari behar dira (' . count($dortsalak) . ' bidali dituzu).');
}
if (count(array_unique($dortsalak)) !== count($dortsalak)) {
    fail('Txirrindulari bat baino gehiagotan errepikatuta dago.');
}

// ───── Dortsal guztiak txapelketaren startlist-ean egon behar dira ──────────
try {
    $startlist = api_select(
        'SELECT h.Dortsala AS dortsala, t.Izena AS izena
         FROM "TxirrindulariakTxapleketanParteHartzea" h
         JOIN "Txirrindulariak" t ON t.Txirrindularia_ID = h.TxirrindulariaID
         WHERE h.TxapelketaID = ?',
        [$txap_id]);
} catch (Throwable $e) {
    fail('DB errorea: ' . $e->getMessage(), 500);
}

$izenak = [];
foreach ($startlist as $r) $izenak[(int) $r['dortsala']] = (string) $r['izena'];

$ezezagunak = array_values(array_filter($dortsalak, fn($d) => !isset($izenak[$d])));
if ($ezezagunak) {
    fail('Dortsal hauek ez daude txapelketan: ' . implode(', ', $ezezagunak));
}

// ───── Log-ean erantsi (testu laua, makinak parseatzeko modukoa) ────────────
$goiburua = '[' . date('Y-m-d H:i:s') . '] TXAP: ' . $txap_id . ' | ' . $txap_izena
          . ' | EZIZENA: ' . $ezizena . ' | IP: ' . $ip;

$lerroak = [$goiburua];
if ($harremana !== '') $lerroak[] = 'HARREMANA: ' . $harremana;
$lerroak[] = 'DORTSALAK: ' . implode(',', $dortsalak);
foreach ($dortsalak as $d) {
    $lerroak[] = sprintf('  %4d  %s', $d, $izenak[$d]);
}
if ($oharrak !== '') $lerroak[] = 'OHARRAK: ' . $oharrak;

$entry = implode("\n", $lerroak) . $SEP;
$ok = @file_put_contents($LOG_FILE, $entry, FILE_APPEND | LOCK_EX);

// ───── Emaila (huts eginda ere, ez du eskaera hondatzen) ────────────────────
$gorputza = "Aurre-porra berria\n\n" . implode("\n", $lerroak) . "\n\n" . $ABISUA . "\n";
$headers = "From: Aramaixo Porra <no-reply@aramaixoporra.eus>\r\nContent-Type: text/plain; charset=utf-8\r\n";
@mail($EMAIL_TO, 'Aurre-porra: ' . $ezizena . ' (' . $txap_izena . ')', $gorputza, $headers);

if ($ok === false) fail('Ezin izan da gorde', 500);
echo json_encode(['ok' => true, 'abisua' => $ABISUA]);
