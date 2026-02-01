<?php
include "local.php";

// Imposta il fuso orario locale
date_default_timezone_set('Europe/Rome');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("Errore di connessione");

$now = microtime(true);
$start_of_day = strtotime("today 00:00:00");
$tt = (int)floor($start_of_day / 86400);

// Funzione per formattare i secondi come nel tuo originale
function fmt_secs($s) {
    $s = (float)$s; $r = round($s);
    if (abs($s - $r) < 0.0005) return (string)(int)$r;
    return rtrim(rtrim(sprintf('%.3f', $s), '0'), '.');
}

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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 950px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #eee; padding-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .logo { max-width: 150px; height: auto; }
        .on-air { background: #fff3e0; padding: 20px; border-left: 5px solid #ff9800; margin: 20px 0; border-radius: 4px; }
        .track-title { font-size: 1.6em; font-weight: bold; color: #e65100; margin-bottom: 5px; }
        .track-info { font-size: 1.1em; color: #555; }
        .countdown-box { text-align: center; margin: 20px 0; padding: 15px; background: #f1f8e9; border-radius: 8px; }
        .countdown { font-size: 2.5em; font-weight: bold; color: #2e7d32; display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.95em; }
        th { background: #455a64; color: #fff; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .row-current { background: #fff9c4; font-weight: bold; border: 2px solid #ffeb3b; }
        .btn-listen { text-decoration: none; background: #d32f2f; color: #fff; padding: 12px 25px; border-radius: 25px; font-weight: bold; transition: background 0.3s; }
        .btn-listen:hover { background: #b71c1c; }
        .footer { text-align: center; font-size: 0.85em; color: #777; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="logo.jpg" alt="Logo" class="logo">
        <div style="flex-grow: 1; text-align: center;">
            <form method="post" style="display: inline-block; background: #f5f5f5; padding: 10px; border-radius: 5px;">
                <input type="text" name="myid" placeholder="Cerca ID" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 80px;">
                <button type="submit" style="padding: 8px 15px; cursor: pointer;">Cerca</button>
            </form>
            <?php
            if (!empty($_POST["myid"])) {
                $ids_esc = mysqli_real_escape_string($con, $_POST["myid"]);
                $q = mysqli_query($con, "SELECT title, author FROM track WHERE id='$ids_esc' LIMIT 1");
                if ($r = mysqli_fetch_assoc($q)) echo "<div style='font-size:12px; color:#d32f2f; margin-top:5px;'><b>{$r['title']}</b> - {$r['author']}</div>";
            }
            ?>
        </div>
        <a href="http://radioacolori.net" target="_blank" class="btn-listen">ASCOLTA LA DIRETTA</a>
    </div>

    <div class="on-air">
        <div style="font-size: 0.8em; text-transform: uppercase; letter-spacing: 1px; color: #666; margin-bottom: 5px;">Ora in Onda</div>
        <?php if ($current): ?>
            <div class="track-title"><?php echo htmlspecialchars($current['title']); ?></div>
            <div class="track-info">di <strong><?php echo htmlspecialchars($current['author']); ?></strong></div>
            <div style="margin-top:10px; font-size: 0.9em; color: #666;">
                Inizio locale: <b><?php echo date("H:i:s", $current['start_ts']); ?></b> | 
                ID: <?php echo $current['id']; ?> |
                Durata: <?php echo fmt_secs($current['dur_music']); ?>s 
                <?php if($current['dur_extra'] > 0) echo "+ ".fmt_secs($current['dur_extra'])."s extra"; ?>
            </div>
        <?php else: ?>
            <div class="track-title">Playlist terminata</div>
        <?php endif; ?>
    </div>

    <div class="countdown-box">
        <span class="countdown" id="cdw"><?php echo $next_sec; ?></span>
        secondi al prossimo cambio brano
    </div>

    <h3 style="color: #455a64; border-bottom: 2px solid #cfd8dc; padding-bottom: 10px;">Programmazione</h3>
    <table>
        <thead>
            <tr>
                <th>Ora Loc.</th>
                <th>ID</th>
                <th>Titolo / Autore</th>
                <th>Genere</th>
                <th>Totale</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $start_list = max(0, $current_idx - 3);
            $end_list = min(count($schedule) - 1, $current_idx + 8);
            for ($i = $start_list; $i <= $end_list; $i++):
                $item = $schedule[$i];
                $is_now = ($i === $current_idx);
            ?>
            <tr class="<?php echo $is_now ? 'row-current' : ''; ?>">
                <td><?php echo date("H:i:s", $item['start_ts']); ?></td>
                <td style="font-family: monospace;"><?php echo $item['id']; ?></td>
                <td>
                    <div style="color: #1565c0; font-weight: 600;"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div style="font-size: 0.85em; color: #666;"><?php echo htmlspecialchars($item['author']); ?></div>
                </td>
                <td><span style="font-size: 0.8em; background: #eee; padding: 2px 6px; border-radius: 3px;"><?php echo $item['genre']; ?></span></td>
                <td><?php echo fmt_secs($item['dur_music'] + $item['dur_extra']); ?>s</td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <div class="footer">
        <p><strong>Radio a Colori</strong> - APS I Colori del Navile</p>
        <p>CF 91357680379 | ROC 33355 | info@radioacolori.net</p>
        <p style="font-size: 0.8em;">Ora del server: <?php echo date("H:i:s"); ?></p>
    </div>
</div>

<script>
    var y = <?php echo $next_sec; ?>;
    setInterval(function(){
        y--;
        if(y < 0) { location.reload(); }
        else { 
            var el = document.getElementById('cdw');
            if(el) el.innerHTML = y; 
        }
    }, 1000);
</script>

</body>
</html>
<?php mysqli_close($con); ?>

