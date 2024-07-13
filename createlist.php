<?php
include "local.php";
$special=array("RADIOAMATORI","SCIENZA","STORIE DEL NAVILE");
$avoid=array("INNOVAZIONE");
$runm=7;
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
$nm2=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm2[$nm]=$row["id"];
  $ttm2[$nm]=$row["tt"];
  $nm2++;
}
mysqli_free_result($query);

$nm1=0;
$query=mysqli_query($con,"select id,tt from track where score=1 and genre not in $listout order by rand()");
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm1[$nm1]=$row["id"];
  $ttm1[$nm1]=$row["tt"];
  $nm1++;
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

$nq1=(int)($nm1/$nc);
$nq2=(int)($nm2/$nc);
$iq1=0;
$iq2=0;
$ttt=0;
for($i=0;$i<$nc;$i++){
  for($q=0;$q<=$runm;$q++){
    if($q==$runm){
      $ida=$idc[$i];
      $tta=$ttc[$i];
    }
    else if($q<$nq2){
      $ida=$idm2[$iq2];
      $tta=$ttm2[$iq2];
      $iq2++;
      if($iq2>=$nm2)$iq2=0;
    }
    else {
      $ida=$idm1[$iq1];
      $tta=$ttm1[$iq1];
      $iq1++;
      if($iq1>=$nm1)$iq1=0;
    }
    echo $p2."$ida.ogg\n";
    echo $p1."intro.ogg\n";
    for($j=0;$j<5;$j++)echo $p1."n".substr($ida,$j,1).".ogg\n";
    echo $p1."coda.ogg\n";
    $ttt+=$tta;
  }
}
mysqli_close($con);
echo "$ttt $nm2 $nm1 $nc $nq\n";

?>
