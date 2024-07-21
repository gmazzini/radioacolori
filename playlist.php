<?php
include "local.php";
$tt=(int)(time()/86400);
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);

$query=mysqli_query($con,"select id from playlist where tt=$tt order by sequence");
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

function mystr($a,$l){
  $la=strlen($a);
  if($la>=$l)return substr($a,0,$l-3)."...";
  if($la==$l-1)return substr($a,0,$l-2)."..";
  if($la==$l-2)return substr($a,0,$l-2).".";
  return $a.str_repeat(" ",$l-$la);
}
?>
