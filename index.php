<?php
include "local.php";

// Impostiamo UTC per i calcoli e Rome per la visualizzazione
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

// 2. Recupera contesto: 3 prima e 7 dopo
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
        .container { max-width: 850px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 15px; margin-bottom: 15px; }
        .on-air { background: #fff3e0; padding: 20px; border-left: 6px solid #ff9800; margin: 15px 0; }
        .track-title { font-size: 1.6em; font-weight: bold; color: #e65100; }
        .countdown { font-size: 2.5em; font-weight: bold; color: #2e7d32; text-align: center; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 0.95em; margin-top: 20px; }
        th { background: #444; color: #fff; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .row-now { background: #fff9c4; font-weight: bold; }
        .btn-live { background: #d32f2f; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; }
    </style>
</head>
<body>

<div class="container">
    <!-- Crediti Superiori -->
    <p style='text-align: center; font-size: 1.1em;'>
        <strong>I Colori del Navile APS presentano Radio a Colori</strong><br>
        Musica libera con licenza <b>CC-BY</b>
    </p>

    <div class="header">
        <img src="logo.jpg" style="height:100px;">
        <!-- Bottone Suona in Diretta (Metodo Originale) -->
        <a href="http://radioacolori.net" target="_blank" class="btn-live">SUONA IN DIRETTA</a>
        
        <form method="post">
            <input type="text" name="myid" placeholder="ID Brano" style="width:70px; padding: 5px;">
            <button type="submit" style="padding: 5px 10px;">Cerca</button>
        </form>
    </div>

    <div class="on-air">
        <?php if ($current): ?>
            <div style="font-size:0.9em; color:blue; font-weight:bold; margin-bottom:5px;">STATE ASCOLTANDO</div>
            <div class="track-title"><?php echo htmlspecialchars($current['title']); ?></div>
            <div style="font-size: 1.2em;">di <b><?php echo htmlspecialchars($current['author']); ?></b></div>
            <div style="color:red; font-size:0.9em; margin-top:10px;">
                Genere: <?php echo htmlspecialchars($current['genre']); ?> | 
                Durata: <?php echo (int)$current['dur']; ?>s | 
                Identificativo: <?php echo $current['id']; ?>
            </div>
        <?php else: ?>
            <div class="track-title">In attesa di segnale...</div>
        <?php endif; ?>
    </div>

    <div class="countdown" id="cdw"><?php echo $next_sec; ?></div>
    <div style="text-align:center; font-size:0.8em; color: #666; margin-bottom:20px;">secondi al cambio brano</div>

    <table>
        <thead>
            <tr>
                <th>Ora Loc.</th>
                <th>ID</th>
                <th>Brano (Titolo - Autore)</th>
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
                <td style="color: #888;"><?php echo $item['id']; ?></td>
                <td>
                    <b><?php echo htmlspecialchars($item['title']); ?></b> - 
                    <small><?php echo htmlspecialchars($item['author']); ?></small>
                </td>
                <td><?php echo (int)$item['dur']; ?>s</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Crediti Inferiori -->
    <p style='text-align: center; margin-top:40px; font-size:0.85em; color:#555; border-top: 1px solid #eee; padding-top: 20px;'>
        Powered by <strong>I Colori del Navile APS</strong><br>
        Email info at radioacolori.net | CF 91357680379 - ROC 33355<br>
        <b>Musica libera con licenza CC-BY</b>
    </p>
</div>

<script>
    var y = <?php echo $next_sec; ?>;
    setInterval(function(){
        if(y > 0) {
            y--;
            document.getElementById('cdw').innerHTML = y;
        } else {
            location.reload();
        }
    }, 1000);
</script>

</body>
</html>
<?php mysqli_close($con); ?>

