<?php
include "local.php";

/** 
 * ENVIRONMENT SETUP 
 */
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
    $esc = array_map(function($v) use ($con) { return "'" . mysqli_real_escape_string($con, (string)$v) . "'"; }, $arr);
    return "(" . implode(",", $esc) . ")";
}

$special_list = sql_list($con, $special);
$avoid_list   = sql_list($con, $avoid);

/**
 * 1. CONTINUITY & ANCHOR POINT
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

// 2. TRACKING VARIABLES
$used_today = []; 
$time_music = 0.0; $time_vocal = 0.0;
$count_s1 = 0; $count_s2 = 0;

$last_was_vocal = false; 
$active_gid = null;      
$required_music_time = 0.0; // Cumulative music "debt"

echo "--- Generation Start: $generation_day_label ---\n";

/**
 * 3. GENERATION LOOP
 */
while ($current_epoch < $end_threshold) {
    $remaining = $end_threshold - $current_epoch;
    $exclude_sql = !empty($used_today) ? "AND id NOT IN " . sql_list($con, $used_today) : "";

    /**
     * DECISION LOGIC
     * - Final Hour: Strictly Music Filler to hit zero drift.
     * - Debt/Interleaving: If we owe music or just played vocal -> Music.
     * - Default: Vocal (Priority Score 2) if ratio is healthy.
     */
    $is_filler_zone = ($remaining < 3600);
    $needs_music_balance = ($time_music < $required_music_time);

    if ($is_filler_zone) {
        $mode = "FILLER";
        $where = "score > 0 AND genre NOT IN $special_list AND genre NOT IN $avoid_list AND (duration + duration_extra) < 180 $exclude_sql";
        $order = "(duration + duration_extra) ASC";
    } elseif ($needs_music_balance || $last_was_vocal) {
        $mode = "MUSIC";
        $where = "score > 0 AND genre NOT IN $special_list AND genre NOT IN $avoid_list $exclude_sql";
        $order = "score DESC, last ASC"; // Prioritize Premium Music
    } else {
        $mode = "VOCAL";
        if ($active_gid) {
            $gid_esc = mysqli_real_escape_string($con, $active_gid);
            $where = "score > 0 AND genre IN $special_list AND gid = '$gid_esc' $exclude_sql";
            $order = "gsel ASC"; // Sequential group play
        } else {
            $where = "score > 0 AND genre IN $special_list $exclude_sql";
            $order = "score DESC, last ASC"; // Prioritize Premium Vocal
        }
    }

    // Candidate selection (Top 30 for randomization)
    $sql = "SELECT id, duration, duration_extra, score, genre, gid, gsel FROM track 
            WHERE $where AND genre NOT IN $avoid_list 
            ORDER BY $order LIMIT 30";
    $res = mysqli_query($con, $sql);
    
    $candidates = [];
    while($r = mysqli_fetch_assoc($res)) $candidates[] = $r;

    if (count($candidates) > 0) {
        // Maintain sequence for GIDs, otherwise random pick
        $track = ($active_gid && $mode == "VOCAL") ? $candidates[0] : $candidates[array_rand($candidates)];
    } else {
        // Fallback: Reset active group and grab oldest music
        $active_gid = null;
        $q_fb = mysqli_query($con, "SELECT id, duration, duration_extra, score, genre, gid, gsel FROM track 
                                     WHERE score > 0 AND genre NOT IN $special_list AND genre NOT IN $avoid_list 
                                     ORDER BY last ASC LIMIT 1");
        $track = mysqli_fetch_assoc($q_fb);
    }

    if (!$track) break;

    /**
     * INSERTION & DEBT UPDATE
     */
    $id_esc = mysqli_real_escape_string($con, $track['id']);
    $d_raw = (float)$track['duration'] + (float)$track['duration_extra'];
    
    if (mysqli_query($con, "INSERT INTO lineup (epoch, id) VALUES ($current_epoch, '$id_esc')")) {
        mysqli_query($con, "UPDATE track SET used = used + 1, last = $current_epoch WHERE id = '$id_esc'");
        $used_today[] = $track['id']; 
        
        if (in_array($track['genre'], $special)) {
            $time_vocal += $d_raw;
            $last_was_vocal = true;
            $active_gid = !empty($track['gid']) ? $track['gid'] : null;
            // Charge the music debt
            $required_music_time += ($d_raw * $ratio);
        } else {
            $time_music += $d_raw;
            $last_was_vocal = false;
        }
        
        if ((int)$track['score'] === 2) $count_s2++; else $count_s1++;
        $current_epoch += (int)ceil($d_raw);
    }
}

/**
 * 4. FINAL REPORT
 */
$total_items = $count_s1 + $count_s2;
$score2_perc = ($total_items > 0) ? round(($count_s2 / $total_items) * 100, 1) : 0;

echo "\n--- Final Generation Report: $generation_day_label ---\n";
echo "Total Tracks: $total_items (Premium Score 2: $count_s2)\n";
echo "Premium Usage: $score2_perc%\n";
echo "Music Time: " . gmdate("H:i:s", $time_music) . " / Vocal Time: " . gmdate("H:i:s", $time_vocal) . "\n";
echo "Final Ratio (M/V): " . ($time_vocal > 0 ? round($time_music / $time_vocal, 2) : "N/A") . " (Target: $ratio)\n";
echo "End Time: " . date('Y-m-d H:i:s', $current_epoch) . " UTC\n";
echo "Daily Drift: " . ($current_epoch - $end_threshold) . "s\n";

mysqli_close($con);
?>
