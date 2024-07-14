<?php
include "local.php";
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$access_token=file_get_contents("/home/www/music.mazzini.org/web/access_token");
$ch=curl_init();
curl_setopt($ch,CURLOPT_URL,"https://sheets.googleapis.com/v4/spreadsheets/1P3DRLJsepd4gmaYhf2XhqIV4_NIArscaO5Jz1h1pmyU/values/brani!A2:J");
curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
curl_setopt($ch,CURLOPT_HTTPHEADER,Array("Content-Type: application/json","Authorization: Bearer ".$access_token));
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
$oo=json_decode(curl_exec($ch),true);
curl_close($ch);
foreach($oo["values"] as $k => $v){
  $id=$v[4];
  $title=mysqli_real_escape_string($con,$v[6]);
  $author=mysqli_real_escape_string($con,$v[7]); 
  $genre=mysqli_real_escape_string($con,$v[8]); 
  $duration=str_replace(",","",$v[5]);
  $score=(int)$v[1];
  $query=mysqli_query($con,"select count(id) from track where id='$id'");
  $row=mysqli_fetch_row($query);
  $presence=$row[0];
  mysqli_free_result($query);
  if($presence)mysqli_query($con,"update track set duration=$duration,title='$title',author='$author',genre='$genre',score=$score where id='$id'");
  else mysqli_query($con,"insert into track (id,duration,title,author,genre,score) values ('$id',$duration,'$title','$author','$genre',$score)");
}
mysqli_close($con);

?>
