

global $sky_conf;
$sky_conf = array(
	'CONF' => 'http://video.sky.it/etc/designs/skyvideoportale/library/static/js/config/config.json',
	'GET_VIDEO_SEARCH' => 'http://video.sky.it/be/getVideoDataSearch?token={token}&section={section}&subsection={subsection}&page={page}&count=30',
	'GET_PLAYLISTS' => 'http://video.sky.it/be/getPlaylistInfo?token={token}&section={section}&subsection={subsection}&start=0&limit=31',
	'GET_PLAYLIST_VIDEO' => 'http://video.sky.it/be/getPlaylistVideoData?token={token}&id={id}',
	'GET_VIDEO_DATA' => 'http://video.sky.it/be/getVideoData?token={token}&id={id}&rendition=web',
	'GET_VOD_ACCESS_TOKEN' => 'http://video.sky.it/SkyItVideoportalUtility/getVODAccessToken.do?token={token}&url={url}&dec=0',
	'TOKEN_SKY' => 'F96WlOd8yoFmLQgiqv6fNQRvHZcsWk5jDaYnDvhbiJk',
);

function sky_main_menu() {
	_logInfo("main menu sky");
	$ff = file_get_contents('http://video.sky.it/');
	if (preg_match_all("@<div class=\"mainvoice\"><a id=\"(\w+)\">(.+?)</a>@", $ff, $mm)) {
		$items = array();
		_logDebug(print_r($mm[1], true));
		$mm[1] = array_unique($mm[1]);
		foreach ($mm[1] as $k => $v) {
			$items[] = array(
				'id' => build_umsp_url('sky_menu', array($mm[1][$k])),
				'dc:title' => $mm[2][$k],
				'upnp:class' => 'object.container',
			);
		}
		return $items;
	} else {
		_logError('Error retrieving Subsections');
		return array(
			'id' => build_umsp_url('sky_error', array('')),
			'dc:title' => 'Error retrieving Subsections',
			'upnp:class' => 'object.container',
		);
	}
}

function sky_menu($id) {
	_logDebug("http://video.sky.it/$id");
	$ff = file_get_contents("http://video.sky.it/$id");
	if (preg_match_all("@<a\s*href=\"https*://video\.sky\.it/$id/([\w-]+)\?.*?\">\s+((<div.+?)|.{0})\s+<span\s*class=\".*?\">\s*([\w\s-&#;è]+?)\s*</span>@", $ff, $mm)) {
		$items = array();
		_logDebug(print_r($mm[1], true));
		$mm[1] = array_unique($mm[1]);
		foreach ($mm[1] as $k => $v) {
			$items[] = array(
				'id' => build_umsp_url('sky_subsection', array($id, $v, $mm[4][$k])),
				'dc:title' => $mm[4][$k],
				'upnp:class' => 'object.container',
			);
		}
		return $items;
	} else {
		_logError('Error retrieving Subsections');
		return array(
			'id' => build_umsp_url('sky_error', array('')),
			'dc:title' => 'Error retrieving Subsections',
			'upnp:class' => 'object.container',
		);
	}
}

function sky_subsection($s, $ss, $tt, $page = 0) {
	_logInfo(">>> subsection: $s - $ss <<<");
	global $sky_conf;
	$pl_url = $sky_conf['GET_VIDEO_SEARCH'];
	$pl_url = preg_replace("@\{token\}@", $sky_conf['TOKEN_SKY'], $pl_url);
	$pl_url = preg_replace("@\{section\}@", $s, $pl_url);
	$pl_url = preg_replace("@\{subsection\}@", $ss, $pl_url);
	$pl_url = preg_replace("@\{page\}@", $page, $pl_url);

	_logDebug('url: ' . $pl_url);
	$ff = json_decode(file_get_contents($pl_url), true);
	if ($ff == null) {
		_logError('JSON DECODE ERROR IN: ' . __FUNCTION__);
		return null;
	}
	_logDebug(print_r($ff, true));
	if ($page == 0) {
		$items[] = array(
			'id' => build_umsp_url('sky_playlist', array($s, $ss)),
			'dc:title' => 'Playlist di ' . $tt,
			'upnp:class' => 'object.container',
		);
	} else {
		$items[] = array(
			'id' => build_umsp_url('sky_subsection', array($s, $ss, $tt, $page - 1)),
			'dc:title' => 'Pagina ' . $page,
			'upnp:class' => 'object.container',
		);
	}
	$items = array_merge($items, sky_parse_playlist($ff));
	$items[] = array(
		'id' => build_umsp_url('sky_subsection', array($s, $ss, $tt, $page + 1)),
		'dc:title' => 'Pagina ' . ($page + 2),
		'upnp:class' => 'object.container',
	);
	return $items;
}

function sky_playlist($s, $ss) {
	_logInfo(">>> $s - $ss playlist <<<");
	global $sky_conf;
	$pl_url = $sky_conf['GET_PLAYLISTS'];
	$pl_url = preg_replace("@\{token\}@", $sky_conf['TOKEN_SKY'], $pl_url);
	$pl_url = preg_replace("@\{section\}@", $s, $pl_url);
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
			'id' => build_umsp_url('sky_playlist_content', array($v['playlist_id'])),
			'dc:title' => $date[3] . '/' . $date[2] . '/' . $date[1] . ' - ' . $v['title'],
			'desc' => $v['short_desc'],
			'upnp:album_art' => $v['thumb'],
			'upnp:class' => 'object.container',
		);
	}
	return $items;
}

function sky_playlist_content($pl_id) {
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
	return sky_parse_playlist($ff);
}

function sky_parse_playlist($pl) {
	$items = array();
	foreach ($pl['assets'] as $v) {
		if (!preg_match("@^\d+/\d+@", $v['modify_date'], $date)) {
			preg_match("@^\d+/\d+@", $v['create_date'], $date);
		}

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

function sky_get_video($asset_id) {
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
	$url = sky_get_video($_GET['asset_id']);
	_logInfo('playing: ' . $url);
	ob_start();
	$url = str_replace("https:", "http:", $url);
	header('Location: ' . $url);
	ob_flush();
	exit();
}
?>