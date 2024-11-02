<?php
include "local.php";

// normalize content last 
$query=mysqli_query($con,"select min(last) from track where score=2 and genre not in $listout order by last asc,rand()");
$nm2=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $idm2[$nm2++]=$row["id"];
}
mysqli_free_result($query);
