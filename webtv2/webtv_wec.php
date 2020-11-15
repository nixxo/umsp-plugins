<?php
include "info.php";
if (defined("WECVERSION") && WECVERSION >= 3) {
	include_once "/usr/share/umsp/funcs-config.php";
	$id = $name = $desc = $author = $date = $version = $url = $url2 = $thumb = $art = $pic = $descr = "";
	extract($pluginInfo);
	$eman = $name;
	if ($thumb || $art) {
		$descr = "<div style='float: left; padding: 4px 10px 4px 4px;'><img src='" . ($thumb ? $thumb : $art) . "' height='80' alt='logo'></div>";
	}

	$descr .= "<div>$eman v$version ($date) by $author.<br>$desc<br>Information: <a href='$url'>$url</a><br>Information (rus): <a href='$url2'>$url2</a></div>";

	$key = strtoupper("{$id}_DESC");
	webtv2($key, $descr, $desc, NULL, WECT_DESC);
	webtv2($id, "Enable '$eman' UMSP plugin", "Включить '$eman' UMSP плагин", NULL, WECT_BOOL, array("off", "on"));
	$wec_options[$id]["readhook"] = wec_umspwrap_read;
	$wec_options[$id]["writehook"] = wec_umspwrap_write;

	webtv2("LISTS", "Count of playlists", "Количество плейлистов", "1", WECT_INT);
	$n = webtvStrPar2("LISTS");
	for ($i = 1; $i <= $n; $i++) {
		webtv2("LIST$i", "Playlist $i", "Format: name=url<br>F.e.: MyIPTV = http://site.com/playlist.m3u");
	}

	webtv2("BUFSIZE", "Buffer size", "Размер буфера", "200000");

}

function webtv2($key, $desc, $longdesc, $def = "", $typ = WECT_TEXT, $avv = NULL) {
	global $wec_options, $eman, $pri, $pic;
	if (!is_null($def)) {
		$key = "WEBTV_$key";
	}

	$wec_options["$key"] = array
		("configname" => $key,
		"configdesc" => $pic . $desc,
		"longdesc" => $longdesc,
		"group" => $eman,
		"type" => $typ,
		"page" => WECP_UMSP,
		"displaypri" => $pri++,
		"defaultval" => $def,
		"availval" => $avv,
	);
}

function webtvStrPar2($par) {
	$config = file_get_contents("/conf/config");
	preg_match("/WEBTV_$par='(.+)'/", $config, $matches);
	return trim($matches[1]);
}
?>
