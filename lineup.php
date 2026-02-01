<?php
include "local.php";

// Set execution environment
set_time_limit(0);
ignore_user_abort(true);
date_default_timezone_set('UTC');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("DB Connection failed");

/**
 * SQL list helper
 */
function sql_list($con, $arr) {
    if (empty($arr)) return "('')";
    $esc = array_map(function($v) use ($con) { return "'" . mysqli_real_escape_string($con, $v) . "'"; }, $arr);
    return "(" . implode(",", $esc) . ")";
}

$special_list = sql_list($con, $special);
$avoid_list   = sql_list($con, $avoid);

/**
 * 1. DEFINE CONTINUITY
 */
$absolute_start = gmmktime(0, 0, 0, 2, 1, 2026); 
$q_last = mysqli_query($con, "SELECT l.epoch, t.duration, t.duration_extra FROM lineup l JOIN track t ON l.id = t.id ORDER BY l.epoch DESC LIMIT 1");

if ($q_last && mysqli_num_rows($q_last) > 0) {
    $row_last = mysqli_fetch_assoc($q_last);
    $current_epoch = (int)ceil($row_last['epoch'] + $row_last['duration'] + $row_last['duration_extra']);
} else {
    $current_epoch = $absolute_start;
}

$end_threshold = $current_epoch + 86400;
$generation_day_label = date('d-m-Y', $current_epoch);

// 2. COUNTERS
$time_music = 0.0; $time_vocal = 0.0;
$count_s1 = 0; $count_s2 = 0;

echo "--- Generation Start: $generation_day_label (Starting at Epoch $current_epoch) ---\n";

/**
 * 3. GENERATION LOOP
 */
while ($current_epoch < $end_threshold) {
    $remaining = $end_threshold - $current_epoch;
    $current_hour = (int)date('G', $current_epoch);
    $current_ratio = ($time_vocal > 0) ? ($time_music / $time_vocal) : 999;

    /**
     * DECISION LOGIC
     */
    if ($remaining < 3600) {
        $mode = "FILLER";
        $where = "genre NOT IN $special_list AND genre NOT IN $avoid_list AND (duration + duration_extra) < 240";
        $order = "(duration + duration_extra) ASC";
        $pool_size = 5; // Keep it tight for precision
    } elseif ($current_hour >= 22 || $current_hour < 4 || $current_ratio < $ratio) {
        $mode = "MUSIC";
        // To boost quality, we focus on Score 2 even in Music mode
        $where = "genre NOT IN $special_list AND genre NOT IN $avoid_list";
        $order = "score DESC, last ASC";
        $pool_size = 30; 
    } else {
        $mode = "VOCAL";
        $where = "genre IN $special_list";
        $order = "score DESC, last ASC";
        $pool_size = 30;
    }

    // Selection with Random Pick from Top Candidates
    $sql = "SELECT id, duration, duration_extra, score, genre FROM track WHERE $where AND genre NOT IN $avoid_list ORDER BY $order LIMIT $pool_size";
    $res = mysqli_query($con, $sql);
    
    $candidates = [];
    while($row = mysqli_fetch_assoc($res)) $candidates[] = $row;

    if (count($candidates) > 0) {
        $track = $candidates[array_rand($candidates)];
    } else {
        // Absolute fallback
        $q_fb = mysqli_query($con, "SELECT id, duration, duration_extra, score, genre FROM track WHERE genre NOT IN $avoid_list ORDER BY last ASC LIMIT 1");
        $track = mysqli_fetch_assoc($q_fb);
    }

    if (!$track) break;

    $id_esc = mysqli_real_escape_string($con, $track['id']);
    $d_raw = (float)$track['duration'] + (float)$track['duration_extra'];
    
    if (mysqli_query($con, "INSERT INTO lineup (epoch, id) VALUES ($current_epoch, '$id_esc')")) {
        mysqli_query($con, "UPDATE track SET used = used + 1, last = $current_epoch WHERE id = '$id_esc'");
        
        if (in_array($track['genre'], $special)) $time_vocal += $d_raw;
        else $time_music += $d_raw;
        
        if ($track['score'] == 2) $count_s2++; else $count_s1++;
        
        $current_epoch += (int)ceil($d_raw);
    }
}

/**
 * 4. FINAL REPORT
 */
$total_tracks = $count_s1 + $count_s2;
$final_ratio = ($time_vocal > 0) ? round($time_music / $time_vocal, 2) : "Inf.";

echo "Target Date: $generation_day_label\n";
echo "Music Time: " . gmdate("H:i:s", (int)$time_music) . "\n";
echo "Vocal Time: " . gmdate("H:i:s", (int)$time_vocal) . "\n";
echo "Ratio Measured: $final_ratio (Target: $ratio)\n";
echo "Score 2 usage: " . round(($count_s2 / $total_tracks) * 100, 1) . "% ($count_s2 tracks)\n";
echo "End Time: " . date('Y-m-d H:i:s', $current_epoch) . " UTC (Drift: " . ($current_epoch - $end_threshold) . "s)\n";

mysqli_close($con);
?>

