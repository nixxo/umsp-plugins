<?php
$pluginInfo = array
( "id"      => "baidu",
  "name"    => "Baidu Cloud",
  "desc"    => "View files on Baidu Cloud",
  "author"  => "avkiev",
  "version" => "1.0",
  "date"    => "05.03.2015",
  "thumb"   => "http://avkiev.16mb.com/wdtv/pic/baidu.jpg"
);

function baidu_get($list, $plug)
{ global $par;
  if (!$list)
  { $n=$par["baidu_URLS"];
    for ($i=1; $i<=$n; $i++)
    { if (!isset($par["baidu_URL$i"])) continue;
      $s=trim($par["baidu_URL$i"]);
      if (!$s) continue;
      @list($tit,$url) = explode("=", $s);
      if (!$url) { $url=$tit; $tit=$i; }
      $ret[] = Container("plug=$plug&list=".urlencode(trim($url)), $tit);
    }    return $ret;
  }

  if (strpos($list,"http")===0)
  { $s  = file_get_contents($list);
    $uk = cut($s, 'SHARE_UK = "', '"');
    $id = cut($s, 'SHARE_ID = "', '"');
    $dir= "&root=1";
  }
  else
  { list($uk,$id,$dir,$fsid) = explode("~", $list, 4);
    if ($fsid)
    { list($fsid,$name,$thumb) = explode("~", $fsid, 3);
      $thumb = urldecode($thumb);
      $dlink = baidu_dlink($uk, $id, $dir, $fsid);
      if ($dlink) $ret[] = Item($dlink, $name, $thumb);
      else        $ret[] = Container("plug=$plug&list=$uk~$id~$dir~", "Link to file \"$name\" temporarily locked by Baidu. Try again later", $thumb, true);
      return $ret;    }
  }

  $j = json_decode(file_get_contents("http://pan.baidu.com/share/list?uk=$uk&shareid=$id&dir=$dir"))->list;
  foreach ($j as $v)
  { $name  = $v->server_filename;
    if ($v->isdir=="1") $ret[] = Container("plug=$plug&list=$uk~$id~$v->path~", $name);
    else                $ret[] = Container("plug=$plug&list=$uk~$id~$dir~$v->fs_id~$name~".urlencode($v->thumbs->url1), "[ $name ]", $v->thumbs->url1);
  }
  usort($ret, "cmp_ret");
  return $ret;
}

function baidu_dlink($uk, $id, $dir, $fsid)
{ $t = file_get_contents("http://pan.baidu.com/wap/shareview?uk=$uk&shareid=$id&dir=$dir&fsid=$fsid");
  if (!$t) return "";
  foreach ($http_response_header as $v)
    if (stripos($v,"Set-Cookie: ")===0) $cook=trim(substr($v,12));
  $time = cut($t, 'FileUtils.timestamp="',    '"');
  $sign = cut($t, 'FileUtils.downloadsign="', '"');
  $head = array("header"=>"Cookie: $cook");
  $t = file_get_contents_headers("http://pan.baidu.com/share/download?uk=$uk&shareid=$id&timestamp=$time&sign=$sign&fid_list=[$fsid]", $head);
  if (!$t) return "";
  $j = json_decode($t);
  if ($j->errno) return "";

  $s=$j->dlink;
  _log($s);

  $head = array("method"=>"HEAD", "header"=>"Cookie: $cook");
  $t = file_get_contents_headers($s, $head);
  _log("T=".$t);
  _log("RESP=".$http_response_header);
  return $s;
}

function file_get_contents_headers($url, $head)
{ return file_get_contents($url, 0, stream_context_create(array("http"=>$head)));
}

/*
http://d.pcs.baidu.com/file/f78bec292e9d543e4aa75fd5ae6cd47e?fid=4284191044-250528-589869492586781&time=1425563075&expires=1425570053&rt=sh&chkv=1&chkpc=et&sign=FDTERVYA-DCb740ccc5511e5e8fedcff06b081203-6QTffZXbOy2IlhH6rG6ZucR8A3Q%3D&r=633160082
http://nb.baidupcs.com/file/f78bec292e9d543e4aa75fd5ae6cd47e?bkt=p2-nb-449&fid=4284191044-250528-589869492586781&time=1425563075&sign=FDTAXERLBH-DCb740ccc5511e5e8fedcff06b081203-T4GT2n0%2FGE%2BFS2F2NWHIJivIN3M%3D&to=nbb&fm=Nin,B,U,ny&newver=1&newfm=1&flow_ver=3&sl=80412750&expires=1425570053&rt=sh&r=633160082&mlogid=2471097745&vuk=4284191044&vbdid=3836126821&fin=Interny.s04e07.SATRip.Rus.avi&fn=Interny.s04e07.SATRip.Rus.avi
http://nj.baidupcs.com/file/effce0f794726853abdfa15af7e192c4?bkt=p2-nj-723&fid=4284191044-250528-6841838863067&time=1425564796&sign=FDTAXERLBH-DCb740ccc5511e5e8fedcff06b081203-uXvkMCr%2B%2Bxef0BjffqS1eZVpyUQ%3D&to=nb&fm=Nan,B,U,ny&newver=1&newfm=1&flow_ver=3&sl=80412750&expires=1425571873&rt=sh&r=387534037&mlogid=1682470520&sh=1&vuk=4284191044&vbdid=3836126821&fin=Interny.s04e08.SATRip.Rus.avi&fn=Interny.s04e08.SATRip.Rus.avi

15:51:44.578[760мс][всего 760мс] Статус: 302[Found]
GET http://d.pcs.baidu.com/file/f78bec292e9d543e4aa75fd5ae6cd47e?fid=4284191044-250528-589869492586781&time=1425563075&expires=1425570053&rt=sh&chkv=1&chkpc=et&sign=FDTERVYA-DCb740ccc5511e5e8fedcff06b081203-6QTffZXbOy2IlhH6rG6ZucR8A3Q%3D&r=633160082 Флаги загрузки[LOAD_DOCUMENT_URI  LOAD_INITIAL_DOCUMENT_URI  ] Размер содержимого[-1] Тип Mime[text/html]
   Заголовки запроса:
      Host[d.pcs.baidu.com]
      User-Agent[Mozilla/5.0 (Windows NT 5.1; rv:36.0) Gecko/20100101 Firefox/36.0]
      Accept[text/html,application/xhtml+xml,application/xml;q=0.9,*;q=0.8]
      Accept-Language[ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3]
      Accept-Encoding[gzip, deflate]
      Cookie[BAIDUID=740962579EB9BD6BF047993CB431F1AB:FG=1; cflag=65279%3A2; BAIDUPSID=740962579EB9BD6BF047993CB431F1AB; H_PS_PSSID=10148_1444_11082; pcsett=1425635006-f61f3849a3be89ef0fce57e37e8ebd68; BDUSS=kV3ZTA4fnJsWUFRSUtXQ3A0Rk5maWQ4NmVFQy1uRTg1QU9FVW9RYnJsMTVmeDVWQVFBQUFBJCQAAAAAAAAAAAEAAADf~5tfAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAHny9lR58vZUM]
      Connection[keep-alive]
   Заголовки ответа:
      Access-Control-Allow-Origin[*]
      Access-Control-Allow-Methods[GET, PUT, POST, DELETE, OPTIONS, HEAD]
      x-pcs-request-id[MTAuNTcuMTIwLjM2OjgwODA6MjQ3MTA5Nzc0NTowNS9NYXIvMjAxNSAyMTo1MTo0NSA=]
      Server[nginx/1.4.2]
      Date[Thu, 05 Mar 2015 13:51:45 GMT]
      Content-Type[text/html]
      Location[http://nb.baidupcs.com/file/f78bec292e9d543e4aa75fd5ae6cd47e?bkt=p2-nb-449&fid=4284191044-250528-589869492586781&time=1425563075&sign=FDTAXERLBH-DCb740ccc5511e5e8fedcff06b081203-T4GT2n0%2FGE%2BFS2F2NWHIJivIN3M%3D&to=nbb&fm=Nin,B,U,ny&newver=1&newfm=1&flow_ver=3&sl=80412750&expires=1425570053&rt=sh&r=633160082&mlogid=2471097745&vuk=4284191044&vbdid=3836126821&fin=Interny.s04e07.SATRip.Rus.avi&fn=Interny.s04e07.SATRip.Rus.avi]
      x-bs-client-ip[MTA5Ljk1LjUwLjE2MQ==]
      Connection[close]
*/
function baidu_wec()
{ global  $pluginInfo;
  extract($pluginInfo);
  webtv($id."_URLS", "Sub-plugin: \"$name\"<br>Count of public urls on $name", "Количество публичных ссылок", "1", WECT_INT);
  $n = webtvStrPar($id."_URLS");
  $d = $def = "Humour (rus) = http://pan.baidu.com/s/1gdxlg3L";
  for ($i=1; $i<=$n; $i++, $d="") webtv($id."_URL$i", "$name - Public url $i", "Format: name=url<br>F.e.: $def", $d);
}
?>
