<?php
include "local.php";
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);

$query=mysqli_query($con,"select id,time from track where score=2 order by rand()");
$ttt=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $id=$row["id"];
  $tt=$row["tt"];
  $ttt+=$tt;
  echo "$id\n";
}
mysqli_free_result($query);
mysqli_close($con);
echo "$ttt\n";

?>
