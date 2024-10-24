<?php
include "local.php";
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);


$query=mysqli_query($con,"select id,duration from track where duration>0 and score>0");
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  echo $row["id"].",".$row["duration"]."\n";
  
}
mysqli_free_result($query);

?>
