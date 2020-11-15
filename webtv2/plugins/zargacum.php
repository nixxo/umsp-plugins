<?php
$pluginInfo = array
( "id"      => "zargacum",
  "name"    => "Zargacum",
  "desc"    => "WebTV from zargacum.net",
  "author"  => "avkiev",
  "version" => "1.0",
  "date"    => "17.02.2015",
  "thumb"   => "http://avkiev.16mb.com/wdtv/pic/zargacum.jpg"
);


function zargacum_get($list, $plug)
{ global $par;
  $z = file_get_contents("http://avkiev.16mb.com/iptv/zargacum.m3u8");
  $z = str_replace("\r", "", $z);
  $z = explode("\n", $z);
  if (!$list)
  { $r = array();
    foreach ($z as $v)
      if (substr($v,0,8)=="#EXTGRP:")
        if (array_key_exists($s=substr($v,8),$r)) $r[$s][1]++;
        else $r[$s]=array($s,1);    foreach ($r as $k=>$v)
      $ret[] = Container("plug=$plug&list=".$v[0], mb_ucfirst("$k ($v[1])"));
  }
  else
  { $key = "/".$par["zargacum_key"]."/";
    $list="#EXTGRP:$list";
    $ret=$z[0]."\n";
    foreach ($z as $k=>$v)
      if ($v==$list) $ret.=$z[$k-1]."\n" . str_replace("/TEST/", $key, $z[$k+1])."\n";
  }
  return $ret;
}


function zargacum_wec()
{ global  $pluginInfo;
  extract($pluginInfo);
//webtv($id, $name, $description[, $default]);
  webtv($id."_key", "Sub-plugin: \"$name\"<br>Just register on zargacum.net, go to IPTV-Menu, copy/paste client key to this field", "Key for $name");
}
?>
