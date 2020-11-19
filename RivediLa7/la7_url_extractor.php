<?php
define('PROXY', 'https://nixxo.altervista.org/file.php?url=');

function la7_main_menu()
{
    _logDebug('main menu 20.11.16');
    $ff = file_get_contents(PROXY . urlencode('https://www.la7.it/rivedila7'));
    if (preg_match_all('/<a href="\/rivedila7\/(\d)\/LA7">\s*<div class="giorno-text">\s*(\w+?)<\/div>\s*<div class="giorno-numero">\s*(\d+?)<\/div>\s*<div class="giorno-mese">\s*(\w+?)<\/div>\s*<\/a>/', $ff, $m)) {
        $days_name   = array_reverse($m[2]);
        $days_number = array_reverse($m[3]);
        $months      = array_reverse($m[4]);
        $ids         = array_reverse($m[1]);
        for ($i = 0; $i < count($days_name); $i++) {
            $items[] = array(
                'id'         => build_umsp_url('la7_day', array( $ids[ $i ] )),
                'dc:title'   => $days_name[ $i ] . ' ' . $days_number[ $i ] . ' ' . $months[ $i ],
                'upnp:class' => 'object.container',
            );
        }
    }
    return $items;
}

function clean_title($tit)
{
    return preg_replace('@&#039;@i', "'", $tit);
}

function la7_day($id)
{
    _logInfo("day $id");
    $items = array();
    $ff    = file_get_contents(PROXY . urlencode("https://www.la7.it/rivedila7/$id/LA7"));

    if (preg_match_all('/<div class="box-left[\s\S]*?<div\s*id/', $ff, $prog)) {
        foreach ($prog[0] as $p) {
            if (preg_match('/<span>CONTENUTO NON DISPONIBILE<\/span>/', $p)) {
                continue;
            }

            // orario
            preg_match('/<div class="orario">(\d{1,2}:\d{1,2})<\/div>/', $p, $orario);
            // thumbnail
            preg_match('/data-background-image="(.+?)"/', $p, $th);
            // link pagina
            preg_match('/<div class="box-right\s*">\s*<a href="(.+?)"/', $p, $video);
            // durata
            preg_match('/<div class="durata">\s*(\d+):(\d+):(\d+)<\/div>/', $p, $dr);
            // titolo + descrizione
            preg_match('/<h2>\s*(.+?)\s*<\/h2>\s*<\/div>\s*<div class="occhiello">\s*(.+?)\s*<\/div>/', $p, $ds);

            if ($orario && $th && $video && $dr && $ds) {
                $items[] = create_play_item(
                    build_server_url(array( 'video' => $video[1] )),
                    clean_title($orario[1] . ' - ' . $ds[1] . ' [' . ( $dr[1] != '00' ? $dr[1] . ':' : '' ) . $dr[2] . ':' . $dr[3] . ']'),
                    $ds[2],
                    'https:' . $th[1],
                    'object.item.videoitem',
                    'http-get:*:video/mp4:*'
                );
            }
        }
    } else {
        _logError('retrieving data failed.');
    }
    return $items;
}

function la7_video($page)
{
    _logDebug('page: ' . $page);
    $ff = file_get_contents(PROXY . urlencode($page));
    if (preg_match('/"m3u8"\s*:\s*".+?content\/entry\/.+_(0_.+?)_\d,\.mp4\.csmil\/master\.m3u8"/', $ff, $m)) {
        $dl = "http://nkdam.iltrovatore.it/p/103/sp/10300/serveFlavor/flavorId/$m[1]";
        _logDebug("playing m3u8: $dl");
        ob_start();
        header('Content-type: video/mp4');
        header('Location: ' . $dl);
        ob_flush();
    } else {
        _logError('Video not found.');
    }
    exit();
}
