<?php
define('PROXY', 'https://nixxo.altervista.org/file.php?url=');
global $sky_conf;
$sky_conf = array(
    'VERSION'                  => '20.11_16',
    'CONF'                     => 'https://video.sky.it/etc/designs/skyvideoportale/library/static/js/config/config.json',
    'GET_VIDEO_SEARCH'         => 'https://video.sky.it/be/getVideoDataSearch?token={token}&section={section}&subsection={subsection}&page={page}&count=30',
    'GET_PLAYLISTS'            => 'https://video.sky.it/be/getPlaylistInfo?token={token}&section={section}&subsection={subsection}&start=0&limit=31',
    'GET_PLAYLIST_VIDEO'       => 'https://video.sky.it/be/getPlaylistVideoData?token={token}&id={id}',
    'GET_VIDEO_DATA'           => 'https://apid.sky.it/vdp/v1/getVideoData?token={token}&caller=sky&rendition=web&id={id}',
    'GET_VIDEO_DATA_OLD'       => 'https://video.sky.it/be/getVideoData?token={token}&id={id}&rendition=web',
    'GET_VOD_ACCESS_TOKEN'     => 'https://apid.sky.it/vdp/v1/getVodAccessToken?token={token}&url={url}&dec=0',
    'GET_VOD_ACCESS_TOKEN_OLD' => 'https://video.sky.it/SkyItVideoportalUtility/getVODAccessToken.do?token={token}&url={url}&dec=0',
    'TOKEN_SKY'                => 'F96WlOd8yoFmLQgiqv6fNQRvHZcsWk5jDaYnDvhbiJk',
);

function sky_main_menu()
{
    global $sky_conf;
    _logInfo('main menu sky v:' . $sky_conf['VERSION']);
    $ff = file_get_contents(PROXY . urlencode('https://video.sky.it/'));
    if (preg_match_all('/<li class="c-menu-video__menu-entry"><a>(.+?)</', $ff, $mm)) {
        $items = array();
        _logDebug(print_r($mm[1], true));
        $mm[1] = array_unique($mm[1]);
        $mm[2] = array_map('strtolower', $mm[1]);
        foreach ($mm[1] as $k => $v) {
            $items[] = array(
                'id'         => build_umsp_url('sky_menu', array( $mm[2][ $k ] )),
                'dc:title'   => $mm[1][ $k ],
                'upnp:class' => 'object.container',
            );
        }
        return $items;
    } else {
        _logError('Error retrieving Subsections');
        return array(
            'id'         => build_umsp_url('sky_error', array( 'a' )),
            'dc:title'   => 'Error retrieving Subsections',
            'upnp:class' => 'object.container',
        );
    }
}

function sky_menu($id)
{
    _logDebug("https://video.sky.it/$id");
    $ff = file_get_contents(PROXY . urlencode("https://video.sky.it/$id"));
    if (preg_match_all('/menu-entry-sub"><a href="https:\/\/video.sky.it\/' . $id . '\/(.+?)">(.+?)<\/a>/', $ff, $mm)) {
        $items = array();
        _logDebug(print_r($mm[1], true));
        $mm[1] = array_unique($mm[1]);
        $mm[2] = array_unique($mm[2]);
        foreach ($mm[1] as $k => $v) {
            $items[] = array(
                'id'         => build_umsp_url('sky_subsection', array( $id, $mm[1][ $k ] )),
                'dc:title'   => $mm[2][ $k ],
                'upnp:class' => 'object.container',
            );
        }
        return $items;
    } else {
        _logError('Error retrieving Subsections');
        return array(
            'id'         => build_umsp_url('sky_error', array( '' )),
            'dc:title'   => 'Error retrieving Subsections',
            'upnp:class' => 'object.container',
        );
    }
}

function sky_subsection($s, $ss, $tt = null, $page = 0)
{
    global $sky_conf;
    _logInfo(">>> subsection: $s - $ss <<<");
    $pl_url = $sky_conf['GET_VIDEO_SEARCH'];
    $pl_url = str_replace('{token}', $sky_conf['TOKEN_SKY'], $pl_url);
    $pl_url = str_replace('{section}', $s, $pl_url);
    $pl_url = str_replace('{subsection}', $ss, $pl_url);
    $pl_url = str_replace('{page}', $page, $pl_url);

    _logDebug('url: ' . $pl_url);
    $ff = json_decode(file_get_contents(PROXY . urlencode($pl_url)), true);
    if ($ff == null) {
        _logError('JSON DECODE ERROR IN: ' . __FUNCTION__);
        return null;
    }
    _logDebug(print_r($ff, true));
    if ($page == 0) {
        $items[] = array(
            'id'         => build_umsp_url('sky_playlist', array( $s, $ss )),
            'dc:title'   => 'Playlist di ' . $tt,
            'upnp:class' => 'object.container',
        );
    } else {
        $items[] = array(
            'id'         => build_umsp_url('sky_subsection', array( $s, $ss, $tt, $page - 1 )),
            'dc:title'   => 'Pagina ' . $page,
            'upnp:class' => 'object.container',
        );
    }
    $items   = array_merge($items, sky_parse_playlist($ff));
    $items[] = array(
        'id'         => build_umsp_url('sky_subsection', array( $s, $ss, $tt, $page + 1 )),
        'dc:title'   => 'Pagina ' . ( $page + 2 ),
        'upnp:class' => 'object.container',
    );
    return $items;
}

function sky_playlist($s, $ss)
{
    global $sky_conf;
    _logInfo(">>> $s - $ss playlist <<<");
    $pl_url = $sky_conf['GET_PLAYLISTS'];
    $pl_url = str_replace('{token}', $sky_conf['TOKEN_SKY'], $pl_url);
    $pl_url = str_replace('{section}', $s, $pl_url);
    $pl_url = str_replace('{subsection}', $ss, $pl_url);

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
        if (!preg_match('/(\d+)\D(\d+)\D(\d+)T/', $v['modify_date'], $date)) {
            preg_match('/(\d+)\D(\d+)\D(\d+)T/', $v['create_date'], $date);
        }

        //_logDebug(print_r($date, true));
        $items[] = array(
            'id'             => build_umsp_url('sky_playlist_content', array( $v['playlist_id'] )),
            'dc:title'       => $date[3] . '/' . $date[2] . '/' . $date[1] . ' - ' . $v['title'],
            'desc'           => $v['short_desc'],
            'upnp:album_art' => $v['thumb'],
            'upnp:class'     => 'object.container',
        );
    }
    return $items;
}

function sky_playlist_content($pl_id)
{
    global $sky_conf;
    _logInfo(">>> playlist id: $pl_id<<<");
    $pl_url = $sky_conf['GET_PLAYLIST_VIDEO'];
    $pl_url = str_replace('{token}', $sky_conf['TOKEN_SKY'], $pl_url);
    $pl_url = str_replace('{id}', $pl_id, $pl_url);

    _logDebug('url: ' . $pl_url);
    $ff = json_decode(file_get_contents($pl_url), true);
    if ($ff == null) {
        _logError('JSON DECODE ERROR IN: ' . __FUNCTION__);
        return null;
    }
    _logDebug(print_r($ff, true));
    return sky_parse_playlist($ff);
}

function sky_parse_playlist($pl)
{
    $items = array();
    foreach ($pl['assets'] as $v) {
        if (!preg_match('/^\d+\/\d+/', $v['modify_date'], $date)) {
            preg_match('/^\d+\/\d+/', $v['create_date'], $date);
        }

        $items[] = createPlayItem(
            build_server_url(array( 'asset_id' => $v['asset_id'] )),
            $date[0] . ' - ' . $v['title'],
            $v['short_desc'],
            $v['video_still'],
            'object.item.videoitem',
            'http-get:*:video/mp4:*'
        );
    }
    return $items;
}

function sky_get_video($asset_id, $old = false)
{
    global $sky_conf;
    _logInfo('>>> get video data with id:' . $asset_id . ' <<<');
    $pl_url = !$old ? $sky_conf['GET_VIDEO_DATA'] : $sky_conf['GET_VIDEO_DATA_OLD'];
    $pl_url = str_replace('{token}', $sky_conf['TOKEN_SKY'], $pl_url);
    $pl_url = str_replace('{id}', $asset_id, $pl_url);
    _logDebug('url: ' . $pl_url);
    $ff = json_decode(file_get_contents(PROXY . urlencode($pl_url)), true);
    if ($ff == null) {
        _logError('JSON DECODE ERROR IN: ' . __FUNCTION__);
        return null;
    }
    _logDebug(print_r($ff, true));

    if (isset($ff['token'])) {
        _logInfo('>>> get video url with token <<<');
        $pl_url = $sky_conf['GET_VOD_ACCESS_TOKEN'];
        $pl_url = str_replace('{token}', $ff['token'], $pl_url);
        $pl_url = str_replace('{url}', $ff['web_high_url'], $pl_url);
        _logDebug('url: ' . $pl_url);
        $ff = json_decode(file_get_contents(PROXY . urlencode($pl_url)), true);
        if ($ff == null) {
            _logError('JSON DECODE ERROR IN: ' . __FUNCTION__);
            return null;
        }
        _logDebug(print_r($ff, true));
        return $ff['url'];
    }
    if (isset($ff['web_hd_url'])) {
        return $ff['web_hd_url'];
    } elseif (isset($ff['web_high_url'])) {
        return $ff['web_high_url'];
    } else {
        return sky_get_video($asset_id, true);
    }
}

if (isset($_GET['asset_id'])) {
    $url = sky_get_video($_GET['asset_id']);
    if ($url) {
        _logInfo('playing: ' . $url);
        ob_start();
        $url = str_replace('https:', 'http:', $url);
        header('Location: ' . $url);
        ob_flush();
    }
    exit();
}
