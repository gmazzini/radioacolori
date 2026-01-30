<?php
include "local.php";

$con = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname);
if (!$con) exit;

// uso microtime per coerenza con durate float (anche se la UI resta a secondi)
$now = microtime(true);
$start_of_day = strtotime("today 00:00:00"); // int
$elapsed_needed = $now - $start_of_day;

// tolleranza anti-balletti float (1 ms)
$eps = 0.001;

// tt coerente col giorno locale
$tt = (int) floor($start_of_day / 86400);

/**
 * Carico playlist del giorno (una riga per brano).
 * NB: lascio JOIN p.id=t.id come nel tuo modello.
 */
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
            'id'             => (int)$r['id'],
            'title'          => (string)$r['title'],
            'author'         => (string)$r['author'],
            'genre'          => (string)$r['genre'],
            'duration'       => (float)$r['duration'],
            'duration_extra' => (float)$r['duration_extra'],
        ];
    }
    mysqli_free_result($res);
}

/**
 * Costruisco “palinsesto” SOLO brani.
 * Ogni brano dura total = duration + duration_extra (extra conta nei tempi ma non compare come riga).
 */
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
        'id'        => (int)$t['id'],
        'start_ts'  => $t_cursor,     // float
        'music_end' => $music_end,    // float
        'end_ts'    => $end_ts,       // float
        'dur_music' => $dur_music,    // float
        'dur_extra' => $dur_extra,    // float
        'dur_total' => $dur_music + $dur_extra,
        'title'     => $t['title'],
        'author'    => $t['author'],
        'genre'     => $t['genre'],
    ];

    $t_cursor = $end_ts;
}

/**
 * Trovo brano corrente: now ∈ [start_ts, end_ts)
 * (con epsilon per evitare oscillazioni ai bordi)
 */
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

// Countdown al prossimo BRANO (fine segmento totale brano+extra)
// uso ceil per non arrivare a 0 “troppo presto”
$next_seconds = 0;
if ($current !== null) {
    $next_seconds = max(0, (int)ceil(($current['end_ts'] - $now)));
}

/**
 * JS countdown (reload al cambio brano)
 */
echo "<script>\n";
echo "var y=".(int)$next_seconds.";\n";
echo "var x=setInterval(function(){\n";
echo "  var el=document.getElementById('cdw');\n";
echo "  if(el) el.innerHTML=y;\n";
echo "  y--;\n";
echo "  if(y<=0){location.reload();}\n";
echo "},1000);\n";
echo "</script>\n";

/**
 * Header + ricerca
 */
echo "<pre><table>";
echo "<td><img src='logo.jpg' width='10%' height='auto'><br>";
echo "<a href='http://radioacolori.net:8000/stream' target='_blank'>webradio</a></td>";

echo "<td><pre><form method='post'>"
   . "<input type='text' name='myid'>"
   . "<input type='submit' value='Cerca'>"
   . "</form>";

if (isset($_POST["myid"])) {
    $ids = (int)$_POST["myid"]; // evita injection
    if ($ids > 0) {
        $q1 = mysqli_query($con, "SELECT title,author,genre,duration FROM track WHERE id=$ids");
        $row1 = $q1 ? mysqli_fetch_assoc($q1) : null;
        if ($q1) mysqli_free_result($q1);

        if ($row1 && isset($row1["title"]) && $row1["title"] !== null) {
            echo "Titolo: ".$row1["title"]."\n";
            echo "Autore: ".$row1["author"]."\n";
            echo "Genere: ".$row1["genre"]."\n";
            echo "Durata: ".fmt_secs((float)$row1["duration"])."s\n";
            echo "Identificativo: ".sprintf('%05d', $ids)."\n";
        }
    }
}
echo "</pre></td></table>";

echo "<p style='text-align: center'>I Colori del Navile APS presentano Radio a Colori\nMusica libera con licenza CC-BY\n</p>";

/**
 * State ascoltando (brano corrente)
 */
echo "<font color='blue'>State Ascoltando\n</font>";

if ($current === null) {
    echo "<font color='red'>Nessun brano determinabile (fine palinsesto o playlist vuota)\n</font>";
    echo "Ora: ".date("Y-m-d H:i:s", (int)$now)."\n\n";
} else {
    $id5 = sprintf('%05d', (int)$current['id']);
    $in_extra = ($now >= ($current['music_end'] - $eps)); // true se siamo nella parte extra

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

/**
 * Palinsesto: SOLO brani, ma orari tengono conto di duration+extra
 */
echo "<font color='blue'>Palinsesto\n</font>";

if (count($schedule) === 0) {
    echo "(vuoto)\n";
} else {
    $pp = ($current_index >= 0) ? $current_index : 0;
    $f = $pp - 4; if ($f < 0) $f = 0;
    $t = $pp + 4; if ($t >= count($schedule)) $t = count($schedule) - 1;

    for ($i = $f; $i <= $t; $i++) {
        $e = $schedule[$i];
        $id5 = sprintf('%05d', (int)$e['id']);

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

/**
 * Stampa secondi float in modo leggibile:
 * - se è (quasi) intero -> "123"
 * - altrimenti -> fino a 3 decimali, senza zeri finali
 */
function fmt_secs($s) {
    $s = (float)$s;
    $eps = 0.0005;
    $r = round($s);
    if (abs($s - $r) < $eps) return (string)(int)$r;
    $str = sprintf('%.3f', $s);
    $str = rtrim(rtrim($str, '0'), '.');
    return $str;
}
?>
