<?php
include "local.php";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = microtime(true);
$start_of_day = strtotime("today 00:00:00");
$elapsed_needed = $now - $start_of_day;

$eps = 0.001;

$tt = (int)floor($start_of_day / 86400);

$p2   = "/home/ices/music/ogg04/";
$glue = "/home/ices/music/glue.ogg";

function fmt_float_for_liq($v) {
    $v = (float)$v;
    if ($v < 0) $v = 0.0;
    $s = sprintf('%.3f', $v);
    $s = rtrim(rtrim($s, '0'), '.');
    return ($s === '') ? '0' : $s;
}

function esc_liq_meta($s) {
    $s = (string)$s;
    $s = str_replace("\\", "\\\\", $s);
    $s = str_replace("\"", "\\\"", $s);
    return $s;
}

$query = "SELECT p.id, t.duration, t.title, t.author
          FROM playlist p
          JOIN track t ON p.id = t.id
          WHERE p.tt = $tt
          ORDER BY p.position ASC";

$res = mysqli_query($con, $query);
if (!$res) {
    echo $glue;
    mysqli_close($con);
    exit;
}

$current_cumulative = 0.0;

while ($row = mysqli_fetch_assoc($res)) {
    $id5 = (string)$row['id'];

    $d_music = (float)$row['duration'];
    if ($d_music < 0) $d_music = 0.0;

    $title  = esc_liq_meta($row['title']);
    $artist = esc_liq_meta($row['author']);

    $music_end = $current_cumulative + $d_music;

    if ($elapsed_needed >= ($current_cumulative - $eps) && $elapsed_needed < ($music_end - $eps)) {
        $offset = $elapsed_needed - $current_cumulative;
        $offset_str = fmt_float_for_liq($offset);
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=$offset_str:$p2{$id5}.ogg";
        mysqli_close($con);
        exit;
    }

    $current_cumulative = $music_end;
}

echo $glue;
mysqli_close($con);
?>
