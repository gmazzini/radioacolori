<?php
include "local.php";

$p2   = "/home/ices/music/ogg04/";
$p3   = "/home/ices/music/ogg04v/";
$glue = "/home/ices/music/glue.ogg";
$cut_file = "/run/cutted.wav";
$logfile  = "/home/ices/sched.log";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = microtime(true);
$now_int = (int)floor($now);

function fmt_liq($v) {
    return rtrim(rtrim(sprintf('%.3f', max(0, (float)$v)), '0'), '.');
}

// Funzione di logging dettagliata (come la volevi tu)
function log_sched($logfile, $now, $id, $path, $shift, $state, $epoch_start) {
    $date_str = date("Y-m-d H:i:s", (int)$now) . substr(sprintf('%.3f', $now - floor($now)), 1);
    $line = "[$date_str] ID:$id Offset:".fmt_liq($shift)." Type:$state Start:$epoch_start Path:$path\n";
    @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
}

$query = "SELECT l.epoch, l.id, t.duration, t.duration_extra, t.title, t.author
          FROM lineup l
          JOIN track t ON l.id = t.id
          WHERE l.epoch <= $now_int
          ORDER BY l.epoch DESC LIMIT 1";

$res = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($res);

if ($row) {
    $id = $row['id'];
    $epoch_start = (int)$row['epoch'];
    $offset_total = $now - (float)$epoch_start;
    $dur_base = (float)$row['duration'];
    
    if ($offset_total < $dur_base) {
        $src = $p2 . $id . ".ogg";
        $off = $offset_total;
        $dur = $dur_base;
        $state = "BASE";
    } else {
        $src = $p3 . $id . ".ogg";
        $off = $offset_total - $dur_base;
        $dur = (float)$row['duration_extra'];
        $state = "EXTRA";
    }

    if (file_exists($src) && ($off < $dur)) {
        // Taglio WAV istantaneo
        $cmd = "/usr/bin/ffmpeg -y -ss " . fmt_liq($off) . " -i " . escapeshellarg($src) . " -t " . fmt_liq($dur - $off) . " -acodec pcm_s16le -ar 22050 -ac 1 $cut_file 2>&1";
        
        exec($cmd, $out, $ret);

        if ($ret === 0) {
            log_sched($logfile, $now, $id, $cut_file, $off, $state, $epoch_start);
            echo "annotate:title=\"" . addslashes($row['title']) . "\",artist=\"" . addslashes($row['author']) . "\":$cut_file";
            mysqli_close($con);
            exit;
        } else {
            log_sched($logfile, $now, $id, "FFMPEG_ERR", $off, "ERROR", $epoch_start);
        }
    } else {
        log_sched($logfile, $now, $id, "EXPIRED_OR_MISSING", $off, $state, $epoch_start);
    }
}

// Fallback
log_sched($logfile, $now, "GLUE", $glue, 0, "FALLBACK", 0);
echo $glue;
mysqli_close($con);
