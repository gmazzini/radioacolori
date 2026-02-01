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

// Get current exact time
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

function log_sched($logfile, $epoch, $path, $shift, $state) {
    $line = $epoch . " " . $path . " " . fmt_liq($shift) . " " . $state . "\n";
    @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * QUERY LINEUP
 * Search for the track that started before 'now' and might still be playing.
 * We look at the last few tracks to find the one covering the current microtime.
 */
$query = "SELECT l.epoch, l.id, t.duration, t.duration_extra, t.title, t.author
          FROM lineup l
          JOIN track t ON l.id = t.id
          WHERE l.epoch <= $now_int
          ORDER BY l.epoch DESC LIMIT 5";

$res = mysqli_query($con, $query);
if (!$res) {
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
    // Time range: [start, start + duration)
    $base_end = $start_track + $dur_base;
    if ($now >= $start_track && $now < $base_end) {
        $offset = $now - $start_track;
        $path = $p2 . $id . ".ogg";
        
        log_sched($logfile, $now_int, $path, $offset, "BASE");
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=" . fmt_liq($offset) . ":" . $path;
        mysqli_close($con);
        exit;
    }

    // 2. Check EXTRA CONTENT (ogg04v)
    // Time range: [start + duration, start + duration + duration_extra)
    $extra_start = $base_end;
    $extra_end   = $extra_start + $dur_extra;
    if ($dur_extra > 0 && $now >= $extra_start && $now < $extra_end) {
        // Offset logic: here we NEVER cut the special content start if possible,
        // but for absolute sync with lineup epoch, we calculate offset from its own start.
        $offset = $now - $extra_start;
        $path = $p3 . $id . ".ogg";
        
        log_sched($logfile, $now_int, $path, $offset, "EXTRA");
        // As per request: cue_in applied to extra if called mid-stream
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=" . fmt_liq($offset) . ":" . $path;
        mysqli_close($con);
        exit;
    }
}

// FALLBACK: If nothing is scheduled, play glue
log_sched($logfile, $now_int, $glue, 0.0, "GLUE");
echo $glue;

mysqli_close($con);
