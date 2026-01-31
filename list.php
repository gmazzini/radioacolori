<?php
include "local.php";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = microtime(true);
$start_of_day = strtotime("today 00:00:00");
$tt = (int) floor($start_of_day / 86400);
$elapsed = $now - $start_of_day;

// anti “ballo” float ai confini
$eps = 0.001;

// Carico playlist del giorno con durate float
$sql = "SELECT p.position, p.id, t.duration, t.duration_extra
        FROM playlist p
        JOIN track t ON p.id = t.id
        WHERE p.tt = $tt
        ORDER BY p.position ASC";
$res = mysqli_query($con, $sql);
if (!$res) {
    mysqli_close($con);
    exit;
}

$seq = [];
$dur = []; 
$ext = [];
while ($row = mysqli_fetch_assoc($res)) {
    $seq[] = (int)$row['id'];
    $d = (float)$row['duration'];
    $e = (float)$row['duration_extra'];
    if ($d < 0) $d = 0.0;
    if ($e < 0) $e = 0.0;
    $dur[] = $d;
    $ext[] = $e;
}
mysqli_free_result($res);

$n = count($seq);
if ($n === 0) {
    mysqli_close($con);
    exit;
}

// Costruisco schedule start_ts per ogni brano (float), come index.php
$sched = [];
$cursor = (float)$start_of_day;
for ($i = 0; $i < $n; $i++) {
    $sched[$i] = $cursor;
    $cursor += ($dur[$i] + $ext[$i]);
}

// Trovo indice corrente come in index.php: elapsed ∈ [start, end)
$pp = 0;
$cursor2 = 0.0;
for ($i = 0; $i < $n; $i++) {
    $start = $cursor2;
    $music_end = $start + $dur[$i];
    $end = $music_end + $ext[$i];

    if ($elapsed >= ($start - $eps) && $elapsed < ($end - $eps)) {
        $pp = $i;
        break;
    }
    $cursor2 = $end;
}

// Countdown “prossimo brano” (fine segmento totale duration+extra)
$start_rel = 0.0;
for ($i = 0; $i < $pp; $i++) $start_rel += ($dur[$i] + $ext[$i]);
$end_rel = $start_rel + $dur[$pp] + $ext[$pp];
$next = max(0, (int)ceil(($end_rel - $elapsed)));

$in_extra = ($elapsed >= ($start_rel + $dur[$pp] - $eps));

// Finestra ±4 attorno al corrente
$f = $pp - 4; if ($f < 0) $f = 0;
$t = $pp + 4; if ($t >= $n) $t = $n - 1;

// Pre-carico metadati solo per gli ID in finestra
$ids = array_slice($seq, $f, $t - $f + 1);
$in = "(" . implode(",", array_map('intval', $ids)) . ")";
$meta = [];

$sqlm = "SELECT id, title, author, genre, duration
         FROM track
         WHERE id IN $in";
$resm = mysqli_query($con, $sqlm);
if ($resm) {
    while ($r = mysqli_fetch_assoc($resm)) {
        $id = (int)$r['id'];
        $meta[$id] = [
            'title'  => (string)$r['title'],
            'author' => (string)$r['author'],
            'genre'  => (string)$r['genre'],
            'duration' => (float)$r['duration'],
        ];
    }
    mysqli_free_result($resm);
}

// Output
echo "tt=$tt\n";
echo "now=" . date("Y-m-d H:i:s", (int)$now) . "\n";
echo "next_reload_in={$next}s\n";
if ($in_extra) echo "note=chiusura_in_corso\n";

for ($i = $f; $i <= $t; $i++) {
    $id = $seq[$i];
    $m = $meta[$id] ?? ['title'=>'', 'author'=>'', 'genre'=>'', 'duration'=>0.0];

    if ($i === $pp) echo ">>";
    echo date("H:i:s", (int)$sched[$i])
        . "," . sprintf('%05d', $id)
        . "," . $m['title']
        . "," . $m['author']
        . "," . $m['genre']
        . "," . fmt_secs($m['duration']) . "s\n";
}

mysqli_close($con);

function fmt_secs($s) {
    $s = (float)$s;
    $eps = 0.0005;
    $r = round($s);
    if (abs($s - $r) < $eps) return (string)(int)$r;
    $str = sprintf('%.3f', $s);
    $str = rtrim(rtrim($str, '0'), '.');
    return $str === '' ? '0' : $str;
}
?>
