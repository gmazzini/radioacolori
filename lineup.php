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
    $current_epoch = (int)ceil($row_last['epoch'] + $row_last['duration'] + $row_last['duration_extra']);
} else {
    $current_epoch = $absolute_start;
}

$end_threshold = $current_epoch + 86400;
$generation_day_label = date('d-m-Y', $current_epoch);

// 2. COUNTERS
$count_s1 = 0; $count_s2 = 0;
$time_s1 = 0.0; $time_s2 = 0.0;

echo "--- Generation Start: $generation_day_label (Starting at Epoch $current_epoch) ---\n";

/**
 * 3. GENERATION LOOP
 */
while ($current_epoch < $end_threshold) {
    $remaining = $end_threshold - $current_epoch;
    $current_hour = (int)date('G', $current_epoch); // 0 to 23 (UTC)
    
    // Calculate current ratio (Music/Vocal)
    $current_ratio = ($time_s2 > 0) ? ($time_s1 / $time_s2) : 999;

    /**
     * DECISION LOGIC
     * Priority 1: Final hour filler (Short Music)
     * Priority 2: Night Time (22:00 - 04:00 UTC) -> Prefer Score 1
     * Priority 3: Day Time -> Prefer Score 2 (unless ratio protection kicks in)
     */
    if ($remaining < 3600) { 
        $mode = "filler"; 
        $query_condition = "score = 1 AND (duration + duration_extra) < 240";
        $query_order = "(duration + duration_extra) ASC";
    } elseif ($current_hour >= 22 || $current_hour < 4) {
        // NIGHT MODE: Prefer Score 1
        $mode = "night_music";
        $query_condition = "score = 1";
        $query_order = "last ASC";
    } elseif ($current_ratio > $ratio) {
        // DAY MODE & GOOD RATIO: Prioritize Score 2
        $mode = "day_priority_vocal";
        $query_condition = "score = 2";
        $query_order = "last ASC";
    } else {
        // DAY MODE & LOW RATIO: Force Score 1
        $mode = "day_force_music";
        $query_condition = "score = 1";
        $query_order = "last ASC";
    }

    // Selection query
    $q = mysqli_query($con, "SELECT id, duration, duration_extra, score FROM track 
                             WHERE $query_condition 
                             ORDER BY $query_order LIMIT 1");

    // General Fallback
    if (mysqli_num_rows($q) == 0) {
        $q = mysqli_query($con, "SELECT id, duration, duration_extra, score FROM track 
                                 ORDER BY score DESC, last ASC LIMIT 1");
    }

    $track = mysqli_fetch_assoc($q);
    if (!$track) break;

    $id_esc = mysqli_real_escape_string($con, $track['id']);
    $d_raw = (float)$track['duration'] + (float)$track['duration_extra'];
    $dur_totale = (int)ceil($d_raw);

    if (mysqli_query($con, "INSERT INTO lineup (epoch, id) VALUES ($current_epoch, '$id_esc')")) {
        mysqli_query($con, "UPDATE track SET used = used + 1, last = $current_epoch WHERE id = '$id_esc'");
        
        if ($track['score'] == 2) {
            $count_s2++;
            $time_s2 += $d_raw;
        } else {
            $count_s1++;
            $time_s1 += $d_raw;
        }
        $current_epoch += $dur_totale;
    }
}

/**
 * 4. FINAL REPORT
 */
$total_tracks = $count_s1 + $count_s2;
$perc_s2 = ($total_tracks > 0) ? round(($count_s2 / $total_tracks) * 100, 1) : 0;
$final_ratio = ($time_s2 > 0) ? round($time_s1 / $time_s2, 2) : "Inf.";

echo "Target Date: $generation_day_label\n";
echo "Score 1 (Music): $count_s1 tracks (" . gmdate("H:i:s", (int)$time_s1) . ")\n";
echo "Score 2 (Vocal): $count_s2 tracks (" . gmdate("H:i:s", (int)$time_s2) . ")\n";
echo "Score 2 usage: $perc_s2% of total tracks\n";
echo "Music/Vocal Ratio: $final_ratio (Target: $ratio)\n";
echo "End Time: " . date('Y-m-d H:i:s', $current_epoch) . " UTC\n";
echo "Daily drift: " . ($current_epoch - $end_threshold) . "s\n";

mysqli_close($con);
?>
