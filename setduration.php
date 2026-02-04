<?php
include "/home/radio/local.php";
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$base="/home/radio/track/base/";
$extra="/home/radio/track/extra/";

$query=mysqli_query($con,"select id from track where (duration=0 or duration_extra=0) and score>0");
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $id=$row["id"];
  $dd=json_decode(shell_exec("ffprobe -v quiet -print_format json -show_streams -hide_banner $base/$id.ogg"),true);
  $duration=$dd["streams"][0]["duration"];
  $dd=json_decode(shell_exec("ffprobe -v quiet -print_format json -show_streams -hide_banner $extra/$id.ogg"),true);
  $duration_extra=$dd["streams"][0]["duration"];
  echo "$id $duration $duration_extra\n";
  mysqli_query($con,"update track set duration=$duration,duration_extra=$duration_extra where id='$id'");
}
mysqli_free_result($query);

?>
