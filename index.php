<?php
include "local.php";

date_default_timezone_set('UTC');
$local_tz = new DateTimeZone('Europe/Rome');
$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("Errore DB");

// --- LOGICA DI RICERCA ---
$search_result = null;
if (isset($_POST['myid']) && !empty($_POST['myid'])) {
    $sid = mysqli_real_escape_string($con, $_POST['myid']);
    $q_search = mysqli_query($con, "SELECT * FROM track WHERE id = '$sid' LIMIT 1");
    $search_result = mysqli_fetch_assoc($q_search);
}

$now = microtime(true);
$now_int = (int)floor($now);

// Trova brano attuale e lista
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
    <title>Radio a Colori - Live</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; color: #333; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 20px; }
        .main-logo { height: 110px; }
        .btn-live { display: inline-block; background: #d32f2f; color: white !important; padding: 15px 30px; border: none; border-radius: 50px; font-weight: bold; cursor: pointer; text-transform: uppercase; margin: 10px 0; font-size: 1.1em; }
        .on-air { background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 6px solid #2196f3; margin: 15px 0; }
        .track-title { font-size: 1.6em; font-weight: bold; color: #d32f2f; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        td, th { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        .row-now { background: #fff9c4 !important; font-weight: bold; }
        .search-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px; text-align: center; }
        .result-found { background: #e8f5e9; border: 1px solid #4caf50; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="logo.jpg" class="main-logo">
        <p><strong>I Colori del Navile APS presentano Radio a Colori</strong></p>
        
        <!-- PLAYER AUDIO NASCOSTO -->
        <audio id="radioPlayer" src="http://radioacolori.net:8000/stream" preload="none"></audio>
        <button onclick="togglePlay()" id="playBtn" class="btn-live">▶ Suona in Diretta</button>
    </div>

    <div class="on-air">
        <b style="color:blue">STATE ASCOLTANDO</b>
        <?php if ($current): ?>
            <div><span class="track-title"><?php echo htmlspecialchars($current['title']); ?></span> di <b><?php echo htmlspecialchars($current['author']); ?></b></div>
            <small>ID: <?php echo $current['id']; ?> | Genere: <?php echo $current['genre']; ?> | Durata: <?php echo (int)$current['dur']; ?>s</small>
        <?php endif; ?>
    </div>

    <div style="text-align:center; margin:10px 0;">Prossimo brano tra: <b id="cdw" style="font-size:1.5em; color:#388e3c;"><?php echo $next_sec; ?></b> secondi</div>

    <!-- RISULTATO RICERCA -->
    <?php if ($search_result): ?>
    <div class="result-found">
        <b>Brano Trovato (ID <?php echo $search_result['id']; ?>):</b><br>
        <?php echo htmlspecialchars($search_result['title']); ?> - <?php echo htmlspecialchars($search_result['author']); ?> (<?php echo $search_result['genre']; ?>)
    </div>
    <?php endif; ?>

    <table>
        <thead><tr><th>Ora</th><th>ID</th><th>Brano</th><th>Durata</th></tr></thead>
        <tbody>
            <?php foreach ($schedule as $item): 
                $dt = new DateTime("@" . (int)$item['start']); $dt->setTimezone($local_tz); ?>
            <tr class="<?php echo $item['is_now'] ? 'row-now' : ''; ?>">
                <td><?php echo $dt->format("H:i:s"); ?></td>
                <td style="color:#777; font-size:0.8em;"><?php echo $item['id']; ?></td>
                <td><?php echo htmlspecialchars($item['title']); ?> - <small><?php echo htmlspecialchars($item['author']); ?></small></td>
                <td><?php echo (int)$item['dur']; ?>s</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="search-box">
        <form method="post">
            Cerca per ID: <input type="text" id="search_id" name="myid" value="<?php echo $_POST['myid'] ?? ''; ?>" style="padding:5px; width:70px;">
            <button type="submit">Cerca</button>
            <?php if ($search_result || isset($_POST['myid'])): ?>
                <a href="?" style="font-size:0.8em; margin-left:10px;">Pulisci</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="text-align:center; font-size:0.8em; margin-top:30px; color:#999; border-top:1px solid #eee; padding-top:10px;">
        Powered by I Colori del Navile APS | Email: info@radioacolori.net
    </div>
</div>

<script>
    var seconds = <?php echo $next_sec; ?>;
    var player = document.getElementById('radioPlayer');
    var playBtn = document.getElementById('playBtn');
    var isTyping = false;

    // Gestione Player
    function togglePlay() {
        if (player.paused) {
            player.play();
            playBtn.innerHTML = "⏸ Sospendi Ascolto";
            playBtn.style.background = "#444";
        } else {
            player.pause();
            player.src = player.src; // Forza reset del buffer per riprendere la diretta reale
            playBtn.innerHTML = "▶ Suona in Diretta";
            playBtn.style.background = "#d32f2f";
        }
    }

    // Blocca refresh durante scrittura
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

