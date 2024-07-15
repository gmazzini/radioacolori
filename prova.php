<?php
include "local.php";
$runm=(int)$argv[1];
$tt=(int)(time()/86400)+1;
$special=array("RADIOAMATORI","SCIENZA","STORIE DEL NAVILE");
$avoid=array("INNOVAZIONE");
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


function myshuffle($a,$f,$t){
  for($j=$t;$j>$f;$j--){
    $r=rand($f,$t);
    $aux=$a[$j];
    $a[$j]=$a[$r];
    $a[$r]=$aux;
  }
}


echo "select id,used from track where score=2 and genre not in $listout order by used\n";
$query=mysqli_query($con,"select id,used from track where score=2 and genre not in $listout order by used");
$nm2=0;
$fromsh=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm2[$nm2]=$row["id"];
  $auxused=$row["used"];
  if($nm2==0)$lastused=$auxused;
  elseif($lastused<>$auxused){
    myshuffle($idm2,$fromsh,$nm2-1);
    $fromsh=$nm2;
    $lastused=$auxused;
  }
  $nm2++;
}
mysqli_free_result($query);
print_r($idm2);

exit(1);

$nm1=0;
$query=mysqli_query($con,"select id,duration from track where score=1 and genre not in $listout order by rand()");
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
      $durationa=$durationm2[$iq2];
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



    
    $el++;
   
    $duration+=$durationa;
    if($duration>86400)break;
  }
}
mysqli_close($con);

echo "$duration $nm2:$um2 $nm1:$um1 $nc:$uc\n";

?>
