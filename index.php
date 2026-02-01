<?php
include "local.php";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("Errore di connessione");

$now = microtime(true);
$start_of_day = strtotime("today 00:00:00");
$tt = (int)floor($start_of_day / 86400);

// Query allineata alla struttura tt della playlist
$sql = "SELECT p.position, p.id, t.title, t.author, t.genre, t.duration, t.duration_extra
        FROM playlist p
        JOIN track t ON p.id = t.id
        WHERE p.tt = $tt
        ORDER BY p.position ASC";

$res = mysqli_query($con, $sql);
$schedule = [];
$t_cursor = (float)$start_of_day;

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $dur_total = (float)$r['duration'] + (float)$r['duration_extra'];
        $schedule[] = [
            'id'        => $r['id'],
            'start_ts'  => $t_cursor,
            'music_end' => $t_cursor + (float)$r['duration'],
            'end_ts'    => $t_cursor + $dur_total,
            'title'     => $r['title'],
            'author'    => $r['author'],
            'genre'     => $r['genre'],
            'dur_music' => (float)$r['duration'],
            'dur_extra' => (float)$r['duration_extra']
        ];
        $t_cursor += $dur_total;
    }
}

// Identificazione brano attuale
$current = null;
$current_idx = -1;
foreach ($schedule as $idx => $item) {
    if ($now >= $item['start_ts'] && $now < $item['end_ts']) {
        $current = $item;
        $current_idx = $idx;
        break;
    }
}

$next_sec = $current ? (int)ceil($current['end_ts'] - $now) : 0;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Radio a Colori - On Air</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f9; color: #333; line-height: 1.6; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #eee; padding-bottom: 20px; }
        .logo { max-width: 150px; }
        .on-air { background: #e3f2fd; padding: 20px; border-left: 5px solid #2196f3; margin: 20px 0; border-radius: 4px; }
        .on-air h2 { margin-top: 0; color: #0d47a1; font-size: 1.2em; text-transform: uppercase; }
        .track-title { font-size: 1.5em; font-weight: bold; color: #d32f2f; }
        .track-info { font-style: italic; color: #555; }
        .countdown { font-size: 2em; font-weight: bold; color: #2e7d32; text-align: center; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9em; margin-top: 20px; }
        th { background: #333; color: #fff; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        .row-current { background: #fff9c4; font-weight: bold; }
        .footer { text-align: center; font-size: 0.8em; color: #777; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .search-box { background: #eee; padding: 10px; border-radius: 4px; }
        input[type="text"] { padding: 5px; border: 1px solid #ccc; border-radius: 3px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="logo.jpg" alt="Logo" class="logo">
        <div class="search-box">
            <form method="post">
                <input type="text" name="myid" placeholder="ID Brano (5 cifre)" maxlength="5">
                <button type="submit">Cerca</button>
            </form>
            <?php
            if (!empty($_POST["myid"])) {
                $ids_esc = mysqli_real_escape_string($con, $_POST["myid"]);
                $q = mysqli_query($con, "SELECT title, author FROM track WHERE id='$ids_esc' LIMIT 1");
                if ($r = mysqli_fetch_assoc($q)) echo "<div style='font-size:11px'>Trovo: <b>{$r['title']}</b> - {$r['author']}</div>";
            }
            ?>
        </div>
        <a href="http://radioacolori.net:8000/stream" target="_blank" style="text-decoration:none; background:#d32f2f; color:#fff; padding:10px 20px; border-radius:20px;">ASCOLTA ORA</a>
    </div>

    <div class="on-air">
        <h2>Ora in Onda</h2>
        <?php if ($current): ?>
            <div class="track-title"><?php echo htmlspecialchars($current['title']); ?></div>
            <div class="track-info">di <?php echo htmlspecialchars($current['author']); ?></div>
            <div style="margin-top:10px">
                Genere: <b><?php echo $current['genre']; ?></b> | 
                Durata: <?php echo round($current['dur_music']); ?>s 
                <?php if($current['dur_extra'] > 0) echo "+ extra ".round($current['dur_extra'])."s"; ?>
            </div>
        <?php else: ?>
            <div class="track-title">Fine Programmazione</div>
        <?php endif; ?>
    </div>

    <div class="countdown" id="cdw"><?php echo $next_sec; ?></div>
    <div style="text-align:center; font-size:0.8em; color:#999;">secondi al prossimo brano</div>

    <h3>Palinsesto Recente</h3>
    <table>
        <thead>
            <tr>
                <th>Ora</th>
                <th>ID</th>
                <th>Titolo / Autore</th>
                <th>Genere</th>
                <th>Durata</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $start_list = max(0, $current_idx - 3);
            $end_list = min(count($schedule) - 1, $current_idx + 5);
            for ($i = $start_list; $i <= $end_list; $i++):
                $item = $schedule[$i];
                $is_now = ($i === $current_idx);
            ?>
            <tr class="<?php echo $is_now ? 'row-current' : ''; ?>">
                <td><?php echo date("H:i:s", $item['start_ts']); ?></td>
                <td><?php echo $item['id']; ?></td>
                <td>
                    <b><?php echo htmlspecialchars($item['title']); ?></b><br>
                    <span style="font-size:0.8em"><?php echo htmlspecialchars($item['author']); ?></span>
                </td>
                <td><?php echo $item['genre']; ?></td>
                <td><?php echo round($item['dur_music'] + $item['dur_extra']); ?>s</td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <div class="footer">
        <p><b>Radio a Colori</b> - Musica libera con licenza CC-BY</p>
        <p>I Colori del Navile APS | CF 91357680379 | ROC 33355 | info@radioacolori.net</p>
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

