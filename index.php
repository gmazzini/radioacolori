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
    <title>Radio a Colori - Live</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f9; padding: 15px; }
        .container { max-width: 850px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; }
        .main-logo { height: 120px; margin-bottom: 10px; }
        .top-search { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #ddd; text-align: center; }
        .btn-direct { display: inline-block; background: #d32f2f; color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 1.2em; margin: 10px 0; }
        .on-air { background: #fff3e0; padding: 15px; border-radius: 8px; border-left: 6px solid #ff9800; margin: 15px 0; text-align: left; }
        .track-title { font-size: 1.6em; font-weight: bold; color: #e65100; display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; text-align: left; }
        th { background: #444; color: #fff; padding: 10px; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .row-now { background: #fff9c4; font-weight: bold; }
        .footer { font-size: 0.9em; color: #555; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px; line-height: 1.6; }
    </style>
</head>
<body>

<div class="container">
    <img src="logo.jpg" class="main-logo">
    <p><strong>I Colori del Navile APS presentano Radio a Colori</strong><br></p>

    <div class="top-search">
        <form method="post">
            Cerca ID: <input type="text" id="search_id" name="myid" value="<?php echo htmlspecialchars($_POST['myid'] ?? ''); ?>" style="width:60px; padding:5px;">
            <button type="submit">Cerca</button>
            <?php if (isset($_POST['myid'])): ?><a href="?" style="margin-left:5px; color:#666; font-size:0.8em;">[Reset]</a><?php endif; ?>
        </form>
        <?php if ($search_result): ?>
            <div style="background:#e8f5e9; padding:10px; margin-top:10px; border-radius:5px;">
                <b>Trovato:</b> <?php echo htmlspecialchars($search_result['title']); ?> - <?php echo htmlspecialchars($search_result['author']); ?>
            </div>
        <?php endif; ?>
    </div>

    <a href="http://radioacolori.net:8000/stream" target="_blank" class="btn-direct">▶ SUONA IN DIRETTA</a>

    <div class="on-air">
        <b style="color:blue">STATE ASCOLTANDO</b>
        <?php if ($current): ?>
            <span class="track-title"><?php echo htmlspecialchars($current['title']); ?></span>
            di <b><?php echo htmlspecialchars($current['author']); ?></b> <small>(ID: <?php echo $current['id']; ?>)</small>
        <?php endif; ?>
    </div>

    <p>Cambio brano tra: <b id="cdw"><?php echo $next_sec; ?></b> s</p>

    <table>
        <thead><tr><th>Ora</th><th>ID</th><th>Brano</th><th>Durata</th></tr></thead>
        <tbody>
            <?php foreach ($schedule as $item): 
                $dt = new DateTime("@" . (int)$item['start']); $dt->setTimezone($local_tz); ?>
            <tr class="<?php echo $item['is_now'] ? 'row-now' : ''; ?>">
                <td><?php echo $dt->format("H:i:s"); ?></td>
                <td><?php echo $item['id']; ?></td>
                <td><?php echo htmlspecialchars($item['title']); ?> - <small><?php echo htmlspecialchars($item['author']); ?></small></td>
                <td><?php echo (int)$item['dur']; ?>s</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <strong>Powered by I Colori del Navile APS</strong><br>
        Musica libera con licenza <strong>CC-BY</strong><br>
        Email info at radioacolori.net | CF 91357680379 - ROC 33355
    </div>
</div>

<script>
    var seconds = <?php echo $next_sec; ?>;
    var isTyping = false;
    document.getElementById('search_id').onfocus = function() { isTyping = true; };
    document.getElementById('search_id').onblur = function() { isTyping = false; };

    setInterval(function(){
        if (seconds <= 0) { if (!isTyping) location.reload(); } 
        else { seconds--; document.getElementById('cdw').innerHTML = seconds; }
    }, 1000);
</script>
</body>
</html>
