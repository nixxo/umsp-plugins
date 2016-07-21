<?php
include_once '/usr/share/umsp/funcs-log.php';
global $logLevel;
global $logIdent;
$logLevel = L_ALL;
$logIdent = 'RaiReplayPlugIn';

$f = file_get_contents("http://nixxo.altervista.org/umsp-plugins/rai_url_extractor.php");
file_put_contents("/tmp/RaiReplay-temp.php", "<?php $f");
include_once '/tmp/RaiReplay-temp.php';

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
	return rai_main_menu();
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
	_logDebug("video: " . $_GET['video']);
	$url = '';
	$ids = explode('@', $_GET['video']);

	foreach ($ids as $id) {
		$url = rai_getLink($id);
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