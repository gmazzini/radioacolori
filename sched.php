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

function read_last_log($logfile) {
    if (!is_readable($logfile)) return null;
    $fp = @fopen($logfile, "rb");
    if (!$fp) return null;

    $pos = -1;
    $line = "";
    $stat = fstat($fp);
    $size = $stat ? (int)$stat['size'] : 0;
    if ($size <= 0) { fclose($fp); return null; }

    // leggi all'indietro fino a newline
    fseek($fp, 0, SEEK_END);
    while (-$pos <= $size) {
        fseek($fp, $pos, SEEK_END);
        $ch = fgetc($fp);
        if ($ch === "\n" && $line !== "") break;
        $line = $ch . $line;
        $pos--;
    }
    fclose($fp);

    $line = trim($line);
    if ($line === "") return null;

    // formato: epoch path shift STATE end_epoch
    $parts = preg_split('/\s+/', $line);
    if (!$parts || count($parts) < 5) return null;

    $last_epoch = (int)$parts[0];
    $last_end   = (int)$parts[count($parts) - 1];
    $last_state = $parts[count($parts) - 2];
    $last_shift = (float)$parts[count($parts) - 3];

    // path può contenere spazi? nel nostro caso no (path linux standard), quindi ok:
    $path_parts = array_slice($parts, 1, count($parts) - 4);
    $last_path = implode(" ", $path_parts);

    return [
        'epoch' => $last_epoch,
        'path'  => $last_path,
        'shift' => $last_shift,
        'state' => $last_state,
        'end'   => $last_end,
    ];
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

$last = read_last_log($logfile);
$repeat_window = 30; // secondi: se richiede lo stesso segmento entro 30s, assumo "EARLY"

$current = 0.0;

while ($row = mysqli_fetch_assoc($res)) {
    $id5 = (string)$row['id'];

    $d_music = (float)$row['duration'];
    $d_close = (float)$row['duration_extra'];
    if ($d_music < 0) $d_music = 0.0;
    if ($d_close < 0) $d_close = 0.0;

    $title  = esc_liq_meta($row['title']);
    $artist = esc_liq_meta($row['author']);

    // MUSICA segment: [current, current + d_music)
    $seg_start_rel = $current;
    $seg_end_rel   = $current + $d_music;

    if ($elapsed >= ($seg_start_rel - $eps) && $elapsed < ($seg_end_rel - $eps)) {
        $offset = $elapsed - $seg_start_rel;
        if ($offset < 0) $offset = 0.0;

        $path = $p2 . $id5 . ".ogg";
        $end_epoch = (int)floor($start_of_day + $seg_end_rel);

        // EARLY detection: Liquidsoap richiede ancora lo stesso segmento poco dopo averglielo già dato
        if ($last && $last['path'] === $path && ($epoch - $last['epoch']) <= $repeat_window && $epoch < $end_epoch) {
            $remain = ($start_of_day + $seg_end_rel) - $now;
            if ($remain < 0) $remain = 0.0;

            log_sched($logfile, $epoch, $glue, 0.0, "EARLY", (int)floor($start_of_day + $seg_end_rel));
            echo $glue;

            mysqli_free_result($res);
            mysqli_close($con);
            exit;
        }

        $offset_str = fmt_float_for_liq($offset);
        log_sched($logfile, $epoch, $path, $offset, "MUSIC", $end_epoch);
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=$offset_str:$path";

        mysqli_free_result($res);
        mysqli_close($con);
        exit;
    }

    $current = $seg_end_rel;

    // CLOSE segment: [current, current + d_close)
    $seg_start_rel = $current;
    $seg_end_rel   = $current + $d_close;

    if ($d_close > 0.0 && $elapsed >= ($seg_start_rel - $eps) && $elapsed < ($seg_end_rel - $eps)) {
        $offset = $elapsed - $seg_start_rel;
        if ($offset < 0) $offset = 0.0;

        $path = $p3 . $id5 . ".ogg";
        $end_epoch = (int)floor($start_of_day + $seg_end_rel);

        if ($last && $last['path'] === $path && ($epoch - $last['epoch']) <= $repeat_window && $epoch < $end_epoch) {
            log_sched($logfile, $epoch, $glue, 0.0, "EARLY", (int)floor($start_of_day + $seg_end_rel));
            echo $glue;

            mysqli_free_result($res);
            mysqli_close($con);
            exit;
        }

        $offset_str = fmt_float_for_liq($offset);
        log_sched($logfile, $epoch, $path, $offset, "CLOSE", $end_epoch);
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=$offset_str:$path";

        mysqli_free_result($res);
        mysqli_close($con);
        exit;
    }

    $current = $seg_end_rel;
}

mysqli_free_result($res);
mysqli_close($con);

log_sched($logfile, $epoch, $glue, 0.0, "GLUE", $epoch);
echo $glue;
?>

