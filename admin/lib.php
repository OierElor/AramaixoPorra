<?php
// ─── Aramaixo Porra — logika (DB-rekiko independentea probatzeko) ──────────
require_once __DIR__ . '/db.php';

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
        $r = db_exec('INSERT INTO `Karrerak` (Txapelketa_ID, Izena, Urtea) VALUES (?, ?, ?)', [$norm['Txapelketa_ID'],$norm['Izena'],$norm['Urtea']]);
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

// ─── Tour Excel inportatzailea (apustuak eta dortsalak) ──────────────────────
// Frontend-ak "Porra denak" sareta parseatuta bidaltzen du:
//   { txapelketa_id, bettors: [ { izena, riders: [ {dortsala, izena}, ... ] }, ... ] }
// Idempotentea da: berriz inportatuz gero ez du bikoizten (INSERT IGNORE / upsert).

function _tour_txap_id($payload) {
    $tid = $payload['txapelketa_id'] ?? null;
    if ($tid === null || $tid === '') throw new Exception('Txapelketa bat hautatu behar da');
    return (int)$tid;
}

function tour_porrak_preview($payload) {
    $bettors = $payload['bettors'] ?? [];
    $new_porralariak = []; $new_riders = []; $seen_r = [];
    $bet_count = 0; $warnings = [];
    foreach ($bettors as $b) {
        $izena = trim((string)($b['izena'] ?? ''));
        if ($izena === '') continue;
        if (find_porralaria_id($izena) === null && !in_array($izena, $new_porralariak, true)) $new_porralariak[] = $izena;
        $riders = $b['riders'] ?? [];
        if (count($riders) !== 15) $warnings[] = "$izena: ".count($riders)." txirrindulari (15 espero)";
        foreach ($riders as $r) {
            $rn = trim((string)($r['izena'] ?? ''));
            if ($rn === '') continue;
            $bet_count++;
            if (in_array($rn, $seen_r, true)) continue;
            $seen_r[] = $rn;
            if (find_txirrindularia_id($rn) === null) {
                $new_riders[] = ['izena'=>$rn, 'suggestions'=>find_fuzzy_matches(strip_country($rn), 60)];
            }
        }
    }
    return [
        'bettors'=>count($bettors),
        'new_porralariak'=>$new_porralariak,
        'new_riders'=>$new_riders,
        'bet_count'=>$bet_count,
        'warnings'=>$warnings,
    ];
}

function tour_porrak_import($payload) {
    $txap = _tour_txap_id($payload);
    $bettors = $payload['bettors'] ?? [];
    $merge_map = $payload['merge_map'] ?? [];   // txirrindulari-izena → Txirrindularia_ID (edo null=berri)
    $created_p = 0; $created_e = 0; $bets = 0; $dortsalak = 0; $errors = [];
    foreach ($bettors as $b) {
        $izena = trim((string)($b['izena'] ?? ''));
        if ($izena === '') continue;
        try {
            $pid_before = find_porralaria_id($izena);
            $pid = ensure_porralaria_id($izena);
            if ($pid_before === null) $created_p++;
            $eid_before = find_ezizen_id($txap, $izena);
            $eid = ensure_ezizen_id($txap, $izena);
            if ($eid_before === null) $created_e++;
            db_exec('INSERT IGNORE INTO `PorralariTaldeenEzizenak` (Ezizen_ID, Porralaria_ID) VALUES (?, ?)', [$eid, $pid]);
            foreach (($b['riders'] ?? []) as $r) {
                $rn = trim((string)($r['izena'] ?? ''));
                if ($rn === '') continue;
                if (array_key_exists($rn, $merge_map) && $merge_map[$rn] !== null) $rid = (int)$merge_map[$rn];
                else $rid = ensure_txirrindularia_id($rn);
                $res = db_exec('INSERT IGNORE INTO `PorraApustuak` (Txapelketa_ID, Ezizen_ID, Txirrindularia_ID) VALUES (?, ?, ?)', [$txap, $eid, $rid]);
                if (($res['affected'] ?? 0) > 0) $bets++;
                $dor = to_int($r['dortsala'] ?? null);
                if ($dor !== null) { upsert_dortsala($txap, $rid, $dor); $dortsalak++; }
            }
        } catch (Exception $e) {
            $errors[] = ['izena'=>$izena, 'reason'=>$e->getMessage()];
        }
    }
    return ['porralariak_berri'=>$created_p, 'ezizenak_berri'=>$created_e, 'apustuak'=>$bets, 'dortsalak'=>$dortsalak, 'errors'=>$errors];
}

// Dortsalak: { txapelketa_id, riders: [ {dortsala, izena}, ... ] }
function tour_dortsalak_preview($payload) {
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

function tour_dortsalak_import($payload) {
    $txap = _tour_txap_id($payload);
    $riders = $payload['riders'] ?? [];
    $merge_map = $payload['merge_map'] ?? [];
    $set = 0; $created = 0; $errors = [];
    foreach ($riders as $r) {
        $rn = trim((string)($r['izena'] ?? ''));
        $dor = to_int($r['dortsala'] ?? null);
        if ($rn === '' || $dor === null) continue;
        try {
            if (array_key_exists($rn, $merge_map) && $merge_map[$rn] !== null) $rid = (int)$merge_map[$rn];
            else { if (find_txirrindularia_id($rn) === null) $created++; $rid = ensure_txirrindularia_id($rn); }
            upsert_dortsala($txap, $rid, $dor);
            $set++;
        } catch (Exception $e) {
            $errors[] = ['izena'=>$rn, 'reason'=>$e->getMessage()];
        }
    }
    return ['dortsalak'=>$set, 'txirrindulariak_berri'=>$created, 'errors'=>$errors];
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
        'SELECT ez.Ezizen_ID, ez.Ezizena, ez.Txapelketa_ID, t.Izena AS Txapelketa, t.Urtea AS Urtea ' .
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
        $races = array_map(fn($r)=>(int)$r['Karrerak_ID'], db_rows('SELECT Karrerak_ID FROM `Karrerak` WHERE Txapelketa_ID = ? ORDER BY Karrerak_ID', [$txap_id]));
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
    $changed = ['txirrindulariak'=>0,'porralariak'=>0];
    foreach (db_rows('SELECT Txirrindularia_ID, Izena FROM `Txirrindulariak`') as $row) {
        $new = mb_convert_case($row['Izena'], MB_CASE_TITLE, 'UTF-8');
        if ($new !== $row['Izena']) { db_exec('UPDATE `Txirrindulariak` SET Izena = ? WHERE Txirrindularia_ID = ?', [$new, $row['Txirrindularia_ID']]); $changed['txirrindulariak']++; }
    }
    foreach (db_rows('SELECT Porralaria_ID, Izena FROM `Porralariak`') as $row) {
        $new = mb_convert_case($row['Izena'], MB_CASE_TITLE, 'UTF-8');
        if ($new !== $row['Izena']) { db_exec('UPDATE `Porralariak` SET Izena = ? WHERE Porralaria_ID = ?', [$new, $row['Porralaria_ID']]); $changed['porralariak']++; }
    }
    return ['ok'=>true,'changed'=>$changed];
}

// ─── Sariak ──────────────────────────────────────────────────────────────────
function get_sariak($txap_id) {
    return db_rows('SELECT Posizioa, Saria FROM `Sariak` WHERE Txapelketa_ID = ? ORDER BY Posizioa', [$txap_id]);
}

function save_sariak($payload) {
    $txap_id = $payload['txapelketa_id'] ?? null;
    $sariak = $payload['sariak'] ?? [];
    if (!$txap_id) return ['ok'=>false,'reason'=>'txapelketa_id behar da'];
    $clean=[]; $seen=[];
    foreach ($sariak as $s) {
        $pos = $s['Posizioa'] ?? null;
        $saria = trim((string)($s['Saria'] ?? ''));
        if ($pos === null || $pos === '' || $saria === '') continue;
        if (!is_numeric($pos)) return ['ok'=>false,'reason'=>"Posizio baliogabea: $pos"];
        $pos = (int)$pos;
        if (in_array($pos, $seen)) return ['ok'=>false,'reason'=>"Posizioa errepikatuta: $pos"];
        $seen[] = $pos; $clean[] = [$pos, $saria];
    }
    try {
        if (!db_one('SELECT 1 AS x FROM `Txapelketak` WHERE Txapelketa_ID = ?', [$txap_id]))
            return ['ok'=>false,'reason'=>"Txapelketa $txap_id ez da existitzen"];
        db_begin();
        db_exec('DELETE FROM `Sariak` WHERE Txapelketa_ID = ?', [$txap_id]);
        foreach ($clean as [$pos, $saria])
            db_exec('INSERT INTO `Sariak` (Txapelketa_ID, Posizioa, Saria) VALUES (?, ?, ?)', [$txap_id, $pos, $saria]);
        db_commit();
        return ['ok'=>true,'count'=>count($clean)];
    } catch (Exception $e) { db_rollback(); return ['ok'=>false,'reason'=>$e->getMessage()]; }
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
    $stages_no_results = db_rows(
        "SELECT k.Karrerak_ID AS id, k.Izena FROM `Karrerak` k
         WHERE k.Txapelketa_ID = ? AND k.Kategoria IS NOT NULL AND k.Kategoria <> ''
           AND NOT EXISTS (SELECT 1 FROM `KarreraSailkapena` ks WHERE ks.Karrera_ID = k.Karrerak_ID)
         ORDER BY k.Karrerak_ID", [$txap_id]);
    $bettors_wrong_picks = db_rows(
        "SELECT ez.Ezizen_ID AS id, ez.Ezizena, COUNT(pa.Txirrindularia_ID) AS n
         FROM `PorraEzizenak` ez
         LEFT JOIN `PorraApustuak` pa ON pa.Ezizen_ID = ez.Ezizen_ID AND pa.Txapelketa_ID = ez.Txapelketa_ID
         WHERE ez.Txapelketa_ID = ?
         GROUP BY ez.Ezizen_ID, ez.Ezizena HAVING n <> 15
         ORDER BY n, ez.Ezizena", [$txap_id]);
    $picked_no_dortsal = db_rows(
        "SELECT DISTINCT t.Txirrindularia_ID AS id, t.Izena
         FROM `PorraApustuak` pa JOIN `Txirrindulariak` t ON t.Txirrindularia_ID = pa.Txirrindularia_ID
         WHERE pa.Txapelketa_ID = ?
           AND NOT EXISTS (SELECT 1 FROM `TxirrindulariakTxapleketanParteHartzea` h
                           WHERE h.TxapelketaID = pa.Txapelketa_ID AND h.TxirrindulariaID = pa.Txirrindularia_ID)
         ORDER BY t.Izena", [$txap_id]);
    $unlinked_ezizenak = db_rows(
        "SELECT ez.Ezizen_ID AS id, ez.Ezizena FROM `PorraEzizenak` ez
         WHERE ez.Txapelketa_ID = ?
           AND NOT EXISTS (SELECT 1 FROM `PorralariTaldeenEzizenak` l WHERE l.Ezizen_ID = ez.Ezizen_ID)
         ORDER BY ez.Ezizena", [$txap_id]);
    $has_results = ((int)db_scalar(
        "SELECT COUNT(*) FROM `KarreraSailkapena` ks JOIN `Karrerak` k ON k.Karrerak_ID = ks.Karrera_ID
         WHERE k.Txapelketa_ID = ?", [$txap_id])) > 0;
    $has_standings = ((int)db_scalar(
        "SELECT COUNT(*) FROM `TxapelketaSailkapenaPorralariak` WHERE Txapelketa_ID = ?", [$txap_id])) > 0;
    return [
        'stages_no_results' => $stages_no_results,
        'bettors_wrong_picks' => $bettors_wrong_picks,
        'picked_no_dortsal' => $picked_no_dortsal,
        'unlinked_ezizenak' => $unlinked_ezizenak,
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
