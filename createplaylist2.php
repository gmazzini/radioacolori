<?php
$ratio=2; // ratio among music and content time
$limit_group_time=4000; // maximum duration for day in a single group
$limit_group_element=5; // maximum number of elements for day in a single group
$start_high=5.0*3600; // start high interest time
$end_high=22.5*3600; // end high interest time
include "local.php";

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

// music list with higher score and far used for score=2
$query=mysqli_query($con,"select id from track where score=2 and genre not in $listout order by last asc,id asc,score desc");
$nm2=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm[$nm2++]=$row["id"];
}
mysqli_free_result($query);

// music list with higher score and far used for score=1
$query=mysqli_query($con,"select id from track where score=1 and genre not in $listout order by last asc,id asc,score desc");
$nm1=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm[$nm1++]=$row["id"];
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
    $group_time=0.0;
    $group_element=0;
    for(;;){
      $row2=mysqli_fetch_assoc($query2);
      if($row2==null)break;
      $idc[$nc++]=$row2["id"];
      $group_element++;
      $group_time+=$row2["duration"];
      if($group_time>$limit_group_time || $group_element>$limit_group_element)break;
    }
    mysqli_free_result($query2);
  }
  if(in_array($row["id"],$idc))continue;
  $idc[$nc++]=$row["id"];
}
mysqli_free_result($query);


$tt=30000;
mysqli_query($con,"delete from playlist where tt=$tt");
$mytype=1; // 1=content 0=music
$position=0;
$ic=$im2=$im1=0;
$tot_time=$music_time=$content_time=0.0;
for(;;){
  if($mytype==1){
    $selid=$idc[$ic++];
    if($ic>=$nc)$ic=0;
  }
  else {
    if($tot_time>$start_high && $tot_time<$end_time){
      $selid=$id[$im2++];
      if($im2>=$nm2)$im2=0;
    }
    else {
      $selid=$id[$im1++];
      if($im1>=$nm1)$im1=0;
    }
  }
  $query=mysqli_query($con,"select duration,duration_extra,title,author from track where id='$selid'");
  $row=mysqli_fetch_assoc($query);
  mysqli_free_result($query);
  $tot_time+=$row["duration"]+$row["duration_extra"];
  if($mytype==1)$content_time+=$row["duration"];
  else $music_time+=$row["duration"];
  if($music_time/$content_time<$ratio)$mytype=0;
  else $mytype=1;

  mysqli_query($con,"insert into playlist (tt,id,position) values ($tt,'$selid',$position)");
  $position++;
  mysqli_query($con,"update track set used=used+1,last=$tt where id='$selid'");
  fprintf($fp,"%s%s.ogg\n",$p2,$selid);
  fprintf($fp,"%s%s.ogg\n",$p3,$selid);
  printf("%s %f %f %f %s %s\n",$selid,$row["duration"],$row["duration_extra"],$tot_time,$row["title"],$row["author"]);

  if($tot_time>87000)break;
}
mysqli_close($con);
fclose($fp);

?>
