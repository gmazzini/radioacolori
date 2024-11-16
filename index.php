<?php
include "local.php";
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$tt=(int)(time()/86400);

$ll=file("/var/log/ices/ices.log");
for($i=count($ll)-1;$i>0;$i--)
  if(strpos($ll[$i],"Currently playing \"/home/ices/music/ogg04/")!==false)
    break;
$id=current(explode(".",end(explode("/",$ll[$i]))));
$xx=strtotime(substr($ll[$i],1,20));

$query=mysqli_query($con,"select title,author,genre,duration,duration_extra from track where id='$id'");
$row=mysqli_fetch_assoc($query);
$next=(int)($xx-time()+$row["duration"]+$row["duration_extra"]);
mysqli_free_result($query);

echo "<script>\n";
echo "var y=$next;\n";
echo "var x = setInterval(function(){\n";
echo "  document.getElementById('cdw').innerHTML=y;\n";
echo "  y--;\n";
echo "  if(y<=0){location.reload();}\n";
echo "},1000);\n";
echo "</script>\n";

?>
Benvenuti alla Caccia al Tesoro di Radio a Colori, 
organizzata dall’Associazione di Promozione Sociale "I Colori del Navile" 
per il Festival della Cultura Tecnica! Quest'anno, in occasione dei 150 anni 
alla nascita di Guglielmo Marconi, celebreremo la radio – l’invenzione del 
celebre scienziato bolognese – attraverso un’avventura radiofonica.<br><br>

La caccia consiste nel trovare 5 codici numerici di 3 cifre, nascosti in 5 
diverse località coperte dal segnale di Radio a Colori. Sabato 16 novembre 2024, 
su Radio a Colori verranno trasmessi ripetutamente e per tutta la giornata questo 
messaggio introduttivo e 5 indizi che vi  guideranno verso i codici. Gli indizi 
possono essere seguiti in qualsiasi ordine.<br><br>

Radio a Colori trasmette in AM alla frequenza di 1602 KHz, con buona copertura 
nella zona nord del quartiere Navile, a Granarolo, Castel Maggiore e in 
vari punti aperti della pianura circostante. Per partecipare, è sufficiente un ricevitore AM, 
come quelli comunemente presenti nelle auto.<br><br>

Una volta raccolti i codici, inviateli all’organizzazione via email all’indirizzo 
colori.navile@gmail.com. Tutti i partecipanti che troveranno i 5 codici riceveranno una 
cartolina commemorativa di Guglielmo Marconi. Non verranno raccolti o conservati 
dati personali: la vostra email sarà utilizzata solo per l'invio della risposta. 
Vi preghiamo di non includere dati personali, non necessari né utilizzati.<br><br>
<?php


echo "<pre><table>";
echo "<td><img src='logo.jpg' width='10%' height='auto'><br>";
echo "<a href='http://radioacolori.net:8000/stream' target='_blank'>webradio</a></td>";
echo "<td><pre><form method='post'><input type='text' name='myid'><input type='submit' value='Cerca'></form>";
$ids=$_POST["myid"];
$query1=mysqli_query($con,"select title,author,genre,duration from track where id='$ids'");
$row1=mysqli_fetch_assoc($query1);
mysqli_free_result($query1);
if($row1["title"]!=null){
  echo "Titolo: ".$row1["title"]."\n";
  echo "Autore: ".$row1["author"]."\n";
  echo "Genere: ".$row1["genre"]."\n";
  echo "Durata: ".(int)$row1["duration"]."s\n</font>";
  echo "Identificativo: ".$ids."\n";
}
echo "</pre></td></table>";

echo "<p style='text-align: center'>I Colori del Navile APS presentano Radio a Colori\nMusica libera con licenza CC-BY\n</p>";
echo "<font color='blue'>State Ascoltando\n</font>";
echo "<font color='red'>Titolo: ".$row["title"]."\n";
echo "Autore: ".$row["author"]."\n";
echo "Genere: ".$row["genre"]."\n";
echo "Durata: ".(int)$row["duration"]."s\n</font>";
echo "Inizio: ".date("Y-m-d H:i:s",$xx)."\n";
echo "Identificativo: ".$id."\n\n";







echo "<font color='blue'>Palinsesto\n</font>";
$vv=((int)(time()/86400))*86400-58;
$query=mysqli_query($con,"select p.id,t.duration,t.duration_extra from playlist p,track t where p.id=t.id and tt=$tt order by position");
for($ts=0;;$ts++){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $vv+=round($row["duration"]-$corr,2)+round($row["duration_extra"]-$corr,2);
  $seq[$ts]=$row["id"];
  $sched[$ts]=$vv;
  if($vv>$xx-10 && $vv<$xx+10)$pp=$ts;
}
mysqli_free_result($query);
$f=$pp-4; if($f<0)$f=0;
$t=$pp+4; if($t>=$ts)$t=$ts-1;

for($i=$f;$i<=$t;$i++){
  $query=mysqli_query($con,"select title,author,genre,duration from track where id='$seq[$i]'");
  $row=mysqli_fetch_assoc($query);
  if($i==$pp)echo "<font color='red'>";
  echo date("H:i:s",$sched[$i])." | ".$seq[$i];
  echo " | ".mystr($row["title"],40);
  echo " | ".mystr($row["author"],30);
  echo " | ".mystr($row["genre"],20);
  echo " | ".(int)$row["duration"]."s\n";
  if($i==$pp)echo "</font>";
  mysqli_free_result($query);
}
echo "Prossimo brano tra: <div style='display: inline' id='cdw'></div>s\n\n";
echo "<p style='text-align: center'>Powered by I Colori del Navile APS\n";
echo "Email info at radioacolori.net\nCF 91357680379 - ROC 33355\n</p>";
mysqli_close($con);

function mystr($a,$l){
  $la=mb_strlen($a);
  if($la>=$l)return mb_substr($a,0,$l-1).">";
  return $a.str_repeat(" ",$l-$la);
}

?>
