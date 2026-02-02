<?php
include "local.php";

date_default_timezone_set('UTC');
$local_tz = new DateTimeZone('Europe/Rome');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) {
    http_response_code(500);
    exit;
}
mysqli_set_charset($con, 'utf8mb4');

// Evita cache (importantissimo)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$action = $_GET['action'] ?? 'live';   // live | track
$format = $_GET['format'] ?? 'json';   // json | text

function out_json($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function out_text($text) {
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
}

if ($action === 'track') {
    // --- Track lookup by id ---
    $id_raw = $_GET['id'] ?? '';
    $id_raw = trim($id_raw);

    if ($id_raw === '') {
        http_response_code(400);
        if ($format === 'text') out_text("ERROR: missing id\n");
        else out_json(['ok' => false, 'error' => 'missing_id']);
        mysqli_close($con);
        exit;
    }

    // Prepared statement; supports numeric and string IDs.
    if (ctype_digit($id_raw)) {
        $id_int = (int)$id_raw;
        $stmt = mysqli_prepare($con, "SELECT id, title, author, genre, duration, duration_extra FROM track WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id_int);
    } else {
        $stmt = mysqli_prepare($con, "SELECT id, title, author, genre, duration, duration_extra FROM track WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $id_raw);
    }

    $ok = $stmt && mysqli_stmt_execute($stmt);
    if (!$ok) {
        http_response_code(500);
        if ($format === 'text') out_text("ERROR: db\n");
        else out_json(['ok' => false, 'error' => 'db']);
        if ($stmt) mysqli_stmt_close($stmt);
        mysqli_close($con);
        exit;
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        if ($format === 'text') out_text("NOT FOUND\n");
        else out_json(['ok' => false, 'error' => 'not_found']);
        mysqli_close($con);
        exit;
    }

    $track = [
        'id' => (string)$row['id'],
        'title' => (string)$row['title'],
        'author' => (string)($row['author'] ?? ''),
        'genre' => (string)($row['genre'] ?? ''),
        'duration' => (float)$row['duration'],
        'duration_extra' => (float)$row['duration_extra'],
        'dur_total' => (float)$row['duration'] + (float)$row['duration_extra'],
    ];

    if ($format === 'text') {
        // Minimal human-readable output
        out_text("Trovato: [{$track['id']}] {$track['title']} - {$track['author']}\n");
    } else {
        out_json(['ok' => true, 'track' => $track]);
    }

    mysqli_close($con);
    exit;
}

// --- Default action: LIVE window (schedule around current) ---
$now = microtime(true);
$now_int = (int)floor($now);

// 1) Locate current playing track start epoch
$q_curr = mysqli_query($con, "SELECT epoch FROM lineup WHERE epoch <= $now_int ORDER BY epoch DESC LIMIT 1");
$row_curr = $q_curr ? mysqli_fetch_assoc($q_curr) : null;
$current_track_epoch = $row_curr ? (int)$row_curr['epoch'] : $now_int;

// 2) Fetch context (4 before, current, 4 after)
$sql = "SELECT l.epoch, l.id, t.title, t.author, t.genre, t.duration, t.duration_extra
        FROM (
            (SELECT epoch, id FROM lineup WHERE epoch < $current_track_epoch ORDER BY epoch DESC LIMIT 4)
            UNION ALL
            (SELECT epoch, id FROM lineup WHERE epoch >= $current_track_epoch ORDER BY epoch ASC LIMIT 5)
        ) AS l
        JOIN track t ON l.id = t.id
        ORDER BY l.epoch ASC";

$res = mysqli_query($con, $sql);

$items = [];
$current = null;

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $start = (int)$r['epoch'];
        $dur = (float)$r['duration'] + (float)$r['duration_extra'];
        $end = $start + $dur;

        $is_now = ($now >= $start && $now < $end);

        $dt = new DateTime("@".$start);
        $dt->setTimezone($local_tz);

        $item = [
            'id' => (string)$r['id'],
            'start' => $start,
            'end' => (int)ceil($end),
            'dur' => (int)round($dur),
            'time_local' => $dt->format("H:i:s"),
            'title' => (string)$r['title'],
            'author' => (string)($r['author'] ?? ''),
            'genre' => (string)($r['genre'] ?? ''),
            'is_now' => $is_now
        ];

        if ($is_now) $current = $item;
        $items[] = $item;
    }
}

$payload = [
    'server_now' => $now, // float epoch seconds UTC
    'server_now_int' => $now_int,
    'current' => $current,
    'next_change_sec' => $current ? (int)max(0, ceil($current['end'] - $now)) : 0,
    'items' => $items
];

if ($format === 'text') {
    // KEEP "WhatsApp view" style output
    $found_current = false;
    $next_change = 0;

    $out = '';
    foreach ($items as $it) {
        $marker = $it['is_now'] ? '>' : '';
        if ($it['is_now']) {
            $found_current = true;
            $next_change = (int)max(0, ceil($it['end'] - $now));
        }
        $title_trim = mb_strimwidth($it['title'], 0, 25, "..", "UTF-8");
        $out .= $marker . $it['time_local'] . " [" . $it['id'] . "] " . $title_trim . "\n";
    }
    if ($found_current) $out .= "Next: {$next_change}s\n";

    out_text($out);
} else {
    out_json($payload);
}

mysqli_close($con);

