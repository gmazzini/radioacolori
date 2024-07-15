<?php
include "local.php";
date_default_timezone_set("Europe/Rome");
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$tt=(int)(time()/86400);

echo "<pre>";
$ll=file("/var/log/ices/ices.log");
for($i=count($ll)-1;$i>0;$i--)
  if(strpos($ll[$i],"Currently playing")!==false)
    break;
$id=current(explode(".",end(explode("/",$ll[$i]))));
$xx=strtotime(substr($ll[$i],1,20));

echo "$id $xx\n";
echo time()-$xx."\n";

echo "State Ascoltando";
$query=mysqli_query($con,"select title,author,genre,duration from track where id='$id'");
$row=mysqli_fetch_assoc($query);
echo "Titolo: ".$row["title"]."\n";
echo "Autore: ".$row["author"]."\n";
echo "Genere: ".$row["genre"]."\n";
echo "Durata: ".$row["duration"]."\n";
echo "Inizio: ".date("Y-m-d H:i:s",$xx)."\n";
echo "Identificativo: ".$id."\n";
mysqli_free_result($query);

mysqli_close($con);

  
?>
