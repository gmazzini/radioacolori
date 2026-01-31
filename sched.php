<?php
include "local.php";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = microtime(true);
$epoch = (int)floor($now);

$start_of_day = strtotime("today 00:00:00");
$elapsed = $now - $start_of_day;

$eps = 0.001;

$tt = (int)floor($start_of_day / 86400);

$p2   = "/home/ices/music/ogg04/";
$p3   = "/home/ices/music/ogg04v/";
$glue = "/home/ices/music/glue.ogg";

$logfile = "/home/ices/sched.log";

function fmt_float_for_liq($v) {
    $v = (float)$v;
    if ($v < 0) $v = 0.0;
    $s = sprintf('%.3f', $v);
    $s = rtrim(rtrim($s, '0'), '.');
    return ($s === '') ? '0' : $s;
}

function esc_liq_meta($s) {
    $s = (string)$s;
    $s = str_replace("\\", "\\\\", $s);
    $s = str_replace("\"", "\\\"", $s);
    return $s;
}

function log_sched($logfile, $epoch, $path, $shift, $state, $end_epoch) {
    $line = $epoch . " " . $path . " " . fmt_float_for_liq($shift) . " " . $state . " " . (int)$end_epoch . "\n";
    @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
}

$query = "SELECT p.id, t.duration, t.duration_extra, t.title, t.author
          FROM playlist p
          JOIN track t ON p.id = t.id
          WHERE p.tt = $tt
          ORDER BY p.position ASC";

$res = mysqli_query($con, $query);
if (!$res) {
    log_sched($logfile, $epoch, $glue, 0.0, "GLUE", $epoch);
    echo $glue;
    mysqli_close($con);
    exit;
}

$current = 0.0;

while ($row = mysqli_fetch_assoc($res)) {
    $id5 = (string)$row['id'];

    $d_music = (float)$row['duration'];
    $d_close = (float)$row['duration_extra'];
    if ($d_music < 0) $d_music = 0.0;
    if ($d_close < 0) $d_close = 0.0;

    $title  = esc_liq_meta($row['title']);
    $artist = esc_liq_meta($row['author']);

    // MUSICA: [current, current + d_music)
    $seg_start = $current;
    $seg_end   = $current + $d_music;

    if ($elapsed >= ($seg_start - $eps) && $elapsed < ($seg_end - $eps)) {
        $offset = $elapsed - $seg_start;
        if ($offset < 0) $offset = 0.0;

        $path = $p2 . $id5 . ".ogg";
        $end_epoch = (int)floor($start_of_day + $seg_end);

        log_sched($logfile, $epoch, $path, $offset, "MUSIC", $end_epoch);
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=" . fmt_float_for_liq($offset) . ":" . $path;

        mysqli_free_result($res);
        mysqli_close($con);
        exit;
    }

    $current = $seg_end;

    // CHIUSURA: [current, current + d_close)
    $seg_start = $current;
    $seg_end   = $current + $d_close;

    if ($d_close > 0.0 && $elapsed >= ($seg_start - $eps) && $elapsed < ($seg_end - $eps)) {
        $offset = $elapsed - $seg_start;
        if ($offset < 0) $offset = 0.0;

        $path = $p3 . $id5 . ".ogg";
        $end_epoch = (int)floor($start_of_day + $seg_end);

        // titolo/autore originali anche in chiusura
        log_sched($logfile, $epoch, $path, $offset, "CLOSE", $end_epoch);
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=" . fmt_float_for_liq($offset) . ":" . $path;

        mysqli_free_result($res);
        mysqli_close($con);
        exit;
    }

    $current = $seg_end;
}

mysqli_free_result($res);
mysqli_close($con);

log_sched($logfile, $epoch, $glue, 0.0, "GLUE", $epoch);
echo $glue;
?>

