<?php
include "local.php";
date_default_timezone_set("Europe/Rome");
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$tt=(int)(time()/86400);

echo "<img src='logo.png' width='20%' height='auto'>";
echo "<pre>";
$ll=file("/var/log/ices/ices.log");
for($i=count($ll)-1;$i>0;$i--)
  if(strpos($ll[$i],"Currently playing")!==false)
    break;
$id=current(explode(".",end(explode("/",$ll[$i]))));
$xx=strtotime(substr($ll[$i],1,20));
echo "I Colori del Navile presentano Radio a Colori\nMusica libera con licenza CC-BY\n\n";

echo "<font color='blue'>State Ascoltando\n</font>";
$query=mysqli_query($con,"select title,author,genre,duration from track where id='$id'");
$row=mysqli_fetch_assoc($query);
echo "Titolo: ".$row["title"]."\n";
echo "Autore: ".$row["author"]."\n";
echo "Genere: ".$row["genre"]."\n";
echo "Durata: ".(int)$row["duration"]."s\n";
echo "Inizio: ".date("Y-m-d H:i:s",$xx)."\n";
echo "Identificativo: ".$id."\n\n";
mysqli_free_result($query);

echo "<font color='blue'>Palinsesto\n</font>";
$query=mysqli_query($con,"select id from playlist where tt=$tt order by position");
for($ts=0;;$ts++){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $seq[$ts]=$row["id"];
  if($seq[$ts]==$id)$pp=$ts;
}
mysqli_free_result($query);
$f=$pp-2; if($f<0)$f=0;
$t=$pp+2; if($t>=$ts)$t=$ts-1;
for($i=$f;$i<=$t;$i++){
  echo $seq[$i]."\n";
}

echo "Prossimo brano tra: ".$xx+(int)$row["duration"]-time()."s\n";


mysqli_close($con);

  
?>
