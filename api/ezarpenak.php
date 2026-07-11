<?php
/**
 * Aramaixo Porra — ezarpen publikoak (irakurketa soilik, auth gabe).
 *
 * Fitxategi-moten karpeta-mapa itzultzen du: webgune publikoak (js/txapelketak.js) hemendik
 * jakiten du PDFak eta profil-irudiak ZEIN karpetatan dauden. Adminak karpeta bat aldatzen
 * badu (admin panela → Fitxategiak → Karpetak), gunea berehala moldatzen da.
 *
 * Iturria: admin/ezarpenak.json (zerbitzari-jabetzakoa, git-etik kanpo).
 * Fitxategirik ez badago edo hondatuta badago, LEHENETSIAK itzultzen dira → gunea ez da
 * inoiz hausten.
 *
 * SEGURTASUNA: irakurketa hutsa, DB gabe. Karpeta-izenak balidatuta itzultzen dira
 * (bide-zeharkatzea saihesteko, bezeroak URL bat eraikitzen baitu horiekin).
 */

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
    }
}

echo json_encode(['karpetak' => $map], JSON_UNESCAPED_UNICODE);
