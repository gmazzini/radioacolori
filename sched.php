<?php
include "local.php";

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

foreach (glob("/run/sched_*.wav") as $old_file) {
    if (preg_match('/sched_(\d+)\.wav/', $old_file, $matches)) {
        if ((int)$matches[1] < ($now_int - 3600)) {
            @unlink($old_file);
        }
    }
}

if (!$con) {
    log_sched("DB_CONNECTION_ERROR: " . mysqli_connect_error());
    echo $glue; exit;
}

$query = "SELECT l.epoch, l.id, t.duration, t.duration_extra, t.title, t.author 
          FROM lineup l JOIN track t ON l.id = t.id 
          WHERE l.epoch <= $now_int ORDER BY l.epoch DESC LIMIT 1";

$res = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($res);

if ($row) {
    $epoch_start = (int)$row['epoch'];
    $dur_total = (float)$row['duration'] + (float)$row['duration_extra'];
    $drift = $now - (float)$epoch_start;

    if ($drift >= $dur_total) {
        $query_next = "SELECT l.epoch, l.id, t.duration, t.duration_extra, t.title, t.author 
                       FROM lineup l JOIN track t ON l.id = t.id 
                       WHERE l.epoch > $epoch_start ORDER BY l.epoch ASC LIMIT 1";
        $res_next = mysqli_query($con, $query_next);
        $row = mysqli_fetch_assoc($res_next);
        
        if ($row) {
            $epoch_start = (int)$row['epoch'];
            $drift = $now - (float)$epoch_start;
            if ($drift < 0) {
                log_sched("GAP_WAIT | Playing glue until next track @ $epoch_start");
                echo $glue; exit;
            }
        }
    }
}

if ($row) {
    $id = $row['id'];
    $final_duration = ((float)$row['duration'] + (float)$row['duration_extra']) - $drift;
    

    $cut_file = "/run/sched_" . $epoch_start . ".wav";
    
    $cmd = sprintf(
        "/usr/bin/ffmpeg -y -ss %s -i %s -i %s -filter_complex '[0:a][1:a]concat=n=2:v=0:a=1' -acodec pcm_s16le -ar 22050 -ac 1 %s 2>&1",
        sprintf('%.3f', max(0, $drift)), 
        escapeshellarg($p2 . $id . ".ogg"), 
        escapeshellarg($p3 . $id . ".ogg"), 
        escapeshellarg($cut_file)
    );

    exec($cmd, $out, $ret);

    if ($ret === 0) {
        log_sched("PLAY_OK | ID:$id | Drift:".sprintf('%.3f', $drift)."s | Len:".sprintf('%.2f', $final_duration)."s");
        echo "annotate:title=\"" . addslashes($row['title']) . 
             "\",artist=\"" . addslashes($row['author']) . 
             "\",duration=\"" . sprintf('%.2f', $final_duration) . 
             "\":" . $cut_file;
        exit;
    }
}

echo $glue;

