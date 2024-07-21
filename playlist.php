<?php
include "local.php";
$tt=(int)(time()/86400);
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);

$query=mysqli_query($con,"select id from playlist where tt=$tt order by position");
for($i=0;;$i++){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $id[$i]=$row["id"];
}
mysqli_free_result($query);

echo "<pre>";
$vv=0;
for($j=0;$j<$i;$j++){
  $query=mysqli_query($con,"select title,author,genre,duration,used,score from track where id='$id[$j]'");
  $row=mysqli_fetch_assoc($query);
  $zz=in_arrY($row["genre"],$special);
  if($zz)echo "<font color='red'>";
  echo date("H:i",$vv)." | ".$id[$j];
  echo " | ".mystr($row["title"],40);
  echo " | ".mystr($row["author"],30);
  echo " | ".mystr($row["genre"],20);
  echo " | ".mystr2((int)$row["duration"],4)."s";
  echo " | ".mystr2($row["used"],3);
  echo " | ".mystr2($row["score"],1);
  if($zz)echo "</font>";
  echo "\n";
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
function mystr2($a,$l){
  $la=strlen($a);
  return $a.str_repeat(" ",$l-$la);
}
?>
