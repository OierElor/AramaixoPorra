<?php
// ─── Datu-base konexioa eta laguntzaileak (mysqli) ───────────────────────────

function db() {
    static $conn = null;
    if ($conn === null) {
        $cfg = require __DIR__ . '/config.php';
        mysqli_report(MYSQLI_REPORT_OFF); // erroreak eskuz kudeatu
        $conn = mysqli_init();
        mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        if (!@mysqli_real_connect($conn, $cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], (int)$cfg['port'])) {
            json_out(['error' => 'DB konexio errorea: ' . mysqli_connect_error()], 500);
        }
        mysqli_set_charset($conn, 'utf8mb4');
    }
    return $conn;
}

// Prepared statement bat exekutatu. $sql-ek "?" placeholder-ak erabiltzen ditu.
function db_query($sql, $params = []) {
    $conn = db();
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        throw new Exception('SQL errorea: ' . mysqli_error($conn));
    }
    if (!empty($params)) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p))        $types .= 'i';
            elseif (is_float($p))  $types .= 'd';
            else                   $types .= 's'; // string eta NULL
        }
        // bind_param erreferentziak behar ditu
        $refs = [];
        $refs[] = $types;
        foreach ($params as $k => $v) {
            $refs[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('SQL exekuzio-errorea: ' . $err);
    }
    return $stmt;
}

// Errenkada guztiak array elkartu gisa (mysqlnd gabe ere funtzionatzen du)
function db_rows($sql, $params = []) {
    $stmt = db_query($sql, $params);
    $meta = mysqli_stmt_result_metadata($stmt);
    if (!$meta) { mysqli_stmt_close($stmt); return []; }
    $cols = [];
    while ($f = mysqli_fetch_field($meta)) $cols[] = $f->name;
    mysqli_free_result($meta);
    $vals = array_fill(0, count($cols), null);
    $refs = [$stmt];
    foreach ($vals as $i => &$v) $refs[] = &$v;
    call_user_func_array('mysqli_stmt_bind_result', $refs);
    mysqli_stmt_store_result($stmt);
    $rows = [];
    while (mysqli_stmt_fetch($stmt)) {
        $row = [];
        foreach ($cols as $i => $name) $row[$name] = $vals[$i];
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

// Lehen errenkada (edo null)
function db_one($sql, $params = []) {
    $rows = db_rows($sql, $params);
    return $rows ? $rows[0] : null;
}

// Lehen errenkadako lehen zutabea (edo null)
function db_scalar($sql, $params = []) {
    $r = db_one($sql, $params);
    if ($r === null) return null;
    return array_values($r)[0];
}

// INSERT/UPDATE/DELETE — itzuli ['affected'=>..., 'insert_id'=>...]
function db_exec($sql, $params = []) {
    $stmt = db_query($sql, $params);
    $affected  = mysqli_stmt_affected_rows($stmt);
    $insert_id = mysqli_insert_id(db());
    mysqli_stmt_close($stmt);
    return ['affected' => $affected, 'insert_id' => $insert_id];
}

// Errenkada asko batera sartu (multi-VALUES, zatika). $rows = array bakoitza balio-zerrenda.
function db_insert_many($table, $cols, $rows, $extra_sql = '') {
    if (empty($rows)) return 0;
    $conn = db();
    $col_sql = implode(', ', array_map(fn($c) => '`' . $c . '`', $cols));
    $ncol = count($cols);
    $placeholder = '(' . implode(', ', array_fill(0, $ncol, '?')) . ')';
    $total = 0;
    foreach (array_chunk($rows, 200) as $chunk) {
        $values_sql = implode(', ', array_fill(0, count($chunk), $placeholder));
        $sql = "INSERT INTO `$table` ($col_sql) VALUES $values_sql $extra_sql";
        $flat = [];
        foreach ($chunk as $r) foreach ($r as $v) $flat[] = $v;
        $res = db_exec($sql, $flat);
        $total += count($chunk);
    }
    return $total;
}

function db_begin()    { mysqli_begin_transaction(db()); }
function db_commit()   { mysqli_commit(db()); }
function db_rollback() { @mysqli_rollback(db()); }

function db_table_exists($name) {
    return (bool) db_one(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$name]
    );
}

function db_table_columns($name) {
    return db_rows(
        "SELECT column_name AS name, column_type AS type, IF(column_key='PRI',1,0) AS pk " .
        "FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? " .
        "ORDER BY ordinal_position",
        [$name]
    );
}

function quote_ident($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

// ─── JSON irteera ────────────────────────────────────────────────────────────

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error($msg, $code = 400) {
    json_out(['error' => $msg], $code);
}
