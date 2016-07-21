function rai_main_menu() {
	_logDebug('main menu 16.07.21');
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
			'id' => build_umsp_url('rai_channel', array($channels[$i])),
			'dc:title' => $channels[$i],
			'upnp:album_art' => $logos[$i],
			'upnp:class' => 'object.container',
		);
	}
	$items[] = array(
		'id' => build_umsp_url('rai_config'),
		'dc:title' => 'Configura Plugin',
		'upnp:album_art' => 'http://lh5.googleusercontent.com/-xsH3IJAYXd0/TvwfRdc7DMI/AAAAAAAAAFk/NmvkjuqP_eo/s220/Settings.png',
		'upnp:class' => 'object.container',
	);
	return $items;
}

function clean_title($tit) {
	return preg_replace("@&#039;@i", "'", $tit);
}

function rai_channel($id) {
	_logDebug("channel $id");

	$mesi = array(1 => 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre');
	$giorni = array('Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato');

	$items = array();
	for ($i = 1; $i <= 7; $i++) {
		$days_ago = date('Y_m_d', mktime(0, 0, 0, date("m"), date("d") - $i, date("Y")));
		list($g, $gg, $m) = explode('-', date('w-d-n', mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"))));
		$items[] = array(
			'id' => build_umsp_url('rai_day', array($id . "_" . $days_ago)),
			'dc:title' => $giorni[$g] . " $gg " . $mesi[$m],
			'upnp:class' => 'object.container',
		);
	}
	return $items;
}

function rai_day($id) {
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

function rai_config($key = null, $value = null) {
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

function rai_createLink($url) {
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
	} elseif (preg_match('@replaytv_world\/(.+?\/\d{4,8})[\/_]@', $url, $m)) {
		for ($i = 1; $i <= 4; $i++) {
			$u = "http://creativemedia$i.rai.it/podcastmhp/replaytv_world/" . $m[1] . ($phd ? '_1800' : '_800') . ".mp4";
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

function rai_getLink($id) {
	$prefix = 'http://mediapolisvod.rai.it/relinker/relinkerServlet.htm?cont=';
	$url = $prefix . $id;
	$h = get_headers($url, 1);
	_logDebug("test: $url > " . $h[0]);
	if (preg_match('@HTTP\/1\.[01] *200 *OK@', $h[0])) {
		return rai_createLink(file_get_contents($url));
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
			return rai_createLink($h['Location']);
		}
	}
	return null;
}

?>