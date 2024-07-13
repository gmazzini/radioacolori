<?php
include "local.php";
$special=array("RADIOAMATORI","SCIENZA","STORIE DEL NAVILE");
$avoid=array("INNOVAZIONE");
$music=45*60;
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$p1="/home/ices/music/voice/";
$p2="/home/ices/music/ogg04/";

$listout="("; $listin="(";
$co=0;
foreach($special as $k => $v){
  if($co){$listout.=","; $listin.=",";}
  $listout.="'$v'"; $listin.="'$v'";
  $co=1;
}
foreach($avoid as $k => $v){
  if($co)$listout.=",";
  $listout.="'$v'";
  $co=1;
}
$listout.=")"; $listin.=")";

$query=mysqli_query($con,"select id,tt from track where score=2 and genre not in $listout order by rand()");
$mm=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm[$mm]=$row["id"];
  $ttm[$mm]=$row["tt"];
  $mm++;
}
mysqli_free_result($query);






$query=mysqli_query($con,"select id,tt from track where score=2 and genre in $listin order by rand()");
$ttt=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $id=$row["id"];
  $tt=$row["tt"];
  $ttt+=$tt;
  echo $p1."intro.ogg\n";
  for($i=0;$i<5;$i++)echo $p1."n".substr($id,$i,1).".ogg\n";
  echo $p1."coda.ogg\n";
  echo $p2."$id.ogg\n";
}
mysqli_free_result($query);
mysqli_close($con);
echo "$ttt\n";

?>
