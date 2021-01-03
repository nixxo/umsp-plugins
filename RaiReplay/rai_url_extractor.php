<?php
define("HOST", "https://www.raiplay.it");

global $sections;
$sections = array(
    "Film"            => HOST . "/dl/doc/1528107032466_ico-film.svg",
    "Fiction"         => HOST . "/dl/doc/1528106983569_ico-fiction.svg",
    "Serie TV"        => "",
    "Documentari"     => HOST . "/dl/doc/1528107054296_ico-documentari.svg",
    "Bambini"         => HOST . "/dl/doc/1528806533924_ico-kids.svg",
    "Teen"            => "",
    "Learning"        => "",
    "Programmi"       => HOST . "/dl/doc/1528106939639_ico-programmi.svg",
    "Sport"           => "",
    "Teche Rai"       => "",
    "Musica e Teatro" => "",
);

function rai_main_menu()
{
    global $sections;
    _logDebug('main menu 20.03.04');
    $items    = array();
    $channels = array( "Rai1", "Rai2", "Rai3", "Rai4", "Rai5", "RaiNews24", "RaiMovie", "RaiPremium", "RaiGulp", "RaiYoyo" );
    $logos    = array(
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
    );

    for ($i = 0; $i < count($channels); $i++) {
        $items[] = array(
            'id'             => build_umsp_url('rai_channel', array( $channels[$i] )),
            'dc:title'       => $channels[$i],
            'upnp:album_art' => $logos[$i],
            'upnp:class'     => 'object.container',
        );
    }

    foreach ($sections as $title => $logo) {
        $items[] = array(
            'id'             => build_umsp_url('rai_play', array( to_url($title) )),
            'dc:title'       => $title,
            'upnp:album_art' => $logo,
            'upnp:class'     => 'object.container',
        );
    }

    $items[] = array(
        'id'             => build_umsp_url('rai_config'),
        'dc:title'       => 'Configura Plugin',
        'upnp:album_art' => 'http://lh5.googleusercontent.com/-xsH3IJAYXd0/TvwfRdc7DMI/AAAAAAAAAFk/NmvkjuqP_eo/s220/Settings.png',
        'upnp:class'     => 'object.container',
    );
    return $items;
}

function clean_title($tit)
{
    return str_replace("&#039;", "'", $tit);
}

function rai_channel($id)
{
    _logDebug("channel $id");

    $mesi   = array(
        1 => 'Gennaio',
        'Febbraio',
        'Marzo',
        'Aprile',
        'Maggio',
        'Giugno',
        'Luglio',
        'Agosto',
        'Settembre',
        'Ottobre',
        'Novembre',
        'Dicembre',
    );
    $giorni = array( 'Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato' );

    $items = array();
    if ($id == "RaiNews24old") {
        $ff = file_get_contents("https://studio24.blog.rainews.it/");
        if (preg_match_all("/<a class=['\"]rsswidget['\"] href=['\"](http:\/\/studio24\.blog\.rainews\.it\/\d.+?)['\"]>(.+?)<\/a>/", $ff, $mm)) {
            //_logDebug(print_r($mm, true));
            foreach ($mm[0] as $k => $v) {
                $items[] = createPlayItem(
                    build_server_url(array( 'video_page' => $mm[1][$k] )),
                    clean_title($mm[2][$k]),
                    urlencode($mm[1][$k]),
                    null,
                    'object.item.videoitem',
                    'http-get:*:video/mp4:*'
                );
            }
        }
    } elseif ($id == "RaiNews24") {
        $ff = file_get_contents("https://studio24.blog.rainews.it/rss");
        preg_match_all("/src=\"(.+?\?iframe)\&/", $ff, $mm);
        preg_match_all("/<item>\s*<title>(.+?)<\/title>/", $ff, $mt);
        if ($mm && $mt) {
            //_logDebug(print_r($mm, true));
            //_logDebug(print_r($mt, true));
            foreach ($mm[0] as $k => $v) {
                $items[] = createPlayItem(
                    build_server_url(array( 'video_page' => $mm[1][$k] )),
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
            $days_ago         = date('d-m-Y', mktime(0, 0, 0, date("m"), date("d") - $i, date("Y")));
            list($g, $gg, $m) = explode('-', date('w-d-n', mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"))));
            $items[]          = array(
                'id'         => build_umsp_url('rai_day', array( $id, $days_ago )),
                'dc:title'   => $giorni[$g] . " $gg " . $mesi[$m],
                'upnp:class' => 'object.container',
            );
        }
    }
    return $items;
}

function to_url($id)
{
    $id = strtolower($id);
    $id = str_replace(' e ', '-e-', $id);
    $id = str_replace(' ', '', $id);
    return HOST . "/$id/index.json";
}

function rai_play($url, $level = null)
{
    _logDebug($url);
    $f = file_get_contents($url);
    $j = json_decode($f, true);

    $generale = $programma = $episodi = false;
    //generale
    if ($generale = isset($j['contents'])) {
        $j = $level === null ? $j['contents'] : $j['contents'][$level];
        $j = isset($j['contents']) ? $j['contents'] : $j;
    //programma
    } elseif ($programma = isset($j['blocks'])) {
        $j = $level === null ? $j['blocks'] : $j['blocks'][$level]['sets'];
    //episodi
    } elseif ($episodi = isset($j['items'])) {
        $j = $j['items'];
    }
    foreach ($j as $k => $elm) {
        $video = $elm["type"] == 'RaiPlay Video Item' ? true : false;


        if ($generale && !$video) {
            //skip if element is empty
            if (count($elm) == 0) {
                continue;
            }

            if ($level === null) {
                $ua[] = $url;
                $ua[] = $k;
            } else {
                $ua[] = HOST . $elm['path_id'];
            }
            $title = isset($elm['name']) ? $elm['name'] : $k;
        } elseif ($programma && !$video) {
            if (isset($elm['sets'])) {
                if (count($elm['sets']) == 1) {
                    $ua[] = HOST . $elm['sets'][0]['path_id'];
                } else {
                    $ua[] = $url;
                    $ua[] = $k;
                }
            } else {
                $ua[] = HOST . $elm['path_id'];
            }
            $title = $elm['name'];
        } elseif ($episodi || $video) {
            $ua       = $elm['video_url'];
            $season   = isset($elm['season']) ? $elm['season'] : 0;
            $episode  = isset($elm['episode']) ? $elm['episode'] : 0;
            $eps      = sprintf("%1$01dx%2$02d", $season, $episode);
            $ep_title = isset($elm['episode_title']) ? $elm['episode_title'] : $elm['name'];
            $title    = preg_match("/^\dx\d\d$/", $eps) ? "$eps - $ep_title" : $ep_title;
            //title cleanup
            $title = str_replace('0x00 - ', '', $title);
            $title = preg_replace("/^0x/", 'Ep.', $title);
            $title = preg_replace("/[\s-]+E\d+$/", '', $title);

            $desc  = isset($elm['description']) ? $elm['description'] : $elm['subtitle'];
            $thumb = HOST . $elm['images']['landscape'];
        }

        if (isset($elm['rights_management']['rights']['drm']['VOD']) &&
            $elm['rights_management']['rights']['drm']['VOD'] == true) {
            $title = "[DRM] $title";
        }

        if (($generale || $programma) && !$video) {
            $items[] = array(
                'id'         => build_umsp_url('rai_play', $ua),
                'dc:title'   => htmlspecialchars($title),
                'upnp:class' => 'object.container',
                'chk'        => serialize($ua),
            );
        }
        if ($episodi || $video) {
            $items[] = createPlayItem(
                build_server_url(array( 'video_page' => $ua )),
                $title,
                $desc,
                $thumb,
                'object.item.videoitem',
                'http-get:*:video/mp4:*'
            );
        }
        unset($ua);
    }
    //if only 1 item auto-enter
    if (count($items) == 1 && $programma) {
        if (isset($items[0]['chk'])) {
            $dat = unserialize($items[0]['chk']);
            $url = $dat[0];
            $lev = isset($dat[1]) ? $dat[1] : null;
            _logDebug("auto-enter $url $lev");
            return rai_play($url, $lev);
        }
    }
    return $items;
}

function rai_day($ch, $day)
{
    $ch = strtolower($ch);
    $ch = str_replace("rai", "rai-", $ch);

    _logDebug(">> $ch > $day");
    $items = array();
    //old $ff = file_get_contents("https://www.raiplay.it/guidatv/index.html?canale=$ch&giorno=$day&new");

    $ff = file_get_contents("https://www.raiplay.it/palinsesto/guidatv/lista/$ch/$day.html");
    if (preg_match_all("/<li[\w\W]+?<\/li>/", $ff, $lis)) {
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

                            $img     = preg_match("/^http/", $img[1]) ? $img[1] : "https://www.rai.it" . $img[1];
                            $href    = preg_match("/^http/", $href[1]) ? $href[1] : "https://www.raiplay.it" . $href[1];
                            $items[] = createPlayItem(
                                build_server_url(array( 'video_page' => $href )),
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
        _logError(__FUNCTION__ . " day reg-ex not found.");
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
        'id'             => build_umsp_url('config', array( 'PREFER_HD', !$prefer_hd )),
        'dc:title'       => 'HD ' . ($prefer_hd ? 'on' : 'off') . ' - Seleziona per ' . ($prefer_hd ? 'disattivarlo' : 'attivarlo'),
        'upnp:album_art' => 'http://lh4.googleusercontent.com/-hsbvm1bQllg/Tvwgvvk5BnI/AAAAAAAAAFs/DHQp5lKE7-4/s220/HD-icon.png',
        'upnp:class'     => 'object.container',
    );
    $Items[] = array(
        'id'             => build_umsp_url('main_menu'),
        'dc:title'       => 'Indietro',
        'upnp:album_art' => 'http://lh3.googleusercontent.com/-dsT4ZvjCth4/TvwihbvNZLI/AAAAAAAAAF0/1Jp9s8dLNlY/s220/back_button_icon.png',
        'upnp:class'     => 'object.container',
    );
    return $Items;
}

function rai_createLink($url)
{
    _logDebug("creating link from: $url");
    $phd = getConfigValue('PREFER_HD', 1);
    if (preg_match('/relinkerServlet\.htm/', $url)) {
        $sc = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => 'User-Agent: aria2',
            ),
        ));
        $f  = file_get_contents($url, false, $sc, 0, 0);
        foreach ($http_response_header as $v) {
            if (preg_match("/Location:\s*(.+?)$/", $v, $m)) {
                $f = $m[1];
            }
        }
        preg_match("/akamaihd\.net\/i\/(.+?)\/(\d{5,7})_([\d,]+)\.mp4/", $f, $m);
        for ($i = 1; $i <= 4; $i++) {
            $u = "http://creativemedia$i.rai.it/" . $m[1] . "/" . $m[2] . ($phd ? '_1800' : '_800') . ".mp4";
            $h = get_headers($u);
            _logDebug("test: $u > " . $h[0]);
            if (preg_match('/HTTP\/1\.[01] *200 *OK/', $h[0])) {
                return $u;
            }
        }
        exit;
    //DEPERECATED
    } elseif (preg_match('/replaytv\/(.+?\/\d{4,8})[\/_]/', $url, $m)) {
        for ($i = 1; $i <= 4; $i++) {
            $u = "http://creativemedia$i.rai.it/Italy/podcastmhp/replaytv/" . $m[1] . ($phd ? '_1800' : '_800') . ".mp4";
            $h = get_headers($u);
            _logDebug("test: $u > " . $h[0]);
            if (preg_match('/HTTP\/1\.[01] *200 *OK/', $h[0])) {
                return $u;
            }
        }
    } elseif (preg_match('/replaytv_world\/(.+?\/\d{4,8})[\/_]/', $url, $m)) {
        for ($i = 1; $i <= 4; $i++) {
            $u = "http://creativemedia$i.rai.it/podcastmhp/replaytv_world/" . $m[1] . ($phd ? '_1800' : '_800') . ".mp4";
            $h = get_headers($u);
            _logDebug("test: $u > " . $h[0]);
            if (preg_match('@HTTP\/1\.[01] *200 *OK@', $h[0])) {
                return $u;
            }
        }
    } elseif (preg_match('/^(.+?rai\.it)\/(.+?)\/(\d+?)\/(\d+?)\.ism/', $url, $m)) {
        for ($i = 1; $i <= 4; $i++) {
            $tmp = preg_match("@geoprotetto@i", $m[2]) ? "Italy/" . $m[2] : $m[2];
            $u   = preg_replace('/\d/', $i, $m[1]) . "/" . preg_replace('/podcastmhp/', 'podcastcdn', $tmp) . "/" . $m[3] . ($phd ? '_1800' : '_800') . ".mp4";
            $h   = get_headers($u);
            _logDebug("test: $u > " . $h[0]);
            if (preg_match('/HTTP\/1\.[01] *200 *OK/', $h[0])) {
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
    if (preg_match("/raiplay\.it/", $page)) {
        $u = str_replace(".html?json", ".json", $page);
        $u = str_replace(".html", ".json", $u);
        _logDebug("new page: $u");
    } elseif (preg_match("/rai\.tv/", $page)) {
        $u = str_replace(".html?iframe", ".html?json", $page);
        _logDebug("new page: $u");
    } else {
        $u = $page;
    }

    $ff = file_get_contents($u);
    $jj = json_decode($ff, true);

    if (isset($jj['video']['content_url'])) {
        if (preg_match("/relinkerServlet\.htm\?cont=(.+?)$/", $jj['video']['content_url'], $mm)) {
            return $mm[1];
        }
    }

    //get from webpage if json fails
    $ff = file_get_contents($page);
    if (preg_match("/(data-video-url|videoURL)\s*=\s*\".+?relinkerServlet\.htm\?cont=(.+?)\"/", $ff, $mm)) {
        return $mm[2];
    }
    return null;
}

function rai_getLink($id)
{
    $prefix = 'http://mediapolisvod.rai.it/relinker/relinkerServlet.htm?cont=';
    $url    = $prefix . $id;
    $h      = get_headers($url, 1);
    _logDebug("test: $url > " . $h[0]);
    if (preg_match('/HTTP\/1\.[01] *200 *OK/', $h[0])) {
        return rai_createLink(file_get_contents($url));
    } elseif (preg_match('/HTTP\/1\.[01] *302/', $h[0])) {
        _logDebug("direct mp4?");
        if (preg_match('/\.mp4$/', $h['Location'])) {
            $u = $h['Location'];
            preg_match("/_(\d{3,4})\.mp4/", $u, $m);
            if (isset($m[1])) {
                _logDebug("replacing $m[1] with 1800");
                $u = str_replace("_$m[1].", "_1800.", $u);
            }

            $h = get_headers(str_replace(' ', '%20', $u));
            _logDebug("test: $u > " . $h[0]);
            if (preg_match('/HTTP\/1\.[01] *200 *OK/', $h[0])) {
                return $u;
            } elseif (preg_match('/HTTP\/1\.0 504 Gateway Time-out/', $h[0])) {
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
    if (preg_match("/studio24|labussola/", $page)) {
        $ff = file_get_contents($page);
        if (preg_match("/src=\"(.+?\?iframe)\&/", $ff, $mm)) {
            $page = $mm[1];
        }
    }
    $id = null;
    if (!preg_match("/relinkerServlet\.htm\?cont\=(.+?Equal)$/", $page, $id)) {
        $page = preg_match("/^\/\//", $page) ? "https:" . $page : $page;
        $page = preg_match("/^\//", $page) ? "https://www.raiplay.it" . $page : $page;
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
