<?php
/**
 * Aramaixo Porra — ezarpen publikoak (irakurketa soilik, auth gabe).
 *
 * Bi ezarpen mota itzultzen ditu:
 *  1) `karpetak` — fitxategi-moten karpeta-mapa: webgune publikoak (js/txapelketak.js)
 *     hemendik jakiten du PDFak eta profil-irudiak ZEIN karpetatan dauden.
 *  2) `tresnak` — zein tresna publiko dagoen ikusgai (admin panela → Webgunea). Katalogo
 *     osoa `api/tresna-katalogoa.php`-tik dator; hemen `ikusgai` bakarrik ebazten da.
 *     ⚠️ `oharra` (adminaren nota pribatua, zergatik itzali zuen) EZ da INOIZ itzultzen
 *     hemendik: endpoint hau auth GABEA da, edonork irakur dezake.
 *
 * Adminak zerbait aldatzen badu (Fitxategiak → Karpetak, edo Webgunea → tresnak), gunea
 * berehala moldatzen da.
 *
 * Iturria: admin/ezarpenak.json (zerbitzari-jabetzakoa, git-etik kanpo).
 * Fitxategirik ez badago edo hondatuta badago, LEHENETSIAK itzultzen dira (karpetak
 * lehenetsiak + tresna GUZTIAK ikusgai) → gunea ez da inoiz hausten.
 *
 * SEGURTASUNA: irakurketa hutsa, DB gabe. Karpeta-izenak balidatuta itzultzen dira
 * (bide-zeharkatzea saihesteko, bezeroak URL bat eraikitzen baitu horiekin).
 */

require_once __DIR__ . '/tresna-katalogoa.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

/** Karpeta lehenetsiak (kodean; ezarpen-fitxategirik ez badago hauek balio dute). */
const EZARPEN_LEHENETSIAK = [
    'arauak'    => 'arauak',
    'dortsalak' => 'dortsalak',
    'porrak'    => 'porrak',
    'profilak'  => 'profilak',
];

/** Karpeta-izen bat onargarria den: letrak, zenbakiak, zuriuneak, '-', '_'. Bide-zatirik ez. */
function ezarpen_karpeta_ok($v) {
    return is_string($v) && preg_match('/^[\p{L}\p{N} _-]{1,80}$/u', $v) === 1;
}

$map = EZARPEN_LEHENETSIAK;
$tresnaEzarpenak = [];

$f = __DIR__ . '/../admin/ezarpenak.json';
if (is_file($f)) {
    $raw = @file_get_contents($f);
    $data = $raw === false ? null : json_decode($raw, true);
    if (is_array($data)) {
        $karpetak = $data['karpetak'] ?? [];
        foreach (EZARPEN_LEHENETSIAK as $mota => $lehenetsia) {
            if (isset($karpetak[$mota]) && ezarpen_karpeta_ok($karpetak[$mota])) {
                $map[$mota] = $karpetak[$mota];
            }
        }
        if (is_array($data['tresnak'] ?? null)) $tresnaEzarpenak = $data['tresnak'];
    }
}

// Katalogo osoa + ikusgaitasuna ebatzi. Ezarpenik ez badago tresna baterako, LEHENETSIA
// ikusgai da (fail-open): tresna berri bat gehitzeak ez du webgunea hausten.
$tresnak = array_map(function ($t) use ($tresnaEzarpenak) {
    $ez = $tresnaEzarpenak[$t['id']] ?? [];
    $ikusgai = !is_array($ez) || !array_key_exists('ikusgai', $ez) || $ez['ikusgai'] !== false;
    return [
        'id'       => $t['id'],
        'ikonoa'   => $t['ikonoa'],
        'izena'    => $t['izena'],
        'azalpena' => $t['azalpena'],
        'bidea'    => $t['bidea'],
        'ikusgai'  => $ikusgai,
    ];
}, TRESNA_KATALOGOA);

echo json_encode(['karpetak' => $map, 'tresnak' => $tresnak], JSON_UNESCAPED_UNICODE);
