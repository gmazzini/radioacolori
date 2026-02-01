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
$used_today = []; // Daily uniqueness filter

// 2. COUNTERS
$time_music = 0.0; $time_vocal = 0.0;
$count_s1 = 0; $count_s2 = 0;

echo "--- Generation Start: $generation_day_label (Epoch: $current_epoch) ---\n";

/**
 * 3. GENERATION LOOP
 */
while ($current_epoch < $end_threshold) {
    $remaining = $end_threshold - $current_epoch;
    $current_hour = (int)date('G', $current_epoch);
    $current_ratio = ($time_vocal > 0) ? ($time_music / $time_vocal) : 999;
    
    // Exclusion of tracks already used in this 24h run
    $exclude_sql = !empty($used_today) ? "AND id NOT IN " . sql_list($con, $used_today) : "";

    /**
     * DECISION LOGIC
     * Priority 1: Closing day (Filler music < 4 min)
     * Priority 2: Night shift (22-04 UTC) -> Force Music (Score 1)
     * Priority 3: Ratio Protection -> Force Music
     * Priority 4: Standard Day -> Vocal/Special (Score 2 priority)
     */
    if ($remaining < 3600) {
        $mode = "FILLER";
        $where = "score > 0 AND genre NOT IN $special_list AND genre NOT IN $avoid_list AND (duration + duration_extra) < 240 $exclude_sql";
        $order = "(duration + duration_extra) ASC";
    } elseif ($current_hour >= 22 || $current_hour < 4 || $current_ratio < $ratio) {
        $mode = "MUSIC";
        $where = "score > 0 AND genre NOT IN $special_list AND genre NOT IN $avoid_list $exclude_sql";
        // Even in music, we prefer Score 2 (Premium Music) if available
        $order = "score DESC, last ASC";
    } else {
        $mode = "VOCAL";
        $where = "score > 0 AND genre IN $special_list $exclude_sql";
        // GID grouping: sort by GID and GSEL to maintain sequence logic
        $order = "gid DESC, gsel ASC, score DESC, last ASC";
    }

    // Candidate selection (pool of 30 for randomization)
    $sql = "SELECT id, duration, duration_extra, score, genre, gid, gsel FROM track 
            WHERE $where AND genre NOT IN $avoid_list 
            ORDER BY $order LIMIT 30";
    
    $res = mysqli_query($con, $sql);
    $candidates = [];
    while($r = mysqli_fetch_assoc($res)) $candidates[] = $r;

    if (count($candidates) > 0) {
        $track = $candidates[array_rand($candidates)];
    } else {
        // Fallback: ignore daily uniqueness if we run out of tracks
        $q_fb = mysqli_query($con, "SELECT id, duration, duration_extra, score, genre, gid, gsel FROM track WHERE score > 0 AND genre NOT IN $avoid_list ORDER BY last ASC LIMIT 1");
        $track = mysqli_fetch_assoc($q_fb);
    }

    if (!$track) break;

    /**
     * GID / THEMATIC GROUPING
     * If we picked a vocal track with a GID, we fetch the next 2 items in sequence
     */
    $batch = [$track];
    if (in_array($track['genre'], $special) && !empty($track['gid'])) {
        $gid_esc = mysqli_real_escape_string($con, $track['gid']);
        $next_gsel = (int)$track['gsel'] + 1;
        
        $q_group = mysqli_query($con, "SELECT id, duration, duration_extra, score, genre, gid, gsel FROM track 
                                      WHERE gid = '$gid_esc' AND gsel >= $next_gsel $exclude_sql
                                      ORDER BY gsel ASC LIMIT 2");
        while($g_row = mysqli_fetch_assoc($q_group)) {
            $batch[] = $g_row;
        }
    }

    /**
     * INSERTION BLOCK
     */
    foreach ($batch as $t) {
        $id_esc = mysqli_real_escape_string($con, $t['id']);
        $d_raw = (float)$t['duration'] + (float)$t['duration_extra'];
        
        if (mysqli_query($con, "INSERT INTO lineup (epoch, id) VALUES ($current_epoch, '$id_esc')")) {
            mysqli_query($con, "UPDATE track SET used = used + 1, last = $current_epoch WHERE id = '$id_esc'");
            $used_today[] = $t['id']; 
            
            if (in_array($t['genre'], $special)) $time_vocal += $d_raw;
            else $time_music += $d_raw;
            
            if ((int)$t['score'] === 2) $count_s2++; else $count_s1++;
            $current_epoch += (int)ceil($d_raw);
        }
        if ($current_epoch >= $end_threshold) break;
    }
}

/**
 * 4. FINAL REPORT
 */
$total_items = $count_s1 + $count_s2;
echo "\n--- Generation Report: $generation_day_label ---\n";
echo "Tracks scheduled: $total_items (Unique: " . count($used_today) . ")\n";
echo "Score 2 usage: " . round(($count_s2 / ($total_items ?: 1)) * 100, 1) . "%\n";
echo "Time Ratio (M/V): " . round($time_music / ($time_vocal ?: 1), 2) . " (Target: $ratio)\n";
echo "End Time: " . date('Y-m-d H:i:s', $current_epoch) . " UTC (Drift: " . ($current_epoch - $end_threshold) . "s)\n";

mysqli_close($con);
?>
