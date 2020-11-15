<?php
  include ("info.php");
  if (defined("WECVERSION") && WECVERSION >= 3)
  { include_once("/usr/share/umsp/funcs-config.php");
    $id = $name = $desc = $author = $date = $version = $url = $url2 = $thumb = $art = $pic = $descr = "";
    extract($pluginInfo); $eman = $name;
    if ($thumb || $art) $descr = "<div style='float: left; padding: 4px 10px 4px 4px;'><img src='".($thumb ? $thumb : $art)."' height='80' alt='logo'></div>";
    $descr .= "<div>$eman v$version ($date) by $author.<br>$desc<br>Information: <a href='$url'>$url</a><br>Information (rus): <a href='$url2'>$url2</a></div>";

    $key = strtoupper("{$id}_DESC");
    webtv($key, $descr, $desc, NULL, WECT_DESC);
    webtv($id, "Enable '$eman' UMSP plugin", "Включить '$eman' UMSP плагин", NULL, WECT_BOOL, array("off", "on"));
    $wec_options[$id]["readhook"]  = wec_umspwrap_read;
    $wec_options[$id]["writehook"] = wec_umspwrap_write;

    webtv("LISTS", "Count of playlists", "Количество плейлистов", "1", WECT_INT);
    $n = webtvStrPar("LISTS");
    for ($i=1; $i<=$n; $i++) webtv("LIST$i", "Playlist $i", "Format: name=url<br>F.e.: MyIPTV = http://site.com/playlist.m3u");
    webtv("BUFSIZE", "Buffer size", "Размер буфера", "200000");
    webtv("ADULT_PINCODE",  "Pin-code for adult channels", "You can lock adult channels by help this pin-code", "69");
    webtv("ADULT_KEYWORDS", "Keywords for adult channels", "Example: XXX,adult,porno,18+,redtube,Взрослые", "XXX,adult,porno,18+,redtube,Взрослые");

    foreach (glob("/tmp/umsp-plugins/webtv/plugins/*.php") as $f)
    { $id = $name = $desc = $author = $date = $version = $url = $url2 = $thumb = $art = $pic = "";
      include $f;
      extract($pluginInfo);
      if ($thumb || $art) $pic = "<div style='float: left; padding: 4px 10px 4px 4px;'><img src='".($thumb ? $thumb : $art)."' height='40' alt='logo'></div>";
      $s = "Sub-plugin: \"$name\" v$version ($date) by $author.<br>$desc";
      if ($url ) $s.="<br>Information: <a href='$url'>$url</a>";
      if ($url2) $s.="<br>Information 2: <a href='$url2'>$url2</a>";
      webtv($id, $s, $desc, "ON", WECT_BOOL);
      if (webtvStrPar($id)!="OFF" && function_exists($id."_wec")) call_user_func($id."_wec");
      $pic="";
    }
  }

function webtv($key, $desc, $longdesc, $def="", $typ=WECT_TEXT, $avv=NULL)
{ global $wec_options, $eman, $pri, $pic;
  if (! is_null($def)) $key = "WEBTV_$key";
  $wec_options["$key"] = array
  ( "configname" => $key,
    "configdesc" => $pic.$desc,
    "longdesc"   => $longdesc,
    "group"      => $eman,
    "type"       => $typ,
    "page"       => WECP_UMSP,
    "displaypri" => $pri++,
    "defaultval" => $def,
    "availval"   => $avv
  );
}

function webtvStrPar($par)
{ $config = file_get_contents("/conf/config");
  preg_match("/WEBTV_$par='(.+)'/", $config, $matches);
  return trim($matches[1]);
}
?>
