<?php
// ─── Aramaixo Porra Admin — API sarrera-puntua ──────────────────────────────
// ob_start: nginx-ek PHP fatal erroreak HTML gisa interpreta ez ditzan (5xx)
ob_start();
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        ob_end_clean();
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'PHP errore larria: ' . $err['message']], JSON_UNESCAPED_UNICODE);
    } else {
        ob_end_flush();
    }
});
session_start();
require __DIR__ . '/lib.php';

// ─── HTTP Basic Auth ─────────────────────────────────────────────────────────
(function () {
    $cfg = require __DIR__ . '/config.php';
    // FastCGI/PHP-FPM-rekin PHP_AUTH_USER hutsa egon daiteke; Authorization header-etik atera
    if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] === '') {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Basic\s+(.+)$/i', $h, $m)) {
            [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']] =
                array_pad(explode(':', base64_decode($m[1]), 2), 2, '');
        }
    }
    $u = $_SERVER['PHP_AUTH_USER'] ?? '';
    $p = $_SERVER['PHP_AUTH_PW'] ?? '';
    if (!hash_equals($cfg['auth_user'], $u) || !hash_equals($cfg['auth_pass'], $p)) {
        header('WWW-Authenticate: Basic realm="Aramaixo Porra Admin"');
        json_out(['error' => 'Autentifikazioa behar da'], 401);
    }
})();

// ─── Router ──────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$path = trim((string)($_GET['_path'] ?? ''), '/');
$body = [];
if (in_array($method, ['POST','PUT'])) {
    $raw = file_get_contents('php://input');
    if ($raw) { $body = json_decode($raw, true); if (!is_array($body)) json_error('JSON baliogabea', 400); }
}

try {
    if ($method === 'GET') {
        switch (true) {
            case $path === 'porralariak':
                json_out(db_rows('SELECT p.*, COUNT(ep.Ezizen_ID) AS `Zenbat Porra` FROM `Porralariak` p LEFT JOIN `PorralariTaldeenEzizenak` ep ON p.Porralaria_ID = ep.Porralaria_ID GROUP BY p.Porralaria_ID ORDER BY p.Izena'));
            case $path === 'txirrindulariak':
                json_out(db_rows('SELECT * FROM `Txirrindulariak` ORDER BY Izena'));
            case $path === 'txapelketak':
                json_out(db_rows('SELECT * FROM `Txapelketak` ORDER BY Urtea DESC, Izena'));
            case $path === 'karrerak':
                json_out(db_rows('SELECT * FROM `Karrerak` ORDER BY Urtea DESC, Izena'));
            case $path === 'txirrindulari-emaitzak':
                json_out(db_rows('SELECT e.*, t.Izena AS Txirrindularia FROM `TxapelketaEmaitzaTxirrindulariak` e JOIN `Txirrindulariak` t ON e.Txirrindularia_ID = t.Txirrindularia_ID ORDER BY e.Txapelketa_ID, e.Posizioa'));
            case $path === 'porralari-emaitzak':
                json_out(db_rows('SELECT e.*, ez.Ezizena, GROUP_CONCAT(p.Izena SEPARATOR ", ") AS Porralaria FROM `TxapelketaEmaitzaPorralariak` e JOIN `PorraEzizenak` ez ON e.Ezizen_ID = ez.Ezizen_ID LEFT JOIN `PorralariTaldeenEzizenak` ep ON ez.Ezizen_ID = ep.Ezizen_ID LEFT JOIN `Porralariak` p ON ep.Porralaria_ID = p.Porralaria_ID GROUP BY e.Txapelketa_ID, e.Ezizen_ID ORDER BY e.Txapelketa_ID, e.Posizioa'));
            case $path === 'karrera-sailkapena':
                json_out(db_rows('SELECT ks.*, t.Izena AS Txirrindularia FROM `KarreraSailkapena` ks JOIN `Txirrindulariak` t ON ks.Txirrindularia_ID = t.Txirrindularia_ID ORDER BY ks.Karrera_ID, ks.Puntuak DESC'));
            case $path === 'karrera-emaitza':
                $kid = $_GET['karrera_id'] ?? null;
                if (!$kid) json_error('karrera_id parametroa behar da', 400);
                $karrera = db_one('SELECT k.*, tx.Izena AS Txapelketa FROM `Karrerak` k JOIN `Txapelketak` tx ON k.Txapelketa_ID = tx.Txapelketa_ID WHERE k.Karrerak_ID = ?', [(int)$kid]);
                if (!$karrera) json_error('Karrera ez da aurkitu', 404);
                $sail = db_rows('SELECT ks.Sailkapena, t.Izena AS Txirrindularia, ks.Puntuak FROM `KarreraSailkapena` ks JOIN `Txirrindulariak` t ON ks.Txirrindularia_ID = t.Txirrindularia_ID WHERE ks.Karrera_ID = ? ORDER BY ks.Sailkapena', [(int)$kid]);
                json_out(['karrera'=>$karrera,'sailkapena'=>$sail]);
            case $path === 'ezizenak':
                json_out(api_ezizenak());
            case $path === 'porralaria-ezizenak':
                $pid = $_GET['porralaria_id'] ?? null;
                if (!$pid) json_error('porralaria_id parametroa behar da', 400);
                json_out(porralaria_ezizenak((int)$pid));
            case $path === 'data-quality':
                // Nahita lotu gabe utzitakoak (Ez_Lotu=1) EZ dira zenbatzen (migrazioa eginda badago).
                $ezlotu = db_column_exists('PorraEzizenak', 'Ez_Lotu') ? ' AND COALESCE(e.Ez_Lotu,0) = 0' : '';
                $unlinked = (int)(db_scalar(
                    'SELECT COUNT(*) FROM PorraEzizenak e WHERE NOT EXISTS (SELECT 1 FROM PorralariTaldeenEzizenak WHERE Ezizen_ID = e.Ezizen_ID)' . $ezlotu
                ) ?? 0);
                json_out(['unlinked_ezizenak' => $unlinked]);
            case $path === 'data-health':
                $tid = $_GET['txapelketa_id'] ?? null;
                if ($tid === null || $tid === '') json_error('txapelketa_id parametroa behar da', 400);
                json_out(data_health((int)$tid));
            case $path === 'possible-dups':
                json_out(possible_dups($_GET['kind'] ?? 'txirrindulariak'));
            case $path === 'porra-picks':
                $eid = $_GET['ezizen_id'] ?? null; $tid = $_GET['txapelketa_id'] ?? null;
                if (!$eid || !$tid) json_error('ezizen_id eta txapelketa_id behar dira', 400);
                json_out(porra_picks((int)$eid, (int)$tid));
            case $path === 'export':
                $tid = $_GET['txapelketa_id'] ?? null;
                if ($tid === null || $tid === '') json_error('txapelketa_id parametroa behar da', 400);
                json_out(export_txapelketa((int)$tid));
            case $path === 'proposals':
                json_out(['proposals' => read_proposals()]);
            case $path === 'aurre-porrak':
                json_out(['porrak' => read_aurre_porrak()]);
            case $path === 'files':
                try { json_out(files_list($_GET['dir'] ?? '')); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'ezarpenak':
                try { json_out(read_ezarpenak()); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'migrations':
                try { json_out(migration_status()); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'webgune-orriak':
                try { json_out(webgune_orriak_egoera()); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'backup':
                try {
                    $data = db_full_backup();
                    $fn = 'aramaixoporra-backup-' . date('Ymd-Hi') . '.json';
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $fn . '"');
                    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    exit;
                } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'meta':
                json_out(db_meta());
            case $path === 'undo-state':
                json_out(undo_stack_state());
            case strpos($path, 'table/') === 0:
                $tn = rawurldecode(substr($path, strlen('table/')));
                try { json_out(read_table($tn)); } catch (Exception $e) { json_error($e->getMessage(), 404); }
            default:
                json_error('Not found', 404);
        }
    } elseif ($method === 'POST') {
        switch (true) {
            case $path === 'insert':
                $table = $body['table'] ?? ''; $vals = $body['data'] ?? [];
                if (!$table || !$vals) json_error('table eta data behar dira');
                try {
                    // Karrerek BETI Kategoria eta Ordena behar dituzte: hutsik utziz gero,
                    // akordeoian eta tresnetan ezkutatuta geratuko lirateke.
                    if ($table === 'Karrerak') $vals = normalize_karrera_row($vals);
                    $cols = array_keys($vals);
                    $col_sql = implode(', ', array_map(fn($c)=>quote_ident($c), $cols));
                    $ph = implode(', ', array_fill(0, count($cols), '?'));
                    $r = db_exec("INSERT INTO " . quote_ident($table) . " ($col_sql) VALUES ($ph)", array_values($vals));
                    json_out(['ok'=>true,'id'=>$r['insert_id']]);
                } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'import/startlist-preview': json_out(import_startlist_preview($body));
            case $path === 'import/startlist': json_out(import_startlist($body));
            case $path === 'import/apustuak-preview': json_out(import_apustuak_preview($body));
            case $path === 'import/apustuak': json_out(import_apustuak($body));
            case $path === 'import/emaitzak-preview': json_out(import_emaitzak_preview($body));
            case $path === 'import/emaitzak': json_out(import_emaitzak($body));
            case $path === 'import/etapak-preview': json_out(import_etapak_preview($body));
            case $path === 'import/etapak': json_out(import_etapak($body));
            case $path === 'finalize/preview': json_out(finalize_txapelketa_preview($body));
            case $path === 'finalize/commit': json_out(finalize_txapelketa_commit($body));
            case $path === 'import/karrerak': json_out(import_karrerak($body));
            case $path === 'proposals/clear': json_out(clear_proposals());
            case $path === 'proposals/delete': json_out(delete_proposal($body['idx'] ?? -1));
            case $path === 'aurre-porrak/clear': json_out(clear_aurre_porrak());
            case $path === 'aurre-porrak/delete': json_out(delete_aurre_porra($body['idx'] ?? -1));
            case $path === 'files/upload':
                try { json_out(files_upload($_GET['dir'] ?? '')); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'files/delete':
                try { json_out(files_delete($body['path'] ?? '')); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'files/rename':
                try { json_out(files_rename($body['path'] ?? '', $body['newname'] ?? '')); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'files/mkdir':
                try { json_out(files_mkdir($body['dir'] ?? '', $body['name'] ?? '')); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'ezarpenak':
                try { json_out(save_ezarpenak($body)); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'ezarpenak/tresnak':
                try { json_out(save_tresna_ezarpenak($body)); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'ezarpenak/eskalak':
                try { json_out(save_eskala_ezarpenak($body)); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'undo': json_out(do_undo());
            case $path === 'redo': json_out(do_redo());
            case $path === 'ezizen-lotu': json_out(ezizen_lotu($body));
            case $path === 'porralari-split': json_out(porralari_split($body));
            case $path === 'merge/preview':
                $kind=$body['kind']??''; $k=$body['keep_id']??null; $d=$body['drop_id']??null;
                if (!$kind || $k===null || $d===null) json_error('kind, keep_id eta drop_id behar dira');
                json_out(merge_preview($kind,(int)$k,(int)$d));
            case $path === 'merge/execute':
                $kind=$body['kind']??''; $k=$body['keep_id']??null; $d=$body['drop_id']??null;
                if (!$kind || $k===null || $d===null) json_error('kind, keep_id eta drop_id behar dira');
                if ($kind==='txirrindulariak') json_out(merge_txirrindulariak((int)$k,(int)$d));
                elseif ($kind==='porralariak') json_out(merge_porralariak((int)$k,(int)$d));
                else json_error("Mota ezezaguna: $kind");
            case $path === 'recalculate-zenbat-porra': json_out(recalculate_zenbat_porra());
            case $path === 'porra-zenbakiak/recompute': json_out(recompute_porra_zenbakiak($body['txapelketa_id'] ?? null));
            case $path === 'calculate/txirri-sailkapena':
                $tid=$body['txapelketa_id']??null; if (!$tid) json_error('txapelketa_id behar da');
                json_out(calculate_txirri_sailkapena((int)$tid));
            case $path === 'calculate/porralari-sailkapena':
                $tid=$body['txapelketa_id']??null; if (!$tid) json_error('txapelketa_id behar da');
                json_out(calculate_porralari_sailkapena((int)$tid));
            case $path === 'normalize-izenak': json_out(normalize_izenak());
            case $path === 'izen-ordenak/get': json_out(get_izen_ordenak());
            case $path === 'izen-ordenak/apply': json_out(apply_izen_ordenak($body['aldaketak'] ?? []));
            case $path === 'txirrindulari-ordenak': json_out(get_txirrindulari_ordenak());
            case $path === 'txirrindulari-swap':
                $ids = $body['ids'] ?? []; if (!$ids) json_error('ids behar dira');
                json_out(apply_txirrindulari_swap(array_map('intval', $ids)));
            case $path === 'table-update':
                $tn = $body['table'] ?? '';
                if (!$tn) json_error('table behar da');
                try { json_out(update_table_row($tn, $body)); } catch (Exception $e) { json_error($e->getMessage()); }
            case $path === 'table-delete':
                $tn = $body['table'] ?? '';
                if (!$tn) json_error('table behar da');
                try { json_out(delete_table_row($tn, $body['pk'] ?? [])); } catch (Exception $e) { json_error($e->getMessage()); }
            default: json_error('Not found', 404);
        }
    } elseif ($method === 'PUT') {
        if (strpos($path, 'table/') === 0) {
            $tn = rawurldecode(substr($path, strlen('table/')));
            try { json_out(update_table_row($tn, $body)); } catch (Exception $e) { json_error($e->getMessage()); }
        }
        json_error('Not found', 404);
    } else {
        json_error('Metodo onartezina', 405);
    }
} catch (Exception $e) {
    json_error($e->getMessage(), 500);
}
