<?php
include "local.php";

// Impostiamo UTC per i calcoli e Rome per la visualizzazione
date_default_timezone_set('UTC');
$local_tz = new DateTimeZone('Europe/Rome');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("Errore DB");

$now = microtime(true);
$now_int = (int)floor($now);

// 1. Trova il brano attuale (quello con epoch <= ora, più recente)
$q_curr = mysqli_query($con, "SELECT epoch FROM lineup WHERE epoch <= $now_int ORDER BY epoch DESC LIMIT 1");
$row_curr = mysqli_fetch_assoc($q_curr);
$current_track_epoch = $row_curr ? (int)$row_curr['epoch'] : $now_int;

// 2. Recupera contesto: 3 prima e 6 dopo
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
            'end'   => $start + $dur,
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
        body { font-family: sans-serif; background: #f4f4f9; color: #333; margin: 0; padding: 10px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .on-air { background: #fff3e0; padding: 15px; border-left: 5px solid #ff9800; margin: 15px 0; }
        .track-title { font-size: 1.4em; font-weight: bold; color: #e65100; }
        .countdown { font-size: 2em; font-weight: bold; color: #2e7d32; text-align: center; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        th { background: #444; color: #fff; padding: 8px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
        .row-now { background: #fff9c4; font-weight: bold; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="logo.jpg" style="height:50px;">
        <form method="post">
            <input type="text" name="myid" placeholder="ID Brano" style="width:60px">
            <button type="submit">Cerca</button>
        </form>
    </div>

    <div class="on-air">
        <?php if ($current): ?>
            <div style="font-size:0.8em; color:#666;">ORA IN ONDA</div>
            <div class="track-title"><?php echo htmlspecialchars($current['title']); ?></div>
            <div>di <b><?php echo htmlspecialchars($current['author']); ?></b></div>
        <?php else: ?>
            <div class="track-title">In attesa di segnale...</div>
        <?php endif; ?>
    </div>

    <div class="countdown" id="cdw"><?php echo $next_sec; ?></div>
    <div style="text-align:center; font-size:0.7em; margin-bottom:20px;">secondi al cambio brano</div>

    <table>
        <thead>
            <tr>
                <th>Ora Loc.</th>
                <th>Titolo</th>
                <th>Durata</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedule as $item): 
                $dt = new DateTime("@" . (int)$item['start']);
                $dt->setTimezone($local_tz);
            ?>
            <tr class="<?php echo $item['is_now'] ? 'row-now' : ''; ?>">
                <td><?php echo $dt->format("H:i:s"); ?></td>
                <td>
                    <b><?php echo htmlspecialchars($item['title']); ?></b><br>
                    <span style="font-size:0.8em; color:#666;"><?php echo htmlspecialchars($item['author']); ?></span>
                </td>
                <td><?php echo (int)$item['dur']; ?>s</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="text-align:center; font-size:0.8em; margin-top:20px; color:#999;">
        Radio a Colori - UTC: <?php echo date("H:i:s"); ?> | Locali: <?php 
            $d = new DateTime(); $d->setTimezone($local_tz); echo $d->format("H:i:s"); 
        ?>
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

