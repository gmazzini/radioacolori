<?php
include "local.php";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

// --- Selezione data: default oggi ---
$todayY = (int)date('Y');
$todayM = (int)date('n');
$todayD = (int)date('j');

$Y = isset($_GET['y']) ? (int)$_GET['y'] : $todayY;
$M = isset($_GET['m']) ? (int)$_GET['m'] : $todayM;
$D = isset($_GET['d']) ? (int)$_GET['d'] : $todayD;

// Normalizza range ragionevoli
if ($Y < 1970) $Y = 1970;
if ($Y > 2100) $Y = 2100;
if ($M < 1) $M = 1;
if ($M > 12) $M = 12;

// Se giorno non valido per mese/anno, clamp
$daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $M, $Y);
if ($D < 1) $D = 1;
if ($D > $daysInMonth) $D = $daysInMonth;

// 00:00 locale del giorno scelto (coerente con index/sched)
$start_of_day = mktime(0, 0, 0, $M, $D, $Y);
$tt = (int)floor($start_of_day / 86400);

// --- Carico playlist del giorno con join track ---
$sql = "SELECT p.position, p.id, t.title, t.author, t.genre, t.duration, t.duration_extra, t.score, t.used, t.gid, t.gsel
        FROM playlist p
        JOIN track t ON p.id = t.id
        WHERE p.tt = $tt
        ORDER BY p.position ASC";

$res = mysqli_query($con, $sql);
$rows = [];
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $d = (float)$r['duration'];
        $e = (float)$r['duration_extra'];
        if ($d < 0) $d = 0.0;
        if ($e < 0) $e = 0.0;

        $rows[] = [
            'position' => (int)$r['position'],
            'id'       => (int)$r['id'],
            'title'    => (string)$r['title'],
            'author'   => (string)$r['author'],
            'genre'    => (string)$r['genre'],
            'duration' => $d,
            'extra'    => $e,
            'score'    => isset($r['score']) ? (int)$r['score'] : 0,
            'used'     => isset($r['used']) ? (int)$r['used'] : 0,
            'gid'      => isset($r['gid']) ? (string)$r['gid'] : '',
            'gsel'     => isset($r['gsel']) ? (int)$r['gsel'] : 0,
        ];
    }
    mysqli_free_result($res);
}

mysqli_close($con);

// --- Helpers ---
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function fmt_secs($s) {
    $s = (float)$s;
    $eps = 0.0005;
    $r = round($s);
    if (abs($s - $r) < $eps) return (string)(int)$r;
    $str = sprintf('%.3f', $s);
    $str = rtrim(rtrim($str, '0'), '.');
    return $str === '' ? '0' : $str;
}

function pad($s, $n) {
    $s = (string)$s;
    $len = mb_strlen($s);
    if ($len >= $n) return mb_substr($s, 0, $n - 1) . ">";
    return $s . str_repeat(" ", $n - $len);
}

// --- Output UI ---
header('Content-Type: text/html; charset=utf-8');

echo "<!doctype html><html><head><meta charset='utf-8'><title>Playlist viewer</title>
<style>
body{font-family:monospace;}
form{margin:10px 0 20px 0;}
table{border-collapse:collapse;}
td,th{padding:4px 8px;border:1px solid #ddd;}
th{background:#f5f5f5;}
.small{font-size:12px;color:#555;}
pre{white-space:pre;}
.blue{color:#006;}
</style>
</head><body>";

echo "<h2>Playlist prevista</h2>";

// form data
echo "<form method='get'>
<label>Giorno <input type='number' name='d' min='1' max='31' value='".h($D)."' style='width:5em'></label>
<label>Mese <input type='number' name='m' min='1' max='12' value='".h($M)."' style='width:5em'></label>
<label>Anno <input type='number' name='y' min='1970' max='2100' value='".h($Y)."' style='width:7em'></label>
<button type='submit'>Vai</button>
<span class='small'>&nbsp; (default: oggi)</span>
</form>";

// quick nav (ieri/oggi/domani)
$tsChosen = $start_of_day;
$tsPrev = $tsChosen - 86400;
$tsNext = $tsChosen + 86400;

echo "<div class='small'>";
echo "<a href='?d=".date('j',$tsPrev)."&m=".date('n',$tsPrev)."&y=".date('Y',$tsPrev)."'>← ieri</a> | ";
echo "<a href='?d=".date('j')."&m=".date('n')."&y=".date('Y')."'>oggi</a> | ";
echo "<a href='?d=".date('j',$tsNext)."&m=".date('n',$tsNext)."&y=".date('Y',$tsNext)."'>domani →</a>";
echo "</div><br>";

echo "<div class='small'>Data: <b>".h(date('Y-m-d', $start_of_day))."</b> — tt=<b>".h($tt)."</b></div><br>";

if (count($rows) === 0) {
    echo "<p><b>Nessuna playlist trovata</b> per questo giorno (tt=$tt).</p>";
    echo "</body></html>";
    exit;
}

// calcolo orari (float) come index/sched: start_ts progressivo = start_of_day + cumulativa
$cum = 0.0;
$total_music = 0.0;
$total_extra = 0.0;

echo "<table>";
echo "<tr>
<th>#</th><th>Start</th><th>ID</th><th>Titolo</th><th>Autore</th><th>Genere</th>
<th>Dur</th><th>Extra</th><th>Tot</th><th>Score</th><th>Used</th><th>GID</th><th>Gsel</th>
</tr>";

foreach ($rows as $r) {
    $start_ts = (float)$start_of_day + $cum;
    $start_str = date('H:i:s', (int)$start_ts); // UI a secondi; tempi interni restano float

    $d = (float)$r['duration'];
    $e = (float)$r['extra'];

    $total_music += $d;
    $total_extra += $e;

    $isSpecial = in_array($r['genre'], $special ?? [], true);
    $cls = $isSpecial ? " class='blue'" : "";

    echo "<tr$cls>";
    echo "<td>".h($r['position'])."</td>";
    echo "<td>".h($start_str)."</td>";
    echo "<td>".h(sprintf('%05d',$r['id']))."</td>";
    echo "<td>".h($r['title'])."</td>";
    echo "<td>".h($r['author'])."</td>";
    echo "<td>".h($r['genre'])."</td>";
    echo "<td style='text-align:right'>".h(fmt_secs($d))."s</td>";
    echo "<td style='text-align:right'>".h(fmt_secs($e))."s</td>";
    echo "<td style='text-align:right'>".h(fmt_secs($d+$e))."s</td>";
    echo "<td style='text-align:right'>".h($r['score'])."</td>";
    echo "<td style='text-align:right'>".h($r['used'])."</td>";
    echo "<td>".h($r['gid'])."</td>";
    echo "<td style='text-align:right'>".h($r['gsel'])."</td>";
    echo "</tr>";

    $cum += ($d + $e);
}
echo "</table>";

echo "<br><div class='small'>";
echo "Totale musica (solo duration): <b>".h(fmt_secs($total_music))."s</b> — ";
echo "Totale extra: <b>".h(fmt_secs($total_extra))."s</b> — ";
echo "Totale complessivo: <b>".h(fmt_secs($total_music + $total_extra))."s</b>";
echo "</div>";

echo "</body></html>";
?>
