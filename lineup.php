<?php
include "local.php";

set_time_limit(0);
ignore_user_abort(true);

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$start_of_day = strtotime("today 00:00:00");
$tt = (int)floor($start_of_day / 86400);

function sql_in_list($con, $arr) {
    if (!is_array($arr) || count($arr) === 0) return "(NULL)";
    $out = [];
    foreach ($arr as $v) $out[] = "'" . mysqli_real_escape_string($con, (string)$v) . "'";
    return "(" . implode(",", $out) . ")";
}

$listin = sql_in_list($con, $special);
$listout = sql_in_list($con, array_merge(is_array($special) ? $special : [], is_array($avoid) ? $avoid : []));

$idm2 = [];
$q = mysqli_query($con, "SELECT id FROM track WHERE score=2 AND genre NOT IN $listout ORDER BY last ASC, RAND()");
while ($q && ($row = mysqli_fetch_assoc($q))) $idm2[] = (int)$row["id"];
if ($q) mysqli_free_result($q);

$idm1 = [];
$q = mysqli_query($con, "SELECT id FROM track WHERE score=1 AND genre NOT IN $listout ORDER BY last ASC, RAND()");
while ($q && ($row = mysqli_fetch_assoc($q))) $idm1[] = (int)$row["id"];
if ($q) mysqli_free_result($q);

$idc = [];
$q = mysqli_query($con, "SELECT id, duration, gsel, gid FROM track WHERE score=2 AND genre IN $listin AND (gsel=0 OR gsel=1) ORDER BY last ASC, RAND()");
while ($q && ($row = mysqli_fetch_assoc($q))) {
    $gsel = (int)$row["gsel"];
    $gid  = (string)$row["gid"];

    if ($gsel === 0 || $gid === "") {
        $idc[] = (int)$row["id"];
        continue;
    }

    $gid_esc = mysqli_real_escape_string($con, $gid);
    $q2 = mysqli_query($con, "SELECT id, duration, gsel FROM track WHERE gid='$gid_esc' ORDER BY last ASC, gsel ASC");
    if (!$q2) {
        $idc[] = (int)$row["id"];
        continue;
    }

    $aux = [];
    while ($row2 = mysqli_fetch_assoc($q2)) {
        $aux[] = [
            "id" => (int)$row2["id"],
            "duration" => (float)$row2["duration"],
            "gsel" => (int)$row2["gsel"],
        ];
    }
    mysqli_free_result($q2);

    if (count($aux) === 0) {
        $idc[] = (int)$row["id"];
        continue;
    }

    $startIdx = 0;
    for ($i = 1; $i < count($aux); $i++) {
        if ($aux[$i]["gsel"] !== $aux[$i - 1]["gsel"] + 1) {
            $startIdx = $i;
            break;
        }
    }

    $seq = [];
    for ($i = $startIdx; $i < count($aux); $i++) $seq[] = $aux[$i];
    for ($i = 0; $i < $startIdx; $i++) $seq[] = $aux[$i];

    $group_time = 0.0;
    $group_element = 0;
    foreach ($seq as $e) {
        $idc[] = (int)$e["id"];
        $group_element++;
        $group_time += (float)$e["duration"];
        if ($group_time >= (float)$limit_group_time || $group_element >= (int)$limit_group_element) break;
    }
}
if ($q) mysqli_free_result($q);

if (count($idm2) === 0 && count($idm1) > 0) $idm2 = $idm1;
if (count($idm1) === 0 && count($idm2) > 0) $idm1 = $idm2;
if (count($idm1) === 0 && count($idm2) === 0) { mysqli_close($con); exit; }

mysqli_query($con, "DELETE FROM playlist WHERE tt=$tt");

$mytype = 1;
$position = 0;
$ic = 0;
$im2 = 0;
$im1 = 0;

$tot_time = 0.0;
$music_time = 0.0;
$content_time = 0.0;

$target_total = 87000.0;
$max_iters = 200000;

for ($iter = 0; $iter < $max_iters; $iter++) {
    if ($mytype == 1 && count($idc) > 0) {
        $selid = $idc[$ic++];
        if ($ic >= count($idc)) $ic = 0;
    } else {
        if ($tot_time > (float)$start_high && $tot_time < (float)$end_high) {
            $selid = $idm2[$im2++];
            if ($im2 >= count($idm2)) $im2 = 0;
        } else {
            $selid = $idm1[$im1++];
            if ($im1 >= count($idm1)) $im1 = 0;
        }
    }

    $selid = (int)$selid;

    $qr = mysqli_query($con, "SELECT duration, duration_extra, title, author, score FROM track WHERE id=$selid");
    $row = $qr ? mysqli_fetch_assoc($qr) : null;
    if ($qr) mysqli_free_result($qr);
    if (!$row) {
        echo "Missing track id=$selid\n";
        break;
    }

    $d = (float)$row["duration"];
    $e = (float)$row["duration_extra"];
    if ($d < 0) $d = 0.0;
    if ($e < 0) $e = 0.0;

    $tot_time += ($d + $e);
    if ($mytype == 1) $content_time += $d;
    else $music_time += $d;

    mysqli_query($con, "INSERT INTO playlist (tt,id,position) VALUES ($tt,$selid,$position)");
    $position++;
    mysqli_query($con, "UPDATE track SET used=used+1,last=$tt WHERE id=$selid");

    if ($content_time <= 0.0) {
        $mytype = (count($idc) > 0) ? 1 : 0;
    } else {
        $mytype = (($music_time / $content_time) < (float)$ratio) ? 0 : 1;
        if ($mytype == 1 && count($idc) == 0) $mytype = 0;
    }

    if ($tot_time >= $target_total) break;
}

echo "tt=$tt rows=$position tot_time=$tot_time music_time=$music_time content_time=$content_time\n";

mysqli_close($con);
?>
