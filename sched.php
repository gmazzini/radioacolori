<?php
include "local.php";

$p2   = "/home/ices/music/ogg04/";
$p3   = "/home/ices/music/ogg04v/";
$glue = "/home/ices/music/glue.ogg";
$cut_file = "/run/cutted.ogg";
$logfile  = "/home/ices/sched.log";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = microtime(true);
$now_int = (int)floor($now);

function fmt_liq($v) {
    return rtrim(rtrim(sprintf('%.3f', max(0, (float)$v)), '0'), '.');
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
    $offset = $now - (float)$row['epoch'];
    $dur_base = (float)$row['duration'];
    
    // Scegliamo la sorgente (BASE o EXTRA)
    if ($offset < $dur_base) {
        $src = $p2 . $id . ".ogg";
        $off = $offset;
        $dur = $dur_base;
    } else {
        $src = $p3 . $id . ".ogg";
        $off = $offset - $dur_base;
        $dur = (float)$row['duration_extra'];
    }

    if (file_exists($src) && ($off < $dur)) {
        // COMANDO FFmpeg
        $cmd = "/usr/bin/ffmpeg -y -ss " . fmt_liq($off) . " -i " . escapeshellarg($src) . " -t " . fmt_liq($dur - $off) . " -c copy $cut_file 2>&1";
        
        exec($cmd, $out, $ret);

        if ($ret === 0 && file_exists($cut_file)) {
            echo "annotate:title=\"" . addslashes($row['title']) . "\",artist=\"" . addslashes($row['author']) . "\":$cut_file";
            file_put_contents($logfile, "[" . date("Y-m-d H:i:s") . "] SUCCESS: Created $cut_file (ID: $id)\n", FILE_APPEND);
            exit;
        } else {
            // Se fallisce logghiamo il comando e l'errore
            $err = implode(" ", $out);
            file_put_contents($logfile, "[" . date("Y-m-d H:i:s") . "] FFMPEG FAIL: $cmd | ERROR: $err\n", FILE_APPEND);
        }
    } else {
        file_put_contents($logfile, "[" . date("Y-m-d H:i:s") . "] FILE MISSING OR OFFSET OUT: $src (Offset: $off)\n", FILE_APPEND);
    }
}

echo $glue;
mysqli_close($con);
