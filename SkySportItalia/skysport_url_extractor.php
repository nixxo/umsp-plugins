global $sky_conf;
$sky_conf = array(
	'CONF' => 'http://video.sky.it/etc/designs/skyvideoportale/library/static/js/config/config.json',
	'GET_VIDEO_SEARCH' => 'http://video.sky.it/be/getVideoDataSearch?token={token}&section=sport&subsection={subsection}&start=0&count=31',
	'GET_PLAYLISTS' => 'http://video.sky.it/be/getPlaylistInfo?token={token}&section=sport&subsection={subsection}&start=0&limit=31',
	'GET_PLAYLIST_VIDEO' => 'http://video.sky.it/be/getPlaylistVideoData?token={token}&id={id}',
	'GET_VIDEO_DATA' => 'http://video.sky.it/be/getVideoData?token={token}&id={id}&rendition=web',
	'GET_VOD_ACCESS_TOKEN' => 'http://video.sky.it/SkyItVideoportalUtility/getVODAccessToken.do?token={token}&url={url}&dec=0',
	'TOKEN_SKY' => 'F96WlOd8yoFmLQgiqv6fNQRvHZcsWk5jDaYnDvhbiJk',
);

function skysport_main_menu() {
	$ff = file_get_contents('http://video.sky.it/sport');
	if (preg_match_all("@<a\s*href=\"/sport/([\w-]+)\?.*?\">\s+((<div.+?)|.{0})\s+<span\s*class=\".*?\">\s*([\w\s-]+?)\s*</span>@", $ff, $mm)) {
		$items = array();
		_logDebug(print_r($mm[1], true));
		$mm[1] = array_unique($mm[1]);
		foreach ($mm[1] as $k => $v) {
			$items[] = array(
				'id' => build_umsp_url('skysport_subsection', array($v, $mm[4][$k])),
				'dc:title' => $mm[4][$k],
				'upnp:class' => 'object.container',
			);
		}
		return $items;
	} else {
		_logError('Error retrieving Subsections');
		return array(
			'id' => build_umsp_url('skysport_error', array('')),
			'dc:title' => 'Error retrieving Subsections',
			'upnp:class' => 'object.container',
		);
	}
}

function skysport_subsection($ss, $tt) {
	_logInfo(">>> subsection: $ss <<<");
	global $sky_conf;
	//$pl_url = 'http://video.sky.it/be/getVideoDataSearch?token={token}&section=sport&subsection={subsection}&count=63&page=0';
	$pl_url = $sky_conf['GET_VIDEO_SEARCH'];
	$pl_url = preg_replace("@\{token\}@", $sky_conf['TOKEN_SKY'], $pl_url);
	$pl_url = preg_replace("@\{subsection\}@", $ss, $pl_url);

	_logDebug('url: ' . $pl_url);
	$ff = json_decode(file_get_contents($pl_url), true);
	if ($ff == null) {
		_logError('JSON DECODE ERROR IN: ' . __FUNCTION__);
		return null;
	}
	_logDebug(print_r($ff, true));
	$items[] = array(
		'id' => build_umsp_url('skysport_playlist', array($ss)),
		'dc:title' => 'Playlist di ' . $tt,
		'upnp:class' => 'object.container',
	);
	return array_merge($items, skysport_parse_playlist($ff));
}

function skysport_playlist($ss) {
	_logInfo(">>> $ss playlist <<<");
	global $sky_conf;
	$pl_url = $sky_conf['GET_PLAYLISTS'];
	$pl_url = preg_replace("@\{token\}@", $sky_conf['TOKEN_SKY'], $pl_url);
	$pl_url = preg_replace("@\{subsection\}@", $ss, $pl_url);

	_logDebug('url: ' . $pl_url);
	$ff = json_decode(file_get_contents($pl_url), true);
	if ($ff == null) {
		_logError('JSON DECODE ERROR IN: ' . __FUNCTION__);
		return null;
	}
	_logDebug(print_r($ff, true));

	$items = array();
	foreach ($ff as $v) {
		//2016-06-03T modify_date
		if (!preg_match("@(\d+)\D(\d+)\D(\d+)T@", $v['modify_date'], $date)) {
			preg_match("@(\d+)\D(\d+)\D(\d+)T@", $v['create_date'], $date);
		}

		//_logDebug(print_r($date, true));
		$items[] = array(
			'id' => build_umsp_url('skysport_playlist_content', array($v['playlist_id'])),
			'dc:title' => $date[3] . '/' . $date[2] . '/' . $date[1] . ' - ' . $v['title'],
			'desc' => $v['short_desc'],
			'upnp:album_art' => $v['thumb'],
			'upnp:class' => 'object.container',
		);
	}
	return $items;
}

function skysport_playlist_content($pl_id) {
	_logInfo(">>> playlist id: $pl_id<<<");
	global $sky_conf;
	$pl_url = $sky_conf['GET_PLAYLIST_VIDEO'];
	$pl_url = preg_replace("@\{token\}@", $sky_conf['TOKEN_SKY'], $pl_url);
	$pl_url = preg_replace("@\{id\}@", $pl_id, $pl_url);

	_logDebug('url: ' . $pl_url);
	$ff = json_decode(file_get_contents($pl_url), true);
	if ($ff == null) {
		_logError('JSON DECODE ERROR IN: ' . __FUNCTION__);
		return null;
	}
	_logDebug(print_r($ff, true));
	return skysport_parse_playlist($ff);
}

function skysport_parse_playlist($pl) {
	$items = array();
	foreach ($pl['assets'] as $v) {
		if (!preg_match("@^\d+/\d+@", $v['modify_date'], $date)) {
			preg_match("@^\d+/\d+@", $v['create_date'], $date);
		}

		//_logDebug(print_r($date, true));
		$items[] = createPlayItem(
			build_server_url(array('asset_id' => $v['asset_id'])),
			$date[0] . ' - ' . $v['title'],
			$v['short_desc'],
			$v['video_still'],
			'object.item.videoitem',
			'http-get:*:video/mp4:*');
	}
	return $items;
}

function skysport_get_video($asset_id) {
	_logInfo('>>> get video data with id:' . $asset_id . ' <<<');
	global $sky_conf;
	$pl_url = $sky_conf['GET_VIDEO_DATA'];
	$pl_url = preg_replace("@\{token\}@", $sky_conf['TOKEN_SKY'], $pl_url);
	$pl_url = preg_replace("@\{id\}@", $asset_id, $pl_url);
	_logDebug('url: ' . $pl_url);
	$ff = json_decode(file_get_contents($pl_url), true);
	if ($ff == null) {
		_logError('JSON DECODE ERROR IN: ' . __FUNCTION__);
		return null;
	}
	_logDebug(print_r($ff, true));

	if (isset($ff['token'])) {
		_logInfo('>>> get video url with token <<<');
		$pl_url = $sky_conf['GET_VOD_ACCESS_TOKEN'];
		$pl_url = preg_replace("@\{token\}@", $ff['token'], $pl_url);
		$pl_url = preg_replace("@\{url\}@", $ff['web_high_url'], $pl_url);
		_logDebug('url: ' . $pl_url);
		$ff = json_decode(file_get_contents($pl_url), true);
		if ($ff == null) {
			_logError('JSON DECODE ERROR IN: ' . __FUNCTION__);
			return null;
		}
		_logDebug(print_r($ff, true));
		return $ff['url'];
	}
	return $ff['web_high_url'];

}

if (isset($_GET['asset_id'])) {
	$url = skysport_get_video($_GET['asset_id']);
	_logInfo('playing: ' . $url);
	ob_start();
	$url = str_replace("https:", "http:", $url);
	header('Location: ' . $url);
	ob_flush();
	exit();
}
?>