<?php
include "local.php";

/**
 * MINIMALIST WHATSAPP LIST VIEW
 * Uses lineup table and real-time epoch matching
 */

date_default_timezone_set('UTC'); // Reference is UTC as per your requirement
$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = microtime(true);
$now_int = (int)floor($now);

// 1. Fetch current track + surrounding context (4 before, 4 after)
// First, find the starting epoch of the track playing right now
$q_current = mysqli_query($con, "SELECT epoch FROM lineup WHERE epoch <= $now_int ORDER BY epoch DESC LIMIT 1");
$row_current = mysqli_fetch_assoc($q_current);
$current_track_epoch = $row_last ? (int)$row_current['epoch'] : $now_int;

// Fetch 4 tracks before, the current one, and 4 tracks after
$sql = "SELECT l.epoch, l.id, t.title, t.author, t.genre, t.duration, t.duration_extra
        FROM (
            (SELECT epoch, id FROM lineup WHERE epoch < $current_track_epoch ORDER BY epoch DESC LIMIT 4)
            UNION
            (SELECT epoch, id FROM lineup WHERE epoch >= $current_track_epoch ORDER BY epoch ASC LIMIT 5)
        ) AS l
        JOIN track t ON l.id = t.id
        ORDER BY l.epoch ASC";

$res = mysqli_query($con, $sql);

// 2. Output generation
header('Content-Type: text/plain; charset=utf-8');

echo "NOW: " . date("H:i:s", $now_int) . " UTC\n";
echo "--------------------------\n";

$found_current = false;
$next_change = 0;

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $start = (int)$r['epoch'];
        $dur_tot = (float)$r['duration'] + (float)$r['duration_extra'];
        $end = $start + $dur_tot;
        
        // Marker for current playing track
        $marker = "  ";
        if ($now >= $start && $now < $end) {
            $marker = ">>";
            $found_current = true;
            $next_change = (int)ceil($end - $now);
        }

        // Clean text for WhatsApp
        $line = $marker . date("H:i", $start) . " " 
              . substr($r['title'], 0, 20) . " - " 
              . substr($r['author'], 0, 15) 
              . " (" . (int)$dur_tot . "s)";
        
        echo $line . "\n";
    }
}

if ($found_current) {
    echo "--------------------------\n";
    echo "NEXT CHANGE IN: {$next_change}s\n";
} else {
    echo "\n[No active tracks found]\n";
}

mysqli_close($con);
?>
