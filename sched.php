<?php
include "local.php";

/**
 * SETTINGS & PATHS
 */
$p2   = "/home/ices/music/ogg04/";  // Base music/vocal content
$p3   = "/home/ices/music/ogg04v/"; // Extra/Closing content
$glue = "/home/ices/music/glue.ogg";
$logfile = "/home/ices/sched.log";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

// Get current exact time with microseconds
$now = microtime(true);
$now_int = (int)floor($now);

/**
 * HELPER FUNCTIONS
 */
function fmt_liq($v) {
    return rtrim(rtrim(sprintf('%.3f', max(0, (float)$v)), '0'), '.');
}

function esc_liq($s) {
    return str_replace(["\\", "\""], ["\\\\", "\\\""], (string)$s);
}

/**
 * DETAILED LOGGING
 * Format: [Current Timestamp] ID: [TrackID] Offset: [cue_in] Type: [BASE/EXTRA] Epoch_Start: [DB_Epoch] Path: [file]
 */
function log_sched($logfile, $now, $id, $path, $shift, $state, $epoch_start) {
    $date_str = date("Y-m-d H:i:s", (int)$now) . substr(sprintf('%.3f', $now - floor($now)), 1);
    $line = "[$date_str] ID:$id Offset:".fmt_liq($shift)." Type:$state Start:$epoch_start Path:$path\n";
    @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * QUERY LINEUP
 */
$query = "SELECT l.epoch, l.id, t.duration, t.duration_extra, t.title, t.author
          FROM lineup l
          JOIN track t ON l.id = t.id
          WHERE l.epoch <= $now_int
          ORDER BY l.epoch DESC LIMIT 5";

$res = mysqli_query($con, $query);
if (!$res) {
    log_sched($logfile, $now, "GLUE", $glue, 0.0, "ERROR_DB", 0);
    echo $glue;
    mysqli_close($con);
    exit;
}

while ($row = mysqli_fetch_assoc($res)) {
    $start_track = (float)$row['epoch'];
    $dur_base    = (float)$row['duration'];
    $dur_extra   = (float)$row['duration_extra'];
    
    $id     = (string)$row['id'];
    $title  = esc_liq($row['title']);
    $artist = esc_liq($row['author']);

    // 1. Check BASE CONTENT (ogg04)
    $base_end = $start_track + $dur_base;
    if ($now >= $start_track && $now < $base_end) {
        $offset = $now - $start_track;
        $path = $p2 . $id . ".ogg";
        
        log_sched($logfile, $now, $id, $path, $offset, "BASE", $row['epoch']);
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=" . fmt_liq($offset) . ":" . $path;
        mysqli_close($con);
        exit;
    }

    // 2. Check EXTRA CONTENT (ogg04v)
    $extra_start = $base_end;
    $extra_end   = $extra_start + $dur_extra;
    if ($dur_extra > 0 && $now >= $extra_start && $now < $extra_end) {
        $offset = $now - $extra_start;
        $path = $p3 . $id . ".ogg";
        
        log_sched($logfile, $now, $id, $path, $offset, "EXTRA", (int)$extra_start);
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=" . fmt_liq($offset) . ":" . $path;
        mysqli_close($con);
        exit;
    }
}

// FALLBACK
log_sched($logfile, $now, "GLUE", $glue, 0.0, "GLUE", 0);
echo $glue;

mysqli_close($con);
