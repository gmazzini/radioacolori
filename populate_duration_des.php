<?php
include "local.php";
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$query=mysqli_query($con,"select id from track where duration_des=0.0");
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $id=$row["id"];
  $oo=json_decode(shell_exec("ffprobe -i music/ogg04v/$id.ogg -v quiet -print_format json -show_streams -hide_banner"),true);
  $duration_des=$oo["streams"][0]["duration"];
  print_r($oo); 
  echo $duration_des; exit(1);
}
mysqli_free_result($query);
mysqli_close($con);

?>
