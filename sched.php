<?php
include "local.php";

// --- CONFIGURATION ---
$threshold = 3.0; 
$p2 = "/home/radio/music/ogg04/";
$p3 = "/home/radio/music/ogg04v/";
$glue = "/home/radio/music/glue.wav"; 
$logfile = "/home/radio/sched.log";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
$now = microtime(true);
$now_int = (int)floor($now);

function log_sched($msg) {
    global $logfile, $now;
    $timestamp = date("Y-m-d H:i:s", (int)$now) . substr(sprintf('%.3f', $now - floor($now)), 1);
    file_put_contents($logfile, "[$timestamp] $msg\n", FILE_APPEND);
}

// --- CLEANUP ---
foreach (glob("/run/sched_*.wav") as $f) {
    if (filemtime($f) < ($now - 3600)) @unlink($f);
}

if (!$con) exit;

// --- 1. GET THE VALID TRACK (Considering threshold) ---
// We select the track where the current time is before (start + duration - threshold)
$query = "SELECT l.epoch, l.id, t.duration, t.duration_extra, t.title, t.author 
          FROM lineup l JOIN track t ON l.id = t.id 
          WHERE ($now <= (l.epoch + t.duration + t.duration_extra - $threshold))
          ORDER BY l.epoch ASC LIMIT 1";

$res = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($res);

if ($row) {
    $drift = max(0, $now - (float)$row['epoch']);
    
    // --- 2. GAP CHECK ---
    // If the best valid track hasn't started yet, play glue
    if ($now < (float)$row['epoch']) {
        log_sched("GAP | Next track in " . sprintf('%.2f', abs($now - $row['epoch'])) . "s");
        echo $glue; exit;
    }

    // --- 3. FFMPEG EXECUTION ---
    $id = $row['id'];
    $final_dur = ((float)$row['duration'] + (float)$row['duration_extra']) - $drift;
    $cut_file = "/run/sched_" . (int)$row['epoch'] . ".wav";

    $cmd = sprintf(
        "/usr/bin/ffmpeg -y -ss %s -i %s -i %s -filter_complex '[0:a][1:a]concat=n=2:v=0:a=1' -acodec pcm_s16le -ar 22050 -ac 1 %s 2>&1",
        sprintf('%.3f', $drift), escapeshellarg($p2.$id.".ogg"), escapeshellarg($p3.$id.".ogg"), escapeshellarg($cut_file)
    );

    exec($cmd, $out, $ret);

    if ($ret === 0) {
        log_sched("PLAY | ID:$id | Drift:".sprintf('%.3f', $drift)."s | Len:".sprintf('%.2f', $final_dur)."s");
        echo "annotate:title=\"" . addslashes($row['title']) . "\",artist=\"" . addslashes($row['author']) . "\",duration=\"" . sprintf('%.2f', $final_dur) . "\":" . $cut_file;
        exit;
    }
}

log_sched("FALLBACK | Glue");
echo $glue;


