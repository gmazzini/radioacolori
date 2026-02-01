<?php
include "local.php";

$p2   = "/home/ices/music/ogg04/";
$p3   = "/home/ices/music/ogg04v/";
$glue = "/home/ices/music/glue.ogg";
$cut_file = "/run/cutted.wav"; // Usiamo WAV per velocità e precisione
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
        // Taglio istantaneo in WAV (mono, 22050Hz per matchare Liquidsoap)
        $cmd = "/usr/bin/ffmpeg -y -ss " . fmt_liq($off) . " -i " . escapeshellarg($src) . " -t " . fmt_liq($dur - $off) . " -acodec pcm_s16le -ar 22050 -ac 1 $cut_file 2>&1";
        
        exec($cmd, $out, $ret);

        if ($ret === 0) {
            // Passiamo il WAV a Liquidsoap
            echo "annotate:title=\"" . addslashes($row['title']) . "\",artist=\"" . addslashes($row['author']) . "\":$cut_file";
            exit;
        }
    }
}

// Fallback se qualcosa fallisce o siamo in un buco di programmazione
echo $glue;
mysqli_close($con);

