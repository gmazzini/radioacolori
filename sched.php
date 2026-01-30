<?php
include "local.php";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

// float time (coerente con durate float)
$now = microtime(true);
$start_of_day = strtotime("today 00:00:00"); // int
$elapsed_needed = $now - $start_of_day;      // float

// tolleranza anti “ballo” float (1 ms)
$eps = 0.001;

$tt = (int) floor($start_of_day / 86400);

$p2   = "/home/ices/music/ogg04/";
$p3   = "/home/ices/music/ogg04v/";
$glue = "/home/ices/music/glue.ogg";

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

$current_cumulative = 0.0;

/**
 * Normalizza float come stringa per cue_in:
 * max 3 decimali, punto, senza zeri finali.
 */
function fmt_float_for_liq($v) {
    $v = (float)$v;
    if ($v < 0) $v = 0.0;
    $s = sprintf('%.3f', $v);
    $s = rtrim(rtrim($s, '0'), '.');
    if ($s === '') $s = '0';
    return $s;
}

while ($row = mysqli_fetch_assoc($res)) {
    $id_num = (int)$row['id'];
    $id5    = sprintf('%05d', $id_num);

    $d_music = (float)$row['duration'];
    $d_close = (float)$row['duration_extra'];

    if ($d_music < 0) $d_music = 0.0;
    if ($d_close < 0) $d_close = 0.0;

    // metadati originali del brano (sempre, anche in chiusura)
    $title  = addslashes((string)$row['title']);
    $artist = addslashes((string)$row['author']);

    // segmento MUSICA: [cur, cur + d_music)
    $music_end = $current_cumulative + $d_music;
    if ($elapsed_needed >= ($current_cumulative - $eps) && $elapsed_needed < ($music_end - $eps)) {
        $offset = $elapsed_needed - $current_cumulative;
        $offset_str = fmt_float_for_liq($offset);
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=$offset_str:$p2{$id5}.ogg";
        mysqli_close($con);
        exit;
    }
    $current_cumulative = $music_end;

    // segmento CHIUSURA: [cur, cur + d_close)
    $close_end = $current_cumulative + $d_close;
    if ($d_close > 0.0 && $elapsed_needed >= ($current_cumulative - $eps) && $elapsed_needed < ($close_end - $eps)) {
        $offset = $elapsed_needed - $current_cumulative;
        $offset_str = fmt_float_for_liq($offset);
        // NB: qui usiamo title/artist originali (non "Chiusura")
        echo "annotate:title=\"$title\",artist=\"$artist\",cue_in=$offset_str:$p3{$id5}.ogg";
        mysqli_close($con);
        exit;
    }
    $current_cumulative = $close_end;
}

echo $glue;
mysqli_close($con);
?>
