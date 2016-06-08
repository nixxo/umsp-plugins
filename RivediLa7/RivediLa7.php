<?php
include_once '/usr/share/umsp/funcs-log.php';
global $logLevel;
global $logIdent;
$logLevel = L_ALL;
$logIdent = 'La7PlugIn';

function _pluginMain($prmQuery) {
	_logDebug("plugin start");
	if (strpos($prmQuery, '&amp;') !== false) {
		$prmQuery = str_replace('&amp;', '&', $prmQuery);
	}
	parse_str($prmQuery, $params);
	if (isset($params['f']) && function_exists($params['f'])) {
		if (isset($params['args'])) {
			return call_user_func_array($params['f'], $params['args']);
		} else {
			return call_user_func($params['f']);
		}
	}
	return main_menu();
}

function clean_title($tit) {
	return preg_replace("@&#039;@i", "'", $tit);
}

function build_query($f, $args = array()) {
	return http_build_query(array('f' => $f, 'args' => $args), '', '&amp;');
}

function build_umsp_url($f, $args = array()) {
	return 'umsp://plugins/' . basename(dirname(__FILE__)) . '/' . basename(__FILE__, '.php') . '?' . build_query($f, $args);
}

function build_server_url($args) {
	return 'http://' . $_SERVER['HTTP_HOST'] . '/umsp/plugins/' . basename(dirname(__FILE__)) . '/' . basename(__FILE__) . '?' . http_build_query($args);
}

function create_item($title, $thumb, $sortBy, $category = null, $genre = null, $platform = null) {
	return array(
		'id' => build_umsp_url('videos', array($sortBy, $category, $genre, $platform)),
		'dc:title' => $title,
		'upnp:album_art' => $thumb,
		'upnp:class' => 'object.container',
	);
}

function createPlayItem($res, $title, $desc, $album_art, $class, $protocolInfo) {
	return array(
		'id' => build_umsp_url('play', array($res, $title, $desc, $album_art, $class, $protocolInfo)),
		'res' => $res,
		'dc:title' => $title,
		'desc' => $desc,
		'upnp:album_art' => $album_art,
		'upnp:class' => $class,
		'protocolInfo' => $protocolInfo,
	);
}

function main_menu() {
	_logDebug('main menu');
	$ff = file_get_contents('http://www.la7.it/rivedila7');
	if (preg_match_all('@<div class="giorno *.{0,7}">\s*<a href="\/rivedila7\/(\d)\/LA7">[\s\S]{1,100}<div class="dateDay">(\d+)<\/div>[\s\S]{1,100}<div class="dateMonth">(.+?)<\/div>[\s\S]{1,100}<div class="dateRowWeek *.{0,7}">(.{5,15})<\/div>@', $ff, $m)) {
		$days_name = array_reverse($m[4]);
		$days_number = array_reverse($m[2]);
		$months = array_reverse($m[3]);
		$ids = array_reverse($m[1]);
		for ($i = 0; $i < count($days_name); $i++) {
			$items[] = array(
				'id' => build_umsp_url('day', array($ids[$i])),
				'dc:title' => $days_name[$i] . ' ' . $days_number[$i] . ' ' . $months[$i],
				'upnp:class' => 'object.container',
			);
		}
	}
	return $items;
}

function day($id) {
	_logDebug("day $id");
	$items = array();
	$ff = file_get_contents("http://www.la7.it/rivedila7/$id/LA7");

	if (preg_match_all('@<div class="orario">(\d{1,2}:\d{1,2})<\/div>\s{0,20}<div class="thumbnail_la7 thumbnail">\s{0,60}<a@', $ff, $or)
		&& preg_match_all('@<img src="(.+kdam\.iltrovatore\.it.+)" *width="\d+" *height="\d+" *alt=".+" *title="(.+)" *\/>@', $ff, $th)
		&& preg_match_all('@<a href="(.+)" *class="thumbVideo">@', $ff, $vd)
		&& preg_match_all('@<div class="approfondisci"> *(\d{1,2}):(\d{1,2}:\d{1,2}) *<\/div>@', $ff, $dr)
		&& preg_match_all('@<div class="descrizione"><p>(.+?)</p>@', $ff, $ds)
	) {
		for ($i = 0; $i < count($or[1]); $i++) {
			$items[] = createPlayItem(
				build_server_url(array('video' => $vd[1][$i])),
				clean_title($or[1][$i] . ' - ' . $th[2][$i] . ' [' . ($dr[1][$i] != '00' ? $dr[1][$i] . ':' : '') . $dr[2][$i] . ']'),
				$ds[1][$i],
				$th[1][$i],
				'object.item.videoitem',
				'http-get:*:video/mp4:*');
		}
	} else {
		_logDebug("retrieving data failed.");
	}
	return $items;
}

if (isset($_GET['video'])) {
	$page_url = rawurldecode($_GET['video']);
	_logDebug("page: " . $page_url);
	$ff = file_get_contents($page_url);
	if (preg_match('@src_mp4 *: *"(.+)" *,@', $ff, $m)) {
		_logDebug("playing: " . $m[1]);
		ob_start();
		header('Content-type: video/mp4');
		header('Location: ' . $m[1]);
		ob_flush();
	} elseif (preg_match('@src *: *".+?content/entry(.+)/master.m3u8" *,@', $ff, $m)) {
		_logDebug("playing: http://vodpmd.la7.it.edgesuite.net/content/entry" . $m[1]);
		ob_start();
		header('Content-type: video/mp4');
		header('Location: http://vodpmd.la7.it.edgesuite.net/content/entry' . $m[1]);
		ob_flush();
	}
	exit();
}
?>