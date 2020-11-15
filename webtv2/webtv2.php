<?php
function _pluginMain($arg)
{
    _log("--- plugin start ---");
    _log("$arg");
    global $par, $adf;
    mb_internal_encoding("UTF-8");
    ini_set("user_agent", "Mozilla/5.0 (Windows NT 5.1; rv:35.0) Gecko/20100101 Firefox/35.0 WDLXTV");
    $list = $plug = "";
    parse_str(html_entity_decode($arg, 0, "UTF-8"));
    preg_match_all('#WEBTV_(.+)=\'(.+)\'#i', file_get_contents("/conf/config"), $m);
    $par = array_combine($m[1], $m[2]);
    extract($par);

    if (!$list && !$plug) {
        for ($i = 1; $i <= $LISTS; $i++) {
            if (!isset($par["LIST$i"])) {
                continue;
            }

            $s = trim($par["LIST$i"]);
            if (!$s) {
                continue;
            }

            list($tit, $url) = explode("=", $s, 2);
            if (!$url) {
                $url = $tit;
            }

            $ret[] = Container("list=" . urlencode(trim($url)), $tit);
        }
        foreach (glob("{/tmp/media/usb/USB1140/C472366A723660FA/*.m3u,/conf/*.m3u,/tmp/umsp-plugins/webtv2/playlists/*.m3u}", GLOB_BRACE) as $f) {
            $ret[] = Container("list=$f", basename($f, ".m3u"));
        }

        foreach (glob("/tmp/umsp-plugins/webtv2/plugins/*.php") as $f) {
            include $f;
            if ($par[$pluginInfo["id"]] != "OFF") {
                $ret[] = @Container("plug=$f", $pluginInfo["name"], $pluginInfo["thumb"]);
            }
        }

        return $ret;
    }

    if ($plug) {
        include $plug;
        $s = call_user_func($pluginInfo["id"] . "_get", $list, $plug);
        if (is_array($s)) {
            //while (count($s)==1 && $s[0]["upnp:class"]=="object.container" && $s[0]["dc:title"]{0}!=" ")
            //  $s=call_user_func($pluginInfo["id"]."_get", urldecode(str_replace("umsp://plugins/webtv?plug=$plug&amp;list=", "", $s[0]["id"])), $plug);
            return $s;
        }
    } else {
        $s = file_get_contents($list);
    }

    $s = str_replace("\r", "", $s);
    $p = '#EXTINF([^,]+),(.+)(\n|\n\n)(http[s]*://)(.+)(\n|\n\n)#iU';
    preg_match_all($p, $s, $m, PREG_SET_ORDER);
    _log("loading playlist: $list");
    foreach ($m as $v) {
        $ext = trim($v[1]);
        $tit = trim($v[2]);
        //$url = "http://" . trim($v[5]);
        $url = $v[4] . trim($v[5]);
        if (substr($url, -3) == "m3u") {
            $ret[] = Container("list=$url", $tit);
            continue;
        }
        $buf = $BUFSIZE;
        if (strpos($tit, "HD") !== false) {
            $buf = 1000000;
        }

        if (strpos($ext, ":-1") !== false || strpos($ext, ": -1") !== false) {
            $url=urlencode($url);
            $url = "http://127.0.0.1/umsp/plugins/webtv2/webtv2.php?url=$url&buf=$buf";
        }

        $ret[] = Item($url, $tit);
    }
    return $ret;
}

$url = $buf = "";
parse_str($_SERVER["QUERY_STRING"]);
$url=urldecode($url);
PlayStream($url, $buf);

function PlayStream($url, $buf)
{
    $url = urldecode($url);
    if (!$url) {
        return;
    }

    if (!$buf) {
        $buf = 1000000;
    }

    if (preg_match("~master\.m3u8~", $url)) {
        _log("HLS detected");
        return PlayM3U8_HLS($url, 1000000);
    }
    if (preg_match("~seedr\.cc/hls_playlist~", $url)) {
        _log("HLS detected");
        return PlayM3U8_HLS($url, $buf);
    }
    if (preg_match("~\.m3u8.+~", $url)) {
        return PlayM3U8($url, $buf);
    }
    if (substr($url, -4) == "m3u8") {
        return PlayM3U8($url, $buf);
    }
    _log("play TS: $url");

    $purl = parse_url($url);
    $host = @$purl["host"];
    $port = @$purl["port"];
    if (!$port) {
        $port = 80;
    }

    $path = @$purl["path"];
    if (!$path) {
        $path = "/";
    }

    $quer = @$purl["query"];
    if ($quer) {
        $path .= "?$quer";
    }

    $f = fsockopen($host, $port, $errno, $errstr, 30);
    if (!$f) {
        _log("PlayStream.fsockopen - $errstr ($errno)");
        return;
    }
    $s = "GET $path HTTP/1.1\r\n";
    $s .= "User-Agent: INTEL_NMPR/2.1 DLNADOC/1.50 dma/3.0 alphanetworks\r\n";
    $s .= "Host: $host\r\n";
    $s .= "Cache-Control: no-cache\r\n";
    $s .= "Connection: Close\r\n\r\n";
    fwrite($f, $s);

    while (true) {
        $s = fgets($f);
        if (!trim($s)) {
            header("Content-Type: video/mpeg");
            header("Content-Size: $buf");
            header("Content-Length: $buf");
            break;
        }

        list($tag, $url) = explode(": ", $s, 2);
        $url = trim($url);
        if (stristr($tag, "Location")) {
            $url=urlencode($url);
            $s = "Location: http://127.0.0.1/umsp/plugins/webtv2/webtv2.php?url=$url&buf=$buf\r\n";
        }

        header($s);
    }
    set_time_limit(0);
    fpassthru($f);
    set_time_limit(10);
    fclose($f);
}

function PlayM3U8_HLS($url, $buf)
{
    preg_match('~#EXT-X-STREAM-INF:(.*)\n(.*)\n~', file_get_contents($url), $m);
    _log("HLS url: $m[2]");
    return PlayM3U8($m[2], $buf);
}

function PlayM3U8($url, $buf)
{
    _log("M3U8 url: $url");
    header("HTTP/1.1 200 OK");
    header("Expires: 0");
    header("Content-Type: video/mpeg");
    header("Content-Size: $buf");
    header("Content-Length: $buf");
    header("transferMode.dlna.org: Streaming");
    header("Cache-control: no-cache");
    header("Connection: Close");
    $path = substr($url, 0, strrpos($url, "/") + 1);
    $tim = microtime(1);
    while (true) {
        set_time_limit(60);
        preg_match_all('~#EXTINF:(.*)\n(.*)\n~', file_get_contents($url), $m);
        $n = count($m[0]);
        for ($i = 0; $i < $n; $i++) {
            $s = trim($m[2][$i]);
            if ($s == $pred || $s == $pred2) {
                _log("continue");
                continue;
            }
            $p = $s;

            if (!preg_match("~^http~", $p)) {
                $p = $path . $p;
            }

            preg_match("~\/([^\/]+?)(\?.+?)*$~", $p, $fn);

            _log("* $p");
            set_error_handler("error_handler");
            try {
                $c = file_get_contents($p);
            } catch (Exception $e) {
                _log("download failed.");
            }
            restore_error_handler();

            //$c = file_get_contents($p);
            //_log("#");
            if (!$c) {
                _log("error on file get > $fn[1]");
                break;
            }
            _log("$i/$n: $fn[1]");
            echo $c;
            $d = $tim - microtime(1);
            if ($d > 0) {
                _log("sleep $d sec.");
                usleep(1000000 * $d);
            }
            //_log("$i/$n: $fn[1]");
            //echo $c;
            $tim += intval($m[1][$i]);
            $pred2 = $pred;
            $pred = $s;
        }
    }
    exit;
}

function Container($id, $title, $thumb = " ", $single = false)
{
    $id = trim($id);
    $title = trim($title);
    if ($single) {
        $title = " " . $title;
    }

    return Container0($id, $title, $thumb);
}

function Container0($id, $title, $thumb = " ")
{
    if ($thumb{0} == "/") {
        $thumb = "http://avkiev.16mb.com/wdtv/pic$thumb";
    }

    return array("id" => "umsp://plugins/webtv2?" . htmlentities($id, 0, "UTF-8"),
        "dc:title" => $title,
        "upnp:album_art" => $thumb,
        "upnp:class" => "object.container",
    );
}

function Item($url, $tit, $thumb = "")
{
    return array("id" => "umsp://plugins/webtv2?url=" . htmlentities($url, 0, "UTF-8"),
        "dc:title" => $tit,
        "res" => $url,
        "upnp:album_art" => $thumb,
        "upnp:class" => "object.item.videoItem",
        "protocolInfo" => "http-get:*:*:*",
    );
}

function _log($s = "")
{
    if (is_array($s)) {
        $s = print_r($s, 1);
    }
    //error_log("WebTV2: $s\n");
    $fp = fopen("/tmp/umsp-log.txt", "a+");
    //fwrite($fp, date('Y.m.d H:i:s') . " WebTV2: $s\n");
    fwrite($fp, "WebTV2: $s\n");
    fclose($fp);
}

function stf($d)
{
    ob_start();
    var_dump($d);
    $data = ob_get_clean();
    $fp = fopen("/tmp/umsp-log.txt", "a+");
    fwrite($fp, $data);
    fclose($fp);
}

function error_handler($severity, $message, $file, $line)
{
    throw new ErrorException($message, $severity, $severity, $file, $line);
}
