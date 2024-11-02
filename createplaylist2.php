<?php
include "local.php";

$tt=(int)(time()/86400)+1;
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$p2="/home/ices/music/ogg04/";
$p3="/home/ices/music/ogg04v/";
$fp=fopen("/home/ices/playlist.txt","wt");

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
$query=mysqli_query($con,"select id from track where score=2 and genre not in $listout order by last asc,rand()");
$nm2=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm2[$nm2++]=$row["id"];
}
mysqli_free_result($query);

// music list with higher score and far used for score=1
$query=mysqli_query($con,"select id from track where score=1 and genre not in $listout order by last asc,rand()");
$nm1=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm1[$nm1++]=$row["id"];
}
mysqli_free_result($query);

// content list with score=2 and far used with group processing
$query=mysqli_query($con,"select id,duration,gsel,gid from track where score=2 and genre in $listin and (gsel=0 or gsel=1) order by last asc,rand()");
$nc=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  if(((int)$row["gsel"])==1){
    $gid=$row["gid"];
    $query2=mysqli_query($con,"select min(last) from track where gid='$gid'");
    $row2=mysqli_fetch_row($query2);
    $lastmin=(isset($row2[0]))?$row2[0]:0;
    mysqli_free_result($query2);
    $query2=mysqli_query($con,"select id,duration from track where gid='$gid' and last=$lastmin order by gsel asc");
    $group_time=0.0;
    $group_element=0;
    for(;;){
      $row2=mysqli_fetch_assoc($query2);
      if($row2==null)break;
      $idc[$nc++]=$row2["id"];
      $group_element++;
      $group_time+=$row2["duration"];
      if($group_time>=$limit_group_time || $group_element>=$limit_group_element)break;
    }
    mysqli_free_result($query2);
    continue;
  }
  $idc[$nc++]=$row["id"];
}
mysqli_free_result($query);

// create the playlist
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
    if($tot_time>$start_high && $tot_time<$end_high){
      $selid=$idm2[$im2++];
      if($im2>=$nm2)$im2=0;
    }
    else {
      $selid=$idm1[$im1++];
      if($im1>=$nm1)$im1=0;
    }
  }
  $query=mysqli_query($con,"select duration,duration_extra,title,author,score from track where id='$selid'");
  $row=mysqli_fetch_assoc($query);
  mysqli_free_result($query);
  $tot_time+=$row["duration"]+$row["duration_extra"];
  if($mytype==1)$content_time+=$row["duration"];
  else $music_time+=$row["duration"];
  printf("%d %s %d %f %f %f %s %s\n",$mytype,$selid,$row["score"],$row["duration"],$row["duration_extra"],$tot_time,$row["title"],$row["author"]);
  if($music_time/$content_time<$ratio)$mytype=0;
  else $mytype=1;

  mysqli_query($con,"insert into playlist (tt,id,position) values ($tt,'$selid',$position)");
  $position++;
  mysqli_query($con,"update track set used=used+1,last=$tt where id='$selid'");
  fprintf($fp,"%s%s.ogg\n",$p2,$selid);
  fprintf($fp,"%s%s.ogg\n",$p3,$selid);

  if($tot_time>87000)break;
}
mysqli_close($con);
fclose($fp);

?>
