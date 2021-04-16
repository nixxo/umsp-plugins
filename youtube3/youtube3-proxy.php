<?php
include_once($_SERVER['DOCUMENT_ROOT'] . '/umsp/funcs-log.php');

// set the logging level, one of L_ALL, L_DEBUG, L_INFO, L_WARNING, L_ERROR, L_OFF
global $logLevel;
$logLevel = L_WARNING;
global $logIdent;
$logIdent = 'YTSubscriptions-proxy';

//Turn the power led on if desired
if (getConfigValue('PROXY_LED') == 'ON') {
    //turn the power led on while the proxy is doing something
    system("sudo su -c 'echo power led on >> /proc/led'");
}

/* Extract the header from $content. Save header elements as key/value pairs */
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

_logInfo('Starting execution. $_SERVER is:' . serialize($_SERVER));
_logInfo('Getting url (global) for video id ' . $_GET['video_id']);

$url = null;
//this for loop is no longer needed, but I left it in just in case. It will loop only once.
for ($i = 1; $i < 2; $i++) {
    $url = _getYTVideo($_GET['video_id']);
    if (!is_null($url)) {
        break;
    } else {
        //sometimes you get an error while searching for the quality. A second try fixes that issue.
        _logWarning("Unable to find video id, try #$i");
    }
}

_logInfo("Downloading through $url");

_DownloadThru($url);

function _TestDownload($url)
{
    $size    = 250000;
    $content = file_get_contents($url, false, null, 0, $size);
    if ($size == strlen($content)) {
        return true;
    }
    _logError("_TestDownload -> size downloaded wrong:" . strlen($content));
    return false;
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
    if ($itemScheme == 'https') {
        $itemPort = 443;
    }
    $itemPath .= array_key_exists('query', $parsedURL) ? '?' . $parsedURL['query'] : '';

    $itemPath = urldecode($itemPath);
    _logDebug("_DownloadThrough -> calling _GetFile($itemHost, $itemPath, $itemPort)");
    _GetFile($itemHost, $itemPath, $itemPort);
}

function _GetFile($prmHost, $prmPath, $prmPort, $numberOfTries = 0)
{
    $protocol = ( $prmPort == 80 ) ? '' : 'ssl://';
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
        $out   .= "Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:14.0) Gecko/20100101 Firefox/14.0.1\r\n";
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
            //Turn the power led off if desired
            if (getConfigValue('PROXY_LED') == 'ON') {
                //turn the power led off when the proxy finished
                system("sudo su -c 'echo power led off >> /proc/led'");
            }
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

function _getYTVideo($id)
{
    //decide what video quality to request
    $quality_map = array(
        '1080P' => 37,
        '720P'  => 22,
        '480P'  => 35,
        '360P'  => 18,
    );
    //keep the same array, but without the P's
    $numeric_quality_map = array();
    foreach ($quality_map as $key => $value) {
        if (preg_match('/([0-9]+)P/i', $key, $m)) {
            $numeric_quality_map[ $m[1] ] = $value;
        }
    }

    //set a default quality setting -> 720P by default
    $fmt = 18;

    $resolution = getConfigValue('YOUTUBE_QUALITY');
    if (preg_match('/([0-9]+)P/', $resolution, $m)) {
        $numeric_resolution = $m[1]; //keep the numerical part
    }

    if (isset($resolution)) {
        if (array_key_exists($resolution, $quality_map)) {
            $fmt = $quality_map[ $resolution ];
        }
    }
    _logInfo("_getYTVideo -> Asking for quality $quality_map[$resolution]");

    _logDebug("_getYTVideo -> Asking for file_get_contents(http://www.youtube.com/watch?v={$id})");

    $html = file_get_contents("http://www.youtube.com/watch?v={$id}");

    //check video availability before starting processing anything else
    if (preg_match('/"playabilityStatus":{"status":"(\w+)"/', $hmtl, $status)) {
        if ($status[1] == 'UNPLAYABLE') {
            if (preg_match('/"reason":"(.+?)"/', $html, $reason)) {
                $reason = $reason[1];
            } else {
                $reason = "Video not available";
            }
            _logError("Video id:$id - $reason");
            exit;
        }
        _logInfo("Video id:$is status is $status[1]");
    }

    //code added by nixxo:
    //check and bypass age restriction if found
    if (preg_match("/\"status\":\"LOGIN_REQUIRED\",\"reason\":\"Accedi per confermare la tua età\.\"/", $html)
        or preg_match("/\"status\":\"LOGIN_REQUIRED\",\"reason\":\"Sign in to confirm your age\"/", $html)
        or preg_match('/player-age-gate-content">/', $html)) {
        _logInfo('Age-gate detected.');
        //get fmt_map from another page
        $fmt_page = file_get_contents("https://www.youtube.com/get_video_info?video_id=$id&eurl=https://youtube.googleapis.com/v/$id")
        ;        //new url format
        if (preg_match('/"formats":\[.+?\]/', urldecode($fmt_page), $ff)) {
            _logInfo('Added formats from age-gated page.');
            $html .= addslashes($ff[0]);
            $html .= $ff[0];
            //old url format: DEPRECATED???
        } else {
            _logError('Age-gate bypass: Fmt_map not found.');
            _logDebug($fmt_page);
            exit;
        }
    }
    //end of added code

    //code added by nixxo:
    //before parsing the video urls decode the player js and get the cipher
    //in order to decode the signature of signed videos.
    $ytCipher = null;
    // something like... https://s.ytimg.com/yts/jsbin/player-it_IT-vflPnd0Bl/base.js
    // or https://www.youtube.com/s/player/a3726513/player_ias.vflset/it_IT/base.js
    if (preg_match('/"js(?:Url)?"\s*:\s*"(.*?)"/', $html, $sc)) {
        $tmp         = stripslashes($sc[1]);
        $ytScriptURL = preg_match('/^\/\//', $tmp) ? 'https:' . $tmp : 'https://www.youtube.com' . $tmp;
        _logInfo("jsPlayer_url: $ytScriptURL");
        if (!$ytCipher = getCypher($ytScriptURL)) {
            $ytScriptSrc = file_get_contents($ytScriptURL);
            if ($ytScriptSrc) {
                $ytCipher = ytGrabCipher($ytScriptSrc);
                saveCypher($ytScriptURL, $ytCipher);
            }
        }
        _logInfo("ytCipher is: $ytCipher");
    } else {
        _logError('No js player found.');
    }
    //end of added code.

    //new format string 2019-09
    preg_match('/"formats".*?:(\[(.+?)\])/', $html, $new_fmt);

    if (isset($new_fmt[0])) {
        _logInfo('new url format');
        _logDebug('Matched formats: ' . $new_fmt[1]);
        $new_fmt = json_decode($new_fmt[1], true);

        if (!$new_fmt) {
            _logError("Json decode error on new_fmt");
            exit;
        }

        foreach ($new_fmt as $nf) {
            if (isset($nf['cipher'])) {
                _logInfo('format [' . $nf['itag'] . '] with cipher found. Decoding...');
                if (preg_match('/url=(?P<url>.*?)(\&|$)/', $nf['cipher'], $result)) {
                    $url = urldecode($result['url']);
                    _logDebug("url= $url");
                    if (preg_match('/(\&|^)s=(?P<sig>.*?)(\&|$)/', urldecode($nf['cipher']), $result)) {
                        //special signature for VEVO clips and other protected content
                        //decode the signature
                        if ($ytCipher) {
                            _logDebug('enc_sig: ' . $result['sig']);
                            $sig = ytDecodeSignature($ytCipher, $result['sig']);
                            _logDebug('dec_sig: ' . $sig);
                        }
                        $hash_qlty_url[ $nf['itag'] ] = $sig ? $url . '&sig=' . $sig : $url;
                    }
                }
            } elseif (isset($nf['url'])) {
                _logInfo('format [' . $nf['itag'] . '] normal.');
                $hash_qlty_url[ $nf['itag'] ] = $nf['url'];
            } elseif (isset($nf['signatureCipher'])) {
                _logInfo('format [' . $nf['itag'] . '] sig cypher.');
                parse_str($nf['signatureCipher'], $str);
                $sig                          = ytDecodeSignature($ytCipher, $str['s']);
                $url                          = urldecode($str['url']);
                $hash_qlty_url[ $nf['itag'] ] = $url . '&sig=' . $sig;
            } else {
                _logWarning('Url not found:');
                _logWarning(print_r($nf, true));
            }
        }
    } else {
        _logError('Unable to find url_encoded_fmt_stream_map! There are changes on the Youtube side!');
        system("sudo su -c 'echo \"Unable to find url_encoded_fmt_stream_map! There are changes on the Youtube side!\" >> /tmp/notice.osd'");

        file_put_contents("/tmp/yt3.$id.log.html", $html);
        //var_dump($html);
        return null;
    }

    //standard, fast option
    if (array_key_exists($fmt, $hash_qlty_url)) {
        //we found the quality we were looking for, so we can return the decoded URL

        //test download to prevent http 503 errors that crashes the player
        if (getConfigValue('YOUTUBE_TEST_DOWNLOAD') == 'ON') {
            if (!_TestDownload(urldecode($hash_qlty_url[ $fmt ]))) {
                _logWarning("_getYTVideo -> [$id] Failed download test for format $fmt, using 18 instead.");
                return urldecode($hash_qlty_url[18]);
            }
        }
        return urldecode($hash_qlty_url[ $fmt ]);
    } else {
        _logWarning("_getYTVideo -> Unable to find url map for quality $fmt ($resolution)");
        //select a different quality - prefer lower quality than desired
        krsort($numeric_quality_map, SORT_NUMERIC); //sort key high to low
        foreach ($numeric_quality_map as $key => $value) {
            if ($key >= $numeric_resolution) {
                continue;
            } //skip qualities that are higher than the user requested
            if (array_key_exists($value, $hash_qlty_url)) {
                //this is the winning resolution
                _logWarning("_getYTVideo -> Selected quality $value ({$key}P) instead");
                return urldecode($hash_qlty_url[ $value ]);
            }
        }

        //if no lower quality is found, prefer a higher quality than desired
        ksort($numeric_quality_map, SORT_NUMERIC); //sort key low to high
        foreach ($numeric_quality_map as $key => $value) {
            if ($key <= $numeric_resolution) {
                continue;
            } //skip qualities that are lower than the user requested
            if (array_key_exists($value, $hash_qlty_url)) {
                //this is the winning resolution
                _logWarning("_getYTVideo -> Selected quality $value ({$key}P) instead");
                return urldecode($hash_qlty_url[ $value ]);
            }
        }

        //the code should return something by now. We shouldn't get here. If we do anyway (because of a bug), select a random quality
        _logWarning("_getYTVideo -> Couldn't find any suitable qualities.");
        system("sudo su -c 'echo \"_getYTVideo -> Couldnt find any suitable qualities.\" >> /tmp/notice.osd'");
        return null;
    }
}

function ytDecodeSignature($ytCipher, $ytSig)
{
    /*
    Function taken from http://darbycrash.eu/PHP/

    Per il decoding delle signatures riporto il copyright del creatore di tale
    funzione originariamente scritta in PERL e che io ho portato in PHP:

    Copyright (C) 2007-2015 Jamie Zawinski <jwz@jwz.org>
    Permission to use, copy, modify, distribute, and sell this software and
    its documentation for any purpose is hereby granted without fee, provided
    that the above copyright notice appear in all copies and that both that
    copyright notice and this permission notice appear in supporting
    documentation.
    No representations are made about the suitability of this software for any
    purpose.
    It is provided "as is" without express or implied warranty.
     */

    $c = explode(' ', $ytCipher);
    $s = str_split($ytSig);
    foreach ($c as $step) {
        if ($step == '') {
        } elseif ($step == 'r') {
            $s = array_reverse($s);
        } elseif (preg_match('/^w(\d+)$/s', $step, $swap)) {
            $swap       = $swap[1];
            $temp       = $s[0];
            $s[0]       = $s[ $swap % count($s) ];
            $s[ $swap ] = $temp;
        } elseif (preg_match('/^s(\d+)$/s', $step, $slice)) {
            $s = array_slice($s, $slice[1]);
        }
    }
    return implode('', $s);
}

function ytGrabCipher($ytJs)
{
    /*
    Function taken from http://darbycrash.eu/PHP/ and slightly modified
    to make it compatible with the plugin.
    I here report the original license.

    Filename: YouTube.php.
    Copyright 2014/2015.
    Author:   Darby_Crash.
    Email:    kihol@inwind.it

    This Program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3, or (at your option)
    any later version.

    This Program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program. If not, see <http://www.gnu.org/licenses/>.
     */

    _logDebug("Parsing JS's functions...");
    $pat = '[\$a-zA-Z][a-zA-Z\d]*';
    $pat = "$pat(?:\.$pat)?";
    preg_match("/(?:\b|[^a-zA-Z0-9$])([a-zA-Z0-9$]{2})\s*=\s*function\(\s*a\s*\)\s*{\s*a\s*=\s*a\.split\(\s*\"\"\s*\)/", $ytJs, $fun);
    if (empty($fun[1])) {
        _logError('Unparsable function! [id:001]');
        exit;
    }
    $fun = $fun[1];
    preg_match("/\bfunction\s+\Q$fun\E\s*\($pat\)\s*{(.*?)}/sx", $ytJs, $fun2);
    $fun2 = preg_replace("/var\s($pat)=($pat)\[0\];\\2\[0\]=\\2\[(\d+)%\\2\.length\];\\2\[\\3\]=\\1;/", '$2=swap($2,$3);', $fun2[1]);
    if (empty($fun2)) {
        preg_match("/(?:\bvar\s+)?\Q$fun\E\s*=\s*function\s*\($pat\)\s*{(.*?)}/sx", $ytJs, $fun);
        $fun = preg_replace("/var\s($pat)=($pat)\[0\];\\2\[0\]=\\2\[(\d+)%\\2\.length\];\\2\[\\3\]=\\1;/", '$2=swap($2,$3);', $fun[1]);
    } else {
        $fun = $fun2;
    }
    if (empty($fun)) {
        _logError('Unparsable function! [id:002]');
        exit;
    }
    $pieces = explode(';', $fun);
    $c      = array();
    foreach ($pieces as $piece) {
        $piece = trim($piece);
        if (preg_match("/^($pat)=\\1\.$pat\(\"\"\)$/", $piece)) {
        } elseif (preg_match("/^($pat)=\\1\.$pat\(\)$/", $piece)) {
            $c[] = 'r';
        } elseif (preg_match("/^($pat)=\\1.$pat\((\d+)\)$/", $piece, $num)) {
            $c[] = 's' . $num[2];
        } elseif (preg_match("/^($pat)=($pat)\(\\1,(\d+)\)$/", $piece, $sw) || preg_match("@^()($pat)\($pat,(\d+)\)$@s", $piece, $sw)) {
            $n = $sw[3];
            $f = preg_replace('/^.*\./s', '', $sw[2]);
            preg_match("/\b\Q$f\E:\s*function\s*\(.*?\)\s*({[^{}]+})/s", $ytJs, $fn3);
            if (preg_match("/var\s($pat)=($pat)\[0\];/s", $fn3[1]) || preg_match("/void 0===($pat)\[($pat)\]/s", $fn3[1])) {
                $c[] = "w$n";
            } elseif (preg_match("/\b$pat\.reverse\(/s", $fn3[1])) {
                $c[] = 'r';
            } elseif (preg_match("/return\s*$pat\.slice/s", $fn3[1]) || preg_match("/\b$pat\.splice/s", $fn3[1])) {
                $c[] = "s$n";
            }
        } elseif (preg_match("/^return\s+$pat\.$pat\(\"\"\)$/s", $piece)) {
        }
    }
    $cipher = join(' ', $c);
    return $cipher;
}

function getConfigValue($key)
{
    $configFile = ( function_exists('_getUMSPConfPath') ? _getUMSPConfPath() : '/conf' ) . '/config';
    $fh         = fopen($configFile, 'r');
    while (!feof($fh)) {
        //read line by line
        $line = fgets($fh);
        //look for the variable we're searching
        preg_match("/^$key=(?:\'|\")?(.*)(?:\'|\")$/", $line, $result);
        if (isset($result[1])) {
            fclose($fh);
            return $result[1]; //we have a match;
        }
    }
    fclose($fh);
    return null;
}


function tempFile()
{
    $tmp = "/tmp/yt3.cyphers.data";
    if (!file_exists($tmp)) {
        file_put_contents($tmp, "");
    }
    return $tmp;
}

function getCypher($pid)
{
    $file = tempFile();
    $f    = file_get_contents($file);
    $f    = json_decode($f, true);
    if (isset($f[md5($pid)])) {
        return $f[$pid];
    }
    return false;
}

function saveCypher($pid, $cyper)
{
    $file         = tempFile();
    $f            = file_get_contents($file);
    $f            = json_decode($f, true);
    $f[md5($pid)] = $cyper;
    $f            = json_encode($f);
    file_put_contents($file, $f);
}

//Turn the power led off if desired
if (getConfigValue('PROXY_LED') == 'ON') {
    //turn the power led off when the proxy finished
    system("sudo su -c 'echo power led off >> /proc/led'");
}
