<?php
include "local.php";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

$now = microtime(true);
$start_of_day = strtotime("today 00:00:00");
$elapsed_needed = $now - $start_of_day;

$eps = 0.001;

$tt = (int)floor($start_of_day / 86400);

$sql = "SELECT p.position, p.id, t.title, t.author, t.genre, t.duration, t.duration_extra
        FROM playlist p
        JOIN track t ON p.id = t.id
        WHERE p.tt = $tt
        ORDER BY p.position ASC";

$res = mysqli_query($con, $sql);
$tracks = [];
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $tracks[] = [
            'position'       => (int)$r['position'],
            'id'             => (string)$r['id'],
            'title'          => (string)$r['title'],
            'author'         => (string)$r['author'],
            'genre'          => (string)$r['genre'],
            'duration'       => (float)$r['duration'],
            'duration_extra' => (float)$r['duration_extra'],
        ];
    }
    mysqli_free_result($res);
}

$schedule = [];
$t_cursor = (float)$start_of_day;

foreach ($tracks as $t) {
    $dur_music = (float)$t['duration'];
    $dur_extra = (float)$t['duration_extra'];

    if ($dur_music < 0) $dur_music = 0.0;
    if ($dur_extra < 0) $dur_extra = 0.0;

    $music_end = $t_cursor + $dur_music;
    $end_ts    = $t_cursor + $dur_music + $dur_extra;

    $schedule[] = [
        'id'        => (string)$t['id'],
        'start_ts'  => $t_cursor,
        'music_end' => $music_end,
        'end_ts'    => $end_ts,
        'dur_music' => $dur_music,
        'dur_extra' => $dur_extra,
        'dur_total' => $dur_music + $dur_extra,
        'title'     => $t['title'],
        'author'    => $t['author'],
        'genre'     => $t['genre'],
    ];

    $t_cursor = $end_ts;
}

$current = null;
$current_index = -1;

for ($i = 0; $i < count($schedule); $i++) {
    $e = $schedule[$i];
    if ($now >= ($e['start_ts'] - $eps) && $now < ($e['end_ts'] - $eps)) {
        $current = $e;
        $current_index = $i;
        break;
    }
}

$next_seconds = 0;
if ($current !== null) {
    $next_seconds = max(0, (int)ceil(($current['end_ts'] - $now)));
}

echo "<script>\n";
echo "var y=".(int)$next_seconds.";\n";
echo "var x=setInterval(function(){\n";
echo "  var el=document.getElementById('cdw');\n";
echo "  if(el) el.innerHTML=y;\n";
echo "  y--;\n";
echo "  if(y<=0){location.reload();}\n";
echo "},1000);\n";
echo "</script>\n";

echo "<pre><table>";
echo "<td><img src='logo.jpg' width='10%' height='auto'><br>";
echo "<a href='http://radioacolori.net:8000/stream' target='_blank'>webradio</a></td>";

echo "<td><pre><form method='post'>"
   . "<input type='text' name='myid'>"
   . "<input type='submit' value='Cerca'>"
   . "</form>";

if (isset($_POST["myid"])) {
    $ids = trim((string)$_POST["myid"]);
    if (preg_match('/^\d{5}$/', $ids)) {
        $ids_esc = mysqli_real_escape_string($con, $ids);
        $q1 = mysqli_query($con, "SELECT title,author,genre,duration FROM track WHERE id='$ids_esc'");
        $row1 = $q1 ? mysqli_fetch_assoc($q1) : null;
        if ($q1) mysqli_free_result($q1);

        if ($row1 && isset($row1["title"]) && $row1["title"] !== null) {
            echo "Titolo: ".$row1["title"]."\n";
            echo "Autore: ".$row1["author"]."\n";
            echo "Genere: ".$row1["genre"]."\n";
            echo "Durata: ".fmt_secs((float)$row1["duration"])."s\n";
            echo "Identificativo: ".$ids."\n";
        } else {
            echo "Nessun risultato\n";
        }
    } else {
        echo "Inserisci un ID a 5 cifre\n";
    }
}
echo "</pre></td></table>";

echo "<p style='text-align: center'>I Colori del Navile APS presentano Radio a Colori\nMusica libera con licenza CC-BY\n</p>";

echo "<font color='blue'>State Ascoltando\n</font>";

if ($current === null) {
    echo "<font color='red'>Nessun brano determinabile (fine palinsesto o playlist vuota)\n</font>";
    echo "Ora: ".date("Y-m-d H:i:s", (int)$now)."\n\n";
} else {
    $id5 = (string)$current['id'];
    $in_extra = ($now >= ($current['music_end'] - $eps));

    echo "<font color='red'>Titolo: ".$current["title"]."\n";
    echo "Autore: ".$current["author"]."\n";
    echo "Genere: ".$current["genre"]."\n";
    echo "Durata: ".fmt_secs((float)$current["dur_music"])."s";
    if ((float)$current["dur_extra"] > 0.0) echo " + extra ".fmt_secs((float)$current["dur_extra"])."s";
    echo "\n</font>";

    if ($in_extra) {
        echo "Nota: chiusura/stacco in corso (considerato nel tempo del brano)\n";
    }

    echo "Inizio: ".date("Y-m-d H:i:s", (int)$current["start_ts"])."\n";
    echo "Identificativo: ".$id5."\n\n";
}

echo "<font color='blue'>Palinsesto\n</font>";

if (count($schedule) === 0) {
    echo "(vuoto)\n";
} else {
    $pp = ($current_index >= 0) ? $current_index : 0;
    $f = $pp - 4; if ($f < 0) $f = 0;
    $t = $pp + 4; if ($t >= count($schedule)) $t = count($schedule) - 1;

    for ($i = $f; $i <= $t; $i++) {
        $e = $schedule[$i];
        $id5 = (string)$e['id'];

        if ($i === $pp) echo "<font color='red'>";
        echo date("H:i:s", (int)$e["start_ts"])." | ".$id5;
        echo " | ".mystr($e["title"], 40);
        echo " | ".mystr($e["author"], 30);
        echo " | ".mystr($e["genre"], 20);
        echo " | ".fmt_secs((float)$e["dur_music"])."s";
        if ((float)$e["dur_extra"] > 0.0) echo "+".fmt_secs((float)$e["dur_extra"])."s";
        echo "\n";
        if ($i === $pp) echo "</font>";
    }
}

echo "Prossimo brano tra: <div style='display: inline' id='cdw'></div>s\n\n";

echo "<p style='text-align: center'>Powered by I Colori del Navile APS\n";
echo "Email info at radioacolori.net\nCF 91357680379 - ROC 33355\n</p>";

mysqli_close($con);

function mystr($a, $l) {
    $a = (string)$a;
    $la = mb_strlen($a);
    if ($la >= $l) return mb_substr($a, 0, $l - 1) . ">";
    return $a . str_repeat(" ", $l - $la);
}

function fmt_secs($s) {
    $s = (float)$s;
    $eps = 0.0005;
    $r = round($s);
    if (abs($s - $r) < $eps) return (string)(int)$r;
    $str = sprintf('%.3f', $s);
    $str = rtrim(rtrim($str, '0'), '.');
    return $str === '' ? '0' : $str;
}
?>
