<?php
function _pluginMain($arg)
{ global $par, $adults, $adf;
  mb_internal_encoding("UTF-8");
  ini_set("user_agent", "Mozilla/5.0 (Windows NT 5.1; rv:35.0) Gecko/20100101 Firefox/35.0 WDLXTV");
  $list = $plug = "";
  parse_str(html_entity_decode($arg,0,"UTF-8"));
  preg_match_all('#WEBTV_(.+)=\'(.+)\'#i', file_get_contents("/conf/config"), $m);
  $par = array_combine($m[1], $m[2]);
  extract($par);

  $adf = "/tmp/webtv_adult.tmp";
  $adults = explode(",", $ADULT_KEYWORDS);
  if (isset($adult) && $adult!="skip")
  { if ($adult=="remove")
    { @unlink($adf);
      $ret[] = Container0("", "Adult content LOCKED !", "/pin-code.jpg");
    }
    elseif ($adult==$ADULT_PINCODE)
    { file_put_contents($adf, "");
      $ret[] = Container0("adult=skip", "Adult content UNLOCKED !", "/pin-code.jpg");
    }
    else
      for ($i=0; $i<=9; $i++) $ret[] = Container($arg.$i, trim(chunk_split($adult.$i, 1, " - ")," -"), "/$i.png");
    return $ret;
  }

  if (!$list && !$plug)
  { if (!isset($adult)) @unlink($adf);
    for ($i=1; $i<=$LISTS; $i++)
    { if (!isset($par["LIST$i"])) continue;
      $s=trim($par["LIST$i"]);
      if (!$s) continue;
      list($tit,$url) = explode("=", $s, 2);
      if (!$url) $url=$tit;
      $ret[] = Container("list=".urlencode(trim($url)), $tit);
    }
    foreach (glob("{/conf/*.m3u,/tmp/umsp-plugins/webtv/playlists/*.m3u}", GLOB_BRACE) as $f)
      $ret[] = Container("list=$f", basename($f,".m3u"));
    foreach (glob("/tmp/umsp-plugins/webtv/plugins/*.php") as $f)
    { include $f;
      if ($par[$pluginInfo["id"]]!="OFF")
        $ret[] = @Container("plug=$f", $pluginInfo["name"], $pluginInfo["thumb"]);
    }
    if (allow_adult()) $ret[] = Container0("adult=remove", "Remove Adult pin-code", "/pin-code.jpg");
    else               $ret[] = Container0("adult=",       "Enter Adult pin-code",  "/pin-code.jpg");
    return $ret;
  }

  if ($plug)
  { include $plug;
    $s = call_user_func($pluginInfo["id"]."_get", $list, $plug);
    if (is_array($s))
    { //while (count($s)==1 && $s[0]["upnp:class"]=="object.container" && $s[0]["dc:title"]{0}!=" ")
      //  $s=call_user_func($pluginInfo["id"]."_get", urldecode(str_replace("umsp://plugins/webtv?plug=$plug&amp;list=", "", $s[0]["id"])), $plug);
      return $s;
    }
  }
  else $s = file_get_contents($list);

  $s = str_replace("\r", "", $s);
  $p = '#EXTINF([^,]+),(.+)(\n|\n\n)(http://)(.+)(\n|\n\n)#iU';
  preg_match_all($p, $s, $m, PREG_SET_ORDER);
  foreach ($m as $v)
  { $ext = trim($v[1]);
    $tit = trim($v[2]);
    $url = "http://".trim($v[5]);
    if (substr($url, -3)=="m3u") { $ret[] = Container("list=$url", $tit); continue; }
    $buf = $BUFSIZE;
    if (strpos($tit,"HD")!==false) $buf=1000000;
    if (strpos($ext,":-1")!==false || strpos($ext,": -1")!==false)
      $url = "http://127.0.0.1/umsp/plugins/webtv/webtv.php?url=$url&buf=$buf";
    switch (is_adult($tit))
    { case 1: $ret[] = Container("adult=", $tit); continue(2);
      case 2: $tit.=" [UNLOCKED]";
    }
    $ret[] = Item($url, $tit);
  }
  return $ret;
}


$url = $buf = "";
parse_str($_SERVER["QUERY_STRING"]);
PlayStream($url, $buf);


function PlayStream($url, $buf)
{ if (!$url) return;
  if (!$buf) $buf=1000000;
  if (substr($url,-4)=="m3u8") return PlayM3U8($url, 1000000);

  $purl = parse_url($url);
  $host = @$purl["host"];
  $port = @$purl["port"]; if (!$port) $port=80;
  $path = @$purl["path"]; if (!$path) $path="/";
  $quer = @$purl["query"];if ( $quer) $path.="?$quer";

  $f = fsockopen($host, $port, $errno, $errstr, 30);
  if (!$f) { _log("PlayStream.fsockopen - $errstr ($errno)"); return; }
  $s = "GET $path HTTP/1.1\r\n";
  $s.= "User-Agent: INTEL_NMPR/2.1 DLNADOC/1.50 dma/3.0 alphanetworks\r\n";
  $s.= "Host: $host\r\n";
  $s.= "Cache-Control: no-cache\r\n";
  $s.= "Connection: Close\r\n\r\n";
  fwrite($f, $s);

  while (true)
  { $s = fgets($f);
    if (!trim($s))
    { header("Content-Type: video/mpeg");
      header("Content-Size: $buf");
      header("Content-Length: $buf");
      break;
    }

    list($tag,$url) = explode(": ", $s, 2); $url=trim($url);
    if (stristr($tag, "Location"))
      $s = "Location: http://127.0.0.1/umsp/plugins/webtv/webtv.php?url=$url&buf=$buf\r\n";
    header($s);
  }
  set_time_limit( 0); fpassthru($f);
  set_time_limit(10); fclose($f);
}


function PlayM3U8($url, $buf)
{ header("HTTP/1.1 200 OK");
  header("Expires: 0");
  header("Content-Type: video/mpeg");
  header("Content-Size: $buf");
  header("Content-Length: $buf");
  header("transferMode.dlna.org: Streaming");
  header("Cache-control: no-cache");
  header("Connection: Close");
  $path = substr($url, 0, strrpos($url,"/")+1);
  $tim = microtime(1);
  while (true)
  { set_time_limit(60);
    preg_match_all('~#EXTINF:(.*)\n(.*)\n~', file_get_contents($url), $m);
    $n = count($m);
    for ($i=0; $i<$n; $i++)
    { $s=trim($m[2][$i]);         if ($s==$pred || $s==$pred2) continue;
      $p=$s;                      if ($s[4]!=":") $p=$path.$p;
      $c = file_get_contents($p); if (!$c) break;
      $d = $tim-microtime(1);     if ($d>0) usleep(1000000 * $d);
      echo $c;                    $tim += intval($m[1][$i]);
      $pred2=$pred;               $pred=$s;
    }
  }
}


function Container($id, $title, $thumb=" ", $single=false)
{ $id=trim($id); $title=trim($title);
  if ($single) $title=" ".$title;
  switch (is_adult($title))
  { case 1: $id="adult="; $title.=" [LOCKED]"; break;
    case 2: $title.=" [UNLOCKED]";
  }
  return Container0($id, $title, $thumb);
}

function Container0($id, $title, $thumb=" ")
{ if ($thumb{0}=="/") $thumb = "http://avkiev.16mb.com/wdtv/pic$thumb";
  return array
  ( "id"             => "umsp://plugins/webtv?".htmlentities($id,0,"UTF-8"),
    "dc:title"       => $title,
    "upnp:album_art" => $thumb,
    "upnp:class"     => "object.container"
  );
}

function Item($url, $tit, $thumb="")
{ return array
  ( "id"             => "umsp://plugins/webtv?url=".htmlentities($url,0,"UTF-8"),
    "dc:title"       => $tit,
    "res"            => $url,
    "upnp:album_art" => $thumb,
    "upnp:class"     => "object.item.videoItem",
    "protocolInfo"   => "http-get:*:*:*"
  );
}

function mb_ucfirst($s) { return mb_strtoupper(mb_substr($s,0,1)).mb_substr($s,1); }

function cmp_ret($a, $b) { return strcmp($a["upnp:class"].$a["dc:title"], $b["upnp:class"].$b["dc:title"]); }

function is_adult($title)
{ global $adults;
  foreach($adults as $v) if (stripos($title,$v)!==false) return (allow_adult() ? 2 : 1);
  return 0;
}

function allow_adult()
{ global $adf;
  return file_exists($adf);
}

function _log($s="")
{ if (is_array($s)) $s=print_r($s,1);
  error_log("WebTV: $s");
}

function cut($str, $beg, $end)
{ $b=str_replace(array('/','[',']','+','(',')'), array('\/','\[','\]','\+','\(','\)'), $beg);
  $e=str_replace(array('/','[',']','+','(',')'), array('\/','\[','\]','\+','\(','\)'), $end);
  preg_match("/$b(.*?)$e/si", $str, $m);
  return trim($m[1]);
}
?>
