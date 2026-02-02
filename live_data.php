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

// Evita cache (importantissimo per non vedere dati "vecchi")
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$format = $_GET['format'] ?? 'json'; // json | text

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

        if ($is_now) {
            $current = $item;
        }
        $items[] = $item;
    }
}

$response = [
    'server_now' => $now,                  // secondi (float) UTC epoch
    'server_now_int' => $now_int,          // secondi (int)
    'current' => $current,                 // null se non trovato
    'next_change_sec' => $current ? (int)max(0, ceil($current['end'] - $now)) : 0,
    'items' => $items
];

if ($format === 'text') {
    header('Content-Type: text/plain; charset=utf-8');

    $found_current = false;
    $next_change = 0;

    foreach ($items as $it) {
        $marker = $it['is_now'] ? '>' : '';
        if ($it['is_now']) {
            $found_current = true;
            $next_change = (int)max(0, ceil($it['end'] - $now));
        }

        $title_trim = mb_strimwidth($it['title'], 0, 25, "..", "UTF-8");
        echo $marker . $it['time_local'] . " [" . $it['id'] . "] " . $title_trim . "\n";
    }

    if ($found_current) {
        echo "Next: {$next_change}s\n";
    }

    mysqli_close($con);
    exit;
}

// default json
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

mysqli_close($con);
