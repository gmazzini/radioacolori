<?php
include "local.php";
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$tt=(int)(time()/86400);
$ll=file("/var/log/ices/ices.log");
for($i=count($ll)-1;$i>0;$i--)if(strpos($ll[$i],"Currently playing \"/home/ices/music/ogg04/")!==false)break;
@$id=current(explode(".",end(explode("/",$ll[$i]))));
$xx=strtotime(substr($ll[$i],1,20));
$query=mysqli_query($con,"select title,author,genre,duration,duration_extra from track where id='$id'");
$row=mysqli_fetch_assoc($query);
$next=(int)($xx-(time()+3600)+$row["duration"]+$row["duration_extra"]);
mysqli_free_result($query);
$vv=((int)(time()/86400))*86400-58;
$query=mysqli_query($con,"select p.id,t.duration,t.duration_extra from playlist p,track t where p.id=t.id and tt=$tt order by position");
for($ts=0;;$ts++){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $seq[$ts]=$row["id"];
  $sched[$ts]=$vv;
  if($vv>$xx-10 && $vv<$xx+10)$pp=$ts;
  $vv+=round($row["duration"]-$corr,2)+round($row["duration_extra"]-$corr,2);
}
mysqli_free_result($query);
$f=$pp-4; if($f<0)$f=0;
$t=$pp+4; if($t>=$ts)$t=$ts-1;
for($i=$f;$i<=$t;$i++){
  $query=mysqli_query($con,"select title,author,genre,duration from track where id='$seq[$i]'");
  $row=mysqli_fetch_assoc($query);
  if($i==$pp)echo ">>";
  echo date("H:i:s",$sched[$i]).",".$seq[$i].",".$row["title"].",".$row["author"].",".$row["genre"].",".(int)$row["duration"]."s\n";
  mysqli_free_result($query);
}
mysqli_close($con);
?>
