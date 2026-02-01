<?php
include "local.php";

$target_day = $_GET['day'] ?? '01022026'; 
$day_obj = DateTime::createFromFormat('dmY', $target_day, new DateTimeZone('UTC'));
$start_timestamp = $day_obj->getTimestamp();
$end_threshold = $start_timestamp + 86400;

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);

// 1. Trova il punto di attacco dal database
$q_last = mysqli_query($con, "SELECT (epoch + duration_total) as next_start FROM (
    SELECT l.epoch, (t.duration + t.duration_extra) as duration_total 
    FROM lineup l JOIN track t ON l.id = t.id 
    ORDER BY l.epoch DESC LIMIT 1
) as last_track");

$row_last = mysqli_fetch_assoc($q_last);
$current_epoch = $row_last ? (int)ceil($row_last['next_start']) : $start_timestamp;

// 2. Caricamento pool (Score 2 = Alta priorità, Filler = Brani corti)
$listout = "(NULL)"; // Da popolare con la tua funzione sql_in_list

// Pool per le ultime ore: solo musica, ordinata per la più corta
$q_filler = mysqli_query($con, "SELECT id, duration, duration_extra FROM track WHERE score >= 1 AND genre NOT IN $listout ORDER BY (duration + duration_extra) ASC");
$fillers = [];
while($f = mysqli_fetch_assoc($q_filler)) $fillers[] = $f;

// 3. Generazione
$ifill = 0;
while ($current_epoch < $end_threshold) {
    $remaining = $end_threshold - $current_epoch;

    // Se mancano meno di 3600s (1 ora), passiamo ai brani più corti per precisione
    if ($remaining < 3600) {
        $track = $fillers[$ifill++];
        if ($ifill >= count($fillers)) $ifill = 0;
    } else {
        // Logica standard: qui inserisci la tua alternanza idm2/idm1/idc
        // Per brevità uso una query semplice:
        $q_std = mysqli_query($con, "SELECT id, duration, duration_extra FROM track WHERE score >= 1 ORDER BY last ASC LIMIT 1");
        $track = mysqli_fetch_assoc($q_std);
    }

    $id_esc = mysqli_real_escape_string($con, $track['id']);
    // Calcolo durata per eccesso
    $dur_effettiva = (int)ceil($track['duration'] + $track['duration_extra']);

    // Inserimento
    mysqli_query($con, "INSERT INTO lineup (epoch, id) VALUES ($current_epoch, '$id_esc')");
    
    // Aggiornamento LAST con il timestamp reale
    mysqli_query($con, "UPDATE track SET used=used+1, last=$current_epoch WHERE id='$id_esc'");

    $current_epoch += $dur_effettiva;

    // Se abbiamo superato la soglia, il palinsesto è coperto.
    if ($current_epoch >= $end_threshold) break;
}

echo "Generazione completata. Fine palinsesto: " . date('Y-m-d H:i:s', $current_epoch) . " UTC";
?>
