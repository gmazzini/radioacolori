<?php
include "local.php";
$tt=(int)(time()/86400)+1;
$special=array("RADIOAMATORI","SCIENZA","STORIE DEL NAVILE");
$avoid=array("INNOVAZIONE");
$runm=7;
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$p1="/home/ices/music/voice/";
$p2="/home/ices/music/ogg04/";
$fp=fopen("/home/ices/playlist_$tt.txt","wt");

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

$query=mysqli_query($con,"select id,duration from track where score=2 and genre not in $listout order by rand()");
$nm2=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm2[$nm2]=$row["id"];
  $durationm2[$nm2]=$row["duration"];
  $nm2++;
}
mysqli_free_result($query);

$nm1=0;
$query=mysqli_query($con,"select id,duration from track where score=1 and genre not in $listout order by duration");
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm1[$nm1]=$row["id"];
  $durationm1[$nm1]=$row["duration"];
  $nm1++;
}
mysqli_free_result($query);

$query=mysqli_query($con,"select id,duration from track where score=2 and genre in $listin order by rand()");
$nc=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idc[$nc]=$row["id"];
  $durationc[$nc]=$row["duration"];
  $nc++;
}
mysqli_free_result($query);

mysqli_query($con,"delete from playlist where tt=$tt");
$nq1=(int)($nm1/$nc);
$nq2=(int)($nm2/$nc);
$iq1=$iq2=0;
$um1=$um2=$uc=0;
$duration=0;
$el=0;
for($i=0;$i<$nc;$i++){
  for($q=0;$q<=$runm;$q++){
    if($q==$runm){
      $ida=$idc[$i];
      $durationa=$durationc[$i];
      $uc++;
    }
    else if($q<$nq2){
      $ida=$idm2[$iq2];
      $durationa=$durationa2[$iq2];
      $iq2++;
      if($iq2>=$nm2)$iq2=0;
      $um2++;
    }
    else {
      $ida=$idm1[$iq1];
      $durationa=$durationm1[$iq1];
      $iq1++;
      if($iq1>=$nm1)$iq1=0;
      $um1++;
    }
    mysqli_query($con,"insert into playlist (tt,id,position) values ($tt,'$ida',$el)");
    $el++;
    fprintf($fp,"%s%s.ogg\n",$p2,$ida);
    fprintf($fp,"%sintro.ogg\n",$p1);
    for($j=0;$j<5;$j++)fprintf($fp,"%sn%s.ogg\n",$p1,substr($ida,$j,1));
    fprintf($fp,"%scoda.ogg\n",$p1);
    $duration+=$durationa;
    if($duration>86400)break;
  }
}
mysqli_close($con);
fclose($fp);
echo "$duration $nm2:$um2 $nm1:$um1 $nc:$uc\n";

?>
