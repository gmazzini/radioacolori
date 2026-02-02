<?php
include "local.php";

// --- CONFIGURATION ---
$min_remain_threshold = 3.0; // Minimum seconds left to play a track, otherwise skip
$p2   = "/home/radio/music/ogg04/";
$p3   = "/home/radio/music/ogg04v/";
$glue = "/home/radio/music/glue.wav"; 
$logfile  = "/home/radio/sched.log";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
$now = microtime(true);
$now_int = (int)floor($now);

function log_sched($msg) {
    global $logfile, $now;
    $timestamp = date("Y-m-d H:i:s", (int)$now) . substr(sprintf('%.3f', $now - floor($now)), 1);
    file_put_contents($logfile, "[$timestamp] $msg\n", FILE_APPEND);
}

// --- CLEANUP LOGIC ---
// Remove old temporary wav files from /run/ (older than 1 hour)
foreach (glob("/run/sched_*.wav") as $old_file) {
    if (preg_match('/sched_(\d+)\.wav/', $old_file, $matches)) {
        if ((int)$matches[1] < ($now_int - 3600)) {
            @unlink($old_file);
        }
    }
}

if (!$con) {
    log_sched("DB_ERROR: " . mysqli_connect_error());
    echo $glue; exit;
}

// --- FETCH CURRENT SCHEDULED TRACK ---
$query = "SELECT l.epoch, l.id, t.duration, t.duration_extra, t.title, t.author 
          FROM lineup l JOIN track t ON l.id = t.id 
          WHERE l.epoch <= $now_int ORDER BY l.epoch DESC LIMIT 1";

$res = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($res);

if ($row) {
    $dur_total = (float)$row['duration'] + (float)$row['duration_extra'];
    $drift = $now - (float)$row['epoch'];

    // --- SKIP PROTECTION ---
    // If remaining time is less than threshold, skip to the next track in lineup
    if ($drift >= ($dur_total - $min_remain_threshold)) {
        log_sched("SKIP_TOO_SHORT | ID:{$row['id']} | Remain:".($dur_total - $drift)."s | Seeking next...");
        
        $query_next = "SELECT l.epoch, l.id, t.duration, t.duration_extra, t.title, t.author 
                       FROM lineup l JOIN track t ON l.id = t.id 
                       WHERE l.epoch > {$row['epoch']} ORDER BY l.epoch ASC LIMIT 1";
        $res_next = mysqli_query($con, $query_next);
        $row = mysqli_fetch_assoc($res_next);
        
        if ($row) {
            $drift = $now - (float)$row['epoch'];
            // If next track hasn't started yet, play glue
            if ($drift < 0) {
                log_sched("GAP_WAIT | Next track in ".abs($drift)."s | Playing glue");
                echo $glue; exit;
            }
        }
    }
}

// --- FFMPEG PROCESSING ---
if ($row) {
    $id = $row['id'];
    $epoch_start = (int)$row['epoch'];
    $final_duration = ((float)$row['duration'] + (float)$row['duration_extra']) - $drift;
    
    // Unique filename per epoch to prevent "Packet corrupt" errors
    $cut_file = "/run/sched_" . $epoch_start . ".wav";
    $src_base  = $p2 . $id . ".ogg";
    $src_extra = $p3 . $id . ".ogg";

    // Concat base and extra, then seek to drift offset
    $cmd = sprintf(
        "/usr/bin/ffmpeg -y -ss %s -i %s -i %s -filter_complex '[0:a][1:a]concat=n=2:v=0:a=1' -acodec pcm_s16le -ar 22050 -ac 1 %s 2>&1",
        sprintf('%.3f', max(0, $drift)), 
        escapeshellarg($src_base), 
        escapeshellarg($src_extra), 
        escapeshellarg($cut_file)
    );

    exec($cmd, $out, $ret);

    if ($ret === 0) {
        log_sched("PLAY_OK | ID:$id | Drift:".sprintf('%.3f', $drift)."s | Len:".sprintf('%.2f', $final_duration)."s | File: $cut_file");
        // Annotated response for Liquidsoap
        echo "annotate:title=\"" . addslashes($row['title']) . 
             "\",artist=\"" . addslashes($row['author']) . 
             "\",duration=\"" . sprintf('%.2f', $final_duration) . 
             "\":" . $cut_file;
        exit;
    } else {
        log_sched("FFMPEG_FAIL | ID:$id | Error: " . implode(" ", $out));
    }
}

// --- FINAL FALLBACK ---
log_sched("FALLBACK | Sending glue file");
echo $glue;


