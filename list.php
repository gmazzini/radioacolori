<?php
include "local.php";

/** 
 * ULTRA-MINIMALIST WHATSAPP VIEW 
 * Focus: Local Time, ID, Title
 */

date_default_timezone_set('UTC');
$local_tz = new DateTimeZone('Europe/Rome');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = microtime(true);
$now_int = (int)floor($now);

// 1. Locate current playing track
$q_curr = mysqli_query($con, "SELECT epoch FROM lineup WHERE epoch <= $now_int ORDER BY epoch DESC LIMIT 1");
$row_curr = mysqli_fetch_assoc($q_curr);
$current_track_epoch = $row_curr ? (int)$row_curr['epoch'] : $now_int;

// 2. Fetch context (4 before, current, 4 after)
$sql = "SELECT l.epoch, l.id, t.title, t.duration, t.duration_extra
        FROM (
            (SELECT epoch, id FROM lineup WHERE epoch < $current_track_epoch ORDER BY epoch DESC LIMIT 4)
            UNION
            (SELECT epoch, id FROM lineup WHERE epoch >= $current_track_epoch ORDER BY epoch ASC LIMIT 5)
        ) AS l
        JOIN track t ON l.id = t.id
        ORDER BY l.epoch ASC";

$res = mysqli_query($con, $sql);

header('Content-Type: text/plain; charset=utf-8');

$found_current = false;
$next_change = 0;

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $start = (int)$r['epoch'];
        $dur = (float)$r['duration'] + (float)$r['duration_extra'];
        
        // Time Conversion to Local
        $dt = new DateTime("@" . $start);
        $dt->setTimezone($local_tz);
        $time = $dt->format("H:i");

        $marker = " ";
        if ($now >= $start && $now < ($start + $dur)) {
            $marker = ">";
            $found_current = true;
            $next_change = (int)ceil(($start + $dur) - $now);
        }

        // Output format: [>] TIME [ID] TITLE
        // Title is limited to 25 chars to keep line single on most phones
        $title = mb_strimwidth($r['title'], 0, 25, "..");
        
        printf("%s%s [%s] %s\n", 
            $marker, 
            $time, 
            $r['id'], 
            $title
        );
    }
}

if ($found_current) {
    echo "Next: {$next_change}s\n";
}

mysqli_close($con);
?>
