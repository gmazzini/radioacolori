<?php
include "local.php";

// --- CONFIGURATION ---
$threshold = 3.0; // Max gap to fill by starting next track early
$p2 = "/home/radio/music/ogg04/";
$p3 = "/home/radio/music/ogg04v/";
$glue = "/home/radio/music/glue.wav"; 
$logfile = "/home/radio/sched.log";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
$now = microtime(true) - 126000;
$now_int = (int)floor($now);

function log_sched($msg) {
    global $logfile, $now;
    $timestamp = date("Y-m-d H:i:s", (int)$now) . substr(sprintf('%.3f', $now - floor($now)), 1);
    file_put_contents($logfile, "[$timestamp] $msg\n", FILE_APPEND);
}

// --- CLEANUP ---
// Remove temp files older than 4 hour
foreach (glob("/run/sched_*.wav") as $f) {
    if (filemtime($f) < ($now - 14400)) @unlink($f);
}

if (!$con) exit;

// --- 1. GET THE VALID TRACK ---
// Select the first track that hasn't finished yet (considering threshold)
$query = "SELECT l.epoch, l.id, t.duration, t.duration_extra, t.title, t.author 
          FROM lineup l JOIN track t ON l.id = t.id 
          WHERE ($now <= (l.epoch + t.duration + t.duration_extra - $threshold))
          ORDER BY l.epoch ASC LIMIT 1";

$res = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($res);

if ($row) {
    $epoch_start = (float)$row['epoch'];
    $raw_drift = $now - $epoch_start;

    // --- 2. GAP VS EARLY START LOGIC ---
    if ($raw_drift < 0) {
        if (abs($raw_drift) <= $threshold) {
            // Gap is small: Start next track early from the beginning
            $safe_drift = 0;
            log_sched("EARLY_START | ID:{$row['id']} | Gap was ".sprintf('%.3f', abs($raw_drift))."s");
        } else {
            // Gap is too large: Play glue
            log_sched("GAP | Next track in ".sprintf('%.2f', abs($raw_drift))."s | Playing glue");
            echo $glue; exit;
        }
    } else {
        // Normal operation: Seek into the file to stay synced
        $safe_drift = $raw_drift;
    }

    // --- 3. FFMPEG EXECUTION ---
    $id = $row['id'];
    $final_dur = ((float)$row['duration'] + (float)$row['duration_extra']) - $safe_drift;
    $cut_file = "/run/sched_" . (int)time() . ".wav";
    $tmp_file = "/run/sched_T" . (int)time() . ".wav";

    $cmd = sprintf(
        "/usr/bin/ffmpeg -y -i %s -i %s -filter_complex '[0:a]atrim=start=%s,asetpts=PTS-STARTPTS[a0];[a0][1:a]concat=n=2:v=0:a=1' -t %s -acodec pcm_s16le -ar 22050 -ac 1 %s 2>&1",
        escapeshellarg($p2.$id.".ogg"),
        escapeshellarg($p3.$id.".ogg"),
        sprintf('%.3f', $safe_drift),
        sprintf('%.3f', $final_dur),
        escapeshellarg($tmp_file)
    );
    exec($cmd, $out, $ret);

    if ($ret === 0) {
        @rename($tmp_file, $cut_file);
        log_sched("PLAY | ID:$id | Drift:".sprintf('%.3f', $safe_drift)."s | Len:".sprintf('%.2f', $final_dur)."s");
        echo "annotate:title=\"" . addslashes($row['title']) . "\",artist=\"" . addslashes($row['author']) . "\",duration=\"" . sprintf('%.2f', $final_dur) . "\":" . $cut_file;
        exit;
    }
}

log_sched("FALLBACK | No track found or FFmpeg failed");
echo $glue;

