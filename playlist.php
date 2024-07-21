<?php
include "local.php";
$tt=(int)(time()/86400);

$query=mysqli_query($con,"select id from playlist where tt=$tt order by playlist");
for($i=0;;$i++){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $id[$i]=$row["id"];
}
mysqli_free_result($query);

mysqli_close($con);


?>
