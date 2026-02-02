<?php
include "local.php";
date_default_timezone_set('UTC');
$local_tz = new DateTimeZone('Europe/Rome');
$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("Errore DB");

$search_result = null;
if (isset($_POST['myid']) && !empty($_POST['myid'])) {
    $sid = mysqli_real_escape_string($con, $_POST['myid']);
    $q_search = mysqli_query($con, "SELECT * FROM track WHERE id = '$sid' LIMIT 1");
    $search_result = mysqli_fetch_assoc($q_search);
}

$now = microtime(true);
$now_int = (int)floor($now);
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
        .header { text-align: center; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
        .main-logo { height: 120px; display: block; margin: 0 auto 10px; }
        .top-search { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd; }
        .result-box { background: #e8f5e9; border-left: 5px solid #4caf50; padding: 10px; margin-top: 10px; }
        .live-section { text-align: center; margin-bottom: 20px; }
        .btn-live { background: #d32f2f; color: white !important; padding: 15px 35px; border: none; border-radius: 50px; font-weight: bold; cursor: pointer; font-size: 1.2em; box-shadow: 0 4px 8px rgba(0,0,0,0.3); }
        .btn-live.playing { background: #333; }
        .on-air { background: #fff3e0; padding: 15px; border-radius: 8px; border-left: 6px solid #ff9800; margin: 15px 0; }
        .track-title { font-size: 1.5em; font-weight: bold; color: #e65100; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #444; color: #fff; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .row-now { background: #fff9c4; font-weight: bold; }
        .footer { text-align: center; font-size: 0.85em; color: #777; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="logo.jpg" class="main-logo">
        <p><strong>I Colori del Navile APS presentano Radio a Colori</strong></p>
    </div>

    <div class="top-search">
        <form method="post">
            <strong>Cerca brano per ID:</strong> 
            <input type="text" id="search_id" name="myid" value="<?php echo htmlspecialchars($_POST['myid'] ?? ''); ?>" style="width:70px; padding:6px;">
            <button type="submit">Cerca</button>
            <?php if (isset($_POST['myid'])): ?><a href="?" style="margin-left:10px; color:#666;">[X]</a><?php endif; ?>
        </form>
        <?php if ($search_result): ?>
            <div class="result-box"><b>Trovato:</b> <?php echo htmlspecialchars($search_result['title']); ?> - <?php echo htmlspecialchars($search_result['author']); ?></div>
        <?php endif; ?>
    </div>

    <div class="live-section">
        <button onclick="togglePlay()" id="playBtn" class="btn-live">▶ Suona in Diretta</button>
    </div>

    <div class="on-air">
        <span style="color:blue; font-weight:bold;">STATE ASCOLTANDO</span>
        <?php if ($current): ?>
            <div class="track-title"><?php echo htmlspecialchars($current['title']); ?></div>
            <div>di <b><?php echo htmlspecialchars($current['author']); ?></b> <small>(ID: <?php echo $current['id']; ?>)</small></div>
        <?php endif; ?>
    </div>

    <div style="text-align:center; padding:10px;">Prossimo brano tra: <b id="cdw" style="font-size:1.4em; color:#2e7d32;"><?php echo $next_sec; ?></b> s</div>

    <table>
        <thead><tr><th>Ora</th><th>ID</th><th>Brano</th><th>Durata</th></tr></thead>
        <tbody>
            <?php foreach ($schedule as $item): 
                $dt = new DateTime("@" . (int)$item['start']); $dt->setTimezone($local_tz); ?>
            <tr class="<?php echo $item['is_now'] ? 'row-now' : ''; ?>">
                <td><?php echo $dt->format("H:i:s"); ?></td>
                <td><?php echo $item['id']; ?></td>
                <td><?php echo htmlspecialchars($item['title']); ?> - <?php echo htmlspecialchars($item['author']); ?></td>
                <td><?php echo (int)$item['dur']; ?>s</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">Powered by I Colori del Navile APS | CF 91357680379</div>
</div>

<script>
    var seconds = <?php echo $next_sec; ?>;
    var isTyping = false;
    var player = null;
    var playBtn = document.getElementById('playBtn');

    function togglePlay() {
        if (!player || player.paused) {
            if(!player) player = new Audio();
            player.src = "http://radioacolori.net";
            player.play().then(() => {
                playBtn.innerHTML = "⏸ Sospendi Ascolto";
                playBtn.classList.add('playing');
            }).catch(e => { alert("Errore: controlla se il tuo browser blocca i siti non-https"); });
        } else {
            player.pause();
            player.src = ""; // Taglia la connessione
            playBtn.innerHTML = "▶ Suona in Diretta";
            playBtn.classList.remove('playing');
        }
    }

    document.getElementById('search_id').onfocus = function() { isTyping = true; };
    document.getElementById('search_id').onblur = function() { isTyping = false; };

    setInterval(function(){
        if (seconds <= 0) { if (!isTyping) location.reload(); } 
        else { seconds--; document.getElementById('cdw').innerHTML = seconds; }
    }, 1000);
</script>
</body>
</html>


