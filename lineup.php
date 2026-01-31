<?php
include "local.php";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$start_of_day = strtotime("today 00:00:00");
$tt = (int) floor($start_of_day / 86400);

function sql_in_list($con, $arr) {
    if (!is_array($arr) || count($arr) === 0) return "(NULL)";
    $out = [];
    foreach ($arr as $v) {
        $out[] = "'" . mysqli_real_escape_string($con, (string)$v) . "'";
    }
    return "(" . implode(",", $out) . ")";
}

// ==== costruzione liste generi ====
$listSpecial = sql_in_list($con, $special);
$avoidAll = is_array($avoid) ? array_values($avoid) : [];
$specialAll = is_array($special) ? array_values($special) : [];
$listOutArr = array_merge($specialAll, $avoidAll);
$listOut = sql_in_list($con, $listOutArr); // generi esclusi dalla musica

// ==== helper: fetch colonne singole in array ====
function fetch_ids($res) {
    $ids = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $ids[] = (int)$row['id'];
    }
    return $ids;
}

// ==== 1) liste musica score 2 / score 1 (esclusi generi in listOut) ====
$q = "SELECT id FROM track
      WHERE score=2 AND genre NOT IN $listOut
      ORDER BY last ASC, RAND()";
$r = mysqli_query($con, $q);
$idm2 = $r ? fetch_ids($r) : [];
if ($r) mysqli_free_result($r);

$q = "SELECT id FROM track
      WHERE score=1 AND genre NOT IN $listOut
      ORDER BY last ASC, RAND()";
$r = mysqli_query($con, $q);
$idm1 = $r ? fetch_ids($r) : [];
if ($r) mysqli_free_result($r);

// fallback se una lista è vuota
if (count($idm2) === 0 && count($idm1) > 0) $idm2 = $idm1;
if (count($idm1) === 0 && count($idm2) > 0) $idm1 = $idm2;

// se entrambe vuote, non puoi fare palinsesto
if (count($idm1) === 0 && count($idm2) === 0) {
    mysqli_close($con);
    exit;
}

// ==== 2) costruzione lista content (score=2, genre in special, gsel 0/1) + gruppi ====
$idc = [];

$q = "SELECT id, duration, gsel, gid
      FROM track
      WHERE score=2
        AND genre IN $listSpecial
        AND (gsel=0 OR gsel=1)
      ORDER BY last ASC, RAND()";
$r = mysqli_query($con, $q);
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $gsel = (int)$row['gsel'];
        $gid  = (string)$row['gid'];

        // singolo content
        if ($gsel === 0 || $gid === '') {
            $idc[] = (int)$row['id'];
            continue;
        }

        // gsel==1 => gruppo: carico tutto il gruppo e scelgo sequenza coerente
        $gid_esc = mysqli_real_escape_string($con, $gid);
        $q2 = "SELECT id, duration, gsel
               FROM track
               WHERE gid='$gid_esc'
               ORDER BY last ASC, gsel ASC";
        $r2 = mysqli_query($con, $q2);
        if (!$r2) {
            // se fallisce, almeno metto l'elemento corrente
            $idc[] = (int)$row['id'];
            continue;
        }

        $aux = [];
        while ($row2 = mysqli_fetch_assoc($r2)) {
            $aux[] = [
                'id' => (int)$row2['id'],
                'duration' => (float)$row2['duration'],
                'gsel' => (int)$row2['gsel'],
            ];
        }
        mysqli_free_result($r2);

        if (count($aux) === 0) {
            $idc[] = (int)$row['id'];
            continue;
        }

        // trova il primo "gap" nella sequenza gsel (gsel non consecutivo)
        $startIdx = 0;
        for ($i = 1; $i < count($aux); $i++) {
            if ($aux[$i]['gsel'] !== $aux[$i-1]['gsel'] + 1) {
                $startIdx = $i;
                break;
            }
        }

        // costruisci sequenza starting da startIdx, poi wrap
        $seq = [];
        for ($i = $startIdx; $i < count($aux); $i++) $seq[] = $aux[$i];
        for ($i = 0; $i < $startIdx; $i++) $seq[] = $aux[$i];

        // applica limiti gruppo
        $group_time = 0.0;
        $group_element = 0;
        foreach ($seq as $e) {
            $idc[] = $e['id'];
            $group_element++;
            $group_time += (float)$e['duration'];
            if ($group_time >= (float)$limit_group_time || $group_element >= (int)$limit_group_element) break;
        }
    }
    mysqli_free_result($r);
}

// fallback content: se vuoto, permetti comunque palinsesto solo musica
if (count($idc) === 0) {
    // se vuoi forzare almeno un minimo, puoi copiarci qualche musica
    // qui lasciamo vuoto e gestiamo in loop
}

// ==== 3) Pre-carico cache track(id -> duration, extra, score, title, author) per evitare SELECT per brano ====
$all_ids = array_unique(array_merge($idm1, $idm2, $idc));
$track = []; // id => data

// chunk IN per evitare query troppo lunga
$chunkSize = 800;
for ($off = 0; $off < count($all_ids); $off += $chunkSize) {
    $chunk = array_slice($all_ids, $off, $chunkSize);
    $in = "(" . implode(",", array_map('intval', $chunk)) . ")";
    $q = "SELECT id, duration, duration_extra, score, title, author
          FROM track
          WHERE id IN $in";
    $r = mysqli_query($con, $q);
    if (!$r) continue;
    while ($row = mysqli_fetch_assoc($r)) {
        $id = (int)$row['id'];
        $track[$id] = [
            'duration' => (float)$row['duration'],
            'extra'    => (float)$row['duration_extra'],
            'score'    => (int)$row['score'],
            'title'    => (string)$row['title'],
            'author'   => (string)$row['author'],
        ];
    }
    mysqli_free_result($r);
}

// ==== 4) Crea playlist del giorno ====
mysqli_query($con, "DELETE FROM playlist WHERE tt=$tt");

$position = 0;
$mytype = 1; // 1=content, 0=music

$ic = 0;
$im2 = 0;
$im1 = 0;

$tot_time = 0.0;      // duration + extra
$music_time = 0.0;    // solo duration (no extra)
$content_time = 0.0;  // solo duration (no extra)

// evita loop infinito in caso di liste inconsistenti
$max_iters = 200000;

// target ~ 87000s 
$target_total = 87000.0;

for ($iter = 0; $iter < $max_iters; $iter++) {

    // selezione id
    if ($mytype == 1 && count($idc) > 0) {
        $selid = $idc[$ic++];
        if ($ic >= count($idc)) $ic = 0;
    } else {
        // musica
        if ($tot_time > (float)$start_high && $tot_time < (float)$end_high) {
            $selid = $idm2[$im2++];
            if ($im2 >= count($idm2)) $im2 = 0;
        } else {
            $selid = $idm1[$im1++];
            if ($im1 >= count($idm1)) $im1 = 0;
        }
    }

    $selid = (int)$selid;
    if (!isset($track[$selid])) {
        // se manca in cache, prova fetch puntuale (robusto)
        $q = "SELECT id, duration, duration_extra, score, title, author FROM track WHERE id=$selid";
        $r = mysqli_query($con, $q);
        $row = $r ? mysqli_fetch_assoc($r) : null;
        if ($r) mysqli_free_result($r);
        if (!$row) continue;
        $track[$selid] = [
            'duration' => (float)$row['duration'],
            'extra'    => (float)$row['duration_extra'],
            'score'    => (int)$row['score'],
            'title'    => (string)$row['title'],
            'author'   => (string)$row['author'],
        ];
    }

    $d = (float)$track[$selid]['duration'];
    $e = (float)$track[$selid]['extra'];
    if ($d < 0) $d = 0.0;
    if ($e < 0) $e = 0.0;

    // aggiorna tempi: totale include extra, ratio no
    $tot_time += ($d + $e);
    if ($mytype == 1) $content_time += $d;
    else $music_time += $d;

    // output debug come prima (ma senza warning)
    printf(
        "%d %d %d %.3f %.3f %.3f %s %s\n",
        (int)$mytype,
        (int)$selid,
        (int)$track[$selid]['score'],
        $d,
        $e,
        $tot_time,
        $track[$selid]['title'],
        $track[$selid]['author']
    );

    // inserisci playlist + aggiorna track
    mysqli_query($con, "INSERT INTO playlist (tt,id,position) VALUES ($tt,$selid,$position)");
    $position++;
    mysqli_query($con, "UPDATE track SET used=used+1,last=$tt WHERE id=$selid");

    // alternanza: evita divisione per zero (se ancora 0 content, spingi content se disponibile)
    if ($content_time <= 0.0) {
        $mytype = (count($idc) > 0) ? 1 : 0;
    } else {
        $mytype = (($music_time / $content_time) < (float)$ratio) ? 0 : 1;
        if ($mytype == 1 && count($idc) == 0) $mytype = 0; // niente content
    }

    if ($tot_time >= $target_total) break;
}

mysqli_close($con);
?>
