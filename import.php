<?php
include "local.php";
$con=mysqli_connect($dbhost,$dbuser,$dbpassword,$dbname);
$access_token=file_get_contents("/home/www/data/access_token_google");
$ch=curl_init();
curl_setopt($ch,CURLOPT_URL,"https://sheets.googleapis.com/v4/spreadsheets/1P3DRLJsepd4gmaYhf2XhqIV4_NIArscaO5Jz1h1pmyU/values/brani!A2:L");
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
  @$gid=$v[10];
  @$gsel=(int)$v[11];
  $score=(int)$v[1];
  $query=mysqli_query($con,"select count(id) from track where id='$id'");
  $row=mysqli_fetch_row($query);
  $presence=$row[0];
  mysqli_free_result($query);
  if($presence)mysqli_query($con,"update track set title='$title',author='$author',genre='$genre',score=$score,gid='$gid',gsel=$gsel where id='$id'");
  else mysqli_query($con,"insert into track (id,title,author,genre,score,gid,gsel) values ('$id','$title','$author','$genre',$score,'$gid',$gsel)");
}
mysqli_close($con);

?>
