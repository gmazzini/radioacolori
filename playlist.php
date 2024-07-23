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

$ll=file("/var/log/ices/ices.log");
for($zz=count($ll)-1;$zz>0;$zz--){
  if(strpos($ll[$zz],"Currently playing \"/home/ices/music/ogg04/")!==false){
    $logid=current(explode(".",end(explode("/",$ll[$zz]))));
    $logtime[$logid]=strtotime(substr($ll[$zz],1,20));
    if($logid==$id[0])break;
  }
}

echo "<pre>";
$vv=86400-60;;
for($j=0;$j<$i;$j++){
  $query=mysqli_query($con,"select title,author,genre,duration,duration_extra,used,score from track where id='$id[$j]'");
  $row=mysqli_fetch_assoc($query);
  $zz=in_array($row["genre"],$special);
  if($zz)echo "<font color='blue'>";
  echo date("H:i:s",$vv)." | ".$id[$j];
  if(is_set($logtime[$id[$j]])echo " | ".date("H:i:s",$logtime[$id[$j]]);
  echo " | ".mystr($row["title"],40);
  echo " | ".mystr($row["author"],30);
  echo " | ".mystr($row["genre"],20);
  echo " | ".mystr2((int)$row["duration"],4)."s";
  echo " | ".mystr2($row["used"],3);
  echo " | ".mystr2($row["score"],1);
  if($zz)echo "</font>";
  echo "\n";
  $vv=$vv+$row["duration"]+$row["duration_extra"];
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
