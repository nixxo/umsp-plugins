<?php
$pluginInfo = array
( "id"      => "yandex",
  "name"    => "Yandex Disk",
  "desc"    => "View files on Yandex Disk",
  "author"  => "avkiev",
  "version" => "1.0",
  "date"    => "26.02.2015",
  "thumb"   => "http://avkiev.16mb.com/wdtv/pic/yandex.jpg"
);

function yandex_get($list, $plug)
{ global $par;
  if (!$list)
  { $n=$par["yandex_URLS"];
    for ($i=1; $i<=$n; $i++)
    { if (!isset($par["yandex_URL$i"])) continue;
      $s=trim($par["yandex_URL$i"]);
      if (!$s) continue;
      list($tit,$url) = explode("=", $s, 2);
      if (!$url) $url=$tit;
      $ret[] = Container("plug=$plug&list=".urlencode(trim($url)), $tit);
    }    return $ret;
  }

  list($key,$path) = explode("~", $list."~");
  $j = yandex_api("?public_key=$key&path=$path&limit=100");
  if (isset($j->_embedded->items)) { $deep=false; $z=$j->_embedded->items; }
  else                             { $deep=true;  $z->items=$j; }
  foreach ($z as $v)
  { $name = $v->name;
    $path = $v->path;
    if ($v->type=="dir") $ret[] = Container("plug=$plug&list=$key~$path", $name);
    else
    { $url="";
      if ($deep) $url = yandex_api("/download?public_key=$key&path=$path");
      if ($url && ($f=fopen($url->href,"r")))
      { $m = stream_get_meta_data($f); fclose($f);
        foreach ($m["wrapper_data"] as $w)
          if (substr($w,0,8)=="Location") { $url=substr($w,10); break; }
        $url = str_replace("https://", "http://", $url);
        $ret[] = Item($url, $name, $v->preview);
      }
      else $ret[] = Container("plug=$plug&list=$key~$path", "[ $name ]", $v->preview);
    }
  }  return $ret;
}

function yandex_wec()
{ global  $pluginInfo;
  extract($pluginInfo);
  webtv($id."_URLS", "Sub-plugin: \"$name\"<br>Count of public urls on $name", "Количество публичных ссылок", "1", WECT_INT);
  $n = webtvStrPar($id."_URLS");
  $d = $def = "Kvartal 95 = https://yadi.sk/d/9zRFtWyVentqJ";
  for ($i=1; $i<=$n; $i++, $d="") webtv($id."_URL$i", "$name - Public url $i", "Format: name=url<br>F.e.: $def", $d);
}

function yandex_api($arg)
{ set_time_limit(60);
  return json_decode(file_get_contents("https://cloud-api.yandex.net/v1/disk/public/resources$arg"));
}
?>
