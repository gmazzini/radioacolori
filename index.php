<?php
include "local.php";

date_default_timezone_set('UTC');
$local_tz = new DateTimeZone('Europe/Rome');
$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("Errore DB");

// --- 1. RICERCA IN ALTO ---
$search_result = null;
if (isset($_POST['myid']) && !empty($_POST['myid'])) {
    $sid = mysqli_real_escape_string($con, $_POST['myid']);
    $q_search = mysqli_query($con, "SELECT * FROM track WHERE id = '$sid' LIMIT 1");
    $search_result = mysqli_fetch_assoc($q_search);
}

$now = microtime(true);
$now_int = (int)floor($now);

// --- 2. RECUPERO STATO RADIO ---
$q_curr = mysqli_query($con, "SELECT epoch FROM lineup WHERE epoch <= $now_int ORDER BY epoch DESC LIMIT 1");
$row_curr = mysqli_fetch_assoc($q_curr);
$current_track_epoch = $row_curr ? (int)$row_curr['epoch'] : $now_int;

$sql = "SELECT l.epoch, l.id, t.title, t.author, t.genre, t.duration, t.duration_extra
        FROM (
            (SELECT epoch, id FROM lineup WHERE epoch < $current_track_epoch ORDER BY epoch DESC LIMIT 3)
            UNION
            (SELECT epoch, id FROM lineup WHERE epoch >= $current_track_epoch ORDER BY epoch ASC LIMIT 7)
        ) AS l
        JOIN track t ON l.id = t.id ORDER BY l.epoch ASC";

$res = mysqli_query($con, $sql);
$schedule = []; $current = null;
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $start = (float)$r['epoch'];
        $dur = (float)$r['duration'] + (float)$r['duration_extra'];
        $end = $start + $dur;
        $is_playing = ($now >= $start && $now < $end);
        $item = ['id'=>$r['id'], 'start'=>$start, 'end'=>$end, 'title'=>$r['title'], 'author'=>$r['author'], 'genre'=>$r['genre'], 'dur'=>$dur, 'is_now'=>$is_playing];
        if ($is_playing) $current = $item;
        $schedule[] = $item;
    }
}
$next_sec = $current ? (int)max(0, ceil($current['end'] - $now)) : 0;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Radio a Colori - On Air</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f9; color: #333; margin: 0; padding: 15px; }
        .container { max-width: 850px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; }
        .main-logo { height: 130px; display: block; margin: 0 auto 10px; }
        
        /* Ricerca */
        .top-search { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd; }
        .result-box { background: #e8f5e9; border: 1px solid #4caf50; padding: 10px; margin-top: 10px; border-radius: 5px; }

        /* Bottone Streaming (Tuo metodo originale) */
        .btn-direct { display: inline-block; background: #d32f2f; color: white !important; padding: 15px 35px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 1.2em; margin: 15px 0; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .btn-direct:hover { background: #b71c1c; }

        /* Brano in onda */
        .on-air { background: #fff3e0; padding: 20px; border-radius: 8px; border-left: 8px solid #ff9800; margin: 20px 0; text-align: left; }
        .track-title { font-size: 1.7em; font-weight: bold; color: #e65100; display: block; }
        
        /* Tabella Lista */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; text-align: left; }
        th { background: #444; color: #fff; padding: 12px; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .row-now { background: #fff9c4; font-weight: bold; }

        /* Footer Crediti */
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; font-size: 0.9em; color: #666; line-height: 1.6; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="logo.jpg" class="main-logo" alt="Radio a Colori">
        <p style="font-size: 1.1em;"><strong>I Colori del Navile APS presentano Radio a Colori</strong><br>
        <span style="color: #d32f2f; font-weight: bold;">Musica libera con licenza CC-BY</span></p>
    </div>

    <!-- RICERCA IN ALTO -->
    <div class="top-search">
        <form method="post">
            <strong>Cerca brano per ID:</strong> 
            <input type="text" id="search_id" name="myid" value="<?php echo htmlspecialchars($_POST['myid'] ?? ''); ?>" style="width:70px; padding:6px; border:1px solid #ccc; border-radius:4px;">
            <button type="submit" style="padding:6px 15px; cursor:pointer;">Cerca</button>
            <?php if (isset($_POST['myid'])): ?><a href="?" style="margin-left:10px; color:#999; font-size:0.8em;">[Resetta]</a><?php endif; ?>
        </form>

        <?php if ($search_result): ?>
            <div class="result-box">
                <b>Trovato:</b> <?php echo htmlspecialchars($search_result['title']); ?> - <?php echo htmlspecialchars($search_result['author']); ?> 
                <span style="font-size:0.8em; color:#666;">(Genere: <?php echo $search_result['genre']; ?>)</span>
            </div>
        <?php elseif (isset($_POST['myid'])): ?>
            <div class="result-box" style="background:#ffebee; border-color:#f44336;">Nessun brano trovato con ID <?php echo htmlspecialchars($_POST['myid']); ?></div>
        <?php endif; ?>
    </div>

    <!-- LINK STREAMING DIRETTO -->
    <a href="http://radioacolori.net" target="_blank" class="btn-direct">▶ SUONA IN DIRETTA</a>

    <div class="on-air">
        <span style="color:blue; font-weight:bold; font-size:0.9em;">STATE ASCOLTANDO</span>
        <?php if ($current): ?>
            <span class="track-title"><?php echo htmlspecialchars($current['title']); ?></span>
            <div style="font-size: 1.2em;">di <b><?php echo htmlspecialchars($current['author']); ?></b></div>
            <div style="margin-top:8px; font-size:0.85em; color:#777;">
                Identificativo: <b><?php echo $current['id']; ?></b> | Genere: <?php echo $current['genre']; ?> | 
                Inizio: <?php $d = new DateTime("@" . (int)$current['start']); $d->setTimezone($local_tz); echo $d->format("H:i:s"); ?>
            </div>
        <?php else: ?>
            <div class="track-title">In attesa di segnale...</div>
        <?php endif; ?>
    </div>

    <div style="margin: 20px 0;">
        Prossimo brano tra: <b id="cdw" style="font-size:1.5em; color:#2e7d32;"><?php echo $next_sec; ?></b> secondi
    </div>

    <table>
        <thead>
            <tr><th>Ora</th><th>ID</th><th>Brano (Titolo - Autore)</th><th>Durata</th></tr>
        </thead>
        <tbody>
            <?php foreach ($schedule as $item): 
                $dt = new DateTime("@" . (int)$item['start']); $dt->setTimezone($local_tz); ?>
            <tr class="<?php echo $item['is_now'] ? 'row-now' : ''; ?>">
                <td><?php echo $dt->format("H:i:s"); ?></td>
                <td style="color:#999; font-size:0.85em;"><?php echo $item['id']; ?></td>
                <td><?php echo htmlspecialchars($item['title']); ?> - <small><?php echo htmlspecialchars($item['author']); ?></small></td>
                <td><?php echo (int)$item['dur']; ?>s</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <p><strong>Powered by I Colori del Navile APS</strong><br>
        Musica libera con licenza <a href="https://creativecommons.org" target="_blank" style="color:inherit;">CC-BY</a><br>
        Email: info@radioacolori.net | CF 91357680379 - ROC 33355</p>
    </div>
</div>

<script>
    var seconds = <?php echo $next_sec; ?>;
    var isTyping = false;

    var input = document.getElementById('search_id');
    input.onfocus = function() { isTyping = true; };
    input.onblur = function() { isTyping = false; };

    setInterval(function(){
        if (seconds <= 0) {
            if (!isTyping) { location.reload(); }
        } else {
            seconds--;
            document.getElementById('cdw').innerHTML = seconds;
        }
    }, 1000);
</script>

</body>
</html>

