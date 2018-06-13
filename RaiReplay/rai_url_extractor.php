function rai_main_menu() {
	_logDebug('main menu 17.09.01');
	$items = array();
	$channels = array("Rai1", "Rai2", "Rai3", "Rai4", "Rai5", "RaiNews24", "RaiMovie", "RaiPremium", "RaiGulp", "RaiYoyo", "RaiStoria", "RaiScuola", "RaiSport1", "RaiSport2");
	$logos = array(
		"http://www.rai.tv/dl/replaytv/images/logo_raiuno.png",
		"http://www.rai.tv/dl/replaytv/images/logo_raidue.png",
		"http://www.rai.tv/dl/replaytv/images/logo_raitre.png",
		"",
		"http://www.rai.tv/dl/replaytv/images/logo_raicinque.png",
		"",
		"",
		"http://img.ctrlv.in/img/15/11/23/56530eba364e3.png",
		"http://img.ctrlv.in/img/15/11/23/56530e9a21fb6.png",
		"http://img.ctrlv.in/img/15/11/23/56530ecf81af6.png",
		"",
		"",
		"",
		"",
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
	if ($id == "RaiNews24") {
		$ff = file_get_contents("http://studio24.blog.rainews.it/");
		if (preg_match_all("@<a class=['\"]rsswidget['\"] href=['\"](http:\/\/studio24.blog.rainews.it/\d.+?)['\"]>(.+?)<\/a>@", $ff, $mm)) {
			_logDebug(print_r($mm, true));
			foreach ($mm[0] as $k => $v) {
				$items[] = createPlayItem(
					build_server_url(array('video_page' => $mm[1][$k])),
					clean_title($mm[2][$k]),
					urlencode($mm[1][$k]),
					null,
					'object.item.videoitem',
					'http-get:*:video/mp4:*');
			}
		}
	} else {
		for ($i = 0; $i <= 7; $i++) {
			$days_ago = date('d-m-Y', mktime(0, 0, 0, date("m"), date("d") - $i, date("Y")));
			list($g, $gg, $m) = explode('-', date('w-d-n', mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"))));
			$items[] = array(
				'id' => build_umsp_url('rai_day', array($id, $days_ago)),
				'dc:title' => $giorni[$g] . " $gg " . $mesi[$m],
				'upnp:class' => 'object.container',
			);
		}
	}
	return $items;
}

function rai_day($ch, $day) {
	_logDebug(">> $ch > $day");
	$items = array();
	$ff = file_get_contents("http://www.raiplay.it/guidatv/index.html?canale=$ch&giorno=$day&new");
	if (preg_match_all("@<li[\w\W]+?<\/li>@", $ff, $lis)) {
		foreach ($lis[0] as $li) {

			if (preg_match("@<li[\w\W]+?data-ora=\"(.+?)\"[\w\W]+?data-img=\"(.+?)\"[\w\W]+?data-href=\"(.+?)\"[\w\W]+?\"info\">(.+?)<\/[\w\W]+?\"descProgram\">(.+?)<\/[\w\W]+?\/i>(.+?)<\/[\w\W]+?\"time\">(.+?)<\/[\w\W]+?<\/li>@", $li, $mm)) {
				/*
					1 ora
					2 img
					3 href
					4 tit 1
					5 desc
					6 tit 2
					7 time
				*/
				$items[] = createPlayItem(
					build_server_url(array('video_page' => $mm[3])),
					clean_title($mm[1] . ' - ' . $mm[4]),
					urlencode($mm[5]),
					$mm[2],
					'object.item.videoitem',
					'http-get:*:video/mp4:*');

			} else {
				_logDebug("skipped.");
			}

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
	} elseif (preg_match('@^(.+?rai\.it)\/(.+?)\/(\d+?)\/(\d+?)\.ism@', $url, $m)) {
		for ($i = 1; $i <= 4; $i++) {
			$tmp = preg_match("@geoprotetto@i", $m[2]) ? "Italy/" . $m[2] : $m[2];
			$u = preg_replace('@\d@', $i, $m[1]) . "/" . preg_replace('@podcastmhp@', 'podcastcdn', $tmp) . "/" . $m[3] . ($phd ? '_1800' : '_800') . ".mp4";
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

function rai_getRelinker($page) {
	$ff = file_get_contents($page);
	if (preg_match("@(data-video-url|videoURL)\s*=\s*\".+?relinkerServlet\.htm\?cont=(.+?)\"@", $ff, $mm)) {
		return $mm[2];
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
			$h = get_headers(str_replace(' ', '%20', $u));
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

if (isset($_GET['video_page'])) {
	$page = $_GET['video_page'];
	if (preg_match("@studio24@", $page)) {
		$ff = file_get_contents($page);
		if (preg_match("@src=\"(.+?\?iframe)\&@", $ff, $mm)) {
			$page = $mm[1];
		}
	}
	_logDebug("video_page: " . $page);
	$page = preg_match("@^\/\/@", $page) ? "http:" . $page : $page;
	$id = rai_getRelinker($page);
	if ($id == null) {
		exit();
	}

	_logDebug("id: " . $id);
	$url = rai_getLink($id);

	_logDebug('playing: ' . $url);
	ob_start();
	header('Location: ' . $url);
	ob_flush();
	exit();
}

?>