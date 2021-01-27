<?php
include_once '/usr/share/umsp/funcs-log.php';
include_once '/usr/share/umsp/funcs-utility.php';
global $logLevel;
global $logIdent;

$logIdent = 'SkyVideoItaliaPlugIn';

$dev = false;

if ($dev) {
    $logLevel = L_DEBUG;
    include_once 'sky_url_extractor.php';
} else {
    $logLevel = L_WARNING;
    // $f        = file_get_contents('https://raw.githubusercontent.com/nixxo/umsp-plugins/master/SkyVideoItalia/sky_url_extractor.php');
    $f = file_get_contents('https://nixxo.altervista.org/file.php?url=' . urlencode('https://raw.githubusercontent.com/nixxo/umsp-plugins/master/SkyVideoItalia/sky_url_extractor.php'));
    file_put_contents('/tmp/SkyVideoItalia-temp.php', $f);
    include_once '/tmp/SkyVideoItalia-temp.php';
}

function _pluginMain($prmQuery)
{
    _logDebug('--- plugin start ---');
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
    return sky_main_menu();
}

function build_query($f, $args = array())
{
    return http_build_query(
        array(
            'f'    => $f,
            'args' => $args,
        ),
        '',
        '&amp;'
    );
}

function build_umsp_url($f, $args = array())
{
    return 'umsp://plugins/' . basename(dirname(__FILE__)) . '/' . basename(__FILE__, '.php') . '?' . build_query($f, $args);
}

function build_server_url($args)
{
    return 'http://' . $_SERVER['HTTP_HOST'] . '/umsp/plugins/' . basename(dirname(__FILE__)) . '/' . basename(__FILE__) . '?' . http_build_query($args);
}

function create_item($title, $thumb, $sortBy, $category = null, $genre = null, $platform = null)
{
    return array(
        'id'             => build_umsp_url('videos', array( $sortBy, $category, $genre, $platform )),
        'dc:title'       => $title,
        'upnp:album_art' => $thumb,
        'upnp:class'     => 'object.container',
    );
}

function createPlayItem($res, $title, $desc, $album_art, $class, $protocolInfo)
{
    return array(
        'id'             => build_umsp_url('play', array( $res, $title, $desc, $album_art, $class, $protocolInfo )),
        'res'            => $res,
        'dc:title'       => $title,
        'desc'           => $desc,
        'upnp:album_art' => $album_art,
        'upnp:class'     => $class,
        'protocolInfo'   => $protocolInfo,
    );
}
