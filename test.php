<?php
include "local.php";

$tt=(int)(time()/86400)+1;
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
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

// content list with score=2 and far used with group processing
$query=mysqli_query($con,"select id,duration,gsel,gid from track where score=2 and genre in $listin and (gsel=0 or gsel=1) order by last asc,rand()");
$nc=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  if(((int)$row["gsel"])==1){
    $gid=$row["gid"];
    $llcd=0;
    $aux=array();
    $query2=mysqli_query($con,"select id,duration,gsel from track where gid='$gid' order by last asc,gsel asc");
    for(;;){
      $row2=mysqli_fetch_assoc($query2);
      @$aux[$llcd]["id"]=$row2["id"].",$gid,".$row2["gsel"].",".$row2["duration"];
      @$aux[$llcd]["duration"]=$row2["duration"];
      @$aux[$llcd++]["gsel"]=$row2["gsel"];
      if($row2==null)break;
    }
    mysqli_free_result($query2);
    for($x=1;$x<$llcd;$x++)if($aux[$x]["gsel"]==$aux[$x-1]["gsel"]+1)continue;
    $group_time=0.0;
    $group_element=0;
    if($x<$llcd){
      for($y=x;$y<$llcd;$y++){
        $idc[$nc++]=$aux[$y]["id"];
        $group_element++;
        $group_time+=$aux[$y]["duration"];
        if($group_time>=$limit_group_time || $group_element>=$limit_group_element)break;
      }
      if($y==$llcd){
        for($y=0;$y<$x;$y++){
          $idc[$nc++]=$aux[$y]["id"];
          $group_element++;
          $group_time+=$aux[$y]["duration"];
          if($group_time>=$limit_group_time || $group_element>=$limit_group_element)break;
        }
      }
    }
    else {
      for($y=0;$y<$llcd;$y++){
        $idc[$nc++]=$aux[$y]["id"];
        $group_element++;
        $group_time+=$aux[$y]["duration"];
        if($group_time>=$limit_group_time || $group_element>=$limit_group_element)break;
      }
    }
    continue;
  }
  $idc[$nc++]=$row["id"];
}
mysqli_free_result($query);

print_r($idc);


mysqli_close($con);
?>
