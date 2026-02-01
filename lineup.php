<?php
include "local.php";

// 1. Configurazione Data e Database
$target_day = $_GET['day'] ?? date('dmY'); 
$day_obj = DateTime::createFromFormat('dmY', $target_day, new DateTimeZone('UTC'));
$start_timestamp = $day_obj->getTimestamp();
$end_threshold = $start_timestamp + 86400;

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);

// 2. Logica di "Attacco" (Gestisce Tabella Vuota o Continuità)
// Cerchiamo l'ultimo brano; usiamo una LEFT JOIN per sicurezza
$q_last = mysqli_query($con, "
    SELECT l.epoch, t.duration, t.duration_extra 
    FROM lineup l 
    JOIN track t ON l.id = t.id 
    ORDER BY l.epoch DESC LIMIT 1
");

if ($q_last && mysqli_num_rows($q_last) > 0) {
    $row_last = mysqli_fetch_assoc($q_last);
    // Calcoliamo la fine dell'ultimo brano arrotondando per eccesso
    $current_epoch = (int)ceil($row_last['epoch'] + $row_last['duration'] + $row_last['duration_extra']);
} else {
    // PRIMO AVVIO: Se la tabella è vuota, partiamo dalla mezzanotte del giorno indicato
    $current_epoch = $start_timestamp;
}

// 3. Verifica propedeutica (Il giorno precedente deve esistere se non è il primo avvio)
// Se vuoi forzare la presenza del giorno prima, scommenta queste righe:
/*
if ($current_epoch < $start_timestamp && $current_epoch != $start_timestamp) {
    die("Errore: Il giorno precedente non è ancora stato generato.");
}
*/

// 4. Preparazione Pool (Filler per il finale)
// Pre-carichiamo i brani più corti per la precisione di fine giornata
$q_filler = mysqli_query($con, "SELECT id, duration, duration_extra FROM track WHERE score >= 1 ORDER BY (duration + duration_extra) ASC LIMIT 100");
$fillers = [];
while($f = mysqli_fetch_assoc($q_filler)) $fillers[] = $f;
$ifill = 0;

// 5. Loop di Generazione
while ($current_epoch < $end_threshold) {
    $remaining = $end_threshold - $current_epoch;

    // Se mancano meno di 10 minuti (600s), forziamo i brani più corti (filler)
    // per ridurre lo sforo oltre la mezzanotte al minimo indispensabile.
    if ($remaining < 600) {
        $track = $fillers[$ifill++];
        if ($ifill >= count($fillers)) $ifill = 0;
    } else {
        // Qui inserisci la tua logica di rotazione standard (idm2, idm1, idc)
        // Esempio rapido:
        $res = mysqli_query($con, "SELECT id, duration, duration_extra FROM track ORDER BY last ASC LIMIT 1");
        $track = mysqli_fetch_assoc($res);
    }

    $id_esc = mysqli_real_escape_string($con, $track['id']);
    $dur_totale = (int)ceil($track['duration'] + $track['duration_extra']);

    // Inserimento in lineup
    mysqli_query($con, "INSERT INTO lineup (epoch, id) VALUES ($current_epoch, '$id_esc')");
    
    // Aggiornamento track (campo last ora è un timestamp Epoch)
    mysqli_query($con, "UPDATE track SET used = used + 1, last = $current_epoch WHERE id = '$id_esc'");

    $current_epoch += $dur_totale;
}

echo "Generazione completata con successo per il giorno $target_day.\n";
echo "Il palinsesto termina al secondo: $current_epoch (" . date('H:i:s', $current_epoch) . " UTC)";

mysqli_close($con);
?>
