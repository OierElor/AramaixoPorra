<?php
// ─── Aramaixo Porra — logika (DB-rekiko independentea probatzeko) ──────────
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../api/tresna-katalogoa.php';

// ─── CSV profilak eta aliasak ────────────────────────────────────────────────
$CSV_PROFILES = [
    'porralariak' => ['label'=>'Porralariak','target'=>'Porralariak','fields'=>['Izena'],'required'=>['Izena'],'identity'=>['Porralaria_ID']],
    'txirrindulariak' => ['label'=>'Txirrindulariak','target'=>'Txirrindulariak','fields'=>['Izena'],'required'=>['Izena'],'identity'=>['Txirrindularia_ID']],
    'txapelketak' => ['label'=>'Txapelketak','target'=>'Txapelketak','fields'=>['Izena','Urtea'],'required'=>['Izena','Urtea'],'identity'=>['Txapelketa_ID']],
    'karrerak' => ['label'=>'Karrerak','target'=>'Karrerak','fields'=>['Izena','Urtea'],'context_fields'=>['Txapelketa_ID'],'required'=>['Txapelketa_ID','Izena','Urtea'],'identity'=>['Karrerak_ID']],
    'txirrindulari_emaitzak' => ['label'=>'Txirrindulari emaitzak (txapelketa)','target'=>'TxapelketaEmaitzaTxirrindulariak','fields'=>['Posizioa','Txirrindularia','Puntuak','Puntuak_Sailkapen_Nag','Puntuak_Mendian','Zenbatek'],'context_fields'=>['Txapelketa_ID'],'required'=>['Txapelketa_ID','Posizioa','Txirrindularia'],'identity'=>['Txapelketa_ID','Txirrindularia_ID']],
    'porralari_emaitzak' => ['label'=>'Porralari emaitzak (txapelketa)','target'=>'TxapelketaEmaitzaPorralariak','fields'=>['Posizioa','Ezizena','Puntuak','Puntuak_Mendikoa','Puntuak_Generala'],'context_fields'=>['Txapelketa_ID'],'required'=>['Txapelketa_ID','Posizioa','Ezizena','Puntuak'],'identity'=>['Txapelketa_ID','Ezizen_ID']],
    'karrera_txirrindulari_emaitzak' => ['label'=>'Txirrindulari emaitzak (karrera)','target'=>'KarreraSailkapena','fields'=>['Sailkapena','Txirrindularia','Puntuak'],'context_fields'=>['Karrera_ID'],'required'=>['Karrera_ID','Txirrindularia','Puntuak','Sailkapena'],'identity'=>['Karrera_ID','Txirrindularia_ID']],
];

$FIELD_ALIASES = [
    'Txapelketa_ID'=>['Txapelketa_ID','Txapelketa','Competition','Competition_ID'],
    'Karrera_ID'=>['Karrera_ID','Karrerak_ID','Karrera','Lasterketa','Race','Race_ID'],
    'Karrerak_ID'=>['Karrerak_ID','Karrera_ID'],
    'Ezizena'=>['Ezizena','Porreroa','Porrero','Porralaria','Porralari','Nickname','Taldea','Porra'],
    'Txirrindularia'=>['Txirrindularia','Txirrindulari','Izena','Rider','Cyclist','Name','Nombre'],
    'Porralaria'=>['Porralaria','Porreroa','Porrero','Ezizena','Porralari'],
    'Sailkapena'=>['Sailkapena','Posizioa','Sailkapen','Postua','Aukeratze Sailkapena','Rank','Pos','Position','#'],
    'Posizioa'=>['Posizioa','Sailkapena','Sailkapen','Postua','Aukeratze Sailkapena','Rank','Pos','Position','#'],
    'Puntuak'=>['Puntuak','Guztira','Puntu','Points','Pts','Ptos'],
    'Urtea'=>['Urtea','Year','Año'],
    'Izena'=>['Izena','Name','Title','Nombre','Helmuga'],
    'Dortsala'=>['Dortsala','Dorsala','Dorsalak','Zbkia','Zenbakia','Bib','Dorsal','Dors'],
    'Puntuak_Sailkapen_Nag'=>['Puntuak_Sailkapen_Nag','Puntuak_SailkapenNag','Sailkapen_Nagusia','SailkapenNagusia','Sailkapen Nagusia','Nagusia','Orokorra','orokorra','GC','General'],
    'Puntuak_Mendian'=>['Puntuak_Mendian','Mendian','Mendia','Mendi','Mountain','KOM'],
    'Zenbatek'=>['Zenbatek','Zenbatek?','Zenbatek Daukate?','Zenbat','Count','Kopurua','Aukeratu'],
    'Puntuak_Mendikoa'=>['Puntuak_Mendikoa','Mendikoa','Mendian','Mendia','Mendi','Mountain','KOM'],
    'Puntuak_Generala'=>['Puntuak_Generala','Generala','Orokorra','orokorra','General','GC','Sailkapen Nagusia','Nagusia'],
];

// ─── Izen-normalizazioa eta fuzzy matching ───────────────────────────────────
function strip_country($name) {
    $n = preg_replace('/\s*\(.*?\)\s*$/u', '', trim((string)$name));
    return trim($n);
}

function normalize_name($name) {
    $n = preg_replace('/\(.*?\)/u', '', (string)$name);
    $n = trim($n);
    $n = mb_strtolower($n, 'UTF-8');
    if (class_exists('Normalizer')) {
        $n = Normalizer::normalize($n, Normalizer::FORM_D);
        $n = preg_replace('/\p{Mn}/u', '', $n);
    } else {
        $n = strtr($n, [
            'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a',
            'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
            'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
            'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
            'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
            'ñ'=>'n','ç'=>'c','ž'=>'z','š'=>'s','č'=>'c','ć'=>'c','đ'=>'d',
            'ø'=>'o','ł'=>'l','ě'=>'e','ř'=>'r','ů'=>'u','ý'=>'y','ß'=>'ss',
        ]);
    }
    $n = preg_replace('/\s+/u', ' ', $n);
    return trim($n);
}

function name_tokens($name) {
    $t = explode(' ', normalize_name($name));
    return array_values(array_unique(array_filter($t, fn($x) => $x !== '')));
}

function fuzzy_name_score($a, $b) {
    $na = normalize_name($a); $nb = normalize_name($b);
    if ($na === $nb) return 100;
    $ta = name_tokens($a); $tb = name_tokens($b);
    sort($ta); sort($tb);
    if ($ta === $tb && !empty($ta)) return 98;
    $sa = array_values(array_unique(name_tokens($a)));
    $sb = array_values(array_unique(name_tokens($b)));
    $inter = array_intersect($sa, $sb);
    $union = array_unique(array_merge($sa, $sb));
    if (empty($union)) return 0;
    $jaccard = count($inter) / count($union);
    if (count($inter) >= min(count($sa), count($sb)) && min(count($sa),count($sb)) > 0) {
        return max(85, (int)($jaccard * 100));
    }
    $bigrams = function($s) {
        $r = [];
        $len = mb_strlen($s);
        for ($i = 0; $i < $len - 1; $i++) $r[mb_substr($s, $i, 2)] = true;
        return array_keys($r);
    };
    $ba = $bigrams($na); $bb = $bigrams($nb);
    $denom = count($ba) + count($bb);
    if ($denom === 0) return 0;
    $common = count(array_intersect($ba, $bb));
    $bigram_score = (int)(2 * $common / $denom * 100);
    return max((int)($jaccard * 70), $bigram_score);
}

function find_fuzzy_matches($name, $threshold = 60) {
    $all = db_rows('SELECT Txirrindularia_ID, Izena FROM `Txirrindulariak`');
    $matches = [];
    foreach ($all as $row) {
        $score = fuzzy_name_score($name, $row['Izena']);
        if ($score >= $threshold) {
            $matches[] = ['Txirrindularia_ID'=>(int)$row['Txirrindularia_ID'], 'Izena'=>$row['Izena'], 'score'=>$score];
        }
    }
    usort($matches, fn($x, $y) => $y['score'] - $x['score']);
    return array_slice($matches, 0, 5);
}

function find_txirrindularia_id($name) {
    $row = db_one('SELECT Txirrindularia_ID FROM `Txirrindulariak` WHERE Izena = ?', [$name]);
    if ($row) return (int)$row['Txirrindularia_ID'];
    $stripped = strip_country($name);
    if ($stripped !== $name) {
        $row = db_one('SELECT Txirrindularia_ID FROM `Txirrindulariak` WHERE Izena = ?', [$stripped]);
        if ($row) return (int)$row['Txirrindularia_ID'];
    }
    $norm = normalize_name($name);
    $all = db_rows('SELECT Txirrindularia_ID, Izena FROM `Txirrindulariak`');
    foreach ($all as $r) if (normalize_name($r['Izena']) === $norm) return (int)$r['Txirrindularia_ID'];
    $nt = name_tokens($name); sort($nt);
    foreach ($all as $r) {
        $rt = name_tokens($r['Izena']); sort($rt);
        if ($rt === $nt && !empty($nt)) return (int)$r['Txirrindularia_ID'];
    }
    return null;
}

function ensure_txirrindularia_id($name) {
    $tid = find_txirrindularia_id($name);
    if ($tid !== null) return $tid;
    $res = db_exec('INSERT INTO `Txirrindulariak` (Izena) VALUES (?)', [$name]);
    return (int)$res['insert_id'];
}

function find_ezizen_id($txap_id, $ezizena) {
    $row = db_one('SELECT Ezizen_ID FROM `PorraEzizenak` WHERE Txapelketa_ID = ? AND Ezizena = ?', [$txap_id, $ezizena]);
    return $row ? (int)$row['Ezizen_ID'] : null;
}

function ensure_ezizen_id($txap_id, $ezizena) {
    $eid = find_ezizen_id($txap_id, $ezizena);
    if ($eid !== null) return $eid;
    $res = db_exec('INSERT INTO `PorraEzizenak` (Txapelketa_ID, Ezizena) VALUES (?, ?)', [$txap_id, $ezizena]);
    return (int)$res['insert_id'];
}

function find_porralaria_id($name) {
    $row = db_one('SELECT Porralaria_ID FROM `Porralariak` WHERE Izena = ?', [$name]);
    if ($row) return (int)$row['Porralaria_ID'];
    $norm = normalize_name($name);
    if ($norm === '') return null;
    $all = db_rows('SELECT Porralaria_ID, Izena FROM `Porralariak`');
    foreach ($all as $r) if (normalize_name($r['Izena']) === $norm) return (int)$r['Porralaria_ID'];
    return null;
}

function ensure_porralaria_id($name) {
    $id = find_porralaria_id($name);
    if ($id !== null) return $id;
    $res = db_exec('INSERT INTO `Porralariak` (Izena) VALUES (?)', [$name]);
    return (int)$res['insert_id'];
}

// Dortsala ezarri/eguneratu (TxirrindulariakTxapleketanParteHartzea).
function upsert_dortsala($txap_id, $txirri_id, $dortsala) {
    db_exec('INSERT INTO `TxirrindulariakTxapleketanParteHartzea` (TxapelketaID, TxirrindulariaID, Dortsala) '
        . 'VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE Dortsala = VALUES(Dortsala)',
        [(int)$txap_id, (int)$txirri_id, (int)$dortsala]);
}

// ─── CSV laguntzaileak ───────────────────────────────────────────────────────
function to_int($v) {
    if ($v === null) return null;
    if (is_int($v)) return $v;
    $t = trim((string)$v);
    if ($t === '') return null;
    if (!preg_match('/^-?\d+$/', $t)) {
        if (!is_numeric($t)) return null;
        return (int)$t;
    }
    return (int)$t;
}

function first_match($raw, $names) {
    foreach ($names as $wanted) {
        foreach ($raw as $key => $value) {
            if (mb_strtolower(trim((string)$key)) === mb_strtolower(trim($wanted))) {
                return ($value !== '') ? $value : null;
            }
        }
    }
    foreach ($names as $wanted) {
        $w = mb_strtolower(trim($wanted));
        if (mb_strlen($w) < 3) continue;
        foreach ($raw as $key => $value) {
            $k = mb_strtolower(trim((string)$key));
            if (mb_strpos($k, $w) !== false) return ($value !== '') ? $value : null;
        }
    }
    return null;
}

function resolve_raw_value($raw, $mapping, $logical_key) {
    global $FIELD_ALIASES;
    if (array_key_exists($logical_key, $mapping)) {
        $csv_col = $mapping[$logical_key];
        if (!$csv_col) return null;
        foreach ($raw as $key => $value) {
            if (mb_strtolower(trim((string)$key)) === mb_strtolower(trim((string)$csv_col))) return $value;
        }
        return null;
    }
    $aliases = $FIELD_ALIASES[$logical_key] ?? [$logical_key];
    $value = first_match($raw, $aliases);
    if ($value !== null) return $value;
    return first_match($raw, [$logical_key]);
}

function normalize_row($profile_id, $mapping, $raw, $context, $create_missing) {
    global $CSV_PROFILES;
    $spec = $CSV_PROFILES[$profile_id] ?? null;
    if (!$spec) return null;
    $context = $context ?: [];
    $context_fields = $spec['context_fields'] ?? [];

    $get = function($logical_key) use ($context_fields, $context, $mapping, $raw) {
        if (in_array($logical_key, $context_fields)) {
            $value = $context[$logical_key] ?? null;
        } else {
            $value = resolve_raw_value($raw, $mapping, $logical_key);
        }
        if ($value === null && array_key_exists($logical_key, $context)) $value = $context[$logical_key];
        if (is_string($value)) $value = trim($value);
        return $value;
    };

    if ($profile_id === 'porralariak' || $profile_id === 'txirrindulariak') {
        $izena = $get('Izena');
        if (!$izena) return null;
        return ['Izena' => $izena];
    }
    if ($profile_id === 'txapelketak') {
        $izena = $get('Izena'); $urtea = to_int($get('Urtea'));
        if (!$izena || $urtea === null) return null;
        return ['Izena'=>$izena,'Urtea'=>$urtea];
    }
    if ($profile_id === 'karrerak') {
        $txap = to_int($get('Txapelketa_ID')); $izena = $get('Izena'); $urtea = to_int($get('Urtea'));
        if ($txap === null || !$izena || $urtea === null) return null;
        return ['Txapelketa_ID'=>$txap,'Izena'=>$izena,'Urtea'=>$urtea];
    }
    if ($profile_id === 'txirrindulari_emaitzak') {
        $txap = to_int($get('Txapelketa_ID')); $pos = to_int($get('Posizioa'));
        $name = $get('Txirrindularia'); $pts = to_int($get('Puntuak'));
        if ($txap === null || $pos === null || !$name) return null;
        if ($pts === null) $pts = 0;
        $norm = ['Txapelketa_ID'=>$txap,'Posizioa'=>$pos,'Txirrindularia'=>$name,'Puntuak'=>$pts];
        $dor = $get('Dortsala');
        if ($dor !== null && $dor !== '') $norm['Dortsala'] = to_int($dor);
        $pn = to_int($get('Puntuak_Sailkapen_Nag')); if ($pn !== null) $norm['Puntuak_Sailkapen_Nag'] = $pn;
        $pm = to_int($get('Puntuak_Mendian')); if ($pm !== null) $norm['Puntuak_Mendian'] = $pm;
        $zb = to_int($get('Zenbatek')); if ($zb !== null) $norm['Zenbatek'] = $zb;
        if ($create_missing !== null) {
            $rid = find_txirrindularia_id($name);
            if ($rid !== null) $norm['Txirrindularia_ID'] = $rid;
            elseif ($create_missing) $norm['Txirrindularia_ID'] = ensure_txirrindularia_id($name);
        }
        return $norm;
    }
    if ($profile_id === 'porralari_emaitzak') {
        $txap = to_int($get('Txapelketa_ID')); $pos = to_int($get('Posizioa'));
        $ez = $get('Ezizena'); $pts = to_int($get('Puntuak'));
        if ($txap === null || $pos === null || !$ez || $pts === null) return null;
        $norm = ['Txapelketa_ID'=>$txap,'Posizioa'=>$pos,'Ezizena'=>$ez,'Puntuak'=>$pts];
        foreach (['Puntuak_Mendikoa','Puntuak_Generala'] as $f) {
            $v = to_int($get($f)); if ($v !== null) $norm[$f] = $v;
        }
        if ($create_missing !== null) {
            $eid = find_ezizen_id($txap, $ez);
            if ($eid !== null) $norm['Ezizen_ID'] = $eid;
            elseif ($create_missing) $norm['Ezizen_ID'] = ensure_ezizen_id($txap, $ez);
        }
        return $norm;
    }
    if ($profile_id === 'karrera_txirrindulari_emaitzak') {
        $kar = to_int($get('Karrera_ID')); $name = $get('Txirrindularia');
        $pts = to_int($get('Puntuak')); $sail = to_int($get('Sailkapena'));
        if ($kar === null || !$name || $pts === null || $sail === null) return null;
        $norm = ['Karrera_ID'=>$kar,'Txirrindularia'=>$name,'Puntuak'=>$pts,'Sailkapena'=>$sail];
        if ($create_missing !== null) {
            $rid = find_txirrindularia_id($name);
            if ($rid !== null) $norm['Txirrindularia_ID'] = $rid;
            elseif ($create_missing) $norm['Txirrindularia_ID'] = ensure_txirrindularia_id($name);
        }
        return $norm;
    }
    return null;
}

function row_exists($profile_id, $norm) {
    if ($profile_id === 'porralariak') {
        $r = db_one('SELECT Porralaria_ID FROM `Porralariak` WHERE Izena = ?', [$norm['Izena']]);
        return [(bool)$r, $r ? 'ID='.$r['Porralaria_ID'] : ''];
    }
    if ($profile_id === 'txirrindulariak') {
        $r = db_one('SELECT Txirrindularia_ID FROM `Txirrindulariak` WHERE Izena = ?', [$norm['Izena']]);
        return [(bool)$r, $r ? 'ID='.$r['Txirrindularia_ID'] : ''];
    }
    if ($profile_id === 'txapelketak') {
        $r = db_one('SELECT Txapelketa_ID FROM `Txapelketak` WHERE Izena = ? AND Urtea = ?', [$norm['Izena'],$norm['Urtea']]);
        return [(bool)$r, $r ? 'ID='.$r['Txapelketa_ID'] : ''];
    }
    if ($profile_id === 'karrerak') {
        $r = db_one('SELECT Karrerak_ID FROM `Karrerak` WHERE Izena = ? AND Urtea = ? AND Txapelketa_ID = ?', [$norm['Izena'],$norm['Urtea'],$norm['Txapelketa_ID']]);
        return [(bool)$r, $r ? 'ID='.$r['Karrerak_ID'] : ''];
    }
    if ($profile_id === 'txirrindulari_emaitzak') {
        $rid = $norm['Txirrindularia_ID'] ?? find_txirrindularia_id($norm['Txirrindularia']);
        if ($rid === null) return [false, ''];
        $r = db_one('SELECT 1 AS x FROM `TxapelketaEmaitzaTxirrindulariak` WHERE Txapelketa_ID = ? AND Txirrindularia_ID = ?', [$norm['Txapelketa_ID'],$rid]);
        return [(bool)$r, $r ? 'Txirrindularia_ID='.$rid : ''];
    }
    if ($profile_id === 'porralari_emaitzak') {
        $eid = $norm['Ezizen_ID'] ?? find_ezizen_id($norm['Txapelketa_ID'], $norm['Ezizena']);
        if ($eid === null) return [false, ''];
        $r = db_one('SELECT 1 AS x FROM `TxapelketaEmaitzaPorralariak` WHERE Txapelketa_ID = ? AND Ezizen_ID = ?', [$norm['Txapelketa_ID'],$eid]);
        return [(bool)$r, $r ? 'Ezizen_ID='.$eid : ''];
    }
    if ($profile_id === 'karrera_txirrindulari_emaitzak') {
        $rid = $norm['Txirrindularia_ID'] ?? find_txirrindularia_id($norm['Txirrindularia']);
        if ($rid === null) return [false, ''];
        $r = db_one('SELECT 1 AS x FROM `KarreraSailkapena` WHERE Karrera_ID = ? AND Txirrindularia_ID = ?', [$norm['Karrera_ID'],$rid]);
        return [(bool)$r, $r ? 'Txirrindularia_ID='.$rid : ''];
    }
    return [false, ''];
}

/**
 * Karrera batek BETI Kategoria eta Ordena izan behar ditu.
 *
 * Hutsik utziz gero, karrera hori EZKUTATUTA geratzen da urte-orriko akordeoian eta
 * tresna publikoetan (`Kategoria` iragazten baitute), nahiz eta emaitzak izan. Hemen
 * lehenetsiak ezartzen dira, edozein sortze-bidetatik etorrita ere:
 *   - Kategoria hutsik → 'Etapa'
 *   - Ordena hutsik    → txapelketako hurrengoa (MAX + 1)
 */
function normalize_karrera_row($vals) {
    $txap = (int)($vals['Txapelketa_ID'] ?? 0);

    $kat = trim((string)($vals['Kategoria'] ?? ''));
    $vals['Kategoria'] = $kat === '' ? 'Etapa' : $kat;

    $ordena = $vals['Ordena'] ?? null;
    if ($ordena === '' || $ordena === null) {
        $max = $txap > 0
            ? (int)(db_scalar('SELECT COALESCE(MAX(Ordena),0) FROM `Karrerak` WHERE Txapelketa_ID = ?', [$txap]) ?? 0)
            : 0;
        $vals['Ordena'] = $max + 1;
    } else {
        $vals['Ordena'] = (int)$ordena;
    }
    return $vals;
}

function insert_row($profile_id, $norm) {
    if ($profile_id === 'porralariak') {
        $r = db_exec('INSERT INTO `Porralariak` (Izena) VALUES (?)', [$norm['Izena']]);
        return ['Porralaria_ID'=>(int)$r['insert_id']];
    }
    if ($profile_id === 'txirrindulariak') {
        $r = db_exec('INSERT INTO `Txirrindulariak` (Izena) VALUES (?)', [$norm['Izena']]);
        return ['Txirrindularia_ID'=>(int)$r['insert_id']];
    }
    if ($profile_id === 'txapelketak') {
        $r = db_exec('INSERT INTO `Txapelketak` (Izena, Urtea) VALUES (?, ?)', [$norm['Izena'],$norm['Urtea']]);
        return ['Txapelketa_ID'=>(int)$r['insert_id']];
    }
    if ($profile_id === 'karrerak') {
        $v = normalize_karrera_row($norm);
        $r = db_exec('INSERT INTO `Karrerak` (Txapelketa_ID, Izena, Urtea, Kategoria, Ordena) VALUES (?, ?, ?, ?, ?)',
            [$v['Txapelketa_ID'], $v['Izena'], $v['Urtea'], $v['Kategoria'], $v['Ordena']]);
        return ['Karrerak_ID'=>(int)$r['insert_id']];
    }
    if ($profile_id === 'txirrindulari_emaitzak') {
        $rid = $norm['Txirrindularia_ID'] ?? ensure_txirrindularia_id($norm['Txirrindularia']);
        $cols = ['Txapelketa_ID','Txirrindularia_ID','Posizioa','Puntuak'];
        $vals = [$norm['Txapelketa_ID'],(int)$rid,$norm['Posizioa'],$norm['Puntuak']];
        $map = ['Zenbatek'=>'Zenbatek?'];
        foreach (['Puntuak_Sailkapen_Nag','Puntuak_Mendian','Zenbatek'] as $opt) {
            if (isset($norm[$opt]) && $norm[$opt] !== null) { $cols[] = $map[$opt] ?? $opt; $vals[] = $norm[$opt]; }
        }
        $col_sql = implode(', ', array_map(fn($c)=>'`'.$c.'`', $cols));
        $ph = implode(', ', array_fill(0, count($cols), '?'));
        db_exec("INSERT INTO `TxapelketaEmaitzaTxirrindulariak` ($col_sql) VALUES ($ph)", $vals);
        return ['Txapelketa_ID'=>$norm['Txapelketa_ID'],'Txirrindularia_ID'=>(int)$rid];
    }
    if ($profile_id === 'porralari_emaitzak') {
        $eid = $norm['Ezizen_ID'] ?? ensure_ezizen_id($norm['Txapelketa_ID'], $norm['Ezizena']);
        $cols = ['Txapelketa_ID','Ezizen_ID','Posizioa','Puntuak'];
        $vals = [$norm['Txapelketa_ID'],(int)$eid,$norm['Posizioa'],$norm['Puntuak']];
        foreach (['Puntuak_Mendikoa','Puntuak_Generala'] as $opt) {
            if (isset($norm[$opt]) && $norm[$opt] !== null) { $cols[] = $opt; $vals[] = $norm[$opt]; }
        }
        $col_sql = implode(', ', array_map(fn($c)=>'`'.$c.'`', $cols));
        $ph = implode(', ', array_fill(0, count($cols), '?'));
        db_exec("INSERT INTO `TxapelketaEmaitzaPorralariak` ($col_sql) VALUES ($ph)", $vals);
        return ['Txapelketa_ID'=>$norm['Txapelketa_ID'],'Ezizen_ID'=>(int)$eid];
    }
    if ($profile_id === 'karrera_txirrindulari_emaitzak') {
        $rid = $norm['Txirrindularia_ID'] ?? ensure_txirrindularia_id($norm['Txirrindularia']);
        db_exec('INSERT INTO `KarreraSailkapena` (Karrera_ID, Txirrindularia_ID, Puntuak, Sailkapena) VALUES (?, ?, ?, ?)',
            [$norm['Karrera_ID'],(int)$rid,$norm['Puntuak'],$norm['Sailkapena']]);
        return ['Karrera_ID'=>$norm['Karrera_ID'],'Txirrindularia_ID'=>(int)$rid];
    }
    throw new Exception("Taula ezezaguna: $profile_id");
}

// ─── CSV preview / import / fuzzy ────────────────────────────────────────────
function csv_preview($payload) {
    global $CSV_PROFILES;
    $profile = $payload['profile'] ?? ($payload['table'] ?? '');
    $mapping = $payload['mapping'] ?? [];
    $raw = $payload['rows'] ?? [];
    $context = $payload['context'] ?? [];
    if (!isset($CSV_PROFILES[$profile])) {
        return ['will_insert'=>[],'already_exists'=>[],'errors'=>[['row'=>(object)[],'reason'=>"CSV profila ezezaguna: $profile"]]];
    }
    $will=[]; $exist=[]; $errors=[];
    foreach ($raw as $rr) {
        $norm = normalize_row($profile, $mapping, $rr, $context, false);
        if ($norm === null) { $errors[] = ['row'=>$rr,'reason'=>'Eremu batzuk falta dira']; continue; }
        [$ex, $reason] = row_exists($profile, $norm);
        if ($ex) $exist[] = array_merge($norm, ['_exists_reason'=>$reason]);
        else $will[] = $norm;
    }
    return ['will_insert'=>$will,'already_exists'=>$exist,'errors'=>$errors];
}

function csv_import($payload) {
    global $CSV_PROFILES;
    $profile = $payload['profile'] ?? ($payload['table'] ?? '');
    $mapping = $payload['mapping'] ?? [];
    $raw = $payload['rows'] ?? [];
    $context = $payload['context'] ?? [];
    $label = $payload['label'] ?? "CSV → $profile";
    $merge_map = $payload['merge_map'] ?? [];
    $update_fields = $payload['update_fields'] ?? [];
    $spec = $CSV_PROFILES[$profile] ?? null;
    if (!$spec) return ['inserted'=>0,'skipped'=>0,'errors'=>[['row'=>(object)[],'reason'=>"CSV profila ezezaguna: $profile"]],'batch_id'=>count($_SESSION['undo_stack'] ?? [])];
    $inserted_identities=[]; $inserted_rows=[]; $skipped=0; $errors=[];
    foreach ($raw as $rr) {
        $norm = normalize_row($profile, $mapping, $rr, $context, false);
        if ($norm === null) { $errors[] = ['row'=>$rr,'reason'=>'Eremu batzuk falta dira']; continue; }
        if ($merge_map && isset($norm['Txirrindularia'])) {
            $cn = $norm['Txirrindularia'];
            if (array_key_exists($cn, $merge_map)) {
                $mid = $merge_map[$cn];
                if ($mid !== null) $norm['Txirrindularia_ID'] = (int)$mid;
            }
        }
        [$ex, ] = row_exists($profile, $norm);
        if ($ex) {
            if ($update_fields && $profile === 'txirrindulari_emaitzak') {
                $rid = $norm['Txirrindularia_ID'] ?? null;
                if ($rid) {
                    $map = ['Zenbatek'=>'Zenbatek?'];
                    $set_parts=[]; $set_vals=[];
                    foreach ($update_fields as $f) {
                        $col = $map[$f] ?? $f;
                        if (isset($norm[$f]) && $norm[$f] !== null) { $set_parts[]='`'.$col.'` = ?'; $set_vals[]=$norm[$f]; }
                    }
                    if ($set_parts) {
                        db_exec('UPDATE `TxapelketaEmaitzaTxirrindulariak` SET '.implode(', ',$set_parts).
                            ' WHERE Txapelketa_ID = ? AND Txirrindularia_ID = ?',
                            array_merge($set_vals, [$norm['Txapelketa_ID'], (int)$rid]));
                        $inserted_identities[] = ['Txapelketa_ID'=>$norm['Txapelketa_ID'],'Txirrindularia_ID'=>(int)$rid];
                        $inserted_rows[] = $norm;
                        continue;
                    }
                }
            }
            $skipped++; continue;
        }
        try {
            $identity = insert_row($profile, $norm);
            $inserted_identities[] = $identity;
            $inserted_rows[] = $norm;
        } catch (Exception $e) {
            $errors[] = ['row'=>$norm,'reason'=>$e->getMessage()];
        }
    }
    if ($inserted_identities) {
        if (!isset($_SESSION['undo_stack'])) $_SESSION['undo_stack'] = [];
        $_SESSION['undo_stack'][] = ['label'=>$label,'profile'=>$profile,'target'=>$spec['target'],'rows'=>$inserted_rows,'identities'=>$inserted_identities,'identity_fields'=>$spec['identity']];
        if (count($_SESSION['undo_stack']) > 20) array_shift($_SESSION['undo_stack']);
        $_SESSION['redo_stack'] = [];
    }
    return ['inserted'=>count($inserted_identities),'skipped'=>$skipped,'errors'=>$errors,'batch_id'=>count($_SESSION['undo_stack'] ?? [])];
}

function csv_fuzzy_check($payload) {
    $profile = $payload['profile'] ?? '';
    $mapping = $payload['mapping'] ?? [];
    $raw = $payload['rows'] ?? [];
    $context = $payload['context'] ?? [];
    if (!in_array($profile, ['txirrindulari_emaitzak','karrera_txirrindulari_emaitzak'])) return ['checks'=>[]];
    $results=[]; $seen=[];
    foreach ($raw as $rr) {
        $norm = normalize_row($profile, $mapping, $rr, $context, false);
        if ($norm === null) continue;
        $csv_name = $norm['Txirrindularia'] ?? '';
        if (!$csv_name || in_array($csv_name, $seen)) continue;
        $seen[] = $csv_name;
        $exact = find_txirrindularia_id($csv_name);
        if ($exact) {
            $mr = db_one('SELECT Izena FROM `Txirrindulariak` WHERE Txirrindularia_ID = ?', [$exact]);
            $results[] = ['csv_name'=>$csv_name,'matched_id'=>(int)$exact,'matched_name'=>$mr ? $mr['Izena'] : $csv_name,'suggestions'=>find_fuzzy_matches(strip_country($csv_name), 40)];
        } else {
            $results[] = ['csv_name'=>$csv_name,'matched_id'=>null,'matched_name'=>null,'suggestions'=>find_fuzzy_matches(strip_country($csv_name), 40)];
        }
    }
    return ['checks'=>$results];
}

// ─── Datuak inportatzeko sistema bateratua (Itzuliak + Klasikoak) ────────────
// Apustuak DORTSALEZ lotzen dira: aurretik startlist-a (dortsala→txirrindularia)
// inportatu behar da txapelketa horretan. Idempotentea (INSERT IGNORE / upsert).

function _imp_txap_id($payload) {
    $tid = $payload['txapelketa_id'] ?? null;
    if ($tid === null || $tid === '') throw new Exception('Txapelketa bat hautatu behar da');
    return (int)$tid;
}

// Dortsala → Txirrindularia_ID txapelketa honetan (startlist-etik).
function find_txirri_by_dortsala($txap_id, $dortsala) {
    $d = to_int($dortsala);
    if ($d === null) return null;
    $row = db_one('SELECT TxirrindulariaID FROM `TxirrindulariakTxapleketanParteHartzea` WHERE TxapelketaID = ? AND Dortsala = ?', [(int)$txap_id, $d]);
    return $row ? (int)$row['TxirrindulariaID'] : null;
}

// ── B · Startlist (dortsala + izena) ────────────────────────────────────────
// payload: { txapelketa_id, riders: [ {dortsala, izena}, ... ] }
function import_startlist_preview($payload) {
    $riders = $payload['riders'] ?? [];
    $new_riders = []; $seen = []; $count = 0;
    foreach ($riders as $r) {
        $rn = trim((string)($r['izena'] ?? ''));
        $dor = to_int($r['dortsala'] ?? null);
        if ($rn === '' || $dor === null) continue;
        $count++;
        if (in_array($rn, $seen, true)) continue;
        $seen[] = $rn;
        if (find_txirrindularia_id($rn) === null) {
            $new_riders[] = ['izena'=>$rn, 'suggestions'=>find_fuzzy_matches(strip_country($rn), 60)];
        }
    }
    return ['riders'=>$count, 'new_riders'=>$new_riders];
}

// Izen_Formatua sortu izenetik: tokenak ("Izena"/"Abizena") hitzeko.
//  'auto' → maiuskulazko hitzak = Abizena, besteak = Izena (Excel: "ABIZENA Izena")
//  'abizena_izena' → azken hitza Izena, gainerakoak Abizena
//  'izena_abizena' → lehen hitza Izena, gainerakoak Abizena
//  'none'/'' → NULL (formaturik ez)
function izen_formatua_sortu($name, $mode) {
    if ($mode === null || $mode === '' || $mode === 'none') return null;
    $parts = preg_split('/\s+/u', trim($name));
    $n = count($parts);
    if ($n < 1 || $parts[0] === '') return null;
    if ($n === 1) return 'Izena';
    if ($mode === 'izena_abizena') { $t = array_fill(0, $n, 'Abizena'); $t[0] = 'Izena'; return implode(' ', $t); }
    if ($mode === 'abizena_izena') { $t = array_fill(0, $n, 'Abizena'); $t[$n-1] = 'Izena'; return implode(' ', $t); }
    // auto
    $t = array_map(function($p){
        $isCaps = ($p === mb_strtoupper($p, 'UTF-8')) && preg_match('/\p{L}/u', $p);
        return $isCaps ? 'Abizena' : 'Izena';
    }, $parts);
    if (count(array_unique($t)) < 2) { $t = array_fill(0, $n, 'Abizena'); $t[$n-1] = 'Izena'; }
    return implode(' ', $t);
}

function import_startlist($payload) {
    $txap = _imp_txap_id($payload);
    $riders = $payload['riders'] ?? [];
    // merge_map: izena → Txirrindularia_ID (existitzen denari lotu) | 'skip' (baztertu) | null (lehenetsia)
    $merge_map = $payload['merge_map'] ?? [];
    // format_mode = izen-formatu globala berrientzat; format_map = izenez-izeneko gainidazketak
    $format_mode = $payload['format_mode'] ?? 'auto';
    $format_map = $payload['format_map'] ?? [];
    $set = 0; $created = 0; $lotuta = 0; $baztertuta = 0; $errors = [];
    foreach ($riders as $r) {
        $rn = trim((string)($r['izena'] ?? ''));
        $dor = to_int($r['dortsala'] ?? null);
        if ($rn === '' || $dor === null) continue;
        try {
            $mv = array_key_exists($rn, $merge_map) ? $merge_map[$rn] : null;
            if ($mv === 'skip') { $baztertuta++; continue; }
            if ($mv !== null && $mv !== '') { $rid = (int)$mv; $lotuta++; }
            else {
                $existing = find_txirrindularia_id($rn);
                if ($existing === null) {
                    $created++;
                    $fmt = izen_formatua_sortu($rn, $format_map[$rn] ?? $format_mode);
                    $res = db_exec('INSERT INTO `Txirrindulariak` (Izena, Izen_Formatua) VALUES (?, ?)', [$rn, $fmt]);
                    $rid = (int)$res['insert_id'];
                } else {
                    $rid = $existing;
                }
            }
            upsert_dortsala($txap, $rid, $dor);
            $set++;
        } catch (Exception $e) {
            $errors[] = ['izena'=>$rn, 'reason'=>$e->getMessage()];
        }
    }
    return ['dortsalak'=>$set, 'txirrindulariak_berri'=>$created, 'lotuta'=>$lotuta, 'baztertuta'=>$baztertuta, 'errors'=>$errors];
}

// ── C · Apustuak (dortsalez) ────────────────────────────────────────────────
// payload: { txapelketa_id, mota, bettors: [ { izena, dortsalak: [..] }, ... ] }
function import_apustuak_preview($payload) {
    $txap = _imp_txap_id($payload);
    $bettors = $payload['bettors'] ?? [];
    $expect = ($payload['mota'] ?? '') === 'klasikoak' ? 25 : 15;
    $lotu_gabe = []; $bet_count = 0; $warnings = [];
    $unknown = []; $seen_d = [];
    foreach ($bettors as $b) {
        $izena = trim((string)($b['izena'] ?? ''));
        if ($izena === '') continue;
        // Porralaririk ez da sortuko: existitzen ez direnak lotu gabe geratuko dira
        if (find_porralaria_id($izena) === null && !in_array($izena, $lotu_gabe, true)) $lotu_gabe[] = $izena;
        $dors = $b['dortsalak'] ?? [];
        if (count($dors) !== $expect) $warnings[] = "$izena: ".count($dors)." dortsal ($expect espero)";
        foreach ($dors as $d) {
            $di = to_int($d);
            if ($di === null) continue;
            $bet_count++;
            if (find_txirri_by_dortsala($txap, $di) === null && !in_array($di, $seen_d, true)) {
                $seen_d[] = $di; $unknown[] = $d;
            }
        }
    }
    return [
        'bettors'=>count($bettors),
        'lotu_gabe'=>$lotu_gabe,
        'bet_count'=>$bet_count,
        'unknown_dortsalak'=>$unknown,
        'warnings'=>$warnings,
    ];
}

function import_apustuak($payload) {
    $txap = _imp_txap_id($payload);
    $bettors = $payload['bettors'] ?? [];
    // Porralaririk EZ da sortzen: existitzen bada lotu, bestela ezizena lotu gabe utzi
    // (adminak "Ezizenak lotu" bidez lotuko du gero).
    $created_e = 0; $bets = 0; $lotuta = 0; $lotu_gabe = 0; $errors = []; $unknown = [];
    foreach ($bettors as $b) {
        $izena = trim((string)($b['izena'] ?? ''));
        if ($izena === '') continue;
        try {
            $eid_before = find_ezizen_id($txap, $izena);
            $eid = ensure_ezizen_id($txap, $izena);
            if ($eid_before === null) $created_e++;
            $pid = find_porralaria_id($izena);
            if ($pid !== null) {
                db_exec('INSERT IGNORE INTO `PorralariTaldeenEzizenak` (Ezizen_ID, Porralaria_ID) VALUES (?, ?)', [$eid, $pid]);
                $lotuta++;
            } else {
                $lotu_gabe++;
            }
            foreach (($b['dortsalak'] ?? []) as $d) {
                $di = to_int($d);
                if ($di === null) continue;
                $rid = find_txirri_by_dortsala($txap, $di);
                if ($rid === null) { $unknown[$d] = true; continue; }
                $res = db_exec('INSERT IGNORE INTO `PorraApustuak` (Txapelketa_ID, Ezizen_ID, Txirrindularia_ID) VALUES (?, ?, ?)', [$txap, $eid, $rid]);
                if (($res['affected'] ?? 0) > 0) $bets++;
            }
        } catch (Exception $e) {
            $errors[] = ['izena'=>$izena, 'reason'=>$e->getMessage()];
        }
    }
    return ['ezizenak_berri'=>$created_e, 'lotuta'=>$lotuta, 'lotu_gabe'=>$lotu_gabe, 'apustuak'=>$bets, 'dortsal_ezezagunak'=>array_keys($unknown), 'errors'=>$errors];
}

// ── D · Karrera emaitzak (dortsalez, edo izen-fallback) ─────────────────────
// payload: { karrera_id, txapelketa_id, rows: [ {pos, dortsala, izena, puntuak}, ... ] }
function import_emaitzak_preview($payload) {
    $txap = _imp_txap_id($payload);
    $rows = $payload['rows'] ?? [];
    $out = []; $unknown = [];
    foreach ($rows as $r) {
        $pos = to_int($r['pos'] ?? null);
        if ($pos === null) continue;

        $di = to_int($r['dortsala'] ?? null);
        $nm = trim((string)($r['izena'] ?? ''));

        // Txirrindularia ebatzi: lehenik dortsalez (startlist), gero izenez.
        $rid = null; $bidea = null;
        if ($di !== null) { $rid = find_txirri_by_dortsala($txap, $di); if ($rid !== null) $bidea = 'dortsala'; }
        if ($rid === null && $nm !== '') { $rid = find_txirrindularia_id($nm); if ($rid !== null) $bidea = 'izena'; }

        // Dortsala↔izena osatu: ebatzitako txirrindulariaren izen kanonikoa eta
        // txapelketa honetako dortsala (biak erakusteko).
        $izen_ebatzia = $nm;
        $dortsala_ebatzia = ($di !== null ? (string)$di : null);
        $egoera = 'ok';
        if ($rid !== null) {
            $tx = db_one('SELECT t.Izena AS izena, h.Dortsala AS dortsala
                          FROM `Txirrindulariak` t
                          LEFT JOIN `TxirrindulariakTxapleketanParteHartzea` h
                            ON h.TxirrindulariaID = t.Txirrindularia_ID AND h.TxapelketaID = ?
                          WHERE t.Txirrindularia_ID = ?', [$txap, $rid]);
            if ($tx) {
                $izen_ebatzia = $tx['izena'];
                if ($tx['dortsala'] !== null && $tx['dortsala'] !== '') $dortsala_ebatzia = (string)$tx['dortsala'];
                // Izenez ebatzi baina txapelketan dortsalik gabe (startlist-ean ez dago)
                if ($bidea === 'izena' && ($tx['dortsala'] === null || $tx['dortsala'] === '')) $egoera = 'dortsalik_gabe';
            }
        } elseif ($nm !== '') {
            $egoera = 'sortu';           // izena DBan ez dago → inportatzean sortuko da
        } else {
            $egoera = 'ezezaguna';       // dortsala startlist-ean ez dago, izenik ez
        }

        if ($egoera === 'sortu' || $egoera === 'ezezaguna') {
            $unknown[] = ($nm !== '' ? $nm : (string)($r['dortsala'] ?? '?'));
        }

        $out[] = [
            'pos'      => $pos,
            'dortsala' => $dortsala_ebatzia,
            'izena'    => $izen_ebatzia,
            'puntuak'  => $r['puntuak'] ?? null,
            'egoera'   => $egoera,       // ok | dortsalik_gabe | sortu | ezezaguna
        ];
    }
    return ['count'=>count($out), 'rows'=>$out, 'unknown'=>$unknown];
}

function import_emaitzak($payload) {
    $txap = _imp_txap_id($payload);
    $kid = $payload['karrera_id'] ?? null;
    if (!$kid) throw new Exception('Karrera bat hautatu behar da');
    $kid = (int)$kid;
    $rows = $payload['rows'] ?? [];
    db_exec('DELETE FROM `KarreraSailkapena` WHERE Karrera_ID = ?', [$kid]);
    $ins = 0; $unknown = []; $errors = [];
    foreach ($rows as $r) {
        $pos = to_int($r['pos'] ?? null);
        $pts = to_int($r['puntuak'] ?? null);
        if ($pos === null) continue;
        if ($pts === null) $pts = 0;
        $rid = null;
        $di = to_int($r['dortsala'] ?? null);
        if ($di !== null) $rid = find_txirri_by_dortsala($txap, $di);
        $nm = trim((string)($r['izena'] ?? ''));
        if ($rid === null && $nm !== '') $rid = ensure_txirrindularia_id($nm);
        if ($rid === null) { $unknown[] = (string)($r['dortsala'] ?? '?'); continue; }
        try {
            db_exec('INSERT IGNORE INTO `KarreraSailkapena` (Karrera_ID, Txirrindularia_ID, Puntuak, Sailkapena) VALUES (?, ?, ?, ?)', [$kid, $rid, $pts, $pos]);
            $ins++;
        } catch (Exception $e) { $errors[] = $e->getMessage(); }
    }
    return ['sartuta'=>$ins, 'dortsal_ezezagunak'=>$unknown, 'errors'=>$errors];
}

/** Karrera baten emaitza GUZTIAK ezabatu (KarreraSailkapena). */
function clear_karrera_emaitzak($karrera_id) {
    $kid = (int)$karrera_id;
    if (!$kid) throw new Exception('Karrera bat hautatu behar da');
    $r = db_exec('DELETE FROM `KarreraSailkapena` WHERE Karrera_ID = ?', [$kid]);
    return ['ok'=>true, 'ezabatuta'=>$r['affected']];
}

// ── D2 · Etapak (itzuli-emaitzak batera, "Etapak" orria) ────────────────────
// Etapa bakoitza dortsalez eta postuz; puntuak eskalatik (postuaren arabera).
// Karrera izenez lotzen da (Helmuga = Karreraren izena).
function find_karrera_by_izena($txap_id, $izena) {
    $row = db_one('SELECT Karrerak_ID FROM `Karrerak` WHERE Txapelketa_ID = ? AND Izena = ?', [(int)$txap_id, $izena]);
    if ($row) return (int)$row['Karrerak_ID'];
    $norm = normalize_name($izena);
    if ($norm === '') return null;
    foreach (db_rows('SELECT Karrerak_ID, Izena FROM `Karrerak` WHERE Txapelketa_ID = ?', [(int)$txap_id]) as $r) {
        if (normalize_name($r['Izena']) === $norm) return (int)$r['Karrerak_ID'];
    }
    return null;
}

// payload: { txapelketa_id, stages: [ {izena, results:[{pos,dortsala}]}, ... ] }
function import_etapak_preview($payload) {
    $txap = _imp_txap_id($payload);
    $stages = $payload['stages'] ?? [];
    $out = [];
    foreach ($stages as $s) {
        $izena = trim((string)($s['izena'] ?? ''));
        if ($izena === '') continue;
        $kid = find_karrera_by_izena($txap, $izena);
        $unknown = [];
        foreach (($s['results'] ?? []) as $res) {
            $di = to_int($res['dortsala'] ?? null);
            if ($di !== null && find_txirri_by_dortsala($txap, $di) === null) $unknown[] = (string)($res['dortsala'] ?? '');
        }
        $out[] = ['izena'=>$izena, 'karrera_id'=>$kid, 'n'=>count($s['results'] ?? []), 'unknown'=>$unknown];
    }
    return ['stages'=>$out];
}

// payload: { txapelketa_id, stages:[{izena, results:[{pos,dortsala}]}], puntuak:[6 zenbaki] }
function import_etapak($payload) {
    $txap = _imp_txap_id($payload);
    $stages = $payload['stages'] ?? [];
    $puntuak = $payload['puntuak'] ?? [31,23,17,13,9,7];
    $create_missing = array_key_exists('create_missing', $payload) ? (bool)$payload['create_missing'] : true;
    $trow = db_one('SELECT Urtea FROM `Txapelketak` WHERE Txapelketa_ID = ?', [$txap]);
    $urtea = $trow ? (int)$trow['Urtea'] : (int)date('Y');
    $next_ordena = (int)(db_scalar('SELECT COALESCE(MAX(Ordena),0) FROM `Karrerak` WHERE Txapelketa_ID = ?', [$txap]) ?? 0);
    $done = []; $karrerak_sortuta = 0; $unmatched = []; $unknown_all = [];
    foreach ($stages as $s) {
        $izena = trim((string)($s['izena'] ?? ''));
        if ($izena === '') continue;
        // Ordena: etapa-zenbakia (Etapak orritik) edo hurrengo sekuentziala
        $ordena = to_int($s['zenbakia'] ?? null);
        $kid = find_karrera_by_izena($txap, $izena);
        if ($kid === null) {
            if (!$create_missing) { $unmatched[] = $izena; continue; }
            if ($ordena === null) { $next_ordena++; $ordena = $next_ordena; }
            $res = db_exec('INSERT INTO `Karrerak` (Txapelketa_ID, Izena, Urtea, Kategoria, Ordena) VALUES (?, ?, ?, ?, ?)', [$txap, $izena, $urtea, 'Etapa', $ordena]);
            $kid = (int)$res['insert_id'];
            $karrerak_sortuta++;
        } elseif ($ordena !== null) {
            db_exec('UPDATE `Karrerak` SET Ordena = ? WHERE Karrerak_ID = ?', [$ordena, $kid]);
        }
        db_exec('DELETE FROM `KarreraSailkapena` WHERE Karrera_ID = ?', [$kid]);
        $ins = 0;
        foreach (($s['results'] ?? []) as $res) {
            $pos = to_int($res['pos'] ?? null);
            $di = to_int($res['dortsala'] ?? null);
            if ($pos === null || $di === null) continue;
            $rid = find_txirri_by_dortsala($txap, $di);
            if ($rid === null) { $unknown_all[(string)($res['dortsala'] ?? '')] = true; continue; }
            $pts = ($pos >= 1 && $pos <= count($puntuak)) ? (int)$puntuak[$pos-1] : 0;
            db_exec('INSERT IGNORE INTO `KarreraSailkapena` (Karrera_ID, Txirrindularia_ID, Puntuak, Sailkapena) VALUES (?, ?, ?, ?)', [$kid, $rid, $pts, $pos]);
            $ins++;
        }
        $done[] = ['izena'=>$izena, 'sartuta'=>$ins];
    }
    return ['egindakoak'=>$done, 'karrerak_sortuta'=>$karrerak_sortuta, 'lotu_gabe'=>$unmatched, 'dortsal_ezezagunak'=>array_keys($unknown_all)];
}

// ── Sailkapen finalak → «Puntu finalak» (finalize_txapelketa_*) atalak ordezkatzen ditu.
// Lehen block 5 (import_sailkapenak) eta 5b (import_sailkapenak_txirri) zeuden hemen; kenduta,
// «Puntu finalak»-ek porralari eta txirri sailkapen finalak kalkulatzen/idazten baititu.

// ── Puntu finalak: itzuli handien sailkapen finala + txapelketa itxi ─────────
// TXIRRINDULARIKO sartzen dira sailkapen OROKORREKO (generala) eta MENDIKO bonus-puntuak
// (dortsalez). Tresnak:
//   · Txirri bakoitzaren total = etapak + generala + mendia (etapak KarreraSailkapena-tik).
//   · Porralari bakoitzaren generala/mendia = bere txirrindularien (apustuen) baturak;
//     total = etapak + generala + mendia. (Egiaztatua: TxapelketaEmaitzaPorralariak.Puntuak
//     = etapak + Generala + Mendikoa.)
// Bietan idazten da (txirri + porralari emaitzak) eta txapelketa ixten da.

function _finalize_amaituta_bada() { return db_column_exists('Txapelketak', 'Amaituta'); }

// Postuz ordenatu (total desc). Berdinketek postu bera partekatzen dute.
function _finalize_rank(&$rows) {
    usort($rows, fn($a,$b) => $b['total'] <=> $a['total']);
    $pos = 0; $prev = null;
    foreach ($rows as $i => &$r) {
        if ($prev === null || $r['total'] !== $prev) { $pos = $i + 1; $prev = $r['total']; }
        $r['pos'] = $pos;
    }
    unset($r);
}

// Preview eta commit-ek partekatzen duten kalkulua. Ez du ezer idazten.
// $gc_rows / $men_rows: [ {dortsala, puntuak}, ... ].
// Itzultzen du: ['txirri'=>[...], 'porralari'=>[...], 'dortsal_ezezagunak'=>[...]].
function _finalize_konputatu($txap, $gc_rows, $men_rows) {
    // 1) Dortsalak → txirri ID. Dortsal ezezagunak salatu (baina behin bakarrik).
    $gc = []; $men = []; $ezezagunak = [];
    $ebatzi = function($rows, &$map) use ($txap, &$ezezagunak) {
        foreach ($rows as $r) {
            $d = to_int($r['dortsala'] ?? null);
            $p = to_int($r['puntuak'] ?? null);
            if ($d === null || $p === null) continue;
            $rid = find_txirri_by_dortsala($txap, $d);
            if ($rid === null) { $ezezagunak[] = (string)$d; continue; }
            $map[$rid] = ($map[$rid] ?? 0) + $p;
        }
    };
    $ebatzi($gc_rows, $gc);
    $ebatzi($men_rows, $men);

    // 2) Txirrindularien etapetako puntuak (txapelketa honetako karreretan).
    $et = [];
    foreach (db_rows(
        'SELECT ks.Txirrindularia_ID AS rid, COALESCE(SUM(ks.Puntuak),0) AS pts
         FROM `KarreraSailkapena` ks
         JOIN `Karrerak` k ON k.Karrerak_ID = ks.Karrera_ID
         WHERE k.Txapelketa_ID = ?
         GROUP BY ks.Txirrindularia_ID', [$txap]) as $r)
        $et[(int)$r['rid']] = (int)$r['pts'];

    // 3) Txirri errenkadak: etaparik, GC-rik edo mendirik duen edonor.
    $rids = array_values(array_unique(array_merge(array_keys($et), array_keys($gc), array_keys($men))));
    $izenak = [];
    if ($rids) {
        $in = implode(',', array_fill(0, count($rids), '?'));
        foreach (db_rows("SELECT Txirrindularia_ID, Izena FROM `Txirrindulariak` WHERE Txirrindularia_ID IN ($in)", $rids) as $r)
            $izenak[(int)$r['Txirrindularia_ID']] = $r['Izena'];
    }
    $txirri = [];
    foreach ($rids as $rid) {
        $e = $et[$rid] ?? 0; $g = $gc[$rid] ?? 0; $m = $men[$rid] ?? 0;
        $txirri[] = ['rid'=>$rid, 'izena'=>$izenak[$rid] ?? ('#'.$rid),
                     'etapak'=>$e, 'generala'=>$g, 'mendia'=>$m, 'total'=>$e+$g+$m];
    }
    _finalize_rank($txirri);

    // 4) Apustuak: ezizen → txirri IDak.
    $picks = [];
    foreach (db_rows('SELECT Ezizen_ID, Txirrindularia_ID FROM `PorraApustuak` WHERE Txapelketa_ID = ?', [$txap]) as $r)
        $picks[(int)$r['Ezizen_ID']][] = (int)$r['Txirrindularia_ID'];

    // 5) Parte-hartzaileak (izenak).
    $ezizenak = [];
    foreach (db_rows(
        'SELECT DISTINCT pa.Ezizen_ID AS eid, ez.Ezizena AS izena
         FROM `PorraApustuak` pa
         JOIN `PorraEzizenak` ez ON ez.Ezizen_ID = pa.Ezizen_ID
         WHERE pa.Txapelketa_ID = ?
         ORDER BY ez.Ezizena', [$txap]) as $r)
        $ezizenak[(int)$r['eid']] = $r['izena'];

    // 6) Porralari errenkadak: bonusak txirrindularietatik metatu.
    $porralari = [];
    foreach ($ezizenak as $eid => $izena) {
        $e = 0; $g = 0; $m = 0;
        foreach ($picks[$eid] ?? [] as $rid) {
            $e += $et[$rid] ?? 0;
            $g += $gc[$rid] ?? 0;
            $m += $men[$rid] ?? 0;
        }
        $porralari[] = ['eid'=>$eid, 'ezizena'=>$izena,
                        'etapak'=>$e, 'generala'=>$g, 'mendia'=>$m, 'total'=>$e+$g+$m];
    }
    _finalize_rank($porralari);

    return ['txirri'=>$txirri, 'porralari'=>$porralari,
            'dortsal_ezezagunak'=>array_values(array_unique($ezezagunak))];
}

// payload: { txapelketa_id, gc_rows:[{dortsala,puntuak}], men_rows:[{dortsala,puntuak}] }
function finalize_txapelketa_preview($payload) {
    $txap = _imp_txap_id($payload);
    $res = _finalize_konputatu($txap, $payload['gc_rows'] ?? [], $payload['men_rows'] ?? []);
    $res['amaituta_bada'] = _finalize_amaituta_bada();
    return $res;
}

function finalize_txapelketa_commit($payload) {
    $txap = _imp_txap_id($payload);
    $c = _finalize_konputatu($txap, $payload['gc_rows'] ?? [], $payload['men_rows'] ?? []);
    if (!$c['porralari']) return ['ok'=>false, 'reason'=>'Txapelketa honek ez du apusturik (PorraApustuak)'];
    $amaituta_bada = _finalize_amaituta_bada();
    $txirri_kop = 0; $porra_kop = 0;
    try {
        db_begin();
        // Txirrindularien emaitzak (ez-suntsitzailea: UPDATE edo INSERT).
        foreach ($c['txirri'] as $r) {
            $rid = $r['rid'];
            $bada = db_one('SELECT 1 AS x FROM `TxapelketaEmaitzaTxirrindulariak` WHERE Txapelketa_ID = ? AND Txirrindularia_ID = ?', [$txap, $rid]);
            if ($bada) {
                db_exec('UPDATE `TxapelketaEmaitzaTxirrindulariak` SET Posizioa = ?, Puntuak = ?, Puntuak_Sailkapen_Nag = ?, Puntuak_Mendian = ? WHERE Txapelketa_ID = ? AND Txirrindularia_ID = ?',
                    [$r['pos'], $r['total'], $r['generala'], $r['mendia'], $txap, $rid]);
            } else {
                db_exec('INSERT INTO `TxapelketaEmaitzaTxirrindulariak` (Txapelketa_ID, Txirrindularia_ID, Posizioa, Puntuak, Puntuak_Sailkapen_Nag, Puntuak_Mendian) VALUES (?, ?, ?, ?, ?, ?)',
                    [$txap, $rid, $r['pos'], $r['total'], $r['generala'], $r['mendia']]);
            }
            $txirri_kop++;
        }
        // Porralarien emaitzak.
        foreach ($c['porralari'] as $r) {
            $eid = $r['eid'];
            $bada = db_one('SELECT 1 AS x FROM `TxapelketaEmaitzaPorralariak` WHERE Txapelketa_ID = ? AND Ezizen_ID = ?', [$txap, $eid]);
            if ($bada) {
                db_exec('UPDATE `TxapelketaEmaitzaPorralariak` SET Posizioa = ?, Puntuak = ?, Puntuak_Generala = ?, Puntuak_Mendikoa = ? WHERE Txapelketa_ID = ? AND Ezizen_ID = ?',
                    [$r['pos'], $r['total'], $r['generala'], $r['mendia'], $txap, $eid]);
            } else {
                db_exec('INSERT INTO `TxapelketaEmaitzaPorralariak` (Txapelketa_ID, Ezizen_ID, Posizioa, Puntuak, Puntuak_Generala, Puntuak_Mendikoa) VALUES (?, ?, ?, ?, ?, ?)',
                    [$txap, $eid, $r['pos'], $r['total'], $r['generala'], $r['mendia']]);
            }
            $porra_kop++;
        }
        if ($amaituta_bada) db_exec('UPDATE `Txapelketak` SET Amaituta = 1, Porra_Irekita = 0 WHERE Txapelketa_ID = ?', [$txap]);
        else                db_exec('UPDATE `Txapelketak` SET Porra_Irekita = 0 WHERE Txapelketa_ID = ?', [$txap]);
        db_commit();
    } catch (Exception $e) { db_rollback(); return ['ok'=>false, 'reason'=>$e->getMessage()]; }
    return ['ok'=>true, 'txirri'=>$txirri_kop, 'porralariak'=>$porra_kop,
            'amaituta'=>$amaituta_bada, 'dortsal_ezezagunak'=>$c['dortsal_ezezagunak']];
}

// ── A · Karrerak (lasterketa-zerrenda) ──────────────────────────────────────
// payload: { txapelketa_id, urtea, races: [ {izena, kategoria}, ... ] }
function import_karrerak($payload) {
    $txap = _imp_txap_id($payload);
    $urtea = to_int($payload['urtea'] ?? null);
    if ($urtea === null) {
        $row = db_one('SELECT Urtea FROM `Txapelketak` WHERE Txapelketa_ID = ?', [$txap]);
        $urtea = $row ? (int)$row['Urtea'] : (int)date('Y');
    }
    $races = $payload['races'] ?? [];
    // Ordena: lehendik dagoen maximotik jarraitu (paste-aren ordenean erantsi)
    $next_ordena = (int)(db_scalar('SELECT COALESCE(MAX(Ordena),0) FROM `Karrerak` WHERE Txapelketa_ID = ?', [$txap]) ?? 0);
    $ins = 0; $skip = 0; $errors = [];
    foreach ($races as $r) {
        $izena = trim((string)($r['izena'] ?? ''));
        if ($izena === '') continue;
        $kat = trim((string)($r['kategoria'] ?? 'Etapa'));
        try {
            $exists = db_one('SELECT Karrerak_ID FROM `Karrerak` WHERE Izena = ? AND Urtea = ? AND Txapelketa_ID = ?', [$izena, $urtea, $txap]);
            if ($exists) { $skip++; continue; }
            $next_ordena++;
            db_exec('INSERT INTO `Karrerak` (Txapelketa_ID, Izena, Urtea, Kategoria, Ordena) VALUES (?, ?, ?, ?, ?)', [$txap, $izena, $urtea, $kat, $next_ordena]);
            $ins++;
        } catch (Exception $e) { $errors[] = ['izena'=>$izena, 'reason'=>$e->getMessage()]; }
    }
    return ['sortuta'=>$ins, 'lehendik'=>$skip, 'errors'=>$errors];
}

// ─── Undo / Redo (session) ───────────────────────────────────────────────────
function delete_rows_by_identity($table, $identity_fields, $identities) {
    if (!$identity_fields || !$identities) return 0;
    if (count($identity_fields) === 1) {
        $field = $identity_fields[0];
        $values = [];
        foreach ($identities as $id) if (array_key_exists($field, $id)) $values[] = $id[$field];
        if (!$values) return 0;
        $ph = implode(',', array_fill(0, count($values), '?'));
        db_exec("DELETE FROM `$table` WHERE `$field` IN ($ph)", $values);
        return count($values);
    }
    $clauses=[]; $params=[];
    foreach ($identities as $id) {
        $ok = true; foreach ($identity_fields as $f) if (!array_key_exists($f, $id)) { $ok=false; break; }
        if (!$ok) continue;
        $clauses[] = '(' . implode(' AND ', array_map(fn($f)=>"`$f` = ?", $identity_fields)) . ')';
        foreach ($identity_fields as $f) $params[] = $id[$f];
    }
    if (!$clauses) return 0;
    db_exec("DELETE FROM `$table` WHERE " . implode(' OR ', $clauses), $params);
    return count($clauses);
}

function do_undo() {
    global $CSV_PROFILES;
    $stack = &$_SESSION['undo_stack'];
    if (empty($stack)) return ['ok'=>false,'reason'=>'Undo stack hutsa'];
    $batch = end($stack);
    $spec = $CSV_PROFILES[$batch['profile']] ?? null;
    $tbl = $spec['target'] ?? null;
    $identities = $batch['identities'] ?? [];
    $identity_fields = $batch['identity_fields'] ?? [];
    if (!$tbl || !$identities) return ['ok'=>false,'reason'=>'Batch baliogabea'];
    array_pop($stack);
    if (!isset($_SESSION['redo_stack'])) $_SESSION['redo_stack']=[];
    $_SESSION['redo_stack'][] = $batch;
    delete_rows_by_identity($tbl, $identity_fields, $identities);
    return ['ok'=>true,'deleted'=>count($identities),'label'=>$batch['label']];
}

function do_redo() {
    $stack = &$_SESSION['redo_stack'];
    if (empty($stack)) return ['ok'=>false,'reason'=>'Redo stack hutsa'];
    $batch = end($stack);
    $rows_list = $batch['rows'] ?? [];
    $inserted=[]; $skipped=0; $errors=[];
    foreach ($rows_list as $norm) {
        [$ex, ] = row_exists($batch['profile'], $norm);
        if ($ex) { $skipped++; continue; }
        try { $inserted[] = insert_row($batch['profile'], $norm); }
        catch (Exception $e) { $errors[] = ['row'=>$norm,'reason'=>$e->getMessage()]; }
    }
    array_pop($stack);
    if ($inserted) {
        $batch['identities'] = $inserted;
        $_SESSION['undo_stack'][] = $batch;
        if (count($_SESSION['undo_stack']) > 20) array_shift($_SESSION['undo_stack']);
    }
    return ['ok'=>true,'inserted'=>count($inserted),'skipped'=>$skipped,'errors'=>$errors,'label'=>$batch['label']];
}

function undo_stack_state() {
    $u = $_SESSION['undo_stack'] ?? [];
    $r = $_SESSION['redo_stack'] ?? [];
    $mk = fn($b) => ['label'=>$b['label'],'count'=>count($b['rows'] ?? ($b['identities'] ?? []))];
    return ['undo'=>array_map($mk, array_reverse($u)), 'redo'=>array_map($mk, array_reverse($r))];
}

// ─── Merge ───────────────────────────────────────────────────────────────────
$TXIRRINDULARIA_REFS = [
    ['TxapelketaEmaitzaTxirrindulariak','Txirrindularia_ID',['Txapelketa_ID','Txirrindularia_ID']],
    ['TxapelketaSailkapenaTxirrindulariak','Txirrindularia_ID',['Txapelketa_ID','Txirrindularia_ID','Azken_Karrera_ID']],
    ['KarreraSailkapena','Txirrindularia_ID',['Karrera_ID','Txirrindularia_ID']],
    ['PorraApustuak','Txirrindularia_ID',['Txapelketa_ID','Ezizen_ID','Txirrindularia_ID']],
];
$PORRALARIA_REFS = [
    ['PorralariTaldeenEzizenak','Porralaria_ID',['Ezizen_ID','Porralaria_ID']],
];

function do_merge_refs($table, $col, $pks, $keep_id, $drop_id) {
    if (!db_table_exists($table)) return null;
    $ref_rows = db_rows("SELECT * FROM `$table` WHERE `$col` = ?", [$drop_id]);
    if (!$ref_rows) return null;
    $migrated=0; $skipped=0;
    foreach ($ref_rows as $ref) {
        $new_vals = array_merge($ref, [$col=>$keep_id]);
        $pk_vals = array_map(fn($pk)=>$new_vals[$pk], $pks);
        $pk_clause = implode(' AND ', array_map(fn($pk)=>"`$pk` = ?", $pks));
        $conflict = db_one("SELECT 1 AS x FROM `$table` WHERE $pk_clause", $pk_vals);
        $old_pk_vals = array_map(fn($pk)=>$ref[$pk], $pks);
        if ($conflict) {
            db_exec("DELETE FROM `$table` WHERE $pk_clause", $old_pk_vals);
            $skipped++;
        } else {
            db_exec("UPDATE `$table` SET `$col` = ? WHERE $pk_clause", array_merge([$keep_id], $old_pk_vals));
            $migrated++;
        }
    }
    return ['table'=>$table,'migrated'=>$migrated,'skipped'=>$skipped];
}

function merge_txirrindulariak($keep_id, $drop_id) {
    global $TXIRRINDULARIA_REFS;
    $keep = db_one('SELECT * FROM `Txirrindulariak` WHERE Txirrindularia_ID = ?', [$keep_id]);
    $drop = db_one('SELECT * FROM `Txirrindulariak` WHERE Txirrindularia_ID = ?', [$drop_id]);
    if (!$keep) return ['ok'=>false,'reason'=>"Keep ID $keep_id ez da existitzen"];
    if (!$drop) return ['ok'=>false,'reason'=>"Drop ID $drop_id ez da existitzen"];
    try {
        db_begin();
        $log=[];
        foreach ($TXIRRINDULARIA_REFS as [$t,$c,$pks]) { $r = do_merge_refs($t,$c,$pks,$keep_id,$drop_id); if ($r) $log[]=$r; }
        db_exec('DELETE FROM `Txirrindulariak` WHERE Txirrindularia_ID = ?', [$drop_id]);
        db_commit();
        return ['ok'=>true,'keep'=>['id'=>$keep_id,'izena'=>$keep['Izena']],'dropped'=>['id'=>$drop_id,'izena'=>$drop['Izena']],'log'=>$log];
    } catch (Exception $e) { db_rollback(); return ['ok'=>false,'reason'=>$e->getMessage()]; }
}

function merge_porralariak($keep_id, $drop_id) {
    global $PORRALARIA_REFS;
    $keep = db_one('SELECT * FROM `Porralariak` WHERE Porralaria_ID = ?', [$keep_id]);
    $drop = db_one('SELECT * FROM `Porralariak` WHERE Porralaria_ID = ?', [$drop_id]);
    if (!$keep) return ['ok'=>false,'reason'=>"Keep ID $keep_id ez da existitzen"];
    if (!$drop) return ['ok'=>false,'reason'=>"Drop ID $drop_id ez da existitzen"];
    try {
        db_begin();
        $log=[];
        foreach ($PORRALARIA_REFS as [$t,$c,$pks]) { $r = do_merge_refs($t,$c,$pks,$keep_id,$drop_id); if ($r) $log[]=$r; }
        $kc = (int)($keep['Zenbat_Porra'] ?? 0) ?: 1;
        $dc = (int)($drop['Zenbat_Porra'] ?? 0) ?: 1;
        db_exec('UPDATE `Porralariak` SET `Zenbat_Porra` = ? WHERE Porralaria_ID = ?', [$kc+$dc, $keep_id]);
        db_exec('DELETE FROM `Porralariak` WHERE Porralaria_ID = ?', [$drop_id]);
        db_commit();
        return ['ok'=>true,'keep'=>['id'=>$keep_id,'izena'=>$keep['Izena']],'dropped'=>['id'=>$drop_id,'izena'=>$drop['Izena']],'log'=>$log];
    } catch (Exception $e) { db_rollback(); return ['ok'=>false,'reason'=>$e->getMessage()]; }
}

function porralaria_ezizenak($porralaria_id) {
    return db_rows(
        'SELECT ez.Ezizen_ID, ez.Ezizena, ez.Txapelketa_ID, t.Izena AS Txapelketa ' .
        'FROM `PorralariTaldeenEzizenak` ep ' .
        'JOIN `PorraEzizenak` ez ON ep.Ezizen_ID = ez.Ezizen_ID ' .
        'LEFT JOIN `Txapelketak` t ON ez.Txapelketa_ID = t.Txapelketa_ID ' .
        'WHERE ep.Porralaria_ID = ? ORDER BY t.Urtea DESC, ez.Ezizena', [$porralaria_id]);
}

function merge_preview($kind, $keep_id, $drop_id) {
    global $TXIRRINDULARIA_REFS, $PORRALARIA_REFS;
    $refs_map = $kind === 'txirrindulariak' ? $TXIRRINDULARIA_REFS : $PORRALARIA_REFS;
    $id_col = $kind === 'txirrindulariak' ? 'Txirrindularia_ID' : 'Porralaria_ID';
    $table_n = $kind === 'txirrindulariak' ? 'Txirrindulariak' : 'Porralariak';
    $keep = db_one("SELECT * FROM `$table_n` WHERE $id_col = ?", [$keep_id]);
    $drop = db_one("SELECT * FROM `$table_n` WHERE $id_col = ?", [$drop_id]);
    if (!$keep || !$drop) return ['ok'=>false,'reason'=>'ID bat ez da existitzen'];
    $refs=[];
    foreach ($refs_map as [$t,$c,$pks]) {
        if (!db_table_exists($t)) continue;
        $count = (int)db_scalar("SELECT COUNT(*) FROM `$t` WHERE `$c` = ?", [$drop_id]);
        if ($count) $refs[] = ['table'=>$t,'count'=>$count];
    }
    $result = ['ok'=>true,'keep'=>['id'=>$keep_id,'izena'=>$keep['Izena']],'dropped'=>['id'=>$drop_id,'izena'=>$drop['Izena']],'refs'=>$refs];
    if ($kind === 'porralariak') {
        $result['zenbat_porra_merged'] = ((int)($keep['Zenbat_Porra'] ?? 0) ?: 1) + ((int)($drop['Zenbat_Porra'] ?? 0) ?: 1);
        $result['keep']['ezizenak'] = porralaria_ezizenak($keep_id);
        $result['dropped']['ezizenak'] = porralaria_ezizenak($drop_id);
    }
    return $result;
}

// ─── Ezizenak lotu / split / recompute ───────────────────────────────────────
function api_ezizenak() {
    $ez_rows = db_rows(
        'SELECT ez.*, t.Izena AS Txapelketa, t.Urtea AS Urtea ' .
        'FROM `PorraEzizenak` ez LEFT JOIN `Txapelketak` t ON ez.Txapelketa_ID = t.Txapelketa_ID ' .
        'ORDER BY t.Urtea DESC, ez.Ezizena');
    $link_rows = db_rows(
        'SELECT ep.Ezizen_ID, p.Porralaria_ID, p.Izena FROM `PorralariTaldeenEzizenak` ep ' .
        'JOIN `Porralariak` p ON ep.Porralaria_ID = p.Porralaria_ID ORDER BY p.Izena');
    $by = [];
    foreach ($link_rows as $lr) $by[$lr['Ezizen_ID']][] = ['Porralaria_ID'=>(int)$lr['Porralaria_ID'],'Izena'=>$lr['Izena']];
    foreach ($ez_rows as &$r) {
        $pl = $by[$r['Ezizen_ID']] ?? [];
        $r['Porralariak'] = $pl;
        $r['Porralaria_ID'] = $pl ? $pl[0]['Porralaria_ID'] : null;
        $r['Porralaria'] = $pl ? implode(', ', array_map(fn($x)=>$x['Izena'], $pl)) : null;
    }
    return $ez_rows;
}

/** Porra-zenbakiak ID ordenatik birkalkulatu (txapelketaz txapelketa 1..N). Eskuzko
 *  aldaketak gainidazten ditu. `$txap_id` null bada, txapelketa guztiak. */
function recompute_porra_zenbakiak($txap_id = null) {
    if (!db_column_exists('PorraEzizenak', 'Zenbakia')) {
        return ['ok'=>false, 'reason'=>'Zenbakia zutabea falta da (db/porra-zenbakia.sql exekutatu)'];
    }
    if ($txap_id !== null && $txap_id !== '') {
        db_exec(
            'UPDATE `PorraEzizenak` pe JOIN (
                SELECT Ezizen_ID, ROW_NUMBER() OVER (ORDER BY Ezizen_ID) AS rn
                FROM `PorraEzizenak` WHERE Txapelketa_ID = ?
             ) t ON t.Ezizen_ID = pe.Ezizen_ID SET pe.Zenbakia = t.rn', [(int)$txap_id]);
    } else {
        db_exec(
            'UPDATE `PorraEzizenak` pe JOIN (
                SELECT Ezizen_ID, ROW_NUMBER() OVER (PARTITION BY Txapelketa_ID ORDER BY Ezizen_ID) AS rn
                FROM `PorraEzizenak`
             ) t ON t.Ezizen_ID = pe.Ezizen_ID SET pe.Zenbakia = t.rn');
    }
    return ['ok'=>true];
}

function recompute_zenbat_porra($porralaria_ids) {
    foreach (array_unique(array_filter($porralaria_ids)) as $pid) {
        $n = (int)db_scalar('SELECT COUNT(*) FROM `PorralariTaldeenEzizenak` WHERE Porralaria_ID = ?', [$pid]);
        db_exec('UPDATE `Porralariak` SET `Zenbat_Porra` = ? WHERE Porralaria_ID = ?', [$n, $pid]);
    }
}

function get_or_create_porralaria($izena) {
    $izena = trim((string)$izena);
    if ($izena === '') return null;
    $row = db_one('SELECT Porralaria_ID FROM `Porralariak` WHERE Izena = ?', [$izena]);
    if ($row) return (int)$row['Porralaria_ID'];
    $r = db_exec('INSERT INTO `Porralariak` (Izena, `Zenbat_Porra`) VALUES (?, 1)', [$izena]);
    return (int)$r['insert_id'];
}

function ezizen_lotu($data) {
    $ezizen_id = $data['ezizen_id'] ?? null;
    if (!$ezizen_id) return ['ok'=>false,'reason'=>'ezizen_id behar da'];
    $ids = $data['porralaria_ids'] ?? [];
    if (!empty($data['porralaria_id'])) $ids[] = $data['porralaria_id'];
    $has_set = array_key_exists('porralaria_ids',$data) || array_key_exists('porralaria_id',$data) || !empty($data['new_porralariak']);
    if (!$has_set) return ['ok'=>false,'reason'=>'porralaria_ids edo new_porralariak behar dira'];
    try {
        $ez = db_one('SELECT Ezizen_ID FROM `PorraEzizenak` WHERE Ezizen_ID = ?', [$ezizen_id]);
        if (!$ez) return ['ok'=>false,'reason'=>"Ezizen_ID $ezizen_id ez da existitzen"];
        foreach (($data['new_porralariak'] ?? []) as $izena) {
            $pid = get_or_create_porralaria($izena); if ($pid) $ids[] = $pid;
        }
        $target=[];
        foreach ($ids as $x) { $xi=(int)$x; if (!in_array($xi,$target)) $target[]=$xi; }
        foreach ($target as $pid) {
            if (!db_one('SELECT 1 AS x FROM `Porralariak` WHERE Porralaria_ID = ?', [$pid]))
                return ['ok'=>false,'reason'=>"Porralaria_ID $pid ez da existitzen"];
        }
        $prev = array_map(fn($r)=>(int)$r['Porralaria_ID'], db_rows('SELECT Porralaria_ID FROM `PorralariTaldeenEzizenak` WHERE Ezizen_ID = ?', [$ezizen_id]));
        db_exec('DELETE FROM `PorralariTaldeenEzizenak` WHERE Ezizen_ID = ?', [$ezizen_id]);
        foreach ($target as $pid) db_exec('INSERT IGNORE INTO `PorralariTaldeenEzizenak` (Ezizen_ID, Porralaria_ID) VALUES (?, ?)', [$ezizen_id, $pid]);
        recompute_zenbat_porra(array_merge($prev, $target));
        $porralariak = db_rows('SELECT p.Porralaria_ID, p.Izena FROM `PorralariTaldeenEzizenak` ep JOIN `Porralariak` p ON ep.Porralaria_ID = p.Porralaria_ID WHERE ep.Ezizen_ID = ? ORDER BY p.Izena', [$ezizen_id]);
        return ['ok'=>true,'ezizen_id'=>$ezizen_id,'porralariak'=>$porralariak];
    } catch (Exception $e) { return ['ok'=>false,'reason'=>$e->getMessage()]; }
}

function porralari_split($data) {
    $source_id = $data['source_id'] ?? null;
    $ezizen_ids = array_map('intval', $data['ezizen_ids'] ?? []);
    $target_id = $data['target_id'] ?? null;
    $new_izena = trim((string)($data['new_izena'] ?? ''));
    if (!$source_id) return ['ok'=>false,'reason'=>'source_id behar da'];
    if (!$ezizen_ids) return ['ok'=>false,'reason'=>'Gutxienez ezizen bat aukeratu behar da'];
    if (!$target_id && !$new_izena) return ['ok'=>false,'reason'=>'Helburu porralaria (lehendik edo berria) behar da'];
    try {
        $src = db_one('SELECT Izena FROM `Porralariak` WHERE Porralaria_ID = ?', [$source_id]);
        if (!$src) return ['ok'=>false,'reason'=>"Porralaria $source_id ez da existitzen"];
        if ($target_id) {
            $target_id = (int)$target_id;
            $tgt = db_one('SELECT Izena FROM `Porralariak` WHERE Porralaria_ID = ?', [$target_id]);
            if (!$tgt) return ['ok'=>false,'reason'=>"Helburu porralaria $target_id ez da existitzen"];
            if ($target_id === (int)$source_id) return ['ok'=>false,'reason'=>'Iturria eta helburua ezin dira berdinak izan'];
        } else {
            $target_id = get_or_create_porralaria($new_izena);
        }
        $moved=0;
        foreach ($ezizen_ids as $eid) {
            $link = db_one('SELECT 1 AS x FROM `PorralariTaldeenEzizenak` WHERE Ezizen_ID = ? AND Porralaria_ID = ?', [$eid, $source_id]);
            if (!$link) continue;
            db_exec('DELETE FROM `PorralariTaldeenEzizenak` WHERE Ezizen_ID = ? AND Porralaria_ID = ?', [$eid, $source_id]);
            db_exec('INSERT IGNORE INTO `PorralariTaldeenEzizenak` (Ezizen_ID, Porralaria_ID) VALUES (?, ?)', [$eid, $target_id]);
            $moved++;
        }
        recompute_zenbat_porra([(int)$source_id, (int)$target_id]);
        $tgt_izena = db_scalar('SELECT Izena FROM `Porralariak` WHERE Porralaria_ID = ?', [$target_id]);
        return ['ok'=>true,'source_id'=>(int)$source_id,'target_id'=>(int)$target_id,'target_izena'=>$tgt_izena,'moved'=>$moved];
    } catch (Exception $e) { return ['ok'=>false,'reason'=>$e->getMessage()]; }
}

// ─── Recalculate zenbat porra ────────────────────────────────────────────────
function recalculate_zenbat_porra() {
    $counts = db_rows('SELECT Porralaria_ID, COUNT(*) AS kopurua FROM `PorralariTaldeenEzizenak` GROUP BY Porralaria_ID');
    $aldatuta = 0;
    $counted = [];
    foreach ($counts as $row) {
        $pid = (int)$row['Porralaria_ID']; $kop = (int)$row['kopurua']; $counted[$pid]=true;
        $cur = db_one('SELECT `Zenbat_Porra` FROM `Porralariak` WHERE Porralaria_ID = ?', [$pid]);
        if ($cur && (int)$cur['Zenbat_Porra'] !== $kop) {
            db_exec('UPDATE `Porralariak` SET `Zenbat_Porra` = ? WHERE Porralaria_ID = ?', [$kop, $pid]); $aldatuta++;
        }
    }
    $all = db_rows('SELECT Porralaria_ID FROM `Porralariak`');
    foreach ($all as $r) {
        $pid = (int)$r['Porralaria_ID'];
        if (isset($counted[$pid])) continue;
        $cur = db_one('SELECT `Zenbat_Porra` FROM `Porralariak` WHERE Porralaria_ID = ?', [$pid]);
        if ($cur && (int)$cur['Zenbat_Porra'] !== 0) {
            db_exec('UPDATE `Porralariak` SET `Zenbat_Porra` = 0 WHERE Porralaria_ID = ?', [$pid]); $aldatuta++;
        }
    }
    return ['ok'=>true,'aldatuta'=>$aldatuta,'total'=>count($counts)];
}

// ─── Sailkapenak kalkulatu ───────────────────────────────────────────────────
function calculate_txirri_sailkapena($txap_id) {
    try {
        $races = array_map(fn($r)=>(int)$r['Karrerak_ID'], db_rows('SELECT Karrerak_ID FROM `Karrerak` WHERE Txapelketa_ID = ? ORDER BY (Ordena IS NULL), Ordena, Karrerak_ID', [$txap_id]));
        if (!$races) return ['ok'=>false,'reason'=>'Txapelketa honek ez du karrerarik'];
        db_begin();
        db_exec('DELETE FROM `TxapelketaSailkapenaTxirrindulariak` WHERE Txapelketa_ID = ?', [$txap_id]);
        $totals=[]; $batch=[]; $karrera_kop=0;
        foreach ($races as $kid) {
            $pts=[];
            foreach (db_rows('SELECT Txirrindularia_ID, Puntuak FROM `KarreraSailkapena` WHERE Karrera_ID = ?', [$kid]) as $r)
                $pts[(int)$r['Txirrindularia_ID']] = (int)$r['Puntuak'];
            if (!$pts) continue;
            $karrera_kop++;
            foreach ($pts as $c=>$p) $totals[$c] = ($totals[$c] ?? 0) + $p;
            foreach ($totals as $c=>$tot) $batch[] = [$txap_id, $c, $kid, $tot, $pts[$c] ?? 0, null, null, 0];
        }
        if ($batch) db_insert_many('TxapelketaSailkapenaTxirrindulariak',
            ['Txapelketa_ID','Txirrindularia_ID','Azken_Karrera_ID','Puntuak_Totalean','Puntuak_Azken_Karrera','Puntuak_Sailkapen_nagusia','Puntuak_Mendian','Eboluzioa'],
            $batch);
        db_commit();
        return ['ok'=>true,'karrerak'=>$karrera_kop,'txirrindulariak'=>count($totals),'errenkadak'=>count($batch)];
    } catch (Exception $e) { db_rollback(); return ['ok'=>false,'reason'=>$e->getMessage()]; }
}

function calculate_porralari_sailkapena($txap_id) {
    try {
        $bets=[];
        foreach (db_rows('SELECT Ezizen_ID, Txirrindularia_ID FROM `PorraApustuak` WHERE Txapelketa_ID = ?', [$txap_id]) as $r)
            $bets[(int)$r['Ezizen_ID']][] = (int)$r['Txirrindularia_ID'];
        if (!$bets) return ['ok'=>false,'reason'=>'Txapelketa honek ez du apusturik (PorraApustuak)'];
        $stand=[];
        foreach (db_rows('SELECT Azken_Karrera_ID, Txirrindularia_ID, Puntuak_Totalean, Puntuak_Azken_Karrera FROM `TxapelketaSailkapenaTxirrindulariak` WHERE Txapelketa_ID = ?', [$txap_id]) as $r)
            $stand[(int)$r['Azken_Karrera_ID']][(int)$r['Txirrindularia_ID']] = [(int)($r['Puntuak_Totalean'] ?? 0), (int)($r['Puntuak_Azken_Karrera'] ?? 0)];
        if (!$stand) return ['ok'=>false,'reason'=>'Lehenik txirrindularien sailkapena kalkulatu behar da'];
        db_begin();
        db_exec('DELETE FROM `TxapelketaSailkapenaPorralariak` WHERE Txapelketa_ID = ?', [$txap_id]);
        $batch=[];
        foreach ($stand as $kid=>$cyc) {
            foreach ($bets as $ez=>$chosen) {
                $tot=0; $last=0;
                foreach ($chosen as $c) { if (isset($cyc[$c])) { $tot+=$cyc[$c][0]; $last+=$cyc[$c][1]; } }
                $batch[] = [$txap_id, $ez, $kid, $tot, $last, 0, null, null];
            }
        }
        if ($batch) db_insert_many('TxapelketaSailkapenaPorralariak',
            ['Txapelketa_ID','Ezizen_ID','Azken_Karrera_ID','Puntuak_Totalean','Puntuak_Azken_Karrera','Puntuazio_Finala','Puntuazioa_Fin_Mendikoa','Puntuazioa_Fin_Generala'],
            $batch);
        db_commit();
        return ['ok'=>true,'karrerak'=>count($stand),'porralariak'=>count($bets),'errenkadak'=>count($batch)];
    } catch (Exception $e) { db_rollback(); return ['ok'=>false,'reason'=>$e->getMessage()]; }
}

// ─── Izen-ordena tresnak ─────────────────────────────────────────────────────
function detect_order($name) {
    $parts = preg_split('/\s+/u', trim($name));
    if (count($parts) < 2) return 'unknown';
    $first_upper = ($parts[0] === mb_strtoupper($parts[0],'UTF-8')) && preg_match('/\p{L}/u', $parts[0]);
    $last = $parts[count($parts)-1];
    $last_upper = ($last === mb_strtoupper($last,'UTF-8')) && preg_match('/\p{L}/u', $last);
    if ($first_upper && !$last_upper) return 'abizena_izena';
    if ($last_upper && !$first_upper) return 'izena_abizena';
    return 'unknown';
}

function swap_name($name) {
    $parts = preg_split('/\s+/u', trim($name));
    if (count($parts) < 2) return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    $upper_end = 0;
    foreach ($parts as $i=>$p) {
        if ($p === mb_strtoupper($p,'UTF-8') && preg_match('/\p{L}/u', $p)) $upper_end = $i+1;
        else break;
    }
    if ($upper_end === 0 || $upper_end === count($parts))
        return implode(' ', array_map(fn($p)=>mb_convert_case($p, MB_CASE_TITLE,'UTF-8'), $parts));
    $abizenak = array_slice($parts, 0, $upper_end);
    $izenak = array_slice($parts, $upper_end);
    return implode(' ', array_map(fn($p)=>mb_convert_case($p, MB_CASE_TITLE,'UTF-8'), array_merge($izenak, $abizenak)));
}

function get_txirrindulari_ordenak() {
    $all = db_rows('SELECT Txirrindularia_ID, Izena FROM `Txirrindulariak` ORDER BY Izena');
    $result=[];
    foreach ($all as $row) {
        $order = detect_order($row['Izena']);
        $result[] = ['Txirrindularia_ID'=>(int)$row['Txirrindularia_ID'],'Izena'=>$row['Izena'],'order'=>$order,'suggested'=>$order==='abizena_izena'?swap_name($row['Izena']):null];
    }
    return $result;
}

function apply_txirrindulari_swap($ids) {
    $changed=0; $skipped=0; $errors=[];
    foreach ($ids as $tid) {
        $row = db_one('SELECT Izena FROM `Txirrindulariak` WHERE Txirrindularia_ID = ?', [$tid]);
        if (!$row) { $skipped++; continue; }
        $new = swap_name($row['Izena']);
        if ($new === $row['Izena']) { $skipped++; continue; }
        $ex = db_one('SELECT 1 AS x FROM `Txirrindulariak` WHERE Izena = ? AND Txirrindularia_ID != ?', [$new, $tid]);
        if ($ex) { $errors[] = ['id'=>$tid,'izena'=>$row['Izena'],'reason'=>"'$new' jada existitzen da"]; continue; }
        db_exec('UPDATE `Txirrindulariak` SET Izena = ? WHERE Txirrindularia_ID = ?', [$new, $tid]); $changed++;
    }
    return ['ok'=>true,'changed'=>$changed,'skipped'=>$skipped,'errors'=>$errors];
}

function proposatu_ordena($izena) {
    $parts = preg_split('/\s+/u', trim($izena));
    if (count($parts) < 2) return $izena;
    if ($parts[0] === mb_strtoupper($parts[0],'UTF-8') && mb_strlen($parts[0]) > 2) {
        $rest = array_slice($parts, 1);
        $rest[] = mb_convert_case($parts[0], MB_CASE_TITLE, 'UTF-8');
        return implode(' ', $rest);
    }
    return $izena;
}

function get_izen_ordenak() {
    $all = db_rows('SELECT Txirrindularia_ID, Izena FROM `Txirrindulariak` ORDER BY Izena');
    $result=[];
    foreach ($all as $row) {
        $prop = proposatu_ordena($row['Izena']);
        $result[] = ['Txirrindularia_ID'=>(int)$row['Txirrindularia_ID'],'Izena'=>$row['Izena'],'Proposamena'=>$prop,'Aldatu'=>$prop!==$row['Izena']];
    }
    return $result;
}

function apply_izen_ordenak($aldaketak) {
    $aldatuta=0;
    foreach ($aldaketak as $item) {
        $tid = $item['Txirrindularia_ID'] ?? null;
        $izena = trim((string)($item['Izena_Berria'] ?? ''));
        if (!$tid || !$izena) continue;
        db_exec('UPDATE `Txirrindulariak` SET Izena = ? WHERE Txirrindularia_ID = ?', [$izena, $tid]); $aldatuta++;
    }
    return ['ok'=>true,'aldatuta'=>$aldatuta];
}

function normalize_izenak() {
    // Txirrindulariei bakarrik eragiten die (porralariei ez).
    $changed = ['txirrindulariak'=>0];
    foreach (db_rows('SELECT Txirrindularia_ID, Izena FROM `Txirrindulariak`') as $row) {
        $new = mb_convert_case($row['Izena'], MB_CASE_TITLE, 'UTF-8');
        if ($new !== $row['Izena']) { db_exec('UPDATE `Txirrindulariak` SET Izena = ? WHERE Txirrindularia_ID = ?', [$new, $row['Txirrindularia_ID']]); $changed['txirrindulariak']++; }
    }
    return ['ok'=>true,'changed'=>$changed];
}


// ─── Generic table / meta / insert / update ──────────────────────────────────
function db_meta() {
    $cfg = require __DIR__ . '/config.php';
    $tables = array_map(fn($r)=>$r['TABLE_NAME'] ?? $r['table_name'],
        db_rows("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME"));
    return ['db_path'=>"MySQL: {$cfg['host']}/{$cfg['name']}", 'db_exists'=>true, 'tables'=>$tables];
}

function read_table($table_name) {
    if (!db_table_exists($table_name)) throw new Exception("Taula ez da existitzen: $table_name");
    $q = quote_ident($table_name);
    $cols = db_table_columns($table_name);
    $rows = db_rows("SELECT * FROM $q");
    $columns = array_map(fn($c)=>['name'=>$c['name'],'type'=>$c['type'],'pk'=>(int)$c['pk']], $cols);
    return ['name'=>$table_name,'columns'=>$columns,'rows'=>$rows,'count'=>count($rows)];
}

function update_table_row($table_name, $payload) {
    $pk_values = $payload['pk'] ?? [];
    $values = $payload['values'] ?? [];
    if (!is_array($pk_values) || !is_array($values)) throw new Exception('Datu baliogabeak');
    if (!db_table_exists($table_name)) throw new Exception("Taula ez da existitzen: $table_name");
    $q = quote_ident($table_name);
    $columns = db_table_columns($table_name);
    $column_names = array_map(fn($c)=>$c['name'], $columns);
    $pk_columns = array_map(fn($c)=>$c['name'], array_filter($columns, fn($c)=>(int)$c['pk']));
    if (!$pk_columns) throw new Exception('Taula honek ez dauka primary key-rik');
    foreach ($pk_columns as $col) if (!array_key_exists($col, $pk_values)) throw new Exception('Primary key balioak falta dira');
    $editable = array_diff($column_names, $pk_columns);
    $changes = [];
    foreach ($values as $k=>$v) if (in_array($k, $editable)) $changes[$k] = $v;
    if (!$changes) throw new Exception('Ez dago aldatzeko zutaberik');
    $set_sql = implode(', ', array_map(fn($c)=>quote_ident($c).' = ?', array_keys($changes)));
    $where_sql = implode(' AND ', array_map(fn($c)=>quote_ident($c).' = ?', $pk_columns));
    $params = array_merge(array_values($changes), array_map(fn($c)=>$pk_values[$c], $pk_columns));
    $r = db_exec("UPDATE $q SET $set_sql WHERE $where_sql", $params);
    return ['ok'=>true,'updated'=>$r['affected']];
}

function delete_table_row($table_name, $pk_values) {
    if (!is_array($pk_values)) throw new Exception('Datu baliogabeak');
    if (!db_table_exists($table_name)) throw new Exception("Taula ez da existitzen: $table_name");
    $q = quote_ident($table_name);
    $columns = db_table_columns($table_name);
    $pk_columns = array_map(fn($c)=>$c['name'], array_filter($columns, fn($c)=>(int)$c['pk']));
    if (!$pk_columns) throw new Exception('Taula honek ez dauka primary key-rik');
    foreach ($pk_columns as $col) if (!array_key_exists($col, $pk_values)) throw new Exception('Primary key balioak falta dira');
    $where_sql = implode(' AND ', array_map(fn($c)=>quote_ident($c).' = ?', $pk_columns));
    $params = array_map(fn($c)=>$pk_values[$c], $pk_columns);
    $r = db_exec("DELETE FROM $q WHERE $where_sql", $params);
    return ['ok'=>true,'deleted'=>$r['affected']];
}

// ─── Datu-osasuna (txapelketa baten egiaztapenak) ────────────────────────────
function data_health($txap_id) {
    $txap_id = (int)$txap_id;
    // «Emaitzarik ez du izango» markatutako karrerak EZ dira salatu behar (adib. bertan
    // behera utzitako etapak). Zutabea egon ezean (migrazioa exekutatu gabe), baldintza
    // hori kendu egiten da eta lehen bezala jokatzen du.
    $ez_marka = _emaitzarik_ez_bada() ? " AND COALESCE(k.Emaitzarik_Ez, 0) = 0" : "";
    $stages_no_results = db_rows(
        "SELECT k.Karrerak_ID AS id, k.Izena FROM `Karrerak` k
         WHERE k.Txapelketa_ID = ? AND k.Kategoria IS NOT NULL AND k.Kategoria <> ''
           AND NOT EXISTS (SELECT 1 FROM `KarreraSailkapena` ks WHERE ks.Karrera_ID = k.Karrerak_ID)
           $ez_marka
         ORDER BY k.Karrerak_ID", [$txap_id]);
    // Apustu kopuru zuzena txapelketaren araberakoa da (itzuliak 15, klasikak 25).
    $apustu_kop = 15;
    if (db_column_exists('Txapelketak', 'Apustu_Kopurua')) {
        $ak = db_scalar('SELECT Apustu_Kopurua FROM `Txapelketak` WHERE Txapelketa_ID = ?', [$txap_id]);
        if ($ak !== null && (int)$ak > 0) $apustu_kop = (int)$ak;
    }
    $bettors_wrong_picks = db_rows(
        "SELECT ez.Ezizen_ID AS id, ez.Ezizena, COUNT(pa.Txirrindularia_ID) AS n
         FROM `PorraEzizenak` ez
         LEFT JOIN `PorraApustuak` pa ON pa.Ezizen_ID = ez.Ezizen_ID AND pa.Txapelketa_ID = ez.Txapelketa_ID
         WHERE ez.Txapelketa_ID = ?
         GROUP BY ez.Ezizen_ID, ez.Ezizena HAVING n <> $apustu_kop
         ORDER BY n, ez.Ezizena", [$txap_id]);
    $picked_no_dortsal = db_rows(
        "SELECT DISTINCT t.Txirrindularia_ID AS id, t.Izena
         FROM `PorraApustuak` pa JOIN `Txirrindulariak` t ON t.Txirrindularia_ID = pa.Txirrindularia_ID
         WHERE pa.Txapelketa_ID = ?
           AND NOT EXISTS (SELECT 1 FROM `TxirrindulariakTxapleketanParteHartzea` h
                           WHERE h.TxapelketaID = pa.Txapelketa_ID AND h.TxirrindulariaID = pa.Txirrindularia_ID)
         ORDER BY t.Izena", [$txap_id]);
    // Nahita lotu gabe utzitakoak (Ez_Lotu=1) EZ dira salatzen (migrazioa eginda badago).
    $ez_lotu_baldintza = db_column_exists('PorraEzizenak', 'Ez_Lotu') ? " AND COALESCE(ez.Ez_Lotu,0) = 0" : "";
    $unlinked_ezizenak = db_rows(
        "SELECT ez.Ezizen_ID AS id, ez.Ezizena FROM `PorraEzizenak` ez
         WHERE ez.Txapelketa_ID = ?
           AND NOT EXISTS (SELECT 1 FROM `PorralariTaldeenEzizenak` l WHERE l.Ezizen_ID = ez.Ezizen_ID)
           $ez_lotu_baldintza
         ORDER BY ez.Ezizena", [$txap_id]);
    $has_results = ((int)db_scalar(
        "SELECT COUNT(*) FROM `KarreraSailkapena` ks JOIN `Karrerak` k ON k.Karrerak_ID = ks.Karrera_ID
         WHERE k.Txapelketa_ID = ?", [$txap_id])) > 0;
    $has_standings = ((int)db_scalar(
        "SELECT COUNT(*) FROM `TxapelketaSailkapenaPorralariak` WHERE Txapelketa_ID = ?", [$txap_id])) > 0;
    // Kategoriarik gabeko karrerak: puntuak zenbatzen dira baina sailkapen ofizialean bakarrik;
    // hemen ikusgai jartzen dira, adminak Kategoria/Ordena jar diezaien (Txapelketak atala).
    $karrerak_no_kat = db_rows(
        "SELECT k.Karrerak_ID AS id, k.Izena, k.Ordena
         FROM `Karrerak` k
         WHERE k.Txapelketa_ID = ? AND (k.Kategoria IS NULL OR k.Kategoria = '')
         ORDER BY k.Karrerak_ID", [$txap_id]);
    // Egoera-laburpena (kontrol-panela): urteroko fluxuaren argazkia toki batean.
    $ezizenak_guztira = (int)db_scalar('SELECT COUNT(*) FROM `PorraEzizenak` WHERE Txapelketa_ID = ?', [$txap_id]);
    $apustu_osoak = (int)db_scalar(
        "SELECT COUNT(*) FROM (
            SELECT ez.Ezizen_ID FROM `PorraEzizenak` ez
            LEFT JOIN `PorraApustuak` pa ON pa.Ezizen_ID = ez.Ezizen_ID AND pa.Txapelketa_ID = ez.Txapelketa_ID
            WHERE ez.Txapelketa_ID = ?
            GROUP BY ez.Ezizen_ID HAVING COUNT(pa.Txirrindularia_ID) = $apustu_kop) x", [$txap_id]);
    $amaituta = null;
    if (db_column_exists('Txapelketak', 'Amaituta'))
        $amaituta = ((int)db_scalar('SELECT COALESCE(Amaituta,0) FROM `Txapelketak` WHERE Txapelketa_ID = ?', [$txap_id])) === 1;
    $laburpena = [
        'karrerak'          => (int)db_scalar('SELECT COUNT(*) FROM `Karrerak` WHERE Txapelketa_ID = ?', [$txap_id]),
        'karrerak_kat'      => (int)db_scalar("SELECT COUNT(*) FROM `Karrerak` WHERE Txapelketa_ID = ? AND Kategoria IS NOT NULL AND Kategoria <> ''", [$txap_id]),
        'karrerak_emaitza'  => (int)db_scalar('SELECT COUNT(DISTINCT ks.Karrera_ID) FROM `KarreraSailkapena` ks JOIN `Karrerak` k ON k.Karrerak_ID = ks.Karrera_ID WHERE k.Txapelketa_ID = ?', [$txap_id]),
        'startlist'         => (int)db_scalar('SELECT COUNT(*) FROM `TxirrindulariakTxapleketanParteHartzea` WHERE TxapelketaID = ?', [$txap_id]),
        'ezizenak'          => $ezizenak_guztira,
        'apustu_kop'        => $apustu_kop,
        'apustu_osoak'      => $apustu_osoak,
        'emaitzak'          => $has_results,
        'kalkulatuta'       => $has_standings,
        'emaitza_ofizialak' => ((int)db_scalar('SELECT COUNT(*) FROM `TxapelketaEmaitzaPorralariak` WHERE Txapelketa_ID = ?', [$txap_id])) > 0,
        'amaituta'          => $amaituta,
    ];
    return [
        'laburpena' => $laburpena,
        'stages_no_results' => $stages_no_results,
        'bettors_wrong_picks' => $bettors_wrong_picks,
        'picked_no_dortsal' => $picked_no_dortsal,
        'unlinked_ezizenak' => $unlinked_ezizenak,
        'karrerak_no_kat' => $karrerak_no_kat,
        'recalc_needed' => ($has_results && !$has_standings),
    ];
}

// Izen bikoiztu susmagarriak: token-multzo berdineko izenak taldekatu (O(n), azkarra).
function possible_dups($kind) {
    if ($kind === 'porralariak') {
        $rows = db_rows('SELECT Porralaria_ID AS id, Izena FROM `Porralariak`');
    } else {
        $rows = db_rows('SELECT Txirrindularia_ID AS id, Izena FROM `Txirrindulariak`');
        $kind = 'txirrindulariak';
    }
    $buckets = [];
    foreach ($rows as $r) {
        $t = name_tokens($r['Izena']); sort($t);
        $sig = implode(' ', $t);
        if ($sig === '') continue;
        $buckets[$sig][] = ['id'=>(int)$r['id'], 'Izena'=>$r['Izena']];
    }
    $groups = [];
    foreach ($buckets as $items) if (count($items) > 1) $groups[] = $items;
    return ['kind'=>$kind, 'groups'=>$groups];
}

// ─── Porra baten apustuak (15 txirrindulariak) ───────────────────────────────
function porra_picks($ezizen_id, $txap_id) {
    return db_rows(
        "SELECT t.Txirrindularia_ID AS id, t.Izena, h.Dortsala
         FROM `PorraApustuak` pa
         JOIN `Txirrindulariak` t ON t.Txirrindularia_ID = pa.Txirrindularia_ID
         LEFT JOIN `TxirrindulariakTxapleketanParteHartzea` h
           ON h.TxapelketaID = pa.Txapelketa_ID AND h.TxirrindulariaID = pa.Txirrindularia_ID
         WHERE pa.Ezizen_ID = ? AND pa.Txapelketa_ID = ?
         ORDER BY h.Dortsala IS NULL, h.Dortsala, t.Izena", [(int)$ezizen_id, (int)$txap_id]);
}

// ─── Txapelketa baten esportazioa (babeskopia) ───────────────────────────────
function export_txapelketa($txap_id) {
    $txap_id = (int)$txap_id;
    return [
        'exported_at' => date('c'),
        'Txapelketa' => db_one('SELECT * FROM `Txapelketak` WHERE Txapelketa_ID = ?', [$txap_id]),
        'Karrerak' => db_rows('SELECT * FROM `Karrerak` WHERE Txapelketa_ID = ?', [$txap_id]),
        'PorraEzizenak' => db_rows('SELECT * FROM `PorraEzizenak` WHERE Txapelketa_ID = ?', [$txap_id]),
        'PorralariTaldeenEzizenak' => db_rows(
            'SELECT l.*, p.Izena AS Porralaria FROM `PorralariTaldeenEzizenak` l
             JOIN `PorraEzizenak` ez ON ez.Ezizen_ID = l.Ezizen_ID
             JOIN `Porralariak` p ON p.Porralaria_ID = l.Porralaria_ID
             WHERE ez.Txapelketa_ID = ?', [$txap_id]),
        'PorraApustuak' => db_rows('SELECT * FROM `PorraApustuak` WHERE Txapelketa_ID = ?', [$txap_id]),
        'KarreraSailkapena' => db_rows(
            'SELECT ks.* FROM `KarreraSailkapena` ks JOIN `Karrerak` k ON k.Karrerak_ID = ks.Karrera_ID
             WHERE k.Txapelketa_ID = ?', [$txap_id]),
        'TxirrindulariakTxapleketanParteHartzea' => db_rows(
            'SELECT * FROM `TxirrindulariakTxapleketanParteHartzea` WHERE TxapelketaID = ?', [$txap_id]),
        'TxapelketaEmaitzaPorralariak' => db_rows('SELECT * FROM `TxapelketaEmaitzaPorralariak` WHERE Txapelketa_ID = ?', [$txap_id]),
        'TxapelketaEmaitzaTxirrindulariak' => db_rows('SELECT * FROM `TxapelketaEmaitzaTxirrindulariak` WHERE Txapelketa_ID = ?', [$txap_id]),
        'Sariak' => db_table_exists('Sariak') ? db_rows('SELECT * FROM `Sariak` WHERE Txapelketa_ID = ?', [$txap_id]) : [],
    ];
}

// ─── DB babeskopia OSOA (snapshot arina) ─────────────────────────────────────
// Taula guztiak errenkada guztiekin JSON batean. Segurtasun-sarea migrazio/fusio
// arriskutsuen aurretik. Oharra: phpMyAdmin da SQL restore ofiziala; hau snapshot azkarra.
function db_full_backup() {
    $meta = db_meta();
    $tables = [];
    foreach ($meta['tables'] as $t) {
        $tables[$t] = db_rows('SELECT * FROM ' . quote_ident($t));
    }
    return [
        'exported_at' => date('c'),
        'db' => $meta['db_path'],
        'taula_kopurua' => count($tables),
        'tables' => $tables,
    ];
}

// ─── Migrazio-egoera bateratua ───────────────────────────────────────────────
// Migrazio (db/*.sql) bakoitza zer eskema-objekturi dagokion + exekutatuta dagoen.
// `db_table_exists`/`db_column_exists`-ekin egiaztatzen da; SQL testua diskotik dakar
// (fitxategiak deployatuta daude) panelean kopiatzeko.
function migration_status() {
    // [fitxategia, deskribapena, [egiaztapenak]]. Egiaztapena: 'Taula' edo 'Taula.Zutabea'.
    $defs = [
        ['ordena.sql',       'Karrerak.Ordena (etapa-zenbakia)',        ['Karrerak.Ordena']],
        ['aurre-porrak.sql', 'Txapelketak: aurre-porrak + apustu kop.', ['Txapelketak.Porra_Irekita', 'Txapelketak.Apustu_Kopurua']],
        ['profil-irudia.sql','Karrerak.Profil_Irudia (profil-lotura)',  ['Karrerak.Profil_Irudia']],
        ['emaitzarik-ez.sql', 'Karrerak.Emaitzarik_Ez («emaitzarik ez» marka)', ['Karrerak.Emaitzarik_Ez']],
        ['amaituta.sql',     'Txapelketak.Amaituta (txapelketa itxi)',  ['Txapelketak.Amaituta']],
    ];
    $out = [];
    foreach ($defs as [$fitx, $azalpena, $checks]) {
        $falta = [];
        foreach ($checks as $c) {
            if (strpos($c, '.') !== false) {
                [$tbl, $col] = explode('.', $c, 2);
                if (!db_column_exists($tbl, $col)) $falta[] = $c;
            } else {
                if (!db_table_exists($c)) $falta[] = $c;
            }
        }
        $path = __DIR__ . '/../db/' . $fitx;
        $out[] = [
            'fitxategia' => 'db/' . $fitx,
            'azalpena'   => $azalpena,
            'eginda'     => empty($falta),
            'falta'      => $falta,
            'sql'        => is_file($path) ? file_get_contents($path) : null,
        ];
    }
    return ['migrazioak' => $out];
}

// ─── Webguneko urte-orrien egoera (diagnostikoa) ─────────────────────────────
// Txapelketa bakoitza webgune publikoan agertzeko prest dagoen egiaztatzen du.
// `txapelketa-orria.js`-k id-a AUTO-LOTZEN du kirol-izena + urtea bat eginez, beraz
// hemengo egiaztapenak logika bera darabil: kirola izenetik antzeman + urte-orriaren
// stub-a existitzen den + karrerarik baduen.
function webgune_orriak_egoera() {
    // Kirol-izen kanonikoak (js/txapelketak.js `kirolak`-ekin BAT ETORRI behar dute).
    $kirolak = [
        'tour'     => 'Tour de France',
        'giro'     => "Giro d'Italia",
        'vuelta'   => 'Vuelta a España',
        'klasikak' => 'Klasikak',
    ];
    $rows = db_rows(
        'SELECT t.Txapelketa_ID, t.Izena, t.Urtea,
                (SELECT COUNT(*) FROM `Karrerak` k WHERE k.Txapelketa_ID = t.Txapelketa_ID) AS karrerak,
                (SELECT COUNT(*) FROM `KarreraSailkapena` ks JOIN `Karrerak` k ON k.Karrerak_ID = ks.Karrera_ID WHERE k.Txapelketa_ID = t.Txapelketa_ID) AS emaitzak
         FROM `Txapelketak` t ORDER BY t.Urtea DESC, t.Izena');
    $out = [];
    foreach ($rows as $r) {
        $izena = (string)$r['Izena'];
        $urtea = (string)$r['Urtea'];
        // Kirola antzeman: izen kanonikoa aurrizki gisa (case-insensitive).
        $kirola = null;
        foreach ($kirolak as $k => $kanon) {
            if (stripos($izena, $kanon) === 0) { $kirola = $k; break; }
        }
        $karrerak = (int)$r['karrerak'];
        $url = $kirola ? "/$kirola/$urtea/" : null;
        $stub_bada = $kirola ? is_file(__DIR__ . "/../$kirola/$urtea/index.html") : false;
        if ($kirola === null) { $egoera = 'kirola-ez'; $mezua = 'Izena ez dator bat kirol-izen kanonikoarekin (Tour de France / Giro d\'Italia / Vuelta a España / Klasikak) → auto-lotura ez da funtzionatuko. Izena zuzendu.'; }
        elseif (!$stub_bada)  { $egoera = 'stub-ez';  $mezua = "Urte-orria falta: kopiatu $kirola/$urtea/index.html (stub) eta gehitu txapelketak.js sarrera."; }
        elseif ($karrerak === 0) { $egoera = 'karrera-ez'; $mezua = 'Karrerarik ez oraindik: inportatu karrerak agertzeko.'; }
        else { $egoera = 'ok'; $mezua = 'Webgunean ikusgai (auto-lotuta).'; }
        $out[] = [
            'txapelketa_id' => (int)$r['Txapelketa_ID'],
            'izena' => $izena, 'urtea' => (int)$urtea,
            'kirola' => $kirola, 'url' => $url,
            'karrerak' => $karrerak, 'emaitzak' => (int)$r['emaitzak'],
            'stub_bada' => $stub_bada, 'egoera' => $egoera, 'mezua' => $mezua,
        ];
    }
    return ['orriak' => $out];
}

// ─── Zuzenketa-proposamenak (testu-fitxategia, DB gabe) ──────────────────────
// api/proposal.php-k admin/zuzenketak.log-en eransten ditu; hemen irakurtzen dira.
function _proposals_file() { return __DIR__ . '/zuzenketak.log'; }

function read_proposals() {
    $f = _proposals_file();
    if (!is_file($f)) return [];
    $content = file_get_contents($f);
    if ($content === false || trim($content) === '') return [];
    $blocks = preg_split('/\n=====\n/', $content);
    $out = [];
    foreach ($blocks as $i => $block) {
        $block = trim($block, "\n");
        if ($block === '') continue;
        $lines = explode("\n", $block);
        $head = array_shift($lines);
        $data = ''; $mota = 'bestelakoa'; $ip = '';
        if (preg_match('/^\[(.*?)\]\s*MOTA:\s*(.*?)\s*\|\s*IP:\s*(.*)$/', $head, $m)) {
            $data = $m[1]; $mota = $m[2]; $ip = $m[3];
            $testua = implode("\n", $lines);
        } else {
            $testua = $block;
        }
        $out[] = ['idx' => $i, 'data' => $data, 'mota' => $mota, 'ip' => $ip, 'testua' => $testua];
    }
    return array_reverse($out); // berrienak lehenengo
}

function count_proposals() { return count(read_proposals()); }

// ─── Log-fitxategien laguntzaile komunak (zuzenketak + aurre-porrak) ─────────
function _log_clear($f) {
    if (is_file($f)) file_put_contents($f, '', LOCK_EX);
    return ['ok' => true];
}

function _log_delete($f, $idx) {
    if (!is_file($f)) return ['ok' => true];
    $content = file_get_contents($f);
    if ($content === false) return ['ok' => false, 'reason' => 'Ezin irakurri'];
    $blocks = preg_split('/\n=====\n/', $content);
    $idx = (int)$idx;
    if (!array_key_exists($idx, $blocks)) return ['ok' => false, 'reason' => 'Aurkitu ez'];
    unset($blocks[$idx]);
    $rebuilt = '';
    foreach ($blocks as $b) { $b = trim($b, "\n"); if ($b === '') continue; $rebuilt .= $b . "\n=====\n"; }
    file_put_contents($f, $rebuilt, LOCK_EX);
    return ['ok' => true];
}

function clear_proposals() { return _log_clear(_proposals_file()); }

function delete_proposal($idx) { return _log_delete(_proposals_file(), $idx); }

/**
 * Zutabe bat existitzen den (migrazioa exekutatu gabe egon daiteke).
 * Emaitza cachean gordetzen da eskaera bakoitzeko.
 */
function db_column_exists($table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        $cache[$key] = false;
        foreach (db_table_columns($table) as $c) {
            if (strcasecmp($c['name'], $column) === 0) { $cache[$key] = true; break; }
        }
    }
    return $cache[$key];
}

/** `Karrerak.Emaitzarik_Ez` badago (db/emaitzarik-ez.sql). */
function _emaitzarik_ez_bada() {
    return db_column_exists('Karrerak', 'Emaitzarik_Ez');
}

// ─── Aurre-porrak (testu-fitxategia, DB gabe) ───────────────────────────────
// api/porra.php-k admin/aurre-porrak.log-en eransten ditu; hemen irakurtzen dira.
// Blokearen formatua (ikus api/porra.php):
//   [data] TXAP: 17 | Tour De France 2026 | EZIZENA: xxx | IP: 1.2.3.4
//   HARREMANA: ...            (aukerakoa)
//   DORTSALAK: 1,21,31,...
//     1  Tadej Pogačar        (irakurtzeko soilik)
//   OHARRAK: ...              (aukerakoa)
function _aurre_porrak_file() { return __DIR__ . '/aurre-porrak.log'; }

function read_aurre_porrak() {
    $f = _aurre_porrak_file();
    if (!is_file($f)) return [];
    $content = file_get_contents($f);
    if ($content === false || trim($content) === '') return [];
    $blocks = preg_split('/\n=====\n/', $content);
    $out = [];
    foreach ($blocks as $i => $block) {
        $block = trim($block, "\n");
        if ($block === '') continue;

        $lines = explode("\n", $block);
        $head = $lines[0];
        $data = ''; $txap_id = 0; $txap_izena = ''; $ezizena = ''; $ip = '';
        if (preg_match('/^\[(.*?)\]\s*TXAP:\s*(\d+)\s*\|\s*(.*?)\s*\|\s*EZIZENA:\s*(.*?)\s*\|\s*IP:\s*(\S*)$/', $head, $m)) {
            $data = $m[1]; $txap_id = (int)$m[2]; $txap_izena = $m[3]; $ezizena = $m[4]; $ip = $m[5];
        }

        $harremana = ''; $oharrak = ''; $dortsalak = []; $txirrindulariak = [];
        foreach ($lines as $ln) {
            if (strpos($ln, 'HARREMANA: ') === 0)      $harremana = substr($ln, 11);
            elseif (strpos($ln, 'OHARRAK: ') === 0)    $oharrak = substr($ln, 9);
            elseif (strpos($ln, 'DORTSALAK: ') === 0 && !$dortsalak) {
                foreach (explode(',', substr($ln, 11)) as $d) {
                    $d = trim($d);
                    if ($d !== '' && ctype_digit($d)) $dortsalak[] = (int)$d;
                }
            } elseif (preg_match('/^\s+(\d+)\s\s+(.+)$/', $ln, $mm)) {
                $txirrindulariak[] = ['dortsala' => (int)$mm[1], 'izena' => $mm[2]];
            }
        }

        $out[] = [
            'idx' => $i, 'data' => $data, 'txap_id' => $txap_id, 'txap_izena' => $txap_izena,
            'ezizena' => $ezizena, 'ip' => $ip, 'harremana' => $harremana, 'oharrak' => $oharrak,
            'dortsalak' => $dortsalak, 'txirrindulariak' => $txirrindulariak,
        ];
    }
    return array_reverse($out); // berrienak lehenengo
}

function count_aurre_porrak() { return count(read_aurre_porrak()); }

function clear_aurre_porrak() { return _log_clear(_aurre_porrak_file()); }

function delete_aurre_porra($idx) { return _log_delete(_aurre_porrak_file(), $idx); }

// ─── Fitxategi-kudeatzailea (data/ azpian: PDF, irudiak, etapa-profilak…) ────
// SEGURTASUNA: data/ publikoki zerbitzatzen da eta ez dago erroko .htaccess-ik.
//  1) Luzapen-zerrenda zuria (irudiak + PDF soilik).
//  2) data/.htaccess-ek PHP/script exekuzioa itzaltzen du (_ensure_data_guard).
//  3) Bide-konfinamendua: realpath-ez data/ azpian dagoela egiaztatu (../ galarazita).

const FILES_ALLOWED_EXT = ['jpg','jpeg','png','gif','webp','pdf'];

function _data_base() {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $real = realpath($dir);
    if ($real === false) throw new Exception('data/ karpeta ez da existitzen eta ezin da sortu');
    return $real;
}

/** $child $base barruan (edo bera) den, PHP 7.4-rekin ere (str_starts_with gabe). */
function _within_base($child, $base) {
    if ($child === $base) return true;
    return strncmp($child, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) === 0;
}

/** Bide erlatiboa garbitu: aurreko/atzeko barrak kendu, byte nulua eta '..' ukatu. */
function _clean_rel($rel) {
    $rel = str_replace('\\', '/', (string)$rel);
    if (strpos($rel, "\0") !== false) throw new Exception('Bide baliogabea');
    $rel = trim($rel, '/');
    if ($rel === '') return '';
    $parts = [];
    foreach (explode('/', $rel) as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') throw new Exception('Bide baliogabea (..)');
        $parts[] = $p;
    }
    return implode('/', $parts);
}

/** Existitzen den KARPETA bat data/ azpian ebatzi (dir='' → data/ erroa). */
function _safe_data_dir($rel) {
    $base = _data_base();
    $rel = _clean_rel($rel);
    $abs = realpath($base . ($rel === '' ? '' : '/' . $rel));
    if ($abs === false || !is_dir($abs) || !_within_base($abs, $base)) throw new Exception('Karpeta baliogabea');
    return $abs;
}

/** Existitzen den FITXATEGI/karpeta bat data/ azpian ebatzi. */
function _safe_data_path($rel) {
    $base = _data_base();
    $rel = _clean_rel($rel);
    if ($rel === '') throw new Exception('Bidea behar da');
    $abs = realpath($base . '/' . $rel);
    if ($abs === false || !_within_base($abs, $base)) throw new Exception('Bide baliogabea');
    return $abs;
}

/** Fitxategi-izena garbitu: barrak/kontrol-karaktereak kendu, hasierako puntua debekatu.
 *  Zuriuneak eta karaktere bereziak MANTENTZEN dira (konfigurazioak izen zehatzak ditu). */
function _sanitize_filename($name) {
    $name = basename(str_replace('\\', '/', (string)$name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);   // kontrol-karaktereak
    $name = str_replace(['/', '\\'], '', $name);
    $name = ltrim($name, '.');                                // .htaccess eta ezkutukoak ez
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') throw new Exception('Izen baliogabea');
    if (strlen($name) > 200) throw new Exception('Izena luzeegia');
    return $name;
}

/** Karpeta-izena garbitu: soilik letra, zenbaki, zuriune, '-', '_'. */
function _sanitize_dirname($name) {
    $name = trim((string)$name);
    if (!preg_match('/^[\p{L}\p{N} _-]{1,80}$/u', $name)) {
        throw new Exception('Karpeta-izen baliogabea (letrak, zenbakiak, zuriuneak, - eta _ soilik)');
    }
    return $name;
}

function _files_ext($name) {
    $dot = strrpos($name, '.');
    return $dot === false ? '' : strtolower(substr($name, $dot + 1));
}

function _files_check_ext($name) {
    if (!in_array(_files_ext($name), FILES_ALLOWED_EXT, true)) {
        throw new Exception('Luzapen debekatua: ' . _files_ext($name) . ' (onartuak: ' . implode(', ', FILES_ALLOWED_EXT) . ')');
    }
}

/** data/.htaccess bermatu: PHP/script exekuzioa galarazi. Git-etik kanpo dagoenez,
 *  kodeak sortu/mantendu behar du. Edozein fitxategi-eragiketan deitzen da.
 *
 *  ⚠️ EZ `php_flag`: zerbitzaria PHP-FPM da eta `php_flag` `.htaccess`-ean 500 emango luke
 *  karpeta osorako. Babes eramangarria `<FilesMatch> Require all denied` da (script-etarako
 *  sarbidea 403), admin/.htaccess-ek darabilena bezala (Apache 2.4).
 *
 *  Auto-sendatzailea: edukia falta bada EDO desberdina bada berridazten du, zerbitzarian
 *  gera litekeen bertsio zahar/hautsi bat konpontzeko. */
function _ensure_data_guard() {
    $f = _data_base() . '/.htaccess';
    $rules = "# Aramaixo Porra — data/ babesa (kodeak sortua). Fitxategi estatikoak SOILIK.\n"
        . "# EZ php_flag (PHP-FPM-rekin 500 emango luke). FilesMatch-ek script-ak ukatzen ditu.\n"
        . "Options -Indexes\n"
        . "<FilesMatch \"\\.(php[0-9]?|phtml|pht|phps|cgi|pl|py|sh|htaccess)$\">\n"
        . "    Require all denied\n"
        . "</FilesMatch>\n";
    if (is_file($f) && file_get_contents($f) === $rules) return;
    @file_put_contents($f, $rules);
}

/** Publikoko URLa eraiki, segmentu bakoitza kodetuta (zuriuneak e.a.). */
function _files_url($rel) {
    $rel = _clean_rel($rel);
    if ($rel === '') return '/data/';
    return '/data/' . implode('/', array_map('rawurlencode', explode('/', $rel)));
}

function files_list($dir = '') {
    _ensure_data_guard();
    $base = _data_base();
    $abs = _safe_data_dir($dir);
    $rel = _clean_rel($dir);

    $dirs = []; $files = [];
    foreach (scandir($abs) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if ($entry[0] === '.') continue;                 // ezkutukoak (.htaccess barne)
        $full = $abs . '/' . $entry;
        $childRel = ($rel === '' ? '' : $rel . '/') . $entry;
        if (is_dir($full)) {
            $dirs[] = ['name' => $entry, 'path' => $childRel];
        } else {
            $files[] = [
                'name'  => $entry,
                'path'  => $childRel,
                'size'  => filesize($full),
                'ext'   => _files_ext($entry),
                'url'   => _files_url($childRel),
                'mtime' => filemtime($full),
            ];
        }
    }
    usort($dirs, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    $parent = null;
    if ($rel !== '') { $parent = strpos($rel, '/') === false ? '' : substr($rel, 0, strrpos($rel, '/')); }

    return ['path' => $rel, 'parent' => $parent, 'dirs' => $dirs, 'files' => $files];
}

function files_upload($dir = '') {
    _ensure_data_guard();
    $abs = _safe_data_dir($dir);
    $rel = _clean_rel($dir);
    $overwrite = !empty($_GET['overwrite']) || !empty($_POST['overwrite']);

    if (empty($_FILES)) {
        $max = ini_get('post_max_size');
        throw new Exception("Ez da fitxategirik jaso. Baliteke zerbitzariaren muga gainditzea (post_max_size=$max). Plesk-en igoera-muga handitu.");
    }

    // 'file' eremua: bakarra edo anitza (file[]).
    $saved = []; $errors = [];
    $f = $_FILES['file'] ?? null;
    if ($f === null) throw new Exception("'file' eremua falta da");
    $names = is_array($f['name']) ? $f['name'] : [$f['name']];
    $tmps  = is_array($f['tmp_name']) ? $f['tmp_name'] : [$f['tmp_name']];
    $errs  = is_array($f['error']) ? $f['error'] : [$f['error']];

    $ERR = [
        UPLOAD_ERR_INI_SIZE   => 'Handiegia (upload_max_filesize). Plesk-en muga handitu.',
        UPLOAD_ERR_FORM_SIZE  => 'Handiegia (formularioaren muga).',
        UPLOAD_ERR_PARTIAL    => 'Igoera osatu gabe geratu da.',
        UPLOAD_ERR_NO_FILE    => 'Fitxategirik ez.',
        UPLOAD_ERR_NO_TMP_DIR => 'Zerbitzariak ez du aldi baterako karpetarik.',
        UPLOAD_ERR_CANT_WRITE  => 'Ezin idatzi diskoan.',
        UPLOAD_ERR_EXTENSION  => 'PHP luzapen batek gelditu du.',
    ];

    for ($i = 0; $i < count($names); $i++) {
        $orig = (string)$names[$i];
        try {
            if ($errs[$i] !== UPLOAD_ERR_OK) throw new Exception($ERR[$errs[$i]] ?? ('Errorea (' . $errs[$i] . ')'));
            $name = _sanitize_filename($orig);
            _files_check_ext($name);
            $dest = $abs . '/' . $name;
            if (is_file($dest) && !$overwrite) throw new Exception('Existitzen da jada (gainidazteko markatu)');
            if (!is_uploaded_file($tmps[$i])) throw new Exception('Igoera baliogabea');
            if (!move_uploaded_file($tmps[$i], $dest)) throw new Exception('Ezin gorde');
            @chmod($dest, 0644);
            $childRel = ($rel === '' ? '' : $rel . '/') . $name;
            $saved[] = ['name' => $name, 'url' => _files_url($childRel)];
        } catch (Exception $e) {
            $errors[] = ['name' => $orig, 'reason' => $e->getMessage()];
        }
    }
    return ['saved' => $saved, 'errors' => $errors];
}

function files_delete($path) {
    _ensure_data_guard();
    $abs = _safe_data_path($path);
    if (basename($abs) === '.htaccess') throw new Exception('Babes-fitxategia ezin da ezabatu');
    if (is_dir($abs)) {
        $rest = array_diff(scandir($abs), ['.', '..']);
        if ($rest) throw new Exception('Karpeta ez dago hutsik');
        if (!@rmdir($abs)) throw new Exception('Ezin ezabatu karpeta');
    } else {
        if (!@unlink($abs)) throw new Exception('Ezin ezabatu');
    }
    return ['ok' => true];
}

function files_rename($path, $newname) {
    _ensure_data_guard();
    $abs = _safe_data_path($path);
    if (basename($abs) === '.htaccess') throw new Exception('Babes-fitxategia ezin da berrizendatu');
    $new = is_dir($abs) ? _sanitize_dirname($newname) : _sanitize_filename($newname);
    if (!is_dir($abs)) {
        // Luzapena mantendu edo baliozkoa izan behar du
        _files_check_ext($new);
    }
    $dest = dirname($abs) . '/' . $new;
    if (file_exists($dest)) throw new Exception('Izen hori existitzen da jada');
    if (!@rename($abs, $dest)) throw new Exception('Ezin berrizendatu');
    return ['ok' => true, 'name' => $new];
}

function files_mkdir($dir, $name) {
    _ensure_data_guard();
    $abs = _safe_data_dir($dir);
    $name = _sanitize_dirname($name);
    $dest = $abs . '/' . $name;
    if (file_exists($dest)) throw new Exception('Karpeta hori existitzen da jada');
    if (!@mkdir($dest, 0755)) throw new Exception('Ezin sortu karpeta');
    return ['ok' => true, 'name' => $name];
}

// ─── Ezarpenak: fitxategi-moten karpeta-mapa + tresna publikoen ikusgaitasuna ──
// admin/ezarpenak.json (git-etik kanpo). Webgune publikoak api/ezarpenak.php bidez
// irakurtzen du: aldaketa bat gordetzean, gunea berehala moldatzen da.
// LEHENETSIAK api/ezarpenak.php-koekin bat etorri behar dute.
//
// ⚠️ Fitxategi BAKARRA bi ezarpen-mota gordetzeko erabiltzen da (`karpetak` + `tresnak`).
// Idazketa bakoitzak fitxategi OSOA berridazten du, beraz BIEK _ezarpenak_raw()-etik
// abiatu eta beren zatia bakarrik aldatu behar dute — bestela batak bestearena ezabatuko
// luke (adib. karpetak gordetzean tresnen ikusgaitasuna galtzea).

const EZARPEN_MOTAK = ['arauak' => 'arauak', 'dortsalak' => 'dortsalak',
                       'porrak' => 'porrak', 'profilak' => 'profilak'];

// Puntu-eskalak kategoriaka (admin → inportazioa). KAT_AUKERAK-ekin bat (hutsa kenduta).
// «Monumentua» = arauetako «3» kategoria. «6» arauetan (2024/2025); 2026an kendua baina
// erabilgarri mantentzen da.
const KATEGORIA_ZERRENDA = ['Etapa', 'Monumentua', 'Proseries', '4', '5', '6', 'Berezia'];
const ESKALA_LEHENETSIA_ETAPA = [31, 23, 17, 13, 9, 7];

// GLOBAL lehenetsiak = UNEKO araudia (klasikoak 2026 + Etapa itzuliak). Admin-etik gainidatz
// daitezke; JSON-en balioa lehenetsia baino lehenago doa.
const KATEGORIA_ESKALA_LEHENETSIAK = [
    'Etapa'      => [31, 23, 17, 13, 9, 7],
    'Monumentua' => [800, 640, 520, 440, 360, 280, 240, 200, 160, 135, 110, 95, 85, 65, 55],
    '4'          => [500, 400, 325, 275, 225, 175, 150, 125, 100, 85, 70, 60, 50, 40, 35],
    '5'          => [400, 320, 260, 220, 180, 140, 120, 100, 80, 68, 56, 48, 40, 32, 28],
    '6'          => [300, 250, 215, 175, 120, 115, 95, 75, 60, 50, 40, 35, 30, 25, 20],
    'Berezia'    => [900, 715, 600, 490, 410, 340, 265, 225, 190, 150, 130, 105, 90, 75, 60],
    'Proseries'  => [250, 170, 140, 120, 100, 80, 70, 60, 50, 40, 30, 20, 10, 10, 10],
];

// LEGACY lehenetsiak: global-arekiko aldaketak dituzten urteak (kategoria diferentea soilik).
//  · 2024: Berezia txikiagoa; Proseries-ik ez zen (irrelebantea, kat-6 300,250… global bezala).
//  · 2025: Proseries diferentea (2026an 250,170… bihurtu zen).
const KATEGORIA_ESKALA_ZAHARRAK = [
    '2024' => ['Berezia'   => [600, 475, 400, 325, 275, 225, 175, 150, 125, 100, 85, 70, 60, 50, 40]],
    '2025' => ['Proseries' => [200, 150, 125, 100, 85, 70, 60, 50, 40, 35, 30, 25, 20, 15, 10]],
];

function _ezarpenak_file() { return __DIR__ . '/ezarpenak.json'; }

/** Uneko ezarpenak.json OSOA, dekodifikatuta (edo array hutsa, ez badago/hondatuta badago). */
function _ezarpenak_raw() {
    $f = _ezarpenak_file();
    if (!is_file($f)) return [];
    $data = json_decode((string)@file_get_contents($f), true);
    return is_array($data) ? $data : [];
}

function _ezarpenak_gorde($data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (@file_put_contents(_ezarpenak_file(), $json, LOCK_EX) === false) {
        throw new Exception('Ezin gorde ezarpenak');
    }
}

function read_ezarpenak() {
    $data = _ezarpenak_raw();

    $map = EZARPEN_MOTAK;
    foreach (EZARPEN_MOTAK as $mota => $lehenetsia) {
        $v = $data['karpetak'][$mota] ?? null;
        if (is_string($v) && preg_match('/^[\p{L}\p{N} _-]{1,80}$/u', $v)) $map[$mota] = $v;
    }
    // Karpeta bakoitza existitzen den jakinarazi (adminari abisatzeko)
    $base = _data_base();
    $egoera = [];
    foreach ($map as $mota => $karpeta) $egoera[$mota] = is_dir($base . '/' . $karpeta);

    // Tresnak: katalogo osoa + ikusgaitasuna + oharra (adminak ikusten du, ez publikoak).
    $tresnaEz = is_array($data['tresnak'] ?? null) ? $data['tresnak'] : [];
    $tresnak = array_map(function ($t) use ($tresnaEz) {
        $ez = is_array($tresnaEz[$t['id']] ?? null) ? $tresnaEz[$t['id']] : [];
        return [
            'id'       => $t['id'],
            'ikonoa'   => $t['ikonoa'],
            'izena'    => $t['izena'],
            'azalpena' => $t['azalpena'],
            'bidea'    => $t['bidea'],
            'ikusgai'  => !array_key_exists('ikusgai', $ez) || $ez['ikusgai'] !== false,
            'oharra'   => is_string($ez['oharra'] ?? null) ? $ez['oharra'] : '',
        ];
    }, TRESNA_KATALOGOA);

    // Kategoriako puntu-eskala GLOBALAK (kategoria → int array). JSON-en balioa badago hura,
    // bestela KATEGORIA_ESKALA_LEHENETSIAK (uneko araudia, kodean).
    $eskalaRaw = is_array($data['kategoria_eskalak'] ?? null) ? $data['kategoria_eskalak'] : [];
    $eskalak = [];
    foreach (KATEGORIA_ZERRENDA as $kat) {
        $s = $eskalaRaw[$kat] ?? null;
        if (is_string($s) && trim($s) !== '') {
            $eskalak[$kat] = _eskala_parse($s);
        } else {
            $eskalak[$kat] = KATEGORIA_ESKALA_LEHENETSIAK[$kat] ?? [];
        }
    }

    // Eskala ZAHARRAK (legacy): urteka override-ak. Kode-lehenetsiak (KATEGORIA_ESKALA_ZAHARRAK)
    // + JSON-eko override-ak (JSON-ek irabazten du kategoria bakoitzeko). Global-a gainidazten
    // dute urte HORRETAKO karreretan; iraganeko emaitza gordeak EZ dira aldatzen.
    $zaharRaw = is_array($data['kategoria_eskalak_zaharrak'] ?? null) ? $data['kategoria_eskalak_zaharrak'] : [];
    $eskalakZaharrak = [];
    $urteGuztiak = array_unique(array_merge(array_keys(KATEGORIA_ESKALA_ZAHARRAK), array_keys($zaharRaw)));
    foreach ($urteGuztiak as $urte) {
        if (!preg_match('/^\d{4}$/', (string)$urte)) continue;
        $m = [];
        foreach ((KATEGORIA_ESKALA_ZAHARRAK[$urte] ?? []) as $kat => $arr) {
            if (in_array($kat, KATEGORIA_ZERRENDA, true)) $m[$kat] = $arr;   // kode-lehenetsiak
        }
        $jk = $zaharRaw[$urte] ?? [];
        if (is_array($jk)) foreach (KATEGORIA_ZERRENDA as $kat) {            // JSON-ek gainidazten du
            $s = $jk[$kat] ?? null;
            if (is_string($s) && trim($s) !== '') $m[$kat] = _eskala_parse($s);
        }
        if ($m) $eskalakZaharrak[(string)$urte] = $m;
    }

    return ['karpetak' => $map, 'lehenetsiak' => EZARPEN_MOTAK, 'badaude' => $egoera,
            'tresnak' => $tresnak, 'eskalak' => $eskalak, 'eskalak_zaharrak' => $eskalakZaharrak];
}

/** "31,23,17" → [31,23,17]. Zenbaki osoak, hutsak baztertuta. */
function _eskala_parse($s) {
    return array_map('intval', array_filter(array_map('trim', explode(',', (string)$s)), fn($x) => $x !== '' && is_numeric($x)));
}
/** Eskala-map bat garbitu (kategoria → "31,23,…" string), KATEGORIA_ZERRENDA-koak soilik. */
function _eskala_map_garbitu($in) {
    $map = [];
    if (!is_array($in)) return $map;
    foreach (KATEGORIA_ZERRENDA as $kat) {
        $v = $in[$kat] ?? null;
        if (is_array($v)) $v = implode(',', array_map('intval', $v));
        $v = trim((string)$v);
        if ($v !== '') { $v = implode(',', _eskala_parse($v)); }
        if ($v !== '') $map[$kat] = $v;
    }
    return $map;
}

/** Kategoriako puntu-eskalak gorde: globalak + legacy (urteka). */
function save_eskala_ezarpenak($payload) {
    if (!is_array($payload['eskalak'] ?? null)) throw new Exception('Datu baliogabeak');
    $map = _eskala_map_garbitu($payload['eskalak']);

    $zmap = [];
    $zin = $payload['eskalak_zaharrak'] ?? [];
    if (is_array($zin)) foreach ($zin as $urte => $kats) {
        if (!preg_match('/^\d{4}$/', (string)$urte)) continue;
        $km = _eskala_map_garbitu($kats);
        if ($km) $zmap[(string)$urte] = $km;
    }

    $data = _ezarpenak_raw();
    $data['kategoria_eskalak'] = $map;
    $data['kategoria_eskalak_zaharrak'] = $zmap;
    _ezarpenak_gorde($data);
    return read_ezarpenak();
}

function save_ezarpenak($payload) {
    $in = $payload['karpetak'] ?? [];
    if (!is_array($in)) throw new Exception('Datu baliogabeak');
    $map = [];
    foreach (EZARPEN_MOTAK as $mota => $lehenetsia) {
        $v = trim((string)($in[$mota] ?? $lehenetsia));
        if ($v === '') $v = $lehenetsia;
        $map[$mota] = _sanitize_dirname($v);   // bide-zeharkatzea galarazi
    }
    $data = _ezarpenak_raw();
    $data['karpetak'] = $map;   // 'tresnak' badagoena ukitu gabe
    _ezarpenak_gorde($data);
    return read_ezarpenak();
}

/** Tresna publikoen ikusgaitasuna + oharra gorde. Katalogoko id-ak EZ direnak baztertu. */
function save_tresna_ezarpenak($payload) {
    $in = $payload['tresnak'] ?? [];
    if (!is_array($in)) throw new Exception('Datu baliogabeak');
    $baliozkoak = array_column(TRESNA_KATALOGOA, 'id');

    $tresnak = [];
    foreach ($in as $id => $ez) {
        if (!in_array($id, $baliozkoak, true) || !is_array($ez)) continue;
        $oharra = trim((string)($ez['oharra'] ?? ''));
        if (mb_strlen($oharra) > 200) $oharra = mb_substr($oharra, 0, 200);
        $tresnak[$id] = ['ikusgai' => ($ez['ikusgai'] ?? true) !== false, 'oharra' => $oharra];
    }

    $data = _ezarpenak_raw();
    $data['tresnak'] = $tresnak;   // 'karpetak' badagoena ukitu gabe
    _ezarpenak_gorde($data);
    return read_ezarpenak();
}
