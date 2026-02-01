<?php
include "local.php";

/**
 * CONFIGURAZIONE PERCORSI
 */
$p2   = "/home/ices/music/ogg04/";
$p3   = "/home/ices/music/ogg04v/";
$glue = "/home/ices/music/glue.ogg";
$cut_file = "/run/cutted.raw"; // Formato RAW senza header per stabilità massima
$logfile  = "/home/ices/sched.log";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = microtime(true);
$now_int = (int)floor($now);

/**
 * HELPER FUNCTIONS
 */
function fmt_liq($v) {
    return rtrim(rtrim(sprintf('%.3f', max(0, (float)$v)), '0'), '.');
}

function log_sched($logfile, $now, $id, $path, $shift, $state, $epoch_start) {
    $date_str = date("Y-m-d H:i:s", (int)$now) . substr(sprintf('%.3f', $now - floor($now)), 1);
    $line = "[$date_str] ID:$id Offset:".fmt_liq($shift)." Type:$state Start:$epoch_start Path:$path\n";
    @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * 1. QUERY LINEUP: Cerchiamo la traccia che dovrebbe essere in onda
 */
$query = "SELECT l.epoch, l.id, t.duration, t.duration_extra, t.title, t.author
          FROM lineup l JOIN track t ON l.id = t.id
          WHERE l.epoch <= $now_int ORDER BY l.epoch DESC LIMIT 1";

$res = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($res);

if ($row) {
    $epoch_start = (float)$row['epoch'];
    $dur_base = (float)$row['duration'];
    $offset_base = $now - $epoch_start;

    // Se la parte musicale è già finita, passiamo alla traccia successiva
    // (L'EXTRA/Chiusura deve sempre partire dopo la musica, non la tagliamo mai)
    if ($offset_base >= ($dur_base - 0.2)) {
        $q_next = "SELECT l.epoch, l.id, t.duration, t.duration_extra, t.title, t.author
                   FROM lineup l JOIN track t ON l.id = t.id
                   WHERE l.epoch > $epoch_start ORDER BY l.epoch ASC LIMIT 1";
        $res_next = mysqli_query($con, $q_next);
        $row = mysqli_fetch_assoc($res_next);
        
        if ($row) {
            $epoch_start = (float)$row['epoch'];
            $offset_base = $now - $epoch_start;
            $dur_base = (float)$row['duration'];
        }
    }
}

if ($row) {
    $id = $row['id'];
    $dur_extra = (float)$row['duration_extra'];
    $safe_off = max(0, $offset_base);
    
    $src_base  = $p2 . $id . ".ogg";
    $src_extra = $p3 . $id . ".ogg";

    /**
     * 2. GENERAZIONE COMANDO FFMPEG
     * -f s16le: Forza il formato RAW PCM (rimuove l'header WAV corrotto)
     * filter_complex: Unisce Base (tagliata) e Extra (intera)
     */
    if (file_exists($src_base)) {
        if ($dur_extra > 0 && file_exists($src_extra)) {
            $cmd = sprintf(
                "/usr/bin/ffmpeg -y -ss %s -i %s -i %s -filter_complex '[0:a][1:a]concat=n=2:v=0:a=1' -f s16le -acodec pcm_s16le -ar 22050 -ac 1 %s 2>&1",
                fmt_liq($safe_off),
                escapeshellarg($src_base),
                escapeshellarg($src_extra),
                escapeshellarg($cut_file)
            );
            $state = "MIX_RAW_FULL";
        } else {
            $cmd = sprintf(
                "/usr/bin/ffmpeg -y -ss %s -i %s -f s16le -acodec pcm_s16le -ar 22050 -ac 1 %s 2>&1",
                fmt_liq($safe_off),
                escapeshellarg($src_base),
                escapeshellarg($cut_file)
            );
            $state = "BASE_ONLY_RAW";
        }

        exec($cmd, $out, $ret);

        if ($ret === 0) {
            // Calcolo durata totale per aiutare Liquidsoap (anche se su RAW conta meno)
            $total_duration = max(0, $dur_base - $safe_off) + $dur_extra;

            log_sched($logfile, $now, $id, $cut_file, $safe_off, $state, (int)$epoch_start);
            
            // L'output punta al file .raw
            echo "annotate:title=\"" . addslashes($row['title']) . "\",artist=\"" . addslashes($row['author']) . "\",duration=\"" . $total_duration . "\":" . $cut_file;
            mysqli_close($con);
            exit;
        }
    }
}

/**
 * 3. FALLBACK: Se tutto fallisce, suoniamo il GLUE
 */
log_sched($logfile, $now, "GLUE", $glue, 0, "GLUE", 0);
echo $glue;

mysqli_close($con);
