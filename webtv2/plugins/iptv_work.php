<?php
$pluginInfo = array
( "id"      => "iptv_work",
  "name"    => "IPTV-WORK",
  "desc"    => "Channels from http://iptv-work.at.ua",
  "author"  => "avkiev",
  "version" => "1.0",
  "date"    => "17.02.2015",
  "thumb"   => "http://avkiev.16mb.com/wdtv/pic/iptv-work.jpg"
);

function iptv_work_get()
{ ini_set("user_agent", "WDLXTV");
  return file_get_contents("http://avkiev.16mb.com/iptv/plugins/iptv_work.php5");
}
?>
