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
    $current_ratio = ($time_vocal > 0) ? ($time_music / $time_vocal) : 999;

    /**
     * DECISION LOGIC
     * Priority 1: If less than 1 hour remains, use only short music (Filler)
     * Priority 2: If ratio is below target, force music
     * Priority 3: Otherwise, play vocal content
     */
    if ($remaining < 3600) { 
        $mode = "filler"; 
        $query_filter = "score = 1 AND (duration + duration_extra) < 240"; // Tracks under 4 mins
        $query_order = "(duration + duration_extra) ASC";
    } elseif ($current_ratio < $ratio) {
        $mode = "music";
        $query_filter = "score = 1";
        $query_order = "last ASC";
    } else {
        $mode = "vocal";
        $query_filter = "score = 2";
        $query_order = "last ASC";
    }

    // Execution of the selection
    $q = mysqli_query($con, "SELECT id, duration, duration_extra, score FROM track 
                             WHERE $query_filter 
                             ORDER BY $query_order LIMIT 1");

    // Fallback: if 'filler' query returns empty, grab any shortest music track
    if (mysqli_num_rows($q) == 0) {
        $fallback_filter = ($mode == "vocal") ? "score = 2" : "score = 1";
        $q = mysqli_query($con, "SELECT id, duration, duration_extra, score FROM track 
                                 WHERE $fallback_filter ORDER BY last ASC LIMIT 1");
    }

    $track = mysqli_fetch_assoc($q);
    if (!$track) {
        echo "Error: No tracks found for mode $mode\n";
        break;
    }

    $id_esc = mysqli_real_escape_string($con, $track['id']);
    $d_raw = (float)$track['duration'] + (float)$track['duration_extra'];
    $dur_totale = (int)ceil($d_raw);

    // Database updates
    if (mysqli_query($con, "INSERT INTO lineup (epoch, id) VALUES ($current_epoch, '$id_esc')")) {
        mysqli_query($con, "UPDATE track SET used = used + 1, last = $current_epoch WHERE id = '$id_esc'");
        
        // Stats update
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
echo "Music Tracks: $count_music (" . gmdate("H:i:s", (int)$time_music) . ")\n";
echo "Vocal Tracks: $count_vocal (" . gmdate("H:i:s", (int)$time_vocal) . ")\n";
echo "Final Measured Ratio: $final_ratio (Target: $ratio)\n";
echo "End Epoch: $current_epoch (" . date('Y-m-d H:i:s', $current_epoch) . " UTC)\n";
echo "Daily drift: " . ($current_epoch - $end_threshold) . " seconds beyond target.\n";

mysqli_close($con);
?>
