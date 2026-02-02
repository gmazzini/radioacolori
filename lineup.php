<?php
include "local.php";

// ENVIRONMENT SETUP
set_time_limit(0);
ignore_user_abort(true);
date_default_timezone_set('UTC');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("DB connection failed\n");
mysqli_set_charset($con, 'utf8mb4');

// SQL list helper
function sql_list($con, $arr) {
    if (empty($arr)) return "('')";
    $esc = array_map(function($v) use ($con) {
        return "'" . mysqli_real_escape_string($con, (string)$v) . "'";
    }, $arr);
    return "(" . implode(",", $esc) . ")";
}

// SQL helpers
function q_or_die($con, $sql, $label = "SQL error") {
    $res = mysqli_query($con, $sql);
    if ($res === false) {
        exit($label . ": " . mysqli_error($con) . "\nSQL: " . $sql . "\n");
    }
    return $res;
}

$special_arr = $special ?? [];
$avoid_arr   = $avoid ?? [];

$special_list = sql_list($con, $special_arr);
$avoid_list   = sql_list($con, $avoid_arr);

// CONTINUITY & ANCHOR POINT
$absolute_start = gmmktime(0, 0, 0, 2, 1, 2026);

$q_last = q_or_die(
    $con,
    "SELECT l.epoch, t.duration, t.duration_extra
     FROM lineup l
     JOIN track t ON l.id = t.id
     ORDER BY l.epoch DESC
     LIMIT 1",
    "Failed to read last lineup item"
);

if (mysqli_num_rows($q_last) > 0) {
    $row_last = mysqli_fetch_assoc($q_last);
    $last_start = (int)$row_last['epoch'];
    $last_dur_sec = (int)max(1, round((float)$row_last['duration'] + (float)$row_last['duration_extra']));
    $current_epoch = $last_start + $last_dur_sec;
} else {
    $current_epoch = $absolute_start;
}

// Generate strictly 24h ahead of the last end
$end_threshold = $current_epoch + 86400;
$generation_day_label = gmdate('Y-m-d', $current_epoch);

// TRACKING VARIABLES
$used_today = [];
$time_music = 0;
$time_vocal = 0;
$count_s1 = 0;
$count_s2 = 0;

$last_was_vocal = false;
$active_gid = null;
$required_music_time = 0.0;

// Group limits per UTC day
$group_time = [];      // [dayKey][gid] => seconds
$group_count = [];     // [dayKey][gid] => count
$group_last_gsel = []; // [dayKey][gid] => last gsel played (sequence progress)

// Defaults if not present in local.php
$ratio = $ratio ?? 2;
$limit_group_time = $limit_group_time ?? 4000;
$limit_group_element = $limit_group_element ?? 5;
$start_high = $start_high ?? (5.0 * 3600);
$end_high = $end_high ?? (22.5 * 3600);

echo "--- Generation Start (UTC day): $generation_day_label ---\n";
echo "Start epoch: $current_epoch\n";
echo "Target end epoch: $end_threshold (+" . ($end_threshold - $current_epoch) . "s)\n";

// GENERATION LOOP
while ($current_epoch < $end_threshold) {
    $remaining = $end_threshold - $current_epoch;

    // UTC time-of-day logic (no DST issues)
    $sec_of_day = $current_epoch % 86400; // 0..86399
    $is_high = ($sec_of_day >= $start_high && $sec_of_day < $end_high);

    // UTC day key for per-day caps and per-day group sequence
    $dayKey = gmdate('Y-m-d', $current_epoch);

    // Optional "no repeat within this generation run"
    $exclude_sql = !empty($used_today) ? "AND id NOT IN " . sql_list($con, $used_today) : "";

    // Enforce group limits (UTC day)
    if ($active_gid) {
        $gt = $group_time[$dayKey][$active_gid] ?? 0;
        $gc = $group_count[$dayKey][$active_gid] ?? 0;
        if ($gt >= $limit_group_time || $gc >= $limit_group_element) {
            $active_gid = null;
        }
    }

    // DECISION LOGIC
    // - Final hour: strict short music filler to minimize drift.
    // - Debt/interleaving: if we owe music or we just played vocal -> music.
    // - Default: vocal (special genres), with optional group sequencing.
    $is_filler_zone = ($remaining < 3600);
    $needs_music_balance = ($time_music < $required_music_time);

    $mode = "";
    $where = "";
    $order = "";

    if ($is_filler_zone) {
        $mode = "FILLER";
        $where = "score > 0
                  AND genre NOT IN $special_list
                  AND genre NOT IN $avoid_list
                  AND (duration + duration_extra) < 180
                  $exclude_sql";
        $order = "(duration + duration_extra) ASC";
    } elseif ($needs_music_balance || $last_was_vocal) {
        $mode = "MUSIC";
        $where = "score > 0
                  AND genre NOT IN $special_list
                  AND genre NOT IN $avoid_list
                  $exclude_sql";
        // High interest: prefer premium; Off hours: rotate by oldest last-played
        $order = $is_high ? "score DESC, last ASC" : "last ASC";
    } else {
        $mode = "VOCAL";

        if ($active_gid) {
            $gid_esc = mysqli_real_escape_string($con, $active_gid);

            // Sequence progress within the same UTC day is more important than "no reuse"
            $last_gsel = $group_last_gsel[$dayKey][$active_gid] ?? 0;
            $last_gsel = (int)$last_gsel;

            $where = "score > 0
                      AND genre IN $special_list
                      AND gid = '$gid_esc'
                      AND gsel > $last_gsel";

            // Do NOT apply $exclude_sql here: we want strict sequence continuity
            $order = "gsel ASC";
        } else {
            $where = "score > 0
                      AND genre IN $special_list
                      $exclude_sql";
            // High interest: prefer premium; Off hours: rotate by oldest last-played
            $order = $is_high ? "score DESC, last ASC" : "last ASC";
        }
    }

    // Candidate selection (top 30 for randomization)
    $sql = "SELECT id, duration, duration_extra, score, genre, gid, gsel
            FROM track
            WHERE $where
              AND genre NOT IN $avoid_list
            ORDER BY $order
            LIMIT 30";

    $res = q_or_die($con, $sql, "Failed to select candidates");

    $candidates = [];
    while ($r = mysqli_fetch_assoc($res)) $candidates[] = $r;

    if (count($candidates) > 0) {
        // Maintain strict sequence for active group; otherwise random pick among top 30
        $track = ($active_gid && $mode === "VOCAL") ? $candidates[0] : $candidates[array_rand($candidates)];
    } else {
        // If we were in an active group, it means we reached end-of-sequence for today.
        // Drop the group and retry selection once as non-group VOCAL or other modes.
        if ($active_gid && $mode === "VOCAL") {
            $active_gid = null;
            continue;
        }

        // Fallback: reset active group and grab oldest eligible music
        $active_gid = null;

        $q_fb = q_or_die(
            $con,
            "SELECT id, duration, duration_extra, score, genre, gid, gsel
             FROM track
             WHERE score > 0
               AND genre NOT IN $special_list
               AND genre NOT IN $avoid_list
             ORDER BY last ASC
             LIMIT 1",
            "Fallback query failed"
        );

        $track = mysqli_fetch_assoc($q_fb);
    }

    if (!$track) {
        exit("No candidate track found (including fallback). Stopped at epoch=$current_epoch\n");
    }

    // INSERTION & COUNTERS UPDATE
    $id_esc = mysqli_real_escape_string($con, (string)$track['id']);
    $dur_sec = (int)max(1, round((float)$track['duration'] + (float)$track['duration_extra']));

    $ins = mysqli_query($con, "INSERT INTO lineup (epoch, id) VALUES ($current_epoch, '$id_esc')");
    if (!$ins) {
        exit("INSERT failed at epoch=$current_epoch id=$id_esc err=" . mysqli_error($con) . "\n");
    }

    $upd = mysqli_query($con, "UPDATE track SET used = used + 1, last = $current_epoch WHERE id = '$id_esc'");
    if (!$upd) {
        exit("UPDATE track failed for id=$id_esc err=" . mysqli_error($con) . "\n");
    }

    $used_today[] = $track['id'];

    $is_vocal = in_array($track['genre'], $special_arr, true);

    if ($is_vocal) {
        $time_vocal += $dur_sec;
        $last_was_vocal = true;

        $gid = !empty($track['gid']) ? (string)$track['gid'] : null;
        $gsel = isset($track['gsel']) ? (int)$track['gsel'] : 0;

        // Activate group if present and still allowed today
        if ($gid) {
            $group_time[$dayKey][$gid]  = ($group_time[$dayKey][$gid]  ?? 0) + $dur_sec;
            $group_count[$dayKey][$gid] = ($group_count[$dayKey][$gid] ?? 0) + 1;
            $group_last_gsel[$dayKey][$gid] = $gsel;

            // Enforce daily caps; if reached, do not keep group active
            if (($group_time[$dayKey][$gid]  ?? 0) >= $limit_group_time ||
                ($group_count[$dayKey][$gid] ?? 0) >= $limit_group_element) {
                $active_gid = null;
            } else {
                $active_gid = $gid;
            }
        } else {
            $active_gid = null;
        }

        // Charge the music debt
        $required_music_time += ($dur_sec * $ratio);
    } else {
        $time_music += $dur_sec;
        $last_was_vocal = false;
        $active_gid = null;
    }

    if ((int)$track['score'] === 2) $count_s2++; else $count_s1++;

    $current_epoch += $dur_sec;
}

// FINAL REPORT
$total_items = $count_s1 + $count_s2;
$score2_perc = ($total_items > 0) ? round(($count_s2 / $total_items) * 100, 1) : 0;

echo "\n--- Final Generation Report (UTC day): $generation_day_label ---\n";
echo "Total tracks: $total_items (Premium score=2: $count_s2)\n";
echo "Premium usage: $score2_perc%\n";
echo "Music time: " . gmdate("H:i:s", $time_music) . " / Vocal time: " . gmdate("H:i:s", $time_vocal) . "\n";
echo "Final ratio (M/V): " . ($time_vocal > 0 ? round($time_music / $time_vocal, 2) : "N/A") . " (Target: $ratio)\n";
echo "End time: " . gmdate('Y-m-d H:i:s', $current_epoch) . " UTC\n";
echo "Daily drift vs threshold: " . ($current_epoch - $end_threshold) . "s\n";
echo "Ahead vs now: " . ($current_epoch - time()) . "s\n";

mysqli_close($con);
?>

