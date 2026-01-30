<?php
include "local.php";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = time();
$start_of_day = strtotime("today 00:00:00");
$elapsed_needed = $now - $start_of_day;
$tt = (int) floor($start_of_day / 86400);

$p2   = "/home/ices/music/ogg04/";
$p3   = "/home/ices/music/ogg04v/";
$glue = "/home/ices/music/glue.ogg"; // "disperazione" (path completo)

$query = "SELECT p.id, t.duration, t.duration_extra, t.title, t.author
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

$current_cumulative = 0;

while ($row = mysqli_fetch_assoc($res)) {
    $id_num = (int)$row['id'];
    $id5    = sprintf('%05d', $id_num);

    $d_music = (float)$row['duration'];
    $d_close = (float)$row['duration_extra'];
    $title  = addslashes($row['title']);
    $artist = addslashes($row['author']);

    // segmento MUSICA
    if ($elapsed_needed < ($current_cumulative + $d_music)) {
        $offset = max(0, $elapsed_needed - $current_cumulative);
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=$offset:$p2{$id5}.ogg";
        mysqli_close($con);
        exit;
    }
    $current_cumulative += $d_music;

    // segmento CHIUSURA (stesso id, directory diversa)
    if ($elapsed_needed < ($current_cumulative + $d_close)) {
        $offset = max(0, $elapsed_needed - $current_cumulative);
        echo "annotate:title=\"Chiusura\",artist=\"Radio a Colori\",cue_in=$offset:$p3{$id5}.ogg";
        mysqli_close($con);
        exit;
    }
    $current_cumulative += $d_close;
}

echo $glue;
mysqli_close($con);
?>
