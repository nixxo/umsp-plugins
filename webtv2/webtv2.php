<?php
include_once '/usr/share/umsp/funcs-log.php';
global $logLevel;
global $logIdent;
$logIdent = 'WebTV';
$logLevel = L_DEBUG;

function _pluginMain($arg)
{
    _logInfo("--- plugin start ---");
    _logDebug("$arg");
    global $par, $adf;
    mb_internal_encoding("UTF-8");
    ini_set("user_agent", "Mozilla/5.0 (Windows NT 5.1; rv:35.0) Gecko/20100101 Firefox/35.0 WDLXTV");
    $list = $plug = "";
    parse_str(html_entity_decode($arg, 0, "UTF-8"));
    preg_match_all('/WEBTV_(.+)=\'(.+)\'/i', file_get_contents("/conf/config"), $m);
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
    $p = '/EXTINF([^,]+),(.+)(\n|\n\n)(http[s]*:\/\/)(.+)(\n|\n\n)/iU';
    preg_match_all($p, $s, $m, PREG_SET_ORDER);
    _logDebug("loading playlist: $list");
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
            $url = urlencode($url);
            $url = "http://127.0.0.1/umsp/plugins/webtv2/webtv2.php?url=$url&buf=$buf";
        }

        $ret[] = Item($url, $tit);
    }
    return $ret;
}

$url = $buf = "";
parse_str($_SERVER["QUERY_STRING"]);
$url = urldecode($url);
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

    if (preg_match("/\.mkv$/", $url)) {
        _logInfo("File detected");
        return PlayFile($url);
    }
    if (preg_match("/master\.m3u8/", $url)) {
        _logInfo("HLS detected");
        return PlayM3U8_HLS($url, 1000000);
    }
    if (preg_match("/\.m3u8.+/", $url)) {
        return PlayM3U8($url, $buf);
    }
    if (substr($url, -4) == "m3u8") {
        return PlayM3U8($url, $buf);
    }
    _logInfo("play TS: $url");

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
        _logError("PlayStream.fsockopen - $errstr ($errno)");
        return;
    }
    $s  = "GET $path HTTP/1.1\r\n";
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
        $url             = trim($url);
        if (stristr($tag, "Location")) {
            $url = urlencode($url);
            $s   = "Location: http://127.0.0.1/umsp/plugins/webtv2/webtv2.php?url=$url&buf=$buf\r\n";
        }

        header($s);
    }
    set_time_limit(0);
    fpassthru($f);
    set_time_limit(10);
    fclose($f);
}

function PlayFile($url)
{
    $head = get_headers($url, true);
    $url  = $head['Location'];
    _DownloadThru($url);
}

function parse_header($content)
{
    $newline = "\r\n";
    $parts   = preg_split("/$newline . $newline/", $content);

    $header  = array_shift($parts);
    $content = implode($parts, $newline . $newline);

    $parts = preg_split("/$newline/", $header);
    foreach ($parts as $part) {
        if (preg_match('/(.*)\: (.*)/', $part, $matches)) {
            $headers[ $matches[1] ] = $matches[2];
        }
    }
    _logDebug('parse_header -> returning $headers: ' . serialize($headers));
    return $headers;
}

function _DownloadThru($url)
{
    foreach (array( ' ', "\t", "\n" ) as $char) {
        $url = preg_replace("/$char/", urlencode($char), $url);
    }

    $parsedURL = parse_url($url);

    $itemHost   = $parsedURL['host'];
    $itemPath   = array_key_exists('path', $parsedURL) ? $parsedURL['path'] : '/';
    $itemPort   = array_key_exists('port', $parsedURL) ? (int) $parsedURL['port'] : 80;
    $itemScheme = array_key_exists('scheme', $parsedURL);
    $itemPath  .= array_key_exists('query', $parsedURL) ? '?' . $parsedURL['query'] : '';

    $itemPath = urldecode($itemPath);
    _logDebug("_DownloadThrough -> calling _GetFile($itemHost, $itemPath, $itemPort)");
    _GetFile($itemHost, $itemPath, $itemPort);
}

function _GetFile($prmHost, $prmPath, $prmPort, $numberOfTries = 0)
{
    $protocol = ( $prmPort == 80 ) ? '' : 'ssl://';
    $protocol = '';
    _logDebug("Using protocol $protocol and port $prmPort");
    $fp = fsockopen($protocol . $prmHost, $prmPort, $errno, $errstr, 30);
    if (!$fp) {
        _logError("_GetFile -> $errstr ($errno)");
        echo "$errstr ($errno)<br />\n";
        system("sudo su -c 'echo \"_GetFile -> $errstr ($errno)\" >> /tmp/notice.osd'");
    } else {
        // prepare the header
        $method = $_SERVER['REQUEST_METHOD']; //MediaPlayer knows what to request
        $out    = "$method $prmPath HTTP/1.1\r\n";
        $out   .= "Host: $prmHost\r\n";
        $out   .= "Accept: */*\r\n";
        if (isset($_SERVER['CONTENT_LENGTH'])) { //add content-length if the MediaPlayer specifies it
            $out .= 'Content-Length: ' . $_SERVER['CONTENT_LENGTH'] . "\r\n";
        }
        if (isset($_SERVER['HTTP_RANGE'])) { //jump to the specific range if the MediaPlayer specifies it (when navigating)
            $out .= 'Range: ' . $_SERVER['HTTP_RANGE'] . "\r\n";
        }

        $out .= "\r\n";

        fwrite($fp, $out);
        _logDebug("_GetFile -> Sent header $out");
        $headerpassed  = false;
        $response_text = '';

        //HTTP/1.1 200 OK
        //HTTP/1.1 302 Found
        $http_code = '';

        //read back the response
        while ($headerpassed == false) {
            $line = fgets($fp);
            if ($line == "\r\n") {
                $headerpassed = true;
                //break the loop - we have the header
            } elseif ($http_code == '') {
                $http_code = $line;
                //it's the first line the server sends back
            } else {
                $response_text .= $line;
            } //save the rest of the lines in $response_text
        }
        _logDebug("_GetFile -> Received header $response_text");

        //get an associative array of the response
        $response = parse_header($response_text);

        if ($response['Content-Type'] == 'video/x-matroska') {
            $response['Content-Disposition'] = 'attachment; filename="video.mkv"';  //remember to ask it as an attachment
        }
        if ($response['Content-Type'] == 'video/x-flv') {
            $response['Content-Disposition'] = 'attachment; filename="video.flv"';  //remember to ask it as an attachment
        }
        if ($response['Content-Type'] == 'video/mp4') {
            $response['Content-Disposition'] = 'attachment; filename="video.mp4"';  //remember to ask it as an attachment
        }

        //I'm not getting the file - I'm getting redirected somewhere else
        if ($http_code == "HTTP/1.1 302 Found\r\n" || $http_code == "HTTP/1.1 303 See Other\r\n") {
            fclose($fp);
            _logInfo('_GetFile -> Downloading through ' . $response['Location'] . ' because we received a HTTP 302 or 303');
            _DownloadThru($response['Location']);  //repeat the download process
        } elseif ($http_code == "HTTP/1.1 200 OK\r\n" || $http_code == "HTTP/1.1 206 Partial Content\r\n") {
            //extra headers have been set above for video files and will be sent
            foreach (array_keys($response) as $header) {
                //do a redirect and re-read the file/url
                _logInfo('_GetFile -> Received 200 or 206. Asking for the content with header ' . "$header: " . $response[ $header ]);
                header("$header: " . $response[ $header ]);
            }
            _logInfo('_GetFile -> Flushing the socket and exiting...');
            fpassthru($fp); //flush the socket and exit
            exit;
        } else {
            //the HTTP code is not supported - send the headers anyway, so that MediaLogic won't hang (hopefully)
            _logError("_GetFile -> HTTP code $http_code is not supported. Out: $out. Response Text: $response_text");
            system("sudo su -c 'echo \"_GetFile -> HTTP code $http_code is not supported.\" >> /tmp/notice.osd'");

            //re-request the video file - maybe we'll get a better response. Try this only a few times to prevent and enless loop
            if ($numberOfTries < 2) {
                fclose($fp);
                _logDebug("_GetFile -> trying again ($numberOfTries)...");
                _GetFile($prmHost, $prmPath, $prmPort, ( $numberOfTries + 1 ));
            } else {
                //give up
                foreach (array_keys($response) as $header) {
                    //do a redirect and re-read the file/url
                    _logInfo("_GetFile -> Received $http_code - won't work. Asking for the content with header " . "$header: " . $response[ $header ]);
                    header("$header: " . $response[ $header ]);
                }
                fclose($fp);
            }
        }
    } //from else socket
}

function PlayM3U8_HLS($url, $buf)
{
    preg_match('/#EXT-X-STREAM-INF:(.*)\n(.*)\n/', file_get_contents($url), $m);
    _logInfo("HLS url: $m[2]");
    return PlayM3U8($m[2], $buf);
}

function PlayM3U8($url, $buf)
{
    _logInfo("M3U8 url: $url");
    header("HTTP/1.1 200 OK");
    header("Expires: 0");
    header("Content-Type: video/mpeg");
    header("Content-Size: $buf");
    header("Content-Length: $buf");
    header("transferMode.dlna.org: Streaming");
    header("Cache-control: no-cache");
    header("Connection: Close");
    $path = substr($url, 0, strrpos($url, "/") + 1);
    $tim  = microtime(1);
    while (true) {
        set_time_limit(60);
        preg_match_all('/#EXTINF:(.*)\n(.*)\n/', file_get_contents($url), $m);
        $n = count($m[0]);
        for ($i = 0; $i < $n; $i++) {
            $s = trim($m[2][$i]);
            if ($s == $pred || $s == $pred2) {
                _logDebug("continue");
                continue;
            }
            $p = $s;

            if (!preg_match("/^http/", $p)) {
                $p = $path . $p;
            }

            preg_match("/\/([^\/]+?)(\?.+?)*$/", $p, $fn);

            _logDebug("* $p");
            set_error_handler("error_handler");
            try {
                $c = file_get_contents($p);
            } catch (Exception $e) {
                _logDebug("download failed.");
            }
            restore_error_handler();

            //$c = file_get_contents($p);
            //_log("#");
            if (!$c) {
                _logDebug("error on file get > $fn[1]");
                break;
            }
            _logDebug("$i/$n: $fn[1]");
            echo $c;
            $d = $tim - microtime(1);
            if ($d > 0) {
                _logDebug("sleep $d sec.");
                usleep(1000000 * $d);
            }
            //_log("$i/$n: $fn[1]");
            //echo $c;
            $tim  += intval($m[1][$i]);
            $pred2 = $pred;
            $pred  = $s;
        }
    }
    exit;
}

function Container($id, $title, $thumb = " ", $single = false)
{
    $id    = trim($id);
    $title = trim($title);
    if ($single) {
        $title = " " . $title;
    }

    return Container0($id, $title, $thumb);
}

function Container0($id, $title, $thumb = " ")
{
    if ($thumb[0] == "/") {
        $thumb = "http://avkiev.16mb.com/wdtv/pic$thumb";
    }

    return array(
        "id"             => "umsp://plugins/webtv2?" . htmlentities($id, 0, "UTF-8"),
        "dc:title"       => $title,
        "upnp:album_art" => $thumb,
        "upnp:class"     => "object.container",
    );
}

function Item($url, $tit, $thumb = "")
{
    return array(
        "id"             => "umsp://plugins/webtv2?url=" . htmlentities($url, 0, "UTF-8"),
        "dc:title"       => $tit,
        "res"            => $url,
        "upnp:album_art" => $thumb,
        "upnp:class"     => "object.item.videoItem",
        "protocolInfo"   => "http-get:*:*:*",
    );
}

function error_handler($severity, $message, $file, $line)
{
    throw new ErrorException($message, $severity, $severity, $file, $line);
}
