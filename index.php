<?php
include "local.php";

// Configurazione Timezone
date_default_timezone_set('UTC');
$local_tz = new DateTimeZone('Europe/Rome');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("Errore DB");

$now = microtime(true);
$now_int = (int)floor($now);

// 1. Trova il brano attuale
$q_curr = mysqli_query($con, "SELECT epoch FROM lineup WHERE epoch <= $now_int ORDER BY epoch DESC LIMIT 1");
$row_curr = mysqli_fetch_assoc($q_curr);
$current_track_epoch = $row_curr ? (int)$row_curr['epoch'] : $now_int;

// 2. Recupera contesto (ID incluso nella query)
$sql = "SELECT l.epoch, l.id, t.title, t.author, t.genre, t.duration, t.duration_extra
        FROM (
            (SELECT epoch, id FROM lineup WHERE epoch < $current_track_epoch ORDER BY epoch DESC LIMIT 3)
            UNION
            (SELECT epoch, id FROM lineup WHERE epoch >= $current_track_epoch ORDER BY epoch ASC LIMIT 7)
        ) AS l
        JOIN track t ON l.id = t.id
        ORDER BY l.epoch ASC";

$res = mysqli_query($con, $sql);
$schedule = [];
$current = null;

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $start = (float)$r['epoch'];
        $dur = (float)$r['duration'] + (float)$r['duration_extra'];
        $is_playing = ($now >= $start && $now < ($start + $dur));
        
        $item = [
            'id'    => $r['id'],
            'start' => $start,
            'title' => $r['title'],
            'author'=> $r['author'],
            'genre' => $r['genre'],
            'dur'   => $dur,
            'is_now'=> $is_playing
        ];
        if ($is_playing) $current = $item;
        $schedule[] = $item;
    }
}
$next_sec = $current ? (int)ceil($current['end'] - $now) : 0;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Radio a Colori - On Air</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        
        /* Logo e Header */
        .header { text-align: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
        .main-logo { height: 120px; margin-bottom: 10px; transition: transform 0.3s; }
        .main-logo:hover { transform: scale(1.05); }
        
        /* Pulsante Diretta */
        .btn-live { display: inline-block; background: #d32f2f; color: white; padding: 12px 25px; text-decoration: none; border-radius: 50px; font-weight: bold; margin-top: 10px; text-transform: uppercase; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .btn-live:hover { background: #b71c1c; }

        /* Brano in Play */
        .on-air { background: #e3f2fd; padding: 20px; border-radius: 10px; border-left: 8px solid #2196f3; margin: 20px 0; }
        .track-info-main { font-size: 1.2em; color: #0d47a1; }
        .track-title { font-size: 1.8em; font-weight: bold; display: block; }
        .track-id-badge { background: #2196f3; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.6em; vertical-align: middle; }

        /* Countdown */
        .countdown-box { text-align: center; margin: 20px 0; }
        #cdw { font-size: 3em; font-weight: bold; color: #388e3c; display: block; }

        /* Tabella Lista */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8f9fa; color: #666; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px; border-bottom: 1px solid #eee; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .row-now { background: #fff9c4 !important; font-weight: bold; }
        .id-cell { font-family: monospace; color: #888; font-size: 0.85em; }

        /* Footer e Crediti */
        .footer-credits { margin-top: 40px; text-align: center; border-top: 1px solid #eee; padding-top: 20px; font-size: 0.9em; line-height: 1.6; }
        .blue-text { color: blue; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="logo.jpg" class="main-logo" alt="Radio a Colori">
        <p><strong>I Colori del Navile APS presentano Radio a Colori</strong><br>Musica libera con licenza CC-BY</p>
        <a href="https://streaming.url" class="btn-live">▶ Suona in Diretta</a>
    </div>

    <div class="on-air">
        <span class="blue-text">STATE ASCOLTANDO</span>
        <?php if ($current): ?>
            <div class="track-info-main">
                <span class="track-title"><?php echo htmlspecialchars($current['title']); ?></span>
                di <strong><?php echo htmlspecialchars($current['author']); ?></strong> 
                <span class="track-id-badge">ID: <?php echo $current['id']; ?></span>
            </div>
            <div style="font-size: 0.9em; margin-top:10px; color: #c62828;">
                Genere: <?php echo htmlspecialchars($current['genre']); ?> | 
                Durata: <?php echo (int)$current['dur']; ?>s | 
                Inizio: <?php $d = new DateTime("@" . (int)$current['start']); $d->setTimezone($local_tz); echo $d->format("H:i:s"); ?>
            </div>
        <?php else: ?>
            <div class="track-title">In attesa di segnale...</div>
        <?php endif; ?>
    </div>

    <div class="countdown-box">
        Prossimo brano tra: <span id="cdw"><?php echo $next_sec; ?></span> secondi
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Ora</th>
                <th style="width: 60px;">ID</th>
                <th>Brano (Titolo - Autore)</th>
                <th style="width: 70px;">Durata</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedule as $item): 
                $dt = new DateTime("@" . (int)$item['start']);
                $dt->setTimezone($local_tz);
            ?>
            <tr class="<?php echo $item['is_now'] ? 'row-now' : ''; ?>">
                <td><?php echo $dt->format("H:i:s"); ?></td>
                <td class="id-cell"><?php echo $item['id']; ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($item['title']); ?></strong> - 
                    <span style="color: #666;"><?php echo htmlspecialchars($item['author']); ?></span>
                </td>
                <td><?php echo (int)$item['dur']; ?>s</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Ricerca -->
    <div style="margin-top: 30px; text-align: center; background: #f8f9fa; padding: 15px; border-radius: 8px;">
        <form method="post" action="cerca.php">
            Cerca brano per ID: <input type="text" name="myid" style="width:80px; padding: 5px;">
            <button type="submit" style="padding: 5px 15px;">Cerca</button>
        </form>
    </div>

    <div class="footer-credits">
        <p><strong>Powered by I Colori del Navile APS</strong><br>
        Email: info@radioacolori.net<br>
        CF 91357680379 - ROC 33355</p>
        
        <p style="font-size: 0.8em; color: #999;">
            Orario Server: <?php echo date("H:i:s"); ?> UTC | Orario Locale: <?php 
                $d = new DateTime(); $d->setTimezone($local_tz); echo $d->format("H:i:s"); 
            ?>
        </p>
    </div>
</div>

<script>
    var y = <?php echo $next_sec; ?>;
    setInterval(function(){
        y--;
        if(y < 0) { location.reload(); }
        else { document.getElementById('cdw').innerHTML = y; }
    }, 1000);
</script>

</body>
</html>
<?php mysqli_close($con); ?>
