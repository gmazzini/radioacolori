<?php
include "local.php";
$special=array("RADIOAMATORI","SCIENZA","STORIE DEL NAVILE");
$avoid=array("INNOVAZIONE");
$maxm=10;
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
$nm=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm[$nm]=$row["id"];
  $ttm[$nm]=$row["tt"];
  $nm++;
}
mysqli_free_result($query);

$query=mysqli_query($con,"select id,tt from track where score=2 and genre in $listin order by rand()");
$nc=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idc[$nc]=$row["id"];
  $ttc[$nc]=$row["tt"];
  $nc++;
}
mysqli_free_result($query);

$nq=(int)($nm/$nc);
if($nq>$maxm)$nq=$maxm;

echo $nq."\n";
exit(0);

$iq=0;
$ttt=0;
for($i=0;$i<$nc;$i++){
  for($q=0;$q<=$nm;$q++){
    if($q==$nm){
      $ida=$idc[$i];
      $tta=$ttc[$i];
    }
    else {
      $ida=$idm[$iq];
      $tta=$ttm[$iq];
      $iq++;
      if($iq>=$nm)$iq=0;
    }
    echo $p2."$ida.ogg\n";
    echo $p1."intro.ogg\n";
    for($j=0;$j<5;$j++)echo $p1."n".substr($ida,$j,1).".ogg\n";
    echo $p1."coda.ogg\n";
    $ttt+=$tta;
  }
}
mysqli_free_result($query);
mysqli_close($con);
echo "$ttt\n";

?>
