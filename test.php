<?php
include "local.php";
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

// normalize content last
$query=mysqli_query($con,"select gid from track where score=2 and genre in $listin and gsel=1");
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $gid=$row["gid"];
  $query2=mysqli_query($con,"select min(last),max(gsel) from track where gid='$gid'");
  $row2=mysqli_fetch_row($query2);
  $last_min=$row2[0];
  $gsel_max=$row2[0];
  mysqli_free_result($query2);
  $query2=mysqli_query($con,"select count(*),min(gsel) from track where gid='$gid' and last=$last_min");
  $row2=mysqli_fetch_row($query2);
  $num_min=$row2[0];
  $gsel_min=$row2[1];
  mysqli_free_result($query2);
  printf("%s %d %d %d %d\n",$gid,$last_min,$gsel_max,$num_min,$gsel_min);
  if($num_min<$limit_group_element){
    for($j=0;$j<$limit_group_element-$num_min;$j++){
      $q=$gsel_min+$j;
      if($q>=$gsel_max)$q=1+($q % $gsel_max);
      printf("update track set last=$last_min where gid='$gid' and gsel=$q\n");
      // mysqli_query($con,"update track set last=$last_min where gid='$gid' and gsel=$q");
    }
  }
  
}
mysqli_free_result($query);
