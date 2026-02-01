<?php
include "local.php";

// Set execution environment
set_time_limit(0);
ignore_user_abort(true);
date_default_timezone_set('UTC');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("DB Connection failed");

/**
 * 1. DEFINE THE ANCHOR POINT
 * If the table is empty, we start from 01-02-2026 00:00:00 UTC
 */
$absolute_start = gmmktime(0, 0, 0, 2, 1, 2026); 

// Find the last track in the lineup
$q_last = mysqli_query($con, "
    SELECT l.epoch, t.duration, t.duration_extra 
    FROM lineup l 
    JOIN track t ON l.id = t.id 
    ORDER BY l.epoch DESC LIMIT 1
");

if ($q_last && mysqli_num_rows($q_last) > 0) {
    $row_last = mysqli_fetch_assoc($q_last);
    // Continuity: next start is the exact end of the previous track
    $current_epoch = (int)ceil($row_last['epoch'] + $row_last['duration'] + $row_last['duration_extra']);
} else {
    // First run ever
    $current_epoch = $absolute_start;
}

// Define the end of this generation run (exactly 24 hours later)
$end_threshold = $current_epoch + 86400;
$generation_day_label = date('d-m-Y', $current_epoch);

// 2. COUNTERS FOR RATIO & STATS
$count_music = 0; $count_vocal = 0;
$time_music = 0.0; $time_vocal = 0.0;

echo "--- Generation Start: $generation_day_label (Starting at Epoch $current_epoch) ---\n";

/**
 * 3. GENERATION LOOP
 */
while ($current_epoch < $end_threshold) {
    $remaining = $end_threshold - $current_epoch;

    // Decision Logic: Closure mode (last 10 mins) or Ratio balancing
    if ($remaining < 600) {
        $mode = "filler"; 
    } elseif ($time_vocal == 0 || ($time_music / $time_vocal) >= $ratio) {
        $mode = "vocal"; // score 2
    } else {
        $mode = "music"; // score 1
    }

    // Selection query based on mode
    if ($mode == "filler") {
        // shortest music possible to minimize daily "drift"
        $q = mysqli_query($con, "SELECT id, duration, duration_extra, score FROM track WHERE score=1 ORDER BY (duration + duration_extra) ASC LIMIT 1");
    } elseif ($mode == "vocal") {
        $q = mysqli_query($con, "SELECT id, duration, duration_extra, score FROM track WHERE score=2 ORDER BY last ASC LIMIT 1");
    } else {
        $q = mysqli_query($con, "SELECT id, duration, duration_extra, score FROM track WHERE score=1 ORDER BY last ASC LIMIT 1");
    }

    $track = mysqli_fetch_assoc($q);
    if (!$track) break;

    $id_esc = mysqli_real_escape_string($con, $track['id']);
    $d_raw = (float)$track['duration'] + (float)$track['duration_extra'];
    $dur_totale = (int)ceil($d_raw);

    // Insert into lineup and update track history
    if (mysqli_query($con, "INSERT INTO lineup (epoch, id) VALUES ($current_epoch, '$id_esc')")) {
        mysqli_query($con, "UPDATE track SET used = used + 1, last = $current_epoch WHERE id = '$id_esc'");
        
        // Update local stats
        if ($track['score'] == 2) {
            $count_vocal++;
            $time_vocal += $d_raw;
        } else {
            $count_music++;
            $time_music += $d_raw;
        }
        
        $current_epoch += $dur_totale;
    }
}

/**
 * 4. FINAL REPORT
 */
$final_ratio = ($time_vocal > 0) ? round($time_music / $time_vocal, 2) : "Inf.";
echo "Target Date: $generation_day_label\n";
echo "Music: $count_music tracks (" . gmdate("H:i:s", (int)$time_music) . ")\n";
echo "Vocal: $count_vocal tracks (" . gmdate("H:i:s", (int)$time_vocal) . ")\n";
echo "Measured Ratio: $final_ratio (Target: $ratio)\n";
echo "End Epoch: $current_epoch (" . date('Y-m-d H:i:s', $current_epoch) . " UTC)\n";
echo "Daily drift: " . ($current_epoch - $end_threshold) . " seconds beyond target.\n";

mysqli_close($con);
?>

