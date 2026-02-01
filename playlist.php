<?php
include "local.php";

// Set timezones
$local_tz = new DateTimeZone('Europe/Rome');
$utc_tz = new DateTimeZone('UTC');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("DB Connection failed");

// 1. Get Date from URL or default to today UTC
$today = new DateTime('today', $utc_tz);
$Y = isset($_GET['y']) ? (int)$_GET['y'] : (int)$today->format('Y');
$M = isset($_GET['m']) ? (int)$_GET['m'] : (int)$today->format('m');
$D = isset($_GET['d']) ? (int)$_GET['d'] : (int)$today->format('d');

$start_of_day = new DateTime("$Y-$M-$D 00:00:00", $utc_tz);
$start_ts = $start_of_day->getTimestamp();
$end_ts = $start_ts + 86399;

/**
 * 2. DATA RETRIEVAL & STATS CALCULATION
 */
$sql = "SELECT l.epoch, l.id, t.title, t.author, t.genre, t.duration, t.duration_extra, t.score, t.used
        FROM lineup l
        JOIN track t ON l.id = t.id
        WHERE l.epoch >= $start_ts AND l.epoch <= $end_ts
        ORDER BY l.epoch ASC";

$res = mysqli_query($con, $sql);
$rows = [];
$stats = ['s1' => 0, 's2' => 0, 'vocal_time' => 0, 'music_time' => 0];

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
        // Global Stats per day
        if ((int)$r['score'] === 2) $stats['s2']++; else $stats['s1']++;
        
        $duration = (float)$r['duration'] + (float)$r['duration_extra'];
        if (in_array($r['genre'], $special)) $stats['vocal_time'] += $duration;
        else $stats['music_time'] += $duration;
    }
    mysqli_free_result($res);
}
mysqli_close($con);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Lineup Summary</title>
    <style>
        body{font-family: 'Courier New', monospace; font-size: 13px; padding: 20px; background: #f4f4f4;}
        .container{background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
        table{border-collapse:collapse; width: 100%; margin-top: 10px;}
        td,th{padding:6px 10px; border:1px solid #ddd; text-align: left;}
        th{background:#333; color: #fff;}
        
        /* Color Coding */
        .vocal-bg { background-color: #f9f9f9; } /* Light grey for vocal items */
        .score-2 { color: #d9822b; font-weight: bold; } /* Orange/Gold for Premium */
        .score-1 { color: #333; } /* Standard black */
        .vocal-label { color: #d9534f; text-transform: uppercase; font-size: 10px; }
        
        .summary-box { display: flex; gap: 20px; margin-bottom: 20px; padding: 15px; background: #e9ecef; border-radius: 5px; }
        .stat-item { flex: 1; }
        .nav-bar { margin-bottom: 15px; }
        .badge { padding: 2px 5px; border-radius: 3px; font-size: 11px; background: #eee; }
    </style>
</head>
<body>

<div class="container">
    <h2>Lineup: <?php echo h($start_of_day->format('d-m-Y')); ?> (UTC)</h2>

    <div class='nav-bar'>
        <a href="?y=<?php echo date('Y'); ?>&m=<?php echo date('m'); ?>&d=<?php echo date('d'); ?>">GO TO TODAY</a>
        | <a href="?y=<?php echo date('Y', $start_ts-86400); ?>&m=<?php echo date('m', $start_ts-86400); ?>&d=<?php echo date('d', $start_ts-86400); ?>">PREVIOUS DAY</a>
        | <a href="?y=<?php echo date('Y', $start_ts+86400); ?>&m=<?php echo date('m', $start_ts+86400); ?>&d=<?php echo date('d', $start_ts+86400); ?>">NEXT DAY</a>
    </div>

    <?php if (count($rows) > 0): ?>
    <div class="summary-box">
        <div class="stat-item">
            <strong>COMPOSITION</strong><br>
            Standard (S1): <?php echo $stats['s1']; ?><br>
            Premium (S2): <?php echo $stats['s2']; ?> (<?php echo round(($stats['s2']/(count($rows)))*100,1); ?>%)
        </div>
        <div class="stat-item">
            <strong>TIME BALANCE</strong><br>
            Music: <?php echo gmdate("H:i:s", $stats['music_time']); ?><br>
            Vocal: <?php echo gmdate("H:i:s", $stats['vocal_time']); ?>
        </div>
        <div class="stat-item">
            <strong>RATIO</strong><br>
            Measured: <?php echo round($stats['music_time'] / ($stats['vocal_time'] ?: 1), 2); ?><br>
            Target: <?php echo $ratio; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>UTC</th>
                <th>LOCAL (ITA)</th>
                <th>ID</th>
                <th>TITLE / AUTHOR</th>
                <th>GENRE</th>
                <th>DUR</th>
                <th>SCR</th>
                <th>USED</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            foreach ($rows as $r): 
                $dt = new DateTime("@" . $r['epoch']);
                $dt_utc = clone $dt; $dt_utc->setTimezone($utc_tz);
                $dt_loc = clone $dt; $dt_loc->setTimezone($local_tz);
                
                $isVocal = in_array($r['genre'], $special);
                $rowClass = $isVocal ? 'vocal-bg' : '';
                $scoreClass = ($r['score'] == 2) ? 'score-2' : 'score-1';
            ?>
            <tr class="<?php echo $rowClass; ?>">
                <td><strong><?php echo $dt_utc->format('H:i:s'); ?></strong></td>
                <td style="color: #888;"><?php echo $dt_loc->format('H:i:s'); ?></td>
                <td><span class="badge"><?php echo h($r['id']); ?></span></td>
                <td class="<?php echo $scoreClass; ?>">
                    <?php echo ($r['score'] == 2) ? "⭐ " : ""; ?>
                    <?php echo h($r['title']); ?> <br>
                    <small style="color: #666; font-weight: normal;"><?php echo h($r['author']); ?></small>
                </td>
                <td>
                    <?php if($isVocal): ?><span class="vocal-label">[vocal]</span><br><?php endif; ?>
                    <small><?php echo h($r['genre']); ?></small>
                </td>
                <td><?php echo round($r['duration'] + $r['duration_extra']); ?>s</td>
                <td class="<?php echo $scoreClass; ?>"><?php echo $r['score']; ?></td>
                <td><?php echo $r['used']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No lineup generated for this day.</p>
    <?php endif; ?>
</div>

</body>
</html>
