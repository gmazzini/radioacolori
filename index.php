<?php
include "local.php";

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

// 2. Recupera contesto
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
        $end = $start + $dur;
        $is_playing = ($now >= $start && $now < $end);
        
        $item = [
            'id'    => $r['id'],
            'start' => $start,
            'end'   => $end,
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

// Calcolo secondi mancanti (evita numeri negativi assurdi)
$next_sec = 0;
if ($current) {
    $diff = (int)($current['end'] - $now);
    $next_sec = ($diff > 0) ? $diff : 0;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Radio a Colori - On Air</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
        .main-logo { height: 130px; margin-bottom: 10px; }
        
        .btn-live { display: inline-block; background: #d32f2f; color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 50px; font-weight: bold; margin-top: 15px; text-transform: uppercase; font-size: 1.1em; }
        .btn-live:hover { background: #b71c1c; }

        .on-air { background: #e3f2fd; padding: 20px; border-radius: 10px; border-left: 8px solid #2196f3; margin: 20px 0; }
        .track-title { font-size: 1.8em; font-weight: bold; color: #d32f2f; display: block; }
        .id-badge { background: #2196f3; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.7em; }

        .countdown-box { text-align: center; margin: 20px 0; font-size: 1.2em; }
        #cdw { font-size: 2.5em; font-weight: bold; color: #388e3c; display: block; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px; border-bottom: 1px solid #eee; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .row-now { background: #fff9c4 !important; font-weight: bold; }
        
        .footer-credits { margin-top: 40px; text-align: center; border-top: 1px solid #eee; padding-top: 20px; font-size: 0.9em; line-height: 1.6; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="logo.jpg" class="main-logo" alt="Radio a Colori">
        <p><strong>I Colori del Navile APS presentano Radio a Colori</strong><br>Musica libera con licenza CC-BY</p>
        <!-- Bottone Streaming Corretto -->
        <a href="http://radioacolori.net" target="_blank" class="btn-live">▶ Suona in Diretta</a>
    </div>

    <div class="on-air">
        <b style="color:blue">STATE ASCOLTANDO</b>
        <?php if ($current): ?>
            <div>
                <span class="track-title"><?php echo htmlspecialchars($current['title']); ?></span>
                di <strong><?php echo htmlspecialchars($current['author']); ?></strong> 
                <span class="id-badge">ID: <?php echo $current['id']; ?></span>
            </div>
            <div style="margin-top:10px; color:#555;">
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
                <th style="width: 15%;">Ora</th>
                <th style="width: 10%;">ID</th>
                <th style="width: 60%;">Brano (Titolo - Autore)</th>
                <th style="width: 15%;">Durata</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedule as $item): 
                $dt = new DateTime("@" . (int)$item['start']);
                $dt->setTimezone($local_tz);
            ?>
            <tr class="<?php echo $item['is_now'] ? 'row-now' : ''; ?>">
                <td><?php echo $dt->format("H:i:s"); ?></td>
                <td style="color:#888;"><?php echo $item['id']; ?></td>
                <td>
                    <?php echo htmlspecialchars($item['title']); ?> - 
                    <small><?php echo htmlspecialchars($item['author']); ?></small>
                </td>
                <td><?php echo (int)$item['dur']; ?>s</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 30px; text-align: center; background: #f8f9fa; padding: 20px; border-radius: 8px;">
        <form method="post" action="cerca.php">
            Cerca brano per ID: 
            <input type="text" id="search_id" name="myid" style="width:80px; padding: 8px;" autocomplete="off">
            <button type="submit" style="padding: 8px 20px; cursor:pointer;">Cerca</button>
        </form>
    </div>

    <div class="footer-credits">
        <p><strong>Powered by I Colori del Navile APS</strong><br>
        Email: info@radioacolori.net | CF 91357680379 - ROC 33355</p>
    </div>
</div>

<script>
    var seconds = <?php echo $next_sec; ?>;
    var isTyping = false;

    // Blocca il refresh se l'utente sta scrivendo nel cerca
    document.getElementById('search_id').onfocus = function() { isTyping = true; };
    document.getElementById('search_id').onblur = function() { isTyping = false; };

    setInterval(function(){
        if(seconds <= 0) {
            if(!isTyping) { location.reload(); }
        } else {
            seconds--;
            document.getElementById('cdw').innerHTML = seconds;
        }
    }, 1000);
</script>

</body>
</html>
<?php mysqli_close($con); ?>

