<?php
include "local.php";
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);

$query=mysqli_query($con,"select id from track where (duration=0 or duration_extra=0) and score>0");
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $id=$row["id"];
  $dd=json_decode(exec_shell("ffprobe -v quiet -print_format json -show_streams -hide_banner $p2/$id.ogg"));
  print_r($dd);
  exit(0);
  


}
mysqli_free_result($query);

echo "<pre>";
$vv=86400-60;;
for($j=0;$j<$i;$j++){
  $query=mysqli_query($con,"select title,author,genre,duration,used,score from track where id='$id[$j]'");
  $row=mysqli_fetch_assoc($query);
  $zz=in_array($row["genre"],$special);
  if($zz)echo "<font color='blue'>";
  echo date("H:i:s",$vv)." | ".$id[$j];
  echo " | ".mystr($row["title"],40);
  echo " | ".mystr($row["author"],30);
  echo " | ".mystr($row["genre"],20);
  echo " | ".mystr2((int)$row["duration"],4)."s";
  echo " | ".mystr2($row["used"],3);
  echo " | ".mystr2($row["score"],1);
  if($zz)echo "</font>";
  echo "\n";
  $vv=$vv+$row["duration"]+$dtq;
  mysqli_free_result($query);
}
mysqli_close($con);

function mystr($a,$l){
  $la=mb_strlen($a);
  if($la>=$l)return mb_substr($a,0,$l-1).">";
  return $a.str_repeat(" ",$l-$la);
}
function mystr2($a,$l){
  $la=mb_strlen($a);
  return $a.str_repeat(" ",$l-$la);
}
?>
