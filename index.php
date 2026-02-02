<?php
include "local.php";

date_default_timezone_set('UTC');
$local_tz = new DateTimeZone('Europe/Rome');
$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("Errore DB");

// --- 1. LOGICA DI RICERCA (Spostata in alto) ---
$search_result = null;
if (isset($_POST['myid']) && !empty($_POST['myid'])) {
    $sid = mysqli_real_escape_string($con, $_POST['myid']);
    // Cerchiamo i dati del brano nella tabella track
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
        .container { max-width: 850px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        
        /* Header & Logo */
        .header { text-align: center; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
        .main-logo { height: 120px; display: block; margin: 0 auto 10px; }
        
        /* Ricerca in alto */
        .top-search { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd; }
        .result-box { background: #e8f5e9; border-left: 5px solid #4caf50; padding: 10px; margin-top: 10px; text-align: left; }

        /* Player & Live */
        .live-section { text-align: center; margin-bottom: 20px; }
        .btn-live { background: #d32f2f; color: white !important; padding: 12px 25px; border: none; border-radius: 50px; font-weight: bold; cursor: pointer; font-size: 1.1em; box-shadow: 0 3px 6px rgba(0,0,0,0.2); }
        .btn-live.playing { background: #444; }

        .on-air { background: #fff3e0; padding: 15px; border-radius: 8px; border-left: 6px solid #ff9800; margin: 15px 0; }
        .track-title { font-size: 1.5em; font-weight: bold; color: #e65100; }

        /* Lista brani */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.95em; }
        th { background: #444; color: #fff; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .row-now { background: #fff9c4; font-weight: bold; }

        .footer { text-align: center; font-size: 0.85em; color: #777; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="logo.jpg" class="main-logo" alt="Radio a Colori Logo">
        <p><strong>I Colori del Navile APS presentano Radio a Colori</strong><br>Musica libera con licenza CC-BY</p>
    </div>

    <!-- RICERCA SPOSTATA IN ALTO -->
    <div class="top-search">
        <form method="post" id="searchForm">
            <strong>Cerca brano per ID:</strong> 
            <input type="text" id="search_id" name="myid" value="<?php echo htmlspecialchars($_POST['myid'] ?? ''); ?>" style="width:70px; padding:6px; border:1px solid #ccc; border-radius:4px;">
            <button type="submit" style="padding:6px 15px; cursor:pointer;">Cerca</button>
            <?php if ($search_result || isset($_POST['myid'])): ?>
                <a href="?" style="font-size:0.8em; margin-left:10px; color:#666;">[Resetta]</a>
            <?php endif; ?>
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

    <div class="live-section">
        <!-- PLAYER AUDIO CORRETTO PER ICECAST -->
        <audio id="radioPlayer" preload="none">
            <source src="http://radioacolori.net:8000/stream" type="audio/mpeg">
        </audio>
        <button onclick="togglePlay()" id="playBtn" class="btn-live">▶ Suona in Diretta</button>
    </div>

    <div class="on-air">
        <span style="color:blue; font-weight:bold;">STATE ASCOLTANDO</span>
        <?php if ($current): ?>
            <div class="track-title"><?php echo htmlspecialchars($current['title']); ?></div>
            <div>di <b><?php echo htmlspecialchars($current['author']); ?></b> <span style="background:#2196f3; color:white; padding:1px 5px; border-radius:3px; font-size:0.7em;">ID: <?php echo $current['id']; ?></span></div>
            <div style="font-size:0.85em; margin-top:5px; color:#666;">
                Genere: <?php echo $current['genre']; ?> | Inizio: <?php $d = new DateTime("@" . (int)$current['start']); $d->setTimezone($local_tz); echo $d->format("H:i:s"); ?>
            </div>
        <?php else: ?>
            <div class="track-title">In attesa di segnale...</div>
        <?php endif; ?>
    </div>

    <div style="text-align:center; padding:10px; border:1px dashed #ccc; border-radius:5px;">
        Prossimo brano tra: <b id="cdw" style="font-size:1.4em; color:#2e7d32;"><?php echo $next_sec; ?></b> secondi
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
                <td style="color:#888; font-size:0.8em;"><?php echo $item['id']; ?></td>
                <td><?php echo htmlspecialchars($item['title']); ?> - <small><?php echo htmlspecialchars($item['author']); ?></small></td>
                <td><?php echo (int)$item['dur']; ?>s</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <strong>Powered by I Colori del Navile APS</strong><br>
        Email: info@radioacolori.net | CF 91357680379 - ROC 33355
    </div>
</div>

<script>
    var seconds = <?php echo $next_sec; ?>;
    var player = document.getElementById('radioPlayer');
    var playBtn = document.getElementById('playBtn');
    var isTyping = false;

    // Funzione Player con reset del buffer per evitare ritardi
    function togglePlay() {
        if (player.paused) {
            // Per forzare la diretta "fresca", ricarichiamo la sorgente
            player.load(); 
            player.play().then(() => {
                playBtn.innerHTML = "⏸ Sospendi Ascolto";
                playBtn.classList.add('playing');
            }).catch(e => {
                alert("Clicca di nuovo per avviare (il browser richiede un'interazione utente)");
            });
        } else {
            player.pause();
            playBtn.innerHTML = "▶ Suona in Diretta";
            playBtn.classList.remove('playing');
        }
    }

    // Gestione refresh e input
    var searchInput = document.getElementById('search_id');
    searchInput.onfocus = function() { isTyping = true; };
    searchInput.onblur = function() { isTyping = false; };

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
<?php mysqli_close($con); ?>

