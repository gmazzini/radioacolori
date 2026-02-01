<?php
include "local.php";

/** 
 * MINIMALIST WHATSAPP LIST VIEW 
 * Dual Time: UTC + LOCAL (ITA)
 */

// Force UTC for internal calculations
date_default_timezone_set('UTC');
$local_tz = new DateTimeZone('Europe/Rome');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = microtime(true);
$now_int = (int)floor($now);

// 1. Get the current playing track starting point
$q_curr = mysqli_query($con, "SELECT epoch FROM lineup WHERE epoch <= $now_int ORDER BY epoch DESC LIMIT 1");
$row_curr = mysqli_fetch_assoc($q_curr);
$current_track_epoch = $row_curr ? (int)$row_curr['epoch'] : $now_int;

// 2. Fetch context: 4 tracks before, the current one, and 4 tracks after
$sql = "SELECT l.epoch, l.id, t.title, t.author, t.duration, t.duration_extra
        FROM (
            (SELECT epoch, id FROM lineup WHERE epoch < $current_track_epoch ORDER BY epoch DESC LIMIT 4)
            UNION
            (SELECT epoch, id FROM lineup WHERE epoch >= $current_track_epoch ORDER BY epoch ASC LIMIT 5)
        ) AS l
        JOIN track t ON l.id = t.id
        ORDER BY l.epoch ASC";

$res = mysqli_query($con, $sql);

// 3. Plain text output
header('Content-Type: text/plain; charset=utf-8');

echo "NOW: " . date("H:i:s", $now_int) . " UTC\n";
echo "------------------------------\n";

$found_current = false;
$next_change = 0;

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $start_epoch = (int)$r['epoch'];
        $dur_tot = (float)$r['duration'] + (float)$r['duration_extra'];
        $end_epoch = $start_epoch + $dur_tot;
        
        // Convert times for display
        $dt = new DateTime("@" . $start_epoch);
        $utc_time = $dt->format("H:i");
        $dt->setTimezone($local_tz);
        $loc_time = $dt->format("H:i");

        // Current track marker
        $marker = "  ";
        if ($now >= $start_epoch && $now < $end_epoch) {
            $marker = ">>";
            $found_current = true;
            $next_change = (int)ceil($end_epoch - $now);
        }

        // Output line: [MARK][UTC/LOC] TITLE (DUR)
        // Shortened to fit mobile screens
        $title = mb_strimwidth($r['title'], 0, 18, "..");
        $author = mb_strimwidth($r['author'], 0, 12, "..");
        
        echo sprintf("%s %s/%s %s-%s (%ds)\n", 
            $marker, 
            $utc_time, 
            $loc_time, 
            $title, 
            $author, 
            (int)$dur_tot
        );
    }
}

if ($found_current) {
    echo "------------------------------\n";
    echo "NEXT SWAP IN: {$next_change}s\n";
} else {
    echo "\n[No active schedule found]\n";
}

mysqli_close($con);
?>
