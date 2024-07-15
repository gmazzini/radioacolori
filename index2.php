<?php
echo "<pre>";
$ll=file("/var/log/ices/ices.log");
for($i=count($ll)-1;$i>0;$i--)
  if(strpos($ll[$i],"Currently playing")!==false)
    break;

$aux=end(explode("/",$ll[$i]));
echo "$aux\n";
echo $ll[$i]."\n";

  
  
?>
