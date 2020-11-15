<?php

include_once('/usr/share/umsp/funcs-config.php');
include('info.php');

// Does this WEC version support custom hooks?
if ((defined('WECVERSION')) && (WECVERSION >= 3)) {
    // Insert badge if we have one
    if ((isset($pluginInfo['thumb']))&&($pluginInfo['thumb']!='')) {
        $desc = '<div style="float: left; padding: 4px 10px 4px 4px;"><img src="'.$pluginInfo['thumb'].'" width="60" height="60" alt="logo"></div>'
            .'<div>'.$pluginInfo['name']." v".$pluginInfo['version']." (".$pluginInfo['date'].") by "
            .$pluginInfo['author'].".<br>".$pluginInfo['desc']."<br>Information: <a href='".$pluginInfo['url']."'>".$pluginInfo['url']."</a>"
            .'</div>';
    } elseif ((isset($pluginInfo['art']))&&($pluginInfo['art']!='')) {
        $desc = '<div style="float: left; padding: 4px 10px 4px 4px;"><img src="'.$pluginInfo['art'].'" width="60" height="60" alt="logo"></div>'
            .'<div>'.$pluginInfo['name']." v".$pluginInfo['version']." (".$pluginInfo['date'].") by "
            .$pluginInfo['author'].".<br>".$pluginInfo['desc']."<br>Information: <a href='".$pluginInfo['url']."'>".$pluginInfo['url']."</a>"
            .'</div>';
    } else {
        $desc = $pluginInfo['name'].' v'.$pluginInfo['version'].' ('.$pluginInfo['date'].') by '
            .$pluginInfo['author'].'.<br>'.$pluginInfo['desc']."<br>Information: <a href='".$pluginInfo['url']."'>".$pluginInfo['url'].'</a>';
    }

    $key = strtoupper("{$pluginInfo['id']}_DESC");
    $wec_options[$key] = array(
        'configname'    => $key,
        'configdesc'    => $desc,
        'longdesc'  => '',
        'group'     => $pluginInfo['name'],
        'type'      => WECT_DESC,
        'page'      => WECP_UMSP,
        'displaypri'    => -25,
        'availval'  => array(),
        'availvalname'  => array(),
        'defaultval'    => '',
        'currentval'    => ''
    );

    $wec_options[$pluginInfo['id']] = array(
        'configname'    => $pluginInfo['id'],
        'configdesc'    => 'Enable '.$pluginInfo['name'].' UMSP plugin - Search Youtube and view your subscriptions/playlists',
        'longdesc'  => '',
        'group'     => $pluginInfo['name'],
        'type'      => WECT_BOOL,
        'page'      => WECP_UMSP,
        'displaypri'    => -10,
        'availval'  => array('off','on'),
        'availvalname'  => array(),
        'defaultval'    => '',
        'currentval'    => '',
        'readhook'  => wec_umspwrap_read,
        'writehook' => wec_umspwrap_write,
        'backuphook'    => null,
        'restorehook'   => null
    );

    $wec_options['YOUTUBE_NEW_VIDEOS'] = array(
        'configname'    => 'YOUTUBE_NEW_VIDEOS',
        'configdesc'    => 'Maximum number of videos in New Subscriptions.',
        'longdesc'  => 'Maximum number of videos in New Subscriptions.'
                    .'Default is 300',
        'group'     => $pluginInfo['name'],
        'type'      => WECT_INT,
        'page'      => WECP_UMSP,
        'defaultval'    => '300',
        'currentval'    => ''
    );
        
    $wec_options['PROXY_LED'] = array(
        'configname'    => 'PROXY_LED',
        'configdesc'    => 'Turn on the power LED when the proxy is active',
        'longdesc'  => 'Turn on the power LED each time the proxy is working<br>'
                        .'Turn off the power LED when the proxy passes control to the player.<br>'
                        .'This way you know the proxy is still working when navigating.',
        'group'     => $pluginInfo['name'],
        'type'      => WECT_BOOL,
        'page'      => WECP_UMSP,
        'availval'  => array('OFF','ON'),
        'availvalname'  => array(),
        'defaultval'    => 'OFF',
        'currentval'    => ''
    );
        
    $wec_options['YOUTUBE_USERS'] = array(
        'configname'    => 'YOUTUBE_USERS',
        'configdesc'    => 'Youtube accounts you want listed in the home screen. Separate usernames by comma (,). No passwords are necessary',
        'longdesc'  => 'A comma separated list of youtube (or gmail) accounts<br>'
                      .'to be explored by the plugins. Only public data is retrieved<br>'
                      .'(subscriptions, playlists, uploaded videos, liked videos),<br>'
                      .'password is not needed',
        'group'     => $pluginInfo['name'],
        'type'      => WECT_INT,
        'page'      => WECP_UMSP,
        'defaultval'    => '',
        'currentval'    => ''
    );

    $wec_options['YOUTUBE_CHANNELS'] = array(
        'configname'    => 'YOUTUBE_CHANNELS',
        'configdesc'    => 'Youtube channels you want listed in the home screen. Separate channel ids by comma (,). The value should be shorter than 250 characters to avoid truncation. ',
        'longdesc'  => 'A comma separated list of youtube channel IDs <br>'
                  .'(for new accounts that no longer have a legacy username)<br>'
                      .'to be explored by the plugins. Only public data is retrieved<br>'
                      .'(subscriptions, playlists, uploaded videos, liked videos),<br>'
                      .'password is not needed',
        'group'     => $pluginInfo['name'],
        'type'      => WECT_INT,
        'page'      => WECP_UMSP,
        'defaultval'    => '',
        'currentval'    => ''
    );
}
