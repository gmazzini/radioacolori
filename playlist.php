<?php
include "local.php";

/** 
 * TIMEZONE SETTINGS
 * UTC for server/day logic, Local for display reference
 */
$local_tz = new DateTimeZone('Europe/Rome');
$utc_tz = new DateTimeZone('UTC');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("DB Connection failed");

/**
 * 1. DATE SELECTION
 * Defaults to current UTC day if no parameters are provided
 */
$today = new DateTime('today', $utc_tz);
$Y = isset($_GET['y']) ? (int)$_GET['y'] : (int)$today->format('Y');
$M = isset($_GET['m']) ? (int)$_GET['m'] : (int)$today->format('m');
$D = isset($_GET['d']) ? (int)$_GET['d'] : (int)$today->format('d');

$start_of_day = new DateTime("$Y-$M-$D 00:00:00", $utc_tz);
$start_ts = $start_of_day->getTimestamp();
$end_ts = $start_ts + 86399;

/**
 * 2. DATA RETRIEVAL & STATISTICS
 */
$sql = "SELECT l.epoch, l.id, t.title, t.author, t.genre, t.duration, t.duration_extra, t.score, t.used
        FROM lineup l
        JOIN track t ON l.id = t.id
        WHERE l.epoch >= $start_ts AND l.epoch <= $end_ts
        ORDER BY l.epoch ASC";

$res = mysqli_query($con, $sql);
$rows = [];
$stats = [
    's1' => 0, 
    's2' => 0, 
    'v_time' => 0, 
    'm_time' => 0, 
    'total_count' => 0
];

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
        $stats['total_count']++;
        
        // Quality Score Stats
        if ((int)$r['score'] === 2) $stats['s2']++; else $stats['s1']++;
        
        // Genre/Time Stats
        $dur = (float)$r['duration'] + (float)$r['duration_extra'];
        if (in_array($r['genre'], $special)) {
            $stats['v_time'] += $dur;
        } else {
            $stats['m_time'] += $dur;
        }
    }
    mysqli_free_result($res);
}
mysqli_close($con);

// Calculate Premium Ratio %
$premium_perc = ($stats['total_count'] > 0) ? round(($stats['s2'] / $stats['total_count']) * 100, 1) : 0;

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Lineup Viewer</title>
    <style>
        body{font-family: monospace; font-size: 13px; margin: 0; padding: 20px; background: #fff;}
        
        /* STICKY HEADER & SUMMARY LOGIC */
        .summary { 
            background: #f8f9fa; 
            padding: 15px; 
            border: 1px solid #ddd; 
            margin-bottom: 20px; 
            position: sticky; 
            top: 0; 
            z-index: 20; /* Higher than table header */
        }
        
        table { border-collapse: collapse; width: 100%; }
        th { 
            position: sticky; 
            top: 65px; /* Offset to stay below the sticky summary box */
            background: #333; 
            color: #fff; 
            z-index: 10; 
            padding: 10px;
            border: 1px solid #444;
        }
        
        td { padding: 6px 8px; border: 1px solid #ddd; white-space: nowrap; }
        
        /* COLOR CODING */
        .vocal-row { background-color: #ffecec !important; color: #a00; } /* Light Red for Vocal */
        .music-row { color: #004085; } /* Dark Blue for Music */
        .premium { font-weight: bold; background-color: #fff9c4 !important; } /* Yellow for Score 2 */
        
        .nav { margin-bottom: 10px; }
        .small { font-size: 11px; color: #666; }
        .highlight { color: #000; font-weight: bold; }
    </style>
</head>
<body>

<div class="summary">
    <div class="nav">
        <strong>DATE: <?php echo $start_of_day->format('Y-m-d'); ?> (UTC)</strong> | 
        <a href="?y=<?php echo date('Y'); ?>&m=<?php echo date('n'); ?>&d=<?php echo date('j'); ?>">TODAY</a> |
        <a href="?y=<?php echo date('Y', $start_ts-86400); ?>&m=<?php echo date('n', $start_ts-86400); ?>&d=<?php echo date('j', $start_ts-86400); ?>">PREV</a> |
        <a href="?y=<?php echo date('Y', $start_ts+86400); ?>&m=<?php echo date('n', $start_ts+86400); ?>&d=<?php echo date('j', $start_ts+86400); ?>">NEXT</a>
    </div>
    <div class="small">
        Tracks: <span class="highlight"><?php echo $stats['total_count']; ?></span> | 
        Premium (S2): <span class="highlight"><?php echo $stats['s2']; ?> (<?php echo $premium_perc; ?>%)</span> | 
        Ratio M/V: <span class="highlight"><?php echo round($stats['m_time']/($stats['v_time']?:1), 2); ?></span> (Target: <?php echo $ratio; ?>) |
        Time: Music <span class="highlight"><?php echo gmdate("H:i:s", $stats['m_time']); ?></span> - Vocal <span class="highlight"><?php echo gmdate("H:i:s", $stats['v_time']); ?></span>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>UTC</th>
            <th>LOCAL</th>
            <th>ID</th>
            <th>TITLE</th>
            <th>AUTHOR</th>
            <th>GENRE</th>
            <th>DUR</th>
            <th>SCR</th>
            <th>USED</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): 
            $dt = new DateTime("@" . $r['epoch']);
            $dt_utc = clone $dt; $dt_utc->setTimezone($utc_tz);
            $dt_loc = clone $dt; $dt_loc->setTimezone($local_tz);
            
            // Genre classification
            $isVocal = in_array($r['genre'], $special);
            
            // Build Row Classes
            $class = $isVocal ? 'vocal-row' : 'music-row';
            if ((int)$r['score'] === 2) $class .= ' premium';
        ?>
        <tr class="<?php echo $class; ?>">
            <td><strong><?php echo $dt_utc->format('H:i:s'); ?></strong></td>
            <td style="opacity: 0.6;"><?php echo $dt_loc->format('H:i:s'); ?></td>
            <td><?php echo h($r['id']); ?></td>
            <td><?php echo h($r['title']); ?></td>
            <td><?php echo h($r['author']); ?></td>
            <td><?php echo h($r['genre']); ?></td>
            <td style="text-align:right;"><?php echo (int)($r['duration'] + $r['duration_extra']); ?>s</td>
            <td style="text-align:center;"><?php echo $r['score']; ?></td>
            <td style="text-align:right;"><?php echo $r['used']; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>

