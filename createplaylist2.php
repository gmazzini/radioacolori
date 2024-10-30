<?php
include "local.php";
$ratio=(float)$argv[1]; //final ratio among music time with respect to content time
$tt=(int)(time()/86400)+1;
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$p2="/home/ices/music/ogg04/";
$p3="/home/ices/music/ogg04v/";
$fp=fopen("/home/ices/playlist2.txt","wt");

// list of content (lintin) by admitted genre and no used content (listout)
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

// music list with higher score and far used
$query=mysqli_query($con,"select id,duration from track where score>0 and genre not in $listout order by last asc,id asc,score desc;);
$nm=0;
$maxdurationm2=0;
$fromsh=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm[$nm]=$row["id"];
  $duration[$row["id"]]=$row["duration"];
  $nm++;
}
mysqli_free_result($query);

// content list with score=2 and far used with group processing
$query=mysqli_query($con,"select id,duration,gid from track where score=2 and genre in $listin order by last asc,id asc");
$nc=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $gid=$row["gid"];
  if(strlen($gid)==5){
    $query2=mysqli_query($con,"select min(last),max(last) from track where score=2 and genre in $listin and gid='$gid'");
    $row2=mysqli_fetch_row($query);
    $lastmin=$row[0];
    $lastmax=$row[1];
    mysqli_free_result($query2);
    if($lastmin<$lastmax)$lastlim=$lastmax;
    else $lastlim=$lastmax+1;
    $query2=mysqli_query($con,"select id,duration from track where score=2 and genre in $listin and gid='$gid' and last<$lastlim order by gsel asc");
    for(;;){
      $row2=mysqli_fetch_assoc($query2);
      if($row2==null)break;
      $idc[$nc]=$row2["id"];
      $duration[$row2["id"]]=$row2["duration"];
      $nc++;
    }
    mysqli_free_result($query2);
  }
  if(in_array($row["id"],$idc))continue;
  $idc[$nc]=$row["id"];
  $duration[$row["id"]]=$row["duration"];
  $nc++;
}
mysqli_free_result($query);


$tt=30000;
mysqli_query($con,"delete from playlist where tt=$tt");


$ic=$iq1=$iq2=0;
$um1=$um2=$uc=0;
$totalduration=0;
$el=0;
for($z=1;;){
  if($z){
    $auxid=$idc[$ic];
    if(++$ic>=$nc)$ic=0;
    $uc++;
    $lastdurationcontent=$duration[$auxid];
    $lastdurationmusic=0;
    $z=0;
  }
  else {
    if(rand(0,100)<=$hitm2){
      $auxid=$idm2[$iq2];
      if(++$iq2>=$nm2)$iq2=0;
      $um2++;
    }
    else {
      $auxid=$idm1[$iq1];
      if(++$iq1>=$nm1)$iq1=0;
      $um1++;
    }
    $lastdurationmusic+=$duration[$auxid];
    if($lastdurationmusic>$lastdurationcontent*$ratio)$z=1;
  }
  mysqli_query($con,"insert into playlist (tt,id,position) values ($tt,'$auxid',$el)");
  mysqli_query($con,"update track set used=used+1,last=$tt where id='$auxid'");
  $el++;
  fprintf($fp,"%s%s.ogg\n",$p2,$auxid);
  fprintf($fp,"%s%s.ogg\n",$p3,$auxid);
  $totalduration+=$duration[$auxid];
  if($totalduration>86400)break;
}
mysqli_close($con);
fclose($fp);
printf("totduration=%6.1f nm2=%d,um2=%d nm1=%d,um1=%d nc=%d,uc=%d\n",$totalduration,$nm2,$um2,$nm1,$um1,$nc,$uc);

?>
