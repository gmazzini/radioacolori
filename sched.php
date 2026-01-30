<?php
include "local.php";
$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = time();
$start_of_day = strtotime("today 00:00:00");
$elapsed_needed = $now - $start_of_day;
$tt = (int)(time() / 86400); 

$p2 = "/home/ices/music/ogg04/";
$p3 = "/home/ices/music/ogg04v/";

$query = "SELECT p.id, t.duration, t.duration_extra, t.title, t.author 
          FROM playlist p 
          JOIN track t ON p.id = t.id 
          WHERE p.tt = $tt 
          ORDER BY p.position ASC";

$res = mysqli_query($con, $query);
$current_cumulative = 0;

while ($row = mysqli_fetch_assoc($res)) {
    $id = $row['id'];
    $d_music = (float)$row['duration'];
    $d_jingle = (float)$row['duration_extra'];
    $title = str_replace('"', '\"', $row['title']);
    $artist = str_replace('"', '\"', $row['author']);

    if ($elapsed_needed < ($current_cumulative + $d_music)) {
        $offset = max(0, $elapsed_needed - $current_cumulative);
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=$offset:$p2$id.ogg\n";
        echo "annotate:title=\"Jingle\",artist=\"Radio a Colori\":$p3$id.ogg"; 
        exit;
    }
    $current_cumulative += $d_music;

    if ($elapsed_needed < ($current_cumulative + $d_jingle)) {
        $offset = max(0, $elapsed_needed - $current_cumulative);
        echo "annotate:title=\"Jingle\",artist=\"Radio a Colori\",cue_in=$offset:$p3$id.ogg";
        exit;
    }
    $current_cumulative += $d_jingle;
}

$query_next = "SELECT p.id, t.title, t.author FROM playlist p JOIN track t ON p.id = t.id WHERE p.tt >= $tt AND p.position > 0 LIMIT 1";
$res_next = mysqli_query($con, $query_next);
$next = mysqli_fetch_assoc($res_next);
if($next) {
    $title = str_replace('"', '\"', $next['title']);
    $artist = str_replace('"', '\"', $next['author']);
    echo "annotate:title=\"$title\",artist=\"$artist\":$p2" . $next['id'] . ".ogg";
}

mysqli_close($con);
?>
