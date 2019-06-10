function la7_main_menu() {
	_logDebug('main menu 16.07.21');
	$ff = file_get_contents('http://www.la7.it/rivedila7');
	if (preg_match_all('@<div class="giorno *.{0,7}">\s*<a href="\/rivedila7\/(\d)\/LA7">[\s\S]{1,100}<div class="dateDay">(\d+)<\/div>[\s\S]{1,100}<div class="dateMonth">(.+?)<\/div>[\s\S]{1,100}<div class="dateRowWeek *.{0,7}">(.{5,15})<\/div>@', $ff, $m)) {
		$days_name = array_reverse($m[4]);
		$days_number = array_reverse($m[2]);
		$months = array_reverse($m[3]);
		$ids = array_reverse($m[1]);
		for ($i = 0; $i < count($days_name); $i++) {
			$items[] = array(
				'id' => build_umsp_url('la7_day', array($ids[$i])),
				'dc:title' => $days_name[$i] . ' ' . $days_number[$i] . ' ' . $months[$i],
				'upnp:class' => 'object.container',
			);
		}
	}
	return $items;
}

function clean_title($tit) {
	return preg_replace("@&#039;@i", "'", $tit);
}

function la7_day($id) {
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

function la7_video($page) {
	_logDebug("page: " . $page);
	$ff = file_get_contents($page);
	if (preg_match('@src *: *".+?content/entry(.+)/master.m3u8" *,@', $ff, $m) || preg_match('@"m3u8" *: *".+content/entry(.+?)\,\.mp4\.csmil/master\.m3u8"@', $ff, $m)) {
		$dl = "http://vodpmd.la7.it.edgesuite.net/content/entry$m[1].mp4";
		_logDebug("playing m3u8: $dl");
		ob_start();
		header('Content-type: video/mp4');
		header('Location: ' . $dl);
		ob_flush();
	} elseif (preg_match('@src_mp4 *: *"(.+)" *,@', $ff, $m) || preg_match('@"mp4" *: *"(.+?)"@', $ff, $m)) {
		_logDebug("playing mp4: " . $m[1]);
		ob_start();
		header('Content-type: video/mp4');
		header('Location: ' . $m[1]);
		ob_flush();
	} else {
		_logDebug("Video not found.");
	}
	exit();
}

?>