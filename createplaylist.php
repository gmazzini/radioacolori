<?php
include "local.php";
$runm=(int)$argv[1];
$tt=(int)(time()/86400)+1;
$special=array("RADIOAMATORI","SCIENZA","STORIE DEL NAVILE","POESIE");
$avoid=array("INNOVAZIONE");
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$p2="/home/ices/music/ogg04/";
$p3="/home/ices/music/ogg04v/";
$fp=fopen("/home/ices/playlist.txt","wt");

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

function myshuffle(&$a,$f,$t){
  for($j=$t;$j>$f;$j--){
    $r=rand($f,$t);
    $aux=$a[$j];
    $a[$j]=$a[$r];
    $a[$r]=$aux;
  }
}

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

$query=mysqli_query($con,"select id,used from track where score=1 and genre not in $listout order by used");
$nm1=0;
$fromsh=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm1[$nm1]=$row["id"];
  $auxused=$row["used"];
  if($nm1==0)$lastused=$auxused;
  elseif($lastused<>$auxused){
    myshuffle($idm1,$fromsh,$nm1-1);
    $fromsh=$nm1;
    $lastused=$auxused;
  }
  $nm1++;
}
mysqli_free_result($query);

$query=mysqli_query($con,"select id,used from track where score=2 and genre in $listin order by used");
$nc=0;
$fromsh=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idc[$nc]=$row["id"];
  $auxused=$row["used"];
  if($nc==0)$lastused=$auxused;
  elseif($lastused<>$auxused){
    myshuffle($idc,$fromsh,$nc-1);
    $fromsh=$nc;
    $lastused=$auxused;
  }
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
      $uc++;
    }
    else if($q<$nq2){
      $ida=$idm2[$iq2];
      if(++$iq2>=$nm2)$iq2=0;
      $um2++;
    }
    else {
      $ida=$idm1[$iq1];
      if(++$iq1>=$nm1)$iq1=0;
      $um1++;
    }
    
    mysqli_query($con,"insert into playlist (tt,id,position) values ($tt,'$ida',$el)");
    mysqli_query($con,"update track set used=used+1 where id='$ida'");
    $el++;
    fprintf($fp,"%s%s.ogg\n",$p2,$ida);
    fprintf($fp,"%s%s.ogg\n",$p3,$ida);
    
    $query=mysqli_query($con,"select duration from track where id='$ida'");
    $row=mysqli_fetch_assoc($query);
    $duration+=$row["duration"];
    mysqli_free_result($query);
    if($duration>86400)break;
  }
}
mysqli_close($con);
fclose($fp);
echo "$duration $nm2:$um2 $nm1:$um1 $nc:$uc\n";

?>
