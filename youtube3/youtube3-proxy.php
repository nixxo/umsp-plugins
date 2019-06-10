<?php
include_once( $_SERVER['DOCUMENT_ROOT'] . '/umsp/funcs-log.php' );

// set the logging level, one of L_ALL, L_DEBUG, L_INFO, L_WARNING, L_ERROR, L_OFF
global $logLevel;
$logLevel = L_WARNING;
global $logIdent;
$logIdent = 'YTSubscriptions-proxy';
$cookie="";

//Turn the power led on if desired
if(getConfigValue('PROXY_LED') == 'ON'){
	//turn the power led on while the proxy is doing something
	system("sudo su -c 'echo power led on >> /proc/led'");
}

/* Extract the header from $content. Save header elements as key/value pairs */
function parse_header($content)
{
    $newline = "\r\n";
    $parts = preg_split("/$newline . $newline/", $content);

    $header = array_shift($parts);
    $content = implode($parts, $newline . $newline);

    $parts = preg_split("/$newline/", $header);
    foreach ($parts as $part)
    {
        if (preg_match("/(.*)\: (.*)/", $part, $matches))
        {
            $headers[$matches[1]] = $matches[2];
        }
    }
    _logDebug("parse_header -> returning \$headers: ".serialize($headers));
    return $headers;
}

_logInfo("Starting execution. \$_SERVER is:".serialize($_SERVER));
_logInfo("Getting url (global) for video id ".$_GET['video_id']);

$url = null;
//this for loop is no longer needed, but I left it in just in case. It will loop only once.
for($i=1; $i<2; $i++){
    $url = _getYTVideo($_GET['video_id']);
    if(!is_null($url)){
        break;
    }
    else{
        //sometimes you get an error while searching for the quality. A second try fixes that issue.
        _logWarning("Unable to find video id, try #$i");
    }
}

_logInfo("Downloading through $url");

_DownloadThru($url);

function _DownloadThru($url)
{
	
  	foreach (array (' ',"\t","\n") as $char)
    	$url = preg_replace("/$char/",urlencode($char),$url);

	$parsedURL = parse_url($url);

	$itemHost = $parsedURL['host'];
	$itemPath  = array_key_exists('path', $parsedURL) ? $parsedURL['path'] : "/";
	$itemPort  = array_key_exists('port', $parsedURL) ? (int)$parsedURL['port'] : 80;
        $itemScheme = array_key_exists('scheme', $parsedURL);
        if($itemScheme == 'https'){
                $itemPort = 443;
        }
	$itemPath  .= array_key_exists('query', $parsedURL) ? "?" . $parsedURL['query'] : "";

	$itemPath = urldecode($itemPath);
	_logDebug("_DownloadThrough -> calling _GetFile($itemHost, $itemPath, $itemPort)");
	_GetFile($itemHost, $itemPath, $itemPort);
}

function _GetFile($prmHost, $prmPath, $prmPort, $numberOfTries=0) {
        $protocol = ($prmPort == 80)?"":"ssl://";
        _logDebug("Using protocol $protocol and port $prmPort");
	$fp = fsockopen($protocol.$prmHost, $prmPort, $errno, $errstr, 30);
	if (!$fp) {
		_logError("_GetFile -> $errstr ($errno)");
		echo "$errstr ($errno)<br />\n";
		system("sudo su -c 'echo \"_GetFile -> $errstr ($errno)\" >> /tmp/notice.osd'");
	} else {
		// prepare the header
		$method = $_SERVER['REQUEST_METHOD']; //MediaPlayer knows what to request
		$out  = "$method ". $prmPath .' HTTP/1.1' ."\r\n"; 
		$out .= 'Host: ' . $prmHost . "\r\n";
		$out .= "Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:14.0) Gecko/20100101 Firefox/14.0.1\r\n";
		$out .= "Accept: */*\r\n";
#		$out .= "Accept-Language: en-us;q=0.7,en;q=0.3\r\n";
#		$out .= "Accept-Encoding: gzip,deflate\r\n";
#		$out .= "Connection: keep-alive\r\n";
#		$out .= "Accept-Charset: windows-1251,utf-8;q=0.7,*;q=0.7\r\n";
		if(isset ($_SERVER['CONTENT_LENGTH'])){ //add content-length if the MediaPlayer specifies it
			$out .= "Content-Length: ".$_SERVER['CONTENT_LENGTH']."\r\n";
		}
		if(isset ($_SERVER['HTTP_RANGE'])){ //jump to the specific range if the MediaPlayer specifies it (when navigating)
			$out .= "Range: ".$_SERVER['HTTP_RANGE']."\r\n";
		}
#		if(isset ($GLOBALS['cookie'])){
#		    $out .= "Cookie: ".$GLOBALS['cookie']."\r\n";
#		}

		$out .= "\r\n";

		fwrite($fp, $out);
		_logDebug("_GetFile -> Sent header $out");
		$headerpassed = false;
		$response_text = "";

		//HTTP/1.1 200 OK
		//HTTP/1.1 302 Found
		$http_code = "";
				
		//read back the response
		while ($headerpassed == false) {
			$line = fgets( $fp);
			if( $line == "\r\n" )
				$headerpassed = true; //break the loop - we have the header
			else
				if ($http_code == "")
					$http_code = $line; //it's the first line the server sends back
				else
					$response_text .= $line; //save the rest of the lines in $response_text
		}
		_logDebug("_GetFile -> Received header $response_text");

		//get an associative array of the response
		$response = parse_header($response_text);
		
  		if ($response['Content-Type'] == 'video/x-flv'){
      		$response['Content-Disposition'] = 'attachment; filename="video.flv"';  //remember to ask it as an attachment
        }
  		if ($response['Content-Type'] == 'video/mp4'){
      		$response['Content-Disposition'] = 'attachment; filename="video.mp4"';  //remember to ask it as an attachment
        }
		
		//I'm not getting the file - I'm getting redirected somewhere else
		if ($http_code == "HTTP/1.1 302 Found\r\n" || $http_code == "HTTP/1.1 303 See Other\r\n"){
		    fclose($fp);
			_logInfo("_GetFile -> Downloading through ". $response['Location']. " because we received a HTTP 302 or 303");
		    _DownloadThru($response['Location']);  //repeat the download process
		}
		else
			if ($http_code == "HTTP/1.1 200 OK\r\n" || $http_code == "HTTP/1.1 206 Partial Content\r\n"){
				//extra headers have been set above for video files and will be sent	
				foreach (array_keys($response) as $header){
					//do a redirect and re-read the file/url
					_logInfo("_GetFile -> Received 200 or 206. Asking for the content with header ". "$header: " . $response[$header]);
				    header("$header: " . $response[$header]);
				}
				_logInfo("_GetFile -> Flushing the socket and exiting...");
				//Turn the power led off if desired
				if(getConfigValue('PROXY_LED') == 'ON'){
					//turn the power led off when the proxy finished
					system("sudo su -c 'echo power led off >> /proc/led'");
				}
				fpassthru($fp); //flush the socket and exit
				exit;
			}
			else{
				//the HTTP code is not supported - send the headers anyway, so that MediaLogic won't hang (hopefully)
				_logError("_GetFile -> HTTP code $http_code is not supported. Out: $out. Response Text: $response_text");
				system("sudo su -c 'echo \"_GetFile -> HTTP code $http_code is not supported.\" >> /tmp/notice.osd'");
				
				//re-request the video file - maybe we'll get a better response. Try this only a few times to prevent and enless loop
				if($numberOfTries < 2){
				    fclose($fp);
				    _logDebug("_GetFile -> trying again ($numberOfTries)...");
				    _GetFile($prmHost, $prmPath, $prmPort, ($numberOfTries+1));
				}
				else{
				    //give up
				    foreach (array_keys($response) as $header){
					    //do a redirect and re-read the file/url
					    _logInfo("_GetFile -> Received $http_code - won't work. Asking for the content with header ". "$header: " . $response[$header]);
				        header("$header: " . $response[$header]);
				    }
                                    fclose($fp);
                                }
			}

   } //from else socket
}

function _getYTVideo($id)
{
    //decide what video quality to request
    $quality_map = array('1080P' => 37, '720P' => 22, '480P' => 35, '360P' => 18, '240P' => 34, '270P' => 34);
	//keep the same array, but without the P's
	$numeric_quality_map = array();
    foreach($quality_map as $key => $value){
    	if(preg_match("/([0-9]+)P/", $key, $m)){
    		$numeric_quality_map[ $m[1] ] = $value;
    	}
    }
   
    //set a default quality setting -> 270P by default
    $fmt = 18;
   
    $resolution = getConfigValue('YOUTUBE_QUALITY');
    if(preg_match("/([0-9]+)P/", $resolution, $m)){
        $numeric_resolution = $m[1]; //keep the numerical part
    }

    if(isset($resolution)){
        if(array_key_exists($resolution, $quality_map)){
                $fmt = $quality_map[$resolution];
        }
    }
 	_logInfo("_getYTVideo -> Asking for quality $quality_map[$resolution]");
    
    _logDebug("_getYTVideo -> Asking for file_get_contents(http://www.youtube.com/watch?v={$id})");

    $html = file_get_contents("http://www.youtube.com/watch?v={$id}");
    //first, save the cookie they give us
    for ($i=0; $i < count($http_response_header); $i++){
        if(preg_match('/Set-Cookie: (.*)/', $http_response_header[$i], $result)){
            $GLOBALS['cookie'] .= $result[1]."; ";
            _logDebug("Set cookie to ".$GLOBALS['cookie']);
        }
    }

    //code added by nixxo:
    //check and bypass age restriction if found
    if (preg_match('@player-age-gate-content">@', $html)) {
        _logInfo("Age-gate detected.");
        //load the embed page
        $html = file_get_contents("http://www.youtube.com/embed/$id");
        if (preg_match('@"sts"\\s*:\\s*(\\d+?),@', $html, $sts)) {
            $sts = $sts[1];
            _logDebug("STS: $sts");
        } else {
            _logError("Age-gate bypass: STS not found.");
            exit;
        }
        //get fmt_map from another page
        $fmt_page = file_get_contents("https://www.youtube.com/get_video_info?video_id=$id&sts=$sts&eurl=https://youtube.googleapis.com/v/$id");
        if (preg_match("@url_encoded_fmt_stream_map=([^\"]*?)&@", $fmt_page, $ff)) {
            $ff = urldecode($ff[1]);
            //extract url and itag to create a clean fmt_map
            preg_match_all("@url=([^&]+?)(&|$)@", $ff, $ur);
            preg_match_all("@itag=(\d+)@", $ff, $it);
            $ff = '';
            for($i=0;$i<count($ur[1]);$i++){
                $ff .= "itag=".$it[1][$i]."&url=".$ur[1][$i].",";
            }
            //append the format map to the html page so I don't have to change other code below
            $html .= "\"url_encoded_fmt_stream_map\":\"$ff\"";
        } else {
            _logError("Age-gate bypass: Fmt_map not found.");
            _logDebug($fmt_page);
            exit;
        }
    }
    //end of added code

    //code added by nixxo:
    //before parsing the video urls docode the player js and get the cipher
    //in order to decode the signature of signed videos.
    $ytCipher = null;
    // something like... https://s.ytimg.com/yts/jsbin/player-it_IT-vflPnd0Bl/base.js
    if (preg_match('@"js":\\s*"(.*?)"@', $html, $sc)) {
        $tmp = stripslashes($sc[1]);
        $ytScriptURL = preg_match("@^\/yts@",$tmp) ? 'https://www.youtube.com' . $tmp : 'https:' . $tmp;
        _logDebug("jsPlayer_url: $tmp");
        $ytScriptSrc = file_get_contents($ytScriptURL);
        if ($ytScriptSrc) {
            $ytCipher = ytGrabCipher($ytScriptSrc);
            _logDebug("ytCipher is: $ytCipher");
        }
    }
    //end of added code.

    $useYTDL=0;
    //_logDebug("HTML file: ".$html);
    preg_match("/\"url_encoded_fmt_stream_map\":\s*\"([^\"]*)\"/", $html, $fmt_url_map);
    if(isset($fmt_url_map[1])){
        _logDebug("Matched url_encoded_fmt_stream_map: ".$fmt_url_map[1]);
        foreach(explode(',',$fmt_url_map[1]) as $var_fmt_url_map) {
              _logDebug("var_fmt_url_map: $var_fmt_url_map");
              //get the quality and the url
//              if(preg_match('/(?:itag=(?P<itag>\d+))?.*url=(?P<url>.*?).u0026.*(?:itag=(?P<itag>\d+))?/', ($var_fmt_url_map), $result)){      
                //do the match in several steps, to be more robust
                $itag =0;
                $url = 0;
                $sig = "";
                if(preg_match('/itag=(?P<itag>\d+)/', urldecode($var_fmt_url_map), $result)){
                    $itag = $result['itag'];
                }
                if(preg_match('/(?:.u0026)?url=(?P<url>.*?)(?:.u0026|$)/', urldecode($var_fmt_url_map), $result)){
                    $url = $result['url'];
                }
                if(preg_match('/(?:.u0026)?sig=(?P<sig>.*?)(?:.u0026|$)/', urldecode($var_fmt_url_map), $result)){
                    //regular signature videos
                    //$sig = $result['sig'];
                    //$url.="&signature=".$sig;
                    //$useYTDL=1;
                    //break;
                    
                }
                if(preg_match('/(?:.u0026|^)s=(?P<sig>.*?)(?:.u0026|$)/', urldecode($var_fmt_url_map), $result)){
                    //special signature for VEVO clips and other protected content
                    /*
                    $sig = $result['sig'];
                    $decryptedSignature = decryptSignature($sig);
                    _logDebug("Converted signature $sig to $decryptedSignature");
                    $url.="&signature=".$decryptedSignature;
                    */
                    //decode the signature
                    if ($ytCipher) {
                        _logDebug('enc_sig: ' . $result['sig']);
                        $sig = ytDecodeSignature($ytCipher, $result['sig']);
                        _logDebug('dec_sig: ' . $sig);
                    } else {
                        $useYTDL = 1;
                        break;
                    }
                }
                //add signature if present
                $hash_qlty_url[$itag] = $sig ? $url . "&signature=" . $sig : $url;
                _logDebug("_getYTVideo -> Quality ".$itag." is available and has URL ".$url);
        }
    }
    else{
        _logError("Unable to find url_encoded_fmt_stream_map! There are changes on the Youtube side!");
        system("sudo su -c 'echo \"Unable to find url_encoded_fmt_stream_map! There are changes on the Youtube side!\" >> /tmp/notice.osd'");
        return null;
    }
    
    if(!$useYTDL){
        //standard, fast option
	if(array_key_exists($fmt, $hash_qlty_url)){
		//we found the quality we were looking for, so we can return the decoded URL
		return urldecode($hash_qlty_url[$fmt]);
	}
	else{
		_logWarning("_getYTVideo -> Unable to find url map for quality $fmt ($resolution)");
		//select a different quality - prefer lower quality than desired
		krsort($numeric_quality_map, SORT_NUMERIC); //sort key high to low
		foreach($numeric_quality_map as $key => $value){
			if($key >= $numeric_resolution)
				continue; //skip qualities that are higher than the user requested
			if(array_key_exists($value, $hash_qlty_url)){
				//this is the winning resolution
				_logWarning("_getYTVideo -> Selected quality $value ({$key}P) instead");
				return urldecode($hash_qlty_url[$value]);
			}
		}
		
		//if no lower quality is found, prefer a higher quality than desired
		ksort($numeric_quality_map, SORT_NUMERIC); //sort key low to high
		foreach($numeric_quality_map as $key => $value){
			if($key <= $numeric_resolution)
				continue; //skip qualities that are lower than the user requested
			if(array_key_exists($value, $hash_qlty_url)){
				//this is the winning resolution
				_logWarning("_getYTVideo -> Selected quality $value ({$key}P) instead");
				return urldecode($hash_qlty_url[$value]);
			}
		}
		
		//the code should return something by now. We shouldn't get here. If we do anyway (because of a bug), select a random quality
		_logWarning("_getYTVideo -> Couldn't find any suitable qualities.");
		system("sudo su -c 'echo \"_getYTVideo -> Couldnt find any suitable qualities.\" >> /tmp/notice.osd'");
		return null;
	}
    }
    else{
        //This is a video with (encrytped) signature. We need to use Youtube-DL to do the hard work.
        //Note: youtube-dl requires python.app.bin, and is slow (~30s) on the WDTV...
        //Also, we don't bother asking for a specific quality out of fear of making it even slower...
        
        _logWarning("_getYTVideo -> Using youtube-dl to get the URL of an encrypted video - assuming you have python installed...");
        $youtubeDLCmd = "/tmp/umsp-plugins/youtube3/youtube-dl --quiet --no-warnings --get-url --cache-dir /tmp --no-check-certificate -f 18 'http://www.youtube.com/watch?v={$id}' 2>&1 ";
        _logWarning("_getYTVideo -> Using command $youtubeDLCmd");
        
        exec($youtubeDLCmd, $output);
        //If parsing went ok, the first line should be a valid URL - this is best effort...
        
        _logDebug("_getYTVideo(youtube-dl) -> ".print_r($output,true));
        //identify the first line with a URL and replace https with http
        foreach($output as $line){
                if(preg_match("/^https?:\/\//", $line)){
                        //$url = preg_replace("/^https:/", "http:", $line, 1);
                        $url = $line;
                        _logDebug("_getYTVideo(youtube-dl) -> Returning URL $url");
                        return $url;
                }
        }
    }
}

function ytDecodeSignature($ytCipher, $ytSig) {
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

    $c = explode(" ", $ytCipher);
    $s = str_split($ytSig);
    foreach ($c as $step) {
        if ($step == "") {
        } elseif ($step == "r") {
            $s = array_reverse($s);
        } elseif (preg_match("/^w(\d+)$/s", $step, $swap)) {
            $swap = $swap[1];
            $temp = $s[0];
            $s[0] = $s[$swap % count($s)];
            $s[$swap] = $temp;
        } elseif (preg_match("/^s(\d+)$/s", $step, $slice)) {
            $s = array_slice($s, $slice[1]);
        }
    }
    return implode("", $s);
}

function ytGrabCipher($ytJs) {
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
    preg_match("/(\w+)\s*=\s*function\((\w+)\)\{\s*\w+=\s*\w+\.split\(\"\"\)\s*;/", $ytJs, $fun);
    if (empty($fun[1])) {
        _logWarning("Unparsable function! [id:001]");
        exit;
    }
    $fun = $fun[1];
    preg_match("/\bfunction\s+\Q$fun\E\s*\($pat\)\s*{(.*?)}/sx", $ytJs, $fun2);
    $fun2 = preg_replace("/var\s($pat)=($pat)\[0\];\\2\[0\]=\\2\[(\d+)%\\2\.length\];\\2\[\\3\]=\\1;/", "$2=swap($2,$3);", $fun2[1]);
    if (empty($fun2)) {
        preg_match("/(?:\bvar\s+)?\Q$fun\E\s*=\s*function\s*\($pat\)\s*{(.*?)}/sx", $ytJs, $fun);
        $fun = preg_replace("/var\s($pat)=($pat)\[0\];\\2\[0\]=\\2\[(\d+)%\\2\.length\];\\2\[\\3\]=\\1;/", "$2=swap($2,$3);", $fun[1]);
    } else {
        $fun = $fun2;
    }
    if (empty($fun)) {
        _logWarning("Unparsable function! [id:002]");
        exit;
    }
    $pieces = explode(";", $fun);
    $c = array();
    foreach ($pieces as $piece) {
        $piece = trim($piece);
        if (preg_match("/^($pat)=\\1\.$pat\(\"\"\)$/", $piece)) {
        } elseif (preg_match("/^($pat)=\\1\.$pat\(\)$/", $piece)) {
            $c[] = "r";
        } elseif (preg_match("/^($pat)=\\1.$pat\((\d+)\)$/", $piece, $num)) {
            $c[] = "s" . $num[2];
        } elseif (preg_match("/^($pat)=($pat)\(\\1,(\d+)\)$/", $piece, $sw) || preg_match("@^()($pat)\($pat,(\d+)\)$@s", $piece, $sw)) {
            $n = $sw[3];
            $f = preg_replace('@^.*\.@s', "", $sw[2]);
            preg_match("@\b\Q$f\E:\s*function\s*\(.*?\)\s*({[^{}]+})@s", $ytJs, $fn3);
            if (preg_match("@var\s($pat)=($pat)\[0\];@s", $fn3[1])) {
                $c[] = "w$n";
            } elseif (preg_match("@\b$pat\.reverse\(@s", $fn3[1])) {
                $c[] = "r";
            } elseif (preg_match("@return\s*$pat\.slice@s", $fn3[1]) || preg_match("@\b$pat\.splice@s", $fn3[1])) {
                $c[] = "s$n";
            }
        } elseif (preg_match("@^return\s+$pat\.$pat\(\"\"\)$@s", $piece)) {
        }
    }
    $cipher = join(" ", $c);
    return $cipher;
}

function getConfigValue($key){
        $configFile = (function_exists('_getUMSPConfPath') ? _getUMSPConfPath() : '/conf') . '/config';
        $fh = fopen($configFile, 'r');
        while(!feof($fh)){
                //read line by line
                $line = fgets($fh);
                //look for the variable we're searching
                preg_match("/^$key=(?:\'|\")?(.*)(?:\'|\")$/", $line, $result);
                if(isset($result[1])){
                        fclose($fh);
                        return $result[1]; //we have a match;
                }
        }
        fclose($fh);
        return null;
}


//Turn the power led off if desired
if(getConfigValue('PROXY_LED') == 'ON'){
	//turn the power led off when the proxy finished
	system("sudo su -c 'echo power led off >> /proc/led'");
}
?>
