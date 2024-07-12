<?php
$access_token=file_get_contents("/home/www/music.mazzini.org/web/access_token");
$ch=curl_init();
curl_setopt($ch,CURLOPT_URL,"https://sheets.googleapis.com/v4/spreadsheets/1P3DRLJsepd4gmaYhf2XhqIV4_NIArscaO5Jz1h1pmyU/values/brani!A2:J");
curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
curl_setopt($ch,CURLOPT_HTTPHEADER,Array("Content-Type: application/json","Authorization: Bearer ".$access_token));
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
$oo=json_decode(curl_exec($ch),true);
curl_close($ch);
print_r($oo)

?>
