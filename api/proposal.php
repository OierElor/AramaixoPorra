<?php
/**
 * Aramaixo Porra — zuzenketa-proposamenak (publikoa, DB gabe).
 * Porralariek testu lauzko zuzenketa-proposamenak bidaltzen dituzte.
 * Emaila bidaltzen da eta admin/zuzenketak.log fitxategian eransten da.
 *
 * SEGURTASUNA: ez du SQL-ik erabiltzen; sarrera balidatua eta mugatua.
 */

header('Content-Type: application/json; charset=utf-8');

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST bakarrik', 405);

$LOG_FILE = __DIR__ . '/../admin/zuzenketak.log';
$EMAIL_TO = 'harremana@aramaixoporra.eus';
$MOTAK = ['apustu okerra', 'izen aldaketa', 'porra-lotura', 'fusioa', 'bestelakoa'];
$SEP = "\n=====\n";

$req = json_decode(file_get_contents('php://input'), true);
if (!is_array($req)) fail('JSON baliogabea');

// Honeypot: botek eremu ezkutua betetzen dute → isilik onartu (ez gorde).
if (!empty($req['hp'])) { echo json_encode(['ok' => true]); exit; }

$mota = trim((string)($req['mota'] ?? 'bestelakoa'));
if (!in_array($mota, $MOTAK, true)) $mota = 'bestelakoa';

$testua = trim((string)($req['testua'] ?? ''));
if ($testua === '') fail('Testua behar da');
$testua = mb_substr($testua, 0, 1000, 'UTF-8');
// Kontrol-karaktereak kendu (lerro-jauziak izan ezik)
$testua = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $testua);

$ip = $_SERVER['REMOTE_ADDR'] ?? '?';

// Anti-spam arina: IP beretik azken orduan > 10 sarrera → 429.
if (is_file($LOG_FILE)) {
    $content = file_get_contents($LOG_FILE);
    if ($content !== false) {
        $cutoff = time() - 3600;
        $recent = 0;
        foreach (explode($SEP, $content) as $block) {
            if (preg_match('/^\[(.*?)\].*IP:\s*' . preg_quote($ip, '/') . '\b/m', $block, $m)) {
                $ts = strtotime($m[1]);
                if ($ts !== false && $ts >= $cutoff) $recent++;
            }
        }
        if ($recent >= 10) fail('Gehiegizko eskaerak. Saiatu geroago.', 429);
    }
}

// Fitxategian erantsi (testu laua).
$entry = '[' . date('Y-m-d H:i:s') . '] MOTA: ' . $mota . ' | IP: ' . $ip . "\n" . $testua . $SEP;
$ok = @file_put_contents($LOG_FILE, $entry, FILE_APPEND | LOCK_EX);

// Emaila bidali (huts eginda ere, ez du eskaera hondatzen).
$gorputza = "Zuzenketa-proposamen berria\n\nMota: $mota\nData: " . date('Y-m-d H:i:s') . "\nIP: $ip\n\n$testua\n";
$headers = "From: Aramaixo Porra <no-reply@aramaixoporra.eus>\r\nContent-Type: text/plain; charset=utf-8\r\n";
@mail($EMAIL_TO, 'Zuzenketa berria: ' . $mota, $gorputza, $headers);

if ($ok === false) fail('Ezin izan da gorde', 500);
echo json_encode(['ok' => true]);
