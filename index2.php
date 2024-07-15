<?php
date_default_timezone_set("Europe/Rome");
echo "<pre>";
$ll=file("/var/log/ices/ices.log");
for($i=count($ll)-1;$i>0;$i--)
  if(strpos($ll[$i],"Currently playing")!==false)
    break;
$id=current(explode(".",end(explode("/",$ll[$i]))));
$tt=strtotime(substr($ll[$i],1,20));

echo "$id $tt\n";
echo time()-$tt."\n";
  
  
?>
