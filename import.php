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
foreach($oo as $k => $v){
  $id=$v[4]; $title=$v[6]; $author=$v[7]; $genre=$v[8]; $tt=str_replace(",","",$v[5]); $score=$v[1];
  echo "insert into track (id,time,title,author,genre,score) values ('$id',$tt,'$title','$author','$genre',$score)\m";
  mysqli_query($con,"insert into track (id,time,title,author,genre,score) values ('$id',$tt,'$title','$author','$genre',$score)");
}
mysqli_close($con);

?>
