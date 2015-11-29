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
	_logDebug('main menu');
	$items = array();
	$channels = array("RaiUno", "RaiDue", "RaiTre", "RaiCinque", "RaiPremium", "RaiGulp", "RaiYoyo");
	$logos = array(
		"http://www.rai.tv/dl/replaytv/images/logo_raiuno.png",
		"http://www.rai.tv/dl/replaytv/images/logo_raidue.png",
		"http://www.rai.tv/dl/replaytv/images/logo_raitre.png",
		"http://www.rai.tv/dl/replaytv/images/logo_raicinque.png",
		"http://img.ctrlv.in/img/15/11/23/56530eba364e3.png",
		"http://img.ctrlv.in/img/15/11/23/56530e9a21fb6.png",
		"http://img.ctrlv.in/img/15/11/23/56530ecf81af6.png",
	);

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
			$ids = '';
			if ($phd && preg_match('@relinkerServlet\.htm\?cont=(.+)$@', $v['h264_1800'], $m)) {
				_logDebug('vid_id[1800]: ' . $m[1]);
				$ids = $m[1];
			} elseif (!$phd && preg_match('@relinkerServlet\.htm\?cont=(.+)$@', $v['h264_800'], $m)) {
				_logDebug('vid_id[800]: ' . $m[1]);
				$ids = $m[1];
			} elseif (preg_match('@relinkerServlet\.htm\?cont=(.+)$@', $v['h264'], $m)) {
				_logDebug('vid_id[h264]: ' . $m[1]);
				$ids = $m[1];
			} elseif (preg_match('@relinkerServlet\.htm\?cont=(.+)$@', $v['urlTablet'], $m)) {
				_logDebug('vid_id[urlTablet]: ' . $m[1]);
				$ids = $m[1];
			} else {
				_logDebug('url not found.');
				continue;
			}

			if (preg_match('@relinkerServlet\.htm\?cont=(.+)$@', $v['r'], $m)) {
				if ($m[1] != $ids) {
					_logDebug('vid_id[r]: ' . $m[1]);
					$ids = $ids . '@' . $m[1];
				} else {
					_logDebug('vid[r] not needed.');
				}
			}
			$v['image'] = preg_match('@^http:\/\/@', trim($v['image'])) ? trim($v['image']) : "http://" . trim($v['image']);
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

function createLink($url) {
	_logDebug("creating link from: $url");
	$phd = getConfigValue('PREFER_HD', 1);
	if (preg_match('@replaytv\/(.+?\/\d{4,8})[\/_]@', $url, $m)) {
		for ($i = 1; $i <= 4; $i++) {
			$u = "http://creativemedia$i.rai.it/Italy/podcastmhp/replaytv/" . $m[1] . ($phd ? '_1800' : '_800') . ".mp4";
			$h = get_headers($u);
			_logDebug("test: $u > " . $h[0]);
			if (preg_match('@HTTP\/1\.[01] *200 *OK@', $h[0])) {
				return $u;
			}
		}
	} else {
		_logDebug("***********\r\n UNSUPPORTED LINK: $url\r\n***********");
	}
	return null;
}

function getLink($id) {
	$prefix = 'http://mediapolisvod.rai.it/relinker/relinkerServlet.htm?cont=';
	$url = $prefix . $id;
	$h = get_headers($url, 1);
	_logDebug("test: $url > " . $h[0]);
	if (preg_match('@HTTP\/1\.[01] *200 *OK@', $h[0])) {
		return createLink(file_get_contents($url));
	} elseif (preg_match('@HTTP\/1\.[01] *302@', $h[0])) {
		_logDebug("direct mp4?");
		if (preg_match('@\.mp4$@', $h['Location'])) {
			$u = $h['Location'];
			$h = get_headers($u);
			_logDebug("test: $u > " . $h[0]);
			if (preg_match('@HTTP\/1\.[01] *200 *OK@', $h[0])) {
				return $u;
			}
		} else {
			return createLink($h['Location']);
		}
	}
	return null;
}

if (isset($_GET['video'])) {
	_logDebug("video: " . $_GET['video']);
	$url = '';
	$ids = explode('@', $_GET['video']);

	foreach ($ids as $id) {
		$url = getLink($id);
		if ($url) {
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