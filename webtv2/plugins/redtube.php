<?php
$pluginInfo = array
( "id"      => "redtube",
  "name"    => "RedTube",
  "desc"    => "Adult content from redtube.com",
  "author"  => "avkiev",
  "version" => "1.0",
  "date"    => "18.02.2015",
  "modified"=> "04.02.2018",
  "thumb"   => "http://avkiev.16mb.com/wdtv/pic/redtube.jpg"
);

function redtube_get($list, $plug)
{ set_time_limit(90);
  $cat=""; $pag="";
  parse_str($list);
  $rt="http://api.redtube.com/?data=redtube";
  if (!$cat)
  { $j = json_decode(file_get_contents("$rt.Categories.getCategoriesList"));
    foreach ($j->categories as $c)
      $ret[] = Container("plug=$plug&list=".urlencode("cat=".$c->category."&pag=1"), ucfirst($c->category));
  }
  else
  { $cat=str_replace(" ", "+", $cat);
    $j = json_decode(file_get_contents("$rt.Videos.searchVideos&category=$cat&page=$pag"));
    $cont["http"]["header"]="User-Agent: WDTV";
    $cont = stream_context_create($cont);
    foreach ($j->videos as $v)
    { $v=$v->video;
      $s=file_get_contents($v->url, 0, $cont);
      preg_match('/"videoUrl":"https(.*?)"/si', $s, $m);
      if (count($m)<2) continue;
      $s = "http" . str_replace('\/', '/', $m[1]);
      $ret[] = Item($s, "[$v->duration] $v->title", $v->thumb);
    }
    for ($i=0;$i<16;$i++) { $pag++; $ret[] = Container("plug=$plug&list=".urlencode("cat=$cat&pag=$pag"), "Page $pag"); }
  }
  return $ret;
}
?>
