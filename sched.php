<?php
include "local.php";

$p2   = "/home/radio/music/ogg04/";
$p3   = "/home/radio/music/ogg04v/";
$glue = "/home/radio/music/glue.wav";
$cut_file = "/run/cutted.wav";
$logfile  = "/home/radio/sched.log";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
$now = microtime(true);
$now_int = (int)floor($now);

function log_sched($msg) {
    global $logfile, $now;
    $timestamp = date("Y-m-d H:i:s", (int)$now) . substr(sprintf('%.3f', $now - floor($now)), 1);
    file_put_contents($logfile, "[$timestamp] $msg\n", FILE_APPEND);
}

// 1. Database Check
if (!$con) {
    log_sched("DB_ERROR: " . mysqli_connect_error());
    echo $glue; exit;
}

// 2. Fetch Current Track
$query = "SELECT l.epoch, l.id, t.duration, t.title, t.author 
          FROM lineup l JOIN track t ON l.id = t.id 
          WHERE l.epoch <= $now_int ORDER BY l.epoch DESC LIMIT 1";

$res = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($res);

if ($row) {
    $id = $row['id'];
    $drift = $now - (float)$row['epoch'];
    $src_base  = $p2 . $id . ".ogg";
    $src_extra = $p3 . $id . ".ogg";

    // Precise FFmpeg cutting and merging
    $cmd = sprintf(
        "/usr/bin/ffmpeg -y -ss %s -i %s -i %s -filter_complex '[0:a][1:a]concat=n=2:v=0:a=1' -acodec pcm_s16le -ar 22050 -ac 1 %s 2>&1",
        sprintf('%.3f', $drift), 
        escapeshellarg($src_base), 
        escapeshellarg($src_extra), 
        escapeshellarg($cut_file)
    );

    exec($cmd, $out, $ret);

    if ($ret === 0) {
        log_sched("PLAY_OK | ID:$id | Drift:".sprintf('%.3f', $drift)."s | Title: {$row['title']}");
        echo "annotate:title=\"" . addslashes($row['title']) . "\",artist=\"" . addslashes($row['author']) . "\":" . $cut_file;
        exit;
    } else {
        log_sched("FFMPEG_FAIL | ID:$id | Drift:".sprintf('%.3f', $drift)."s | Error: " . implode(" ", $out));
    }
} else {
    log_sched("GAP_DETECTED | No track found for epoch $now_int");
}

// 3. Fallback to Glue
log_sched("FALLBACK | Playing glue.wav");
echo $glue;

