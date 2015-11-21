<?php
include_once '/usr/share/umsp/funcs-log.php';
global $logLevel;
global $logIdent;
$logLevel = L_ALL;
$logIdent = 'RaiReplayPlugIn';

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
	$channels = array("RaiUno", "RaiDue", "RaiTre", "RaiCinque", "RaiPremium", "RaiGulp", "RaiYoyo");
	$logos = array(
		"https://upload.wikimedia.org/wikipedia/it/thumb/7/73/Logo_Rai_1_2010.svg/240px-Logo_Rai_1_2010.svg.png",
		"https://upload.wikimedia.org/wikipedia/it/thumb/8/83/Logo_Rai_2_2010.svg/240px-Logo_Rai_2_2010.svg.png",
		"https://upload.wikimedia.org/wikipedia/it/thumb/5/5c/Logo_Rai_3_2010.svg/240px-Logo_Rai_3_2010.svg.png",
		"https://upload.wikimedia.org/wikipedia/commons/thumb/6/60/Rai_5_logo.svg/240px-Rai_5_logo.svg.png",
		"https://upload.wikimedia.org/wikipedia/it/thumb/6/61/RAI_Premium_2010_Logo.svg/240px-RAI_Premium_2010_Logo.svg.png",
		"https://upload.wikimedia.org/wikipedia/commons/thumb/7/79/Rai_Gulp_2010.svg/240px-Rai_Gulp_2010.svg.png",
		"https://upload.wikimedia.org/wikipedia/commons/thumb/0/07/RAI_YoYo_2010_Logo.svg/240px-RAI_YoYo_2010_Logo.svg.png",
	);
	_logDebug('main menu');
	$items = array();
	for ($i = 0; $i < count($channels); $i++) {
		$items[] = array(
			'id' => build_umsp_url('channel', array($channels[$i])),
			'dc:title' => $channels[$i],
			'upnp:album_art' => $logos[$i],
			'upnp:class' => 'object.container',
		);
	}
	$items[] = array(
		'id' => build_umsp_url('config'),
		'dc:title' => 'Configura Plugin',
		'upnp:album_art' => 'http://lh5.googleusercontent.com/-xsH3IJAYXd0/TvwfRdc7DMI/AAAAAAAAAFk/NmvkjuqP_eo/s220/Settings.png',
		'upnp:class' => 'object.container',
	);
	return $items;
}

function channel($id) {
	_logDebug("channel $id");

	$mesi = array(1 => 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre');
	$giorni = array('Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato');

	$items = array();
	for ($i = 1; $i <= 7; $i++) {
		$days_ago = date('Y_m_d', mktime(0, 0, 0, date("m"), date("d") - $i, date("Y")));
		list($g, $gg, $m) = explode('-', date('w-d-n', mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"))));
		$items[] = array(
			'id' => build_umsp_url('day', array($id . "_" . $days_ago)),
			'dc:title' => $giorni[$g] . " $gg " . $mesi[$m],
			'upnp:class' => 'object.container',
		);
	}
	return $items;
}

function day($id) {
	_logDebug("day $id");
	$items = array();
	$ff = file_get_contents('http://www.rai.tv/dl/portale/html/palinsesti/replaytv/static/' . $id . '.html');
	$jd = json_decode($ff, true);

	foreach (array_pop($jd) as $dy => $arr) {
		foreach ($arr as $k => $v) {

			$phd = getConfigValue('PREFER_HD', 1);

			if ($phd && preg_match('@relinkerServlet\.htm\?cont=(.+)$@', $v['h264_1800'], $m)) {
				_logDebug('vid_id[1800]: ' . $m[1]);
				$ids = $m[1];
			} elseif (!$phd && preg_match('@relinkerServlet\.htm\?cont=(.+)$@', $v['h264_800'], $m)) {
				_logDebug('vid_id[800]: ' . $m[1]);
				$ids = $m[1];
			} elseif (preg_match('@relinkerServlet\.htm\?cont=(.+)$@', $v['h264'], $m)) {
				_logDebug('vid_id: ' . $m[1]);
				$ids = $m[1];
			} else {
				_logDebug('url not found.');
				continue;
			}
			if (preg_match('@relinkerServlet\.htm\?cont=(.+)$@', $v['urlSmartPhone'], $m)) {
				if ($m[1] != $ids) {
					_logDebug('vid_id[sf]: ' . $m[1]);
					$ids = $ids . '@' . $m[1];
				} else {
					_logDebug('no vid_id[sf]');
				}

			}
			$items[] = createPlayItem(
				build_server_url(array('video' => $ids)),
				clean_title($k . ' - ' . $v['t']),
				$v['d'],
				$v['image'] ? $v['image'] : 'http://www.rai.tv/dl/replaytv/images/tappo_rai.png',
				'object.item.videoitem',
				'http-get:*:video/mp4:*');
		}
	}
	return $items;
}

function config($key = null, $value = null) {
	if ($key != null) {
		putConfigValue($key, $value);
	}
	$prefer_hd = getConfigValue('PREFER_HD', 1);
	$Items[] = array(
		'id' => build_umsp_url('config', array('PREFER_HD', !$prefer_hd)),
		'dc:title' => 'HD ' . ($prefer_hd ? 'on' : 'off') . ' - Seleziona per ' . ($prefer_hd ? 'disattivarlo' : 'attivarlo'),
		'upnp:album_art' => 'http://lh4.googleusercontent.com/-hsbvm1bQllg/Tvwgvvk5BnI/AAAAAAAAAFs/DHQp5lKE7-4/s220/HD-icon.png',
		'upnp:class' => 'object.container',
	);
	$Items[] = array(
		'id' => build_umsp_url('main_menu'),
		'dc:title' => 'Indietro',
		'upnp:album_art' => 'http://lh3.googleusercontent.com/-dsT4ZvjCth4/TvwihbvNZLI/AAAAAAAAAF0/1Jp9s8dLNlY/s220/back_button_icon.png',
		'upnp:class' => 'object.container',
	);
	return $Items;
}

function getConfigValue($key, $default_value) {
	$conf_dir = function_exists('_getUMSPConfPath') ? _getUMSPConfPath() : '/conf';
	$config = file_get_contents($conf_dir . '/config');
	if (preg_match("/RAIREPLAY_$key='(.+)'/", $config, $matches)) {
		return trim($matches[1]);
	}
	return $default_value;
}

function putConfigValue($key, $value) {
	exec("sudo config_tool -c RAIREPLAY_$key='$value' >/dev/null 2>&1");
}

if (isset($_GET['video'])) {
	$prefix = 'http://mediapolisvod.rai.it/relinker/relinkerServlet.htm?cont=';
	_logDebug("video: " . $_GET['video']);
	$url = '';
	$ids = explode('@', $_GET['video']);
	foreach ($ids as $k => $v) {
		_logDebug("test $k > $v");
		$h = get_headers($prefix . $v, 1);
		$url = $h['Location'];
		_logDebug('video: ' . $url);
		$h = get_headers($url, 1);
		if (($h[0] == 'HTTP/1.0 200 OK') && preg_match('@master\.m3u8@', $url)) {
			_logDebug('creating url from: ' . $url);
			$phd = getConfigValue('PREFER_HD', 1);
			if (preg_match('@creativemedia(\d)-.+\/i\/(.+?),\d@', $url, $m)) {
				$url = 'http://creativemedia' . $m[1] . '.rai.it/' . $m[2] . ($phd ? '1800' : '800') . '.mp4';
				break;
			}
			_logDebug('url found: ' . $url);
			break;
		} elseif ($h[0] == 'HTTP/1.0 200 OK') {
			_logDebug('url found: ' . $url);
			break;
		}
	}

	_logDebug('playing: ' . $url);
	ob_start();
	header('Location: ' . $url);
	ob_flush();
	exit();
}
?>