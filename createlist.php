<?php
include "local.php";
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);

$query=mysqli_query($con,"select id,tt from track where score=2 order by rand()");
$ttt=0;
for(;;){
  $row=mysqli_fetch_assoc($query);
  if($row==null)break;
  $id=$row["id"];
  $tt=$row["tt"];
  $ttt+=$tt;
  echo $p1."intro.ogg\n";
  for($i=0;$i<5;$i++)echo $p1."n".substr($id,$i,1).".ogg\n";
  echo $p1."coda.ogg\n";
  echo $p2."$id.ogg\n";
}
mysqli_free_result($query);
mysqli_close($con);
echo "$ttt\n";

?>
