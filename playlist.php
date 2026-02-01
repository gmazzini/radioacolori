<?php
include "local.php";

// Set timezone for Local Time calculations (adjust to your local zone)
$local_tz = new DateTimeZone('Europe/Rome');
$utc_tz = new DateTimeZone('UTC');

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit("DB Connection failed");

// 1. Get Date from URL or default to today UTC
$today = new DateTime('today', $utc_tz);
$Y = isset($_GET['y']) ? (int)$_GET['y'] : (int)$today->format('Y');
$M = isset($_GET['m']) ? (int)$_GET['m'] : (int)$today->format('m');
$D = isset($_GET['d']) ? (int)$_GET['d'] : (int)$today->format('d');

// Build the UTC start and end of the chosen day
$start_of_day = new DateTime("$Y-$M-$D 00:00:00", $utc_tz);
$start_ts = $start_of_day->getTimestamp();
$end_ts = $start_ts + 86399;

/**
 * 2. FETCH DATA FROM NEW LINEUP TABLE
 * We join with 'track' to get the metadata using 'epoch' as the time reference
 */
$sql = "SELECT l.epoch, l.id, t.title, t.author, t.genre, t.duration, t.duration_extra, t.score, t.used
        FROM lineup l
        JOIN track t ON l.id = t.id
        WHERE l.epoch >= $start_ts AND l.epoch <= $end_ts
        ORDER BY l.epoch ASC";

$res = mysqli_query($con, $sql);
$rows = [];
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    mysqli_free_result($res);
}

mysqli_close($con);

// Helper functions for formatting
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function fmt_secs($s) { return round((float)$s, 2) . "s"; }

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Lineup Viewer (UTC Based)</title>
    <style>
        body{font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; padding: 20px;}
        table{border-collapse:collapse; width: 100%; margin-top: 20px;}
        td,th{padding:8px; border:1px solid #eee; text-align: left;}
        th{background:#f8f9fa; color: #333; position: sticky; top: 0;}
        tr:hover{background: #f1f1f1;}
        .vocal-row{color:#d9534f; font-weight: bold;} /* Red for vocal content */
        .music-row{color:#2e6da4;} /* Blue for music */
        .small{font-size:12px; color:#666;}
        .nav-bar{margin-bottom: 20px; background: #eee; padding: 10px; border-radius: 4px;}
        .time-col{background: #fafafa; font-weight: bold;}
    </style>
</head>
<body>

<h2>Schedule for <?php echo h($start_of_day->format('Y-m-d')); ?> (UTC Day)</h2>

<div class='nav-bar'>
    <form method='get' style="display: inline-block;">
        <input type='number' name='d' min='1' max='31' value='<?php echo $D; ?>' style='width:40px'>
        <input type='number' name='m' min='1' max='12' value='<?php echo $M; ?>' style='width:40px'>
        <input type='number' name='y' min='1970' max='2100' value='<?php echo $Y; ?>' style='width:60px'>
        <button type='submit'>Go to Date</button>
    </form>
    |
    <?php 
    $prev = (clone $start_of_day)->modify('-1 day');
    $next = (clone $start_of_day)->modify('+1 day');
    ?>
    <a href="?d=<?php echo $prev->format('d&m=m&y=Y'); ?>">« Previous Day</a> |
    <a href="?d=<?php echo date('d&m=m&y=Y'); ?>">Today</a> |
    <a href="?d=<?php echo $next->format('d&m=m&y=Y'); ?>">Next Day »</a>
</div>

<?php if (count($rows) === 0): ?>
    <p>No tracks scheduled for this day.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>UTC Start</th>
                <th>Local Start</th>
                <th>ID</th>
                <th>Title</th>
                <th>Author</th>
                <th>Genre</th>
                <th>Duration</th>
                <th>Score</th>
                <th>Used</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            foreach ($rows as $r): 
                // Time conversion logic
                $dt = new DateTime("@" . $r['epoch']);
                $dt_utc = clone $dt; $dt_utc->setTimezone($utc_tz);
                $dt_loc = clone $dt; $dt_loc->setTimezone($local_tz);
                
                $isVocal = in_array($r['genre'], $special);
                $rowClass = $isVocal ? 'vocal-row' : 'music-row';
            ?>
            <tr class="<?php echo $rowClass; ?>">
                <td class="time-col"><?php echo $dt_utc->format('H:i:s'); ?></td>
                <td class="time-col" style="color: #666;"><?php echo $dt_loc->format('H:i:s'); ?></td>
                <td><?php echo h($r['id']); ?></td>
                <td><?php echo h($r['title']); ?></td>
                <td><?php echo h($r['author']); ?></td>
                <td><span class="small"><?php echo h($r['genre']); ?></span></td>
                <td><?php echo fmt_secs($r['duration'] + $r['duration_extra']); ?></td>
                <td><?php echo $r['score']; ?></td>
                <td><?php echo $r['used']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class='small' style="margin-top: 20px;">
    * <strong>UTC Time</strong> is the reference for the server schedule.<br>
    * <strong>Local Time</strong> is calculated based on <?php echo $local_tz->getName(); ?>.
</div>

</body>
</html>

