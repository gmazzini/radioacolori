<?php
include "local.php";
date_default_timezone_set('UTC');

$target_day = $_GET['day'] ?? '01022026'; 
$day_obj = DateTime::createFromFormat('dmY', $target_day, new DateTimeZone('UTC'));
$start_timestamp = $day_obj->getTimestamp();
$end_threshold = $start_timestamp + 86400;

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);

// 1. Recupero punto di attacco
$q_last = mysqli_query($con, "SELECT l.epoch, t.duration, t.duration_extra FROM lineup l JOIN track t ON l.id = t.id ORDER BY l.epoch DESC LIMIT 1");
$current_epoch = ($row = mysqli_fetch_assoc($q_last)) ? (int)ceil($row['epoch'] + $row['duration'] + $row['duration_extra']) : $start_timestamp;

// 2. Inizializzazione contatori
$count_music = 0;
$count_vocal = 0;
$time_music = 0.0;
$time_vocal = 0.0;

// 3. Loop di generazione
while ($current_epoch < $end_threshold) {
    $remaining = $end_threshold - $current_epoch;
    
    // Determinazione tipo brano in base al rapporto (ratio impostato in local.php)
    // Se time_vocal è 0, forziamo un contenuto vocale per iniziare il calcolo
    if ($remaining < 600) {
        $tipo_scelta = "filler"; // Solo musica corta nel finale
    } elseif ($time_vocal == 0 || ($time_music / $time_vocal) >= $ratio) {
        $tipo_scelta = "vocal";
    } else {
        $tipo_scelta = "music";
    }

    // Query di selezione filtrata per tipo (assumendo score=1 per musica, score=2 per vocal/special come da tuo script originale)
    if ($tipo_scelta == "filler") {
        $q = mysqli_query($con, "SELECT id, duration, duration_extra, score FROM track WHERE score=1 ORDER BY (duration + duration_extra) ASC LIMIT 1");
    } elseif ($tipo_scelta == "vocal") {
        $q = mysqli_query($con, "SELECT id, duration, duration_extra, score FROM track WHERE score=2 ORDER BY last ASC LIMIT 1");
    } else {
        $q = mysqli_query($con, "SELECT id, duration, duration_extra, score FROM track WHERE score=1 ORDER BY last ASC LIMIT 1");
    }

    $track = mysqli_fetch_assoc($q);
    if (!$track) break;

    $id_esc = mysqli_real_escape_string($con, $track['id']);
    $d_raw = (float)$track['duration'] + (float)$track['duration_extra'];
    $dur_totale = (int)ceil($d_raw);

    // Salvataggio e aggiornamento statistiche
    if (mysqli_query($con, "INSERT INTO lineup (epoch, id) VALUES ($current_epoch, '$id_esc')")) {
        mysqli_query($con, "UPDATE track SET used = used + 1, last = $current_epoch WHERE id = '$id_esc'");
        
        if ($track['score'] == 2) {
            $count_vocal++;
            $time_vocal += $d_raw;
        } else {
            $count_music++;
            $time_music += $d_raw;
        }
        
        $current_epoch += $dur_totale;
    }
}

// 4. Output statistiche
$final_ratio = ($time_vocal > 0) ? round($time_music / $time_vocal, 2) : "Inf.";
echo "Generazione $target_day completata.\n";
echo "Brani Musicali: $count_music (" . gmdate("H:i:s", $time_music) . ")\n";
echo "Brani Vocali: $count_vocal (" . gmdate("H:i:s", $time_vocal) . ")\n";
echo "Rapporto finale M/V: $final_ratio (Target: $ratio)\n";
echo "Sforo mezzanotte: " . ($current_epoch - $end_threshold) . " secondi.\n";

mysqli_close($con);
?>
