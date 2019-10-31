<?php

function rai_main_menu()
{
    _logDebug('main menu 19.10.31');
    $items = array();
    $channels = array("Rai1", "Rai2", "Rai3", "Rai4", "Rai5", "RaiNews24", "RaiMovie", "RaiPremium", "RaiGulp", "RaiYoyo", "RaiSport");
    $logos = array(
        "https://upload.wikimedia.org/wikipedia/commons/f/fa/Rai_1_-_Logo_2016.svg", //1
        "https://upload.wikimedia.org/wikipedia/commons/9/99/Rai_2_-_Logo_2016.svg", //2
        "https://upload.wikimedia.org/wikipedia/commons/4/47/Rai_3_-_Logo_2016.svg", //3
        "https://upload.wikimedia.org/wikipedia/commons/4/4d/Rai_4_-_Logo_2016.svg", //4
        "https://upload.wikimedia.org/wikipedia/commons/f/f8/Rai_5_-_Logo_2017.svg", //5
        "https://upload.wikimedia.org/wikipedia/commons/9/9c/Rai_News_24_-_Logo_2013.svg", //news
        "https://upload.wikimedia.org/wikipedia/commons/6/61/Rai_Movie_-_Logo_2017.svg", //movie
        "https://upload.wikimedia.org/wikipedia/commons/a/ad/Rai_Premium_-_Logo_2017.svg", //premium
        "https://upload.wikimedia.org/wikipedia/commons/c/c1/Rai_Gulp_-_Logo_2017.svg", //gulp
        "https://upload.wikimedia.org/wikipedia/commons/0/01/Rai_Yoyo_-_Logo_2017.svg", //yoyo
        "https://upload.wikimedia.org/wikipedia/commons/a/ae/Rai_Sport_-_Logo_2017.svg", //sport
    );

    for ($i = 0; $i < count($channels); $i++) {
        $items[] = array(
            'id' => build_umsp_url('rai_channel', array($channels[$i])),
            'dc:title' => $channels[$i],
            'upnp:album_art' => $logos[$i],
            'upnp:class' => 'object.container',
        );
    }

    $sections = array(
        "Programmi",
        "Fiction",
        "Film",
        "Teatro",
        "Documentari",
        "Musica",
        "Bambini e ragazzi",
    );

    $section_urls = array();

    for ($i = 0; $i < count($sections); $i++) {
        $section_urls[$i] = "https://www.raiplay.it/" . strtolower($sections[$i]) . "/?json";
    }

    $sections_logos = array(
        "https://www.raiplay.it/dl/doc/1528106939639_ico-programmi.svg",
        "https://www.raiplay.it/dl/doc/1528106983569_ico-fiction.svg",
        "https://www.raiplay.it/dl/doc/1528107032466_ico-film.svg",
        "https://www.raiplay.it/dl/doc/1528115315609_ico-teatro.svg",
        "https://www.raiplay.it/dl/doc/1528107054296_ico-documentari.svg",
        "https://www.raiplay.it/dl/doc/1528107079722_ico-musica.svg",
        "https://www.raiplay.it/dl/doc/1528806533924_ico-kids.svg",
    );

    for ($i = 0; $i < count($sections); $i++) {
        $items[] = array(
            'id' => build_umsp_url('rai_section', array($section_urls[$i])),
            'dc:title' => $sections[$i],
            'upnp:album_art' => $sections_logos[$i],
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

function clean_title($tit)
{
    return preg_replace("@&#039;@i", "'", $tit);
}

function rai_channel($id)
{
    _logDebug("channel $id");

    $mesi = array(1 => 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre');
    $giorni = array('Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato');

    $items = array();
    if ($id == "RaiNews24") {
        $ff = file_get_contents("https://studio24.blog.rainews.it/");
        if (preg_match_all("@<a class=['\"]rsswidget['\"] href=['\"](http:\/\/studio24.blog.rainews.it/\d.+?)['\"]>(.+?)<\/a>@", $ff, $mm)) {
            _logDebug(print_r($mm, true));
            foreach ($mm[0] as $k => $v) {
                $items[] = createPlayItem(
                    build_server_url(array('video_page' => $mm[1][$k])),
                    clean_title($mm[2][$k]),
                    urlencode($mm[1][$k]),
                    null,
                    'object.item.videoitem',
                    'http-get:*:video/mp4:*'
                );
            }
        }
    } elseif ($id == "RaiNews24new") {
        $ff = file_get_contents("https://studio24.blog.rainews.it/rss");
        preg_match_all("@src=\"(.+?\?iframe)\&@", $ff, $mm);
        preg_match_all("@<item>\s*<title>(.+?)</title>@", $ff, $mt);
        if ($mm && $mt) {
            _logDebug(print_r($mm, true));
            _logDebug(print_r($mt, true));
            foreach ($mm[0] as $k => $v) {
                $items[] = createPlayItem(
                    build_server_url(array('video_page' => $mm[1][$k])),
                    clean_title($mt[1][$k]),
                    urlencode($mm[1][$k]),
                    null,
                    'object.item.videoitem',
                    'http-get:*:video/mp4:*'
                );
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

function rai_section($id)
{
    _logDebug("section $id");
    $items = array();

    $itm_type = array(
        "Rai Lancio Item" => "rai_section",
        "PLR programma configuratore palinsesto Item" => "rai_program",
    );

    $f = file_get_contents($id);
    $j = json_decode($f);
    $j = $j->{'blocchi'}[0]->{'lanci'};

    for ($i = 0; $i < count($j); $i++) {
        $tmp = preg_replace("@^/(raiplay|dl)/@", "https://www.raiplay.it/$1/", $j[$i]->{'PathID'});
        $items[] = array(
            'id' => build_umsp_url($itm_type[$j[$i]->{'original-type'}], array($tmp)),
            //'id' => build_umsp_url('rai_section', array($tmp)),
            'dc:title' => $j[$i]->{'name'},
            'upnp:album_art' => str_replace("[RESOLUTION]", "480x480", $j[$i]->{'images'}->{'landscape'}),
            'upnp:class' => 'object.container',
        );
    }

    return $items;
    exit;
}

function rai_sub_section($id)
{
    _logDebug("sub section $id");
    $items = array();

    //old: implementation based on the web page [DEPRECATED]
    $f = file_get_contents($id);

    preg_match_all("@<div class=\"titolo\">\s*(.+?)\s*<\/div>@", $f, $tit);
    preg_match_all("@<div\s*class=\"useraction\"\s*data-path=\"(.+?)\"@", $f, $ul);
    preg_match_all("@\[([^\s]+?)\, medium\]@", $f, $art);

    $tit = $tit[1];
    $ul = preg_replace("@^/(raiplay|programmi)@", "https://www.raiplay.it/$1", $ul[1]);
    $art = preg_replace("@^//@", "https://", $art[1]);

    for ($i = 0; $i < count($tit); $i++) {
        $items[] = array(
            'id' => build_umsp_url('rai_program', array($ul[$i])),
            'dc:title' => $tit[$i],
            'upnp:album_art' => $art[$i],
            'upnp:class' => 'object.container',
        );
    }

    return $items;
}

function rai_program($id)
{
    $id = preg_match("@^\/r@", $id) ? "http://www.raiplay.it" . $id : $id;
    _logDebug("program $id");
    $items = array();

    $f = file_get_contents($id);
    //_logDebug(print_r($f, true));

    $j = json_decode($f, true);
    if (isset($j['Blocks'])) {
        _logDebug("BLOCKS");
        //_logDebug(print_r($j['Blocks'], true));
        foreach ($j['Blocks'] as $bb) {
            foreach ($bb['Sets'] as $b) {
                $tt = $bb['Name'] == $b['Name'] ? $bb['Name'] : $bb['Name'] . ' - ' . $b['Name'];
                $tt = strpos($tt, $j['Name']) ? $tt : $j['Name'] . " " . $tt;
                $items[] = array(
                    'id' => build_umsp_url('rai_program', array($b['url'])),
                    'dc:title' => $tt,
                    'upnp:album_art' => '',
                    'upnp:class' => 'object.container',
                );
            }
        }
        if (count($items) == 1) {
            _logDebug("auto enter");
            return rai_program($j['Blocks'][0]['Sets'][0]['url']);
        }
    } elseif (isset($j['items'])) {
        _logDebug("ITEMS");
        //_logDebug(print_r($j['items'][0], true));
        foreach ($j['items'] as $b) {
            if ($b['type'] == 'RaiTv Media Video Item') {
                $tt = isset($b['titoloEpisodio']) ? $b['titoloEpisodio'] : $b['name'];
                $tt = $tt ? $tt : $b['name'];
                $items[] = createPlayItem(
                    build_server_url(array('video_page' => $b['pathID'])),
                    //$b['titoloEpisodio'] ? $b['titoloEpisodio'] : $b['nomeProgramma'],
                    $tt,
                    $b['subtitle'],
                    str_replace("[RESOLUTION]", "480x480", $b['images']['landscape']),
                    'object.item.videoitem',
                    'http-get:*:video/mp4:*'
                );
            }
        }
    } else {
        $items[] = array(
            'id' => build_umsp_url('rai_program', array()),
            'dc:title' => '- ERRORE -',
            'upnp:album_art' => '',
            'upnp:class' => 'object.container',
        );
    }

    return $items;
    exit;
}

function rai_day($ch, $day)
{
    $ch = strtolower($ch);
    $ch = str_replace("rai", "rai-", $ch);

    _logDebug(">> $ch > $day");
    $items = array();
    //old $ff = file_get_contents("https://www.raiplay.it/guidatv/index.html?canale=$ch&giorno=$day&new");

    $ff = file_get_contents("https://www.raiplay.it/palinsesto/guidatv/lista/$ch/$day.html");
    if (preg_match_all("@<li[\w\W]+?<\/li>@", $ff, $lis)) {
        foreach ($lis[0] as $li) {
            if (preg_match("/data-ora=\"(.+?)\"/", $li, $ora)) {
                if (preg_match("/data-img=\"(.+?)\"/", $li, $img)) {
                    if (preg_match("/data-href=\"(.+?)\"/", $li, $href)) {
                        if (preg_match("/\"info\">(.+?)<\/[\w\W]+?\"descProgram\">([\s\S]+?)<\/[\w\W]+?\/i>(.+?)<\/[\w\W]+?\"time\">(.+?)<\/[\w\W]+?<\/li>/", $li, $mm)) {
                            /*
                                4 tit 1
                                5 desc
                                6 tit 2
                                7 time
                            */

                            $img = preg_match("/^http/", $img[1])?$img[1]:"http://www.rai.it".$img[1];
                            $href = preg_match("/^http/", $href[1])?$href[1]:"http://www.raiplay.it".$href[1];
                            $items[] = createPlayItem(
                                build_server_url(array('video_page' => $href)),
                                clean_title($ora[1] . ' - ' . $mm[1]),
                                urlencode(trim($mm[2])),
                                $img,
                                'object.item.videoitem',
                                'http-get:*:video/mp4:*'
                            );
                        } else {
                            _logDebug("skipped.");
                        }
                    }
                }
            }
        }
    } else {
        _logError(__FUNCTION__." day reg-ex not found.");
    }

    return $items;
}

function rai_config($key = null, $value = null)
{
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

function rai_createLink($url)
{
    _logDebug("creating link from: $url");
    $phd = getConfigValue('PREFER_HD', 1);
    if (preg_match('@relinkerServlet\.htm@', $url)) {
        $sc = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => 'User-Agent: aria2',
            ),
        ));
        $f = file_get_contents($url, false, $sc, 0, 0);
        foreach ($http_response_header as $v) {
            if (preg_match("@Location:\s*(.+?)$@", $v, $m)) {
                $f = $m[1];
            }
        }
        preg_match("@akamaihd\.net/i/(.+?)\/(\d{5,7})_([\d,]+)\.mp4@", $f, $m);
        for ($i = 1; $i <= 4; $i++) {
            $u = "http://creativemedia$i.rai.it/" . $m[1] . "/" . $m[2] . ($phd ? '_1800' : '_800') . ".mp4";
            $h = get_headers($u);
            _logDebug("test: $u > " . $h[0]);
            if (preg_match('@HTTP\/1\.[01] *200 *OK@', $h[0])) {
                return $u;
            }
        }
        exit;
    //DEPERECATED
    } elseif (preg_match('@replaytv\/(.+?\/\d{4,8})[\/_]@', $url, $m)) {
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

function rai_getRelinker($page)
{
    //try json first
    $u = str_replace("html?json", "html", $page);
    $u = str_replace("html", "html?json", $u);

    $ff = file_get_contents($u);
    $jj = json_decode($ff, true);

    if (isset($jj['video']['contentUrl'])) {
        if (preg_match("@relinkerServlet\.htm\?cont=(.+?)$@", $jj['video']['contentUrl'], $mm)) {
            return $mm[1];
        }
    }

    //get from webpage if json fails
    $ff = file_get_contents($page);
    if (preg_match("@(data-video-url|videoURL)\s*=\s*\".+?relinkerServlet\.htm\?cont=(.+?)\"@", $ff, $mm)) {
        return $mm[2];
    }
    return null;
}

function rai_getLink($id)
{
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
            preg_match("@_(\d{3,4})\.mp4@", $u, $m);
            if (isset($m[1])) {
                _logDebug("replacing $m[1] with 1800");
                $u = str_replace("_$m[1].", "_1800.", $u);
            }

            $h = get_headers(str_replace(' ', '%20', $u));
            _logDebug("test: $u > " . $h[0]);
            if (preg_match('@HTTP\/1\.[01] *200 *OK@', $h[0])) {
                return $u;
            } elseif (preg_match('@HTTP\/1\.0 504 Gateway Time-out@', $h[0])) {
                return rai_createLink($u);
            }
        } else {
            //return rai_createLink($url);
            return rai_createLink($h['Location']);
        }
    }
    return null;
}

if (isset($_GET['video_page'])) {
    $page = $_GET['video_page'];
    if (preg_match("@studio24|labussola@", $page)) {
        $ff = file_get_contents($page);
        if (preg_match("@src=\"(.+?\?iframe)\&@", $ff, $mm)) {
            $page = $mm[1];
        }
    }
    $id = null;
    if (!preg_match("@relinkerServlet\.htm\?cont\=(.+?Equal)$@", $page, $id)) {
        $page = preg_match("@^\/\/@", $page) ? "https:" . $page : $page;
        $page = preg_match("@^\/@", $page) ? "https://www.raiplay.it" . $page : $page;
        _logDebug("video_page: " . $page);
        $id = rai_getRelinker($page);
        if ($id == null) {
            exit();
        }
    } else {
        $id = $id[1];
    }

    _logDebug("id: " . $id);
    $url = rai_getLink($id);

    _logDebug('playing: ' . $url);
    ob_start();
    header('Location: ' . $url);
    ob_flush();
    exit();
}
