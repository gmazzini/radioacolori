<?php
include "local.php";
$special=array("RADIOAMATORI","SCIENZA","STORIE DEL NAVILE","POESIE");
$tt=(int)(time()/86400);

$query=mysqli_query($con,"select id from playlist where tt=$tt order by playlist");
for($i=0;;$i++){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $id[$i]=$row["id"];
}
mysqli_free_result($query);

echo "<pre>";
$vv=0;
for($j=0;$j<$i;$j++){
  $query=mysqli_query($con,"select title,author,genre,duration from track where id='$id[$j]'");
  $row=mysqli_fetch_assoc($query);
  echo date("H:i",$vv)." | ".$id[$j];
  echo " | ".mystr($row["title"],40);
  echo " | ".mystr($row["author"],30);
  echo " | ".mystr($row["genre"],20);
  echo " | ".(int)$row["duration"]."s\n";
  $vv=$vv+(int)$row["duration"]+$dtq;
  mysqli_free_result($query);
}

mysqli_close($con);


?>
