<?php
/*
 * youtube3.php
 *
 * Copyright 2015 mad_ady
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 *
*/

require_once $_SERVER['DOCUMENT_ROOT'] . '/umsp/funcs-log.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/umsp/funcs-utility.php';
// set the logging level, one of L_ALL, L_DEBUG, L_INFO, L_WARNING, L_ERROR, L_OFF
global $logLevel;
$logLevel = L_WARNING;
global $logIdent;
$logIdent = 'Youtube3';

set_time_limit(0);

define('PLUGIN_NAME', str_replace('.php', '', basename(__file__)));
define('PROXY_URL', 'http://' . $_SERVER['SERVER_ADDR'] . ':' . $_SERVER['SERVER_PORT'] . '/umsp/plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '-proxy.php');
//Youtube simple-api v3 key. Don't use it for other projects, please
define('DEVELOPER_KEY', 'AIzaSyDDGgNfJ43o8ssRGwdZvwdyg8FGiV_8kT8');

global $nextPage;
$nextPage = '';

/* Main function, called on each request */
function _pluginMain($prmQuery)
{
    global $nextPage;
    _logDebug("In _pluginMain($prmQuery)");
    $queryData = array();
    $prmQuery  = htmlspecialchars_decode($prmQuery);
    parse_str(urldecode($prmQuery), $queryData);
    $retMediaItems = array();

    //handle BrowseMetadata request for a single item
    if (isset($queryData['video_id'])) {
        _logDebug('returning data for video_id ' . $queryData['video_id']);
        $thumb_url       = 'http://i.ytimg.com/vi/' . $queryData['video_id'] . '/hqdefault.jpg'; //derive the thumbnail from the video id
        $retMediaItems[] = array(
            'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . htmlspecialchars($prmQuery),
            'res'            => PROXY_URL . '?' . htmlspecialchars($prmQuery),
            'dc:title'       => $queryData['ClipName'],
            'upnp:class'     => 'object.item.videoItem',
            'upnp:album_art' => $thumb_url,
            'protocolInfo'   => 'http-get:*:video/*:*',
        );
        return $retMediaItems;
    }

    if (!function_exists('curl_init')) {
        //You are using an old version of the firmware
        showErrorMessage('You need to upgrade your firmware to use this plugin (you need php with curl support)', $retMediaItems);
    }

    //  _logDebug("Random switch: ".$queryData['rnd']);
    if (!isset($queryData['cmd'])) {
        $queryData['cmd'] = '';
    }
    switch ($queryData['cmd']) {
        case 'overview':
            /* Generate links to relevant content */
            $thumb_url       = 'http://lh6.googleusercontent.com/_CsOEwmjx9p8/TZ1nkPHMtRI/AAAAAAAAO7U/YzvL2RFemdw/yt-subscriptions.png';
            $dataString      = array(
                'channelId' => $queryData['channelId'],
                'cmd'       => 'all_subscriptions',
                'rnd'       => rand(),
            );
            $retMediaItems[] = array(
                'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . http_build_query($dataString, '', '&amp;'),
                'dc:title'       => 'Subscriptions',
                'upnp:class'     => 'object.container',
                'upnp:album_art' => $thumb_url,
                'protocolInfo'   => '*:*:*:*',
            );

            $thumb_url       = 'http://lh6.googleusercontent.com/_CsOEwmjx9p8/TZ1nj28Ru1I/AAAAAAAAO7Q/riplGEmZu0k/yt-playlists.png';
            $dataString      = array(
                'channelId' => $queryData['channelId'],
                'cmd'       => 'playlists',
                'rnd'       => rand(),
            );
            $retMediaItems[] = array(
                'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . http_build_query($dataString, '', '&amp;'),
                'dc:title'       => 'Playlists',
                'upnp:class'     => 'object.container',
                'upnp:album_art' => $thumb_url,
                'protocolInfo'   => '*:*:*:*',
            );

            if (isset($queryData['favorites'])) {
                $thumb_url       = 'http://lh4.googleusercontent.com/_CsOEwmjx9p8/TZ1njyVr_QI/AAAAAAAAO7M/_RqgYCoSKpM/yt-favorites.png';
                $dataString      = array(
                    'channelId' => $queryData['channelId'],
                    'favorites' => $queryData['favorites'],
                    'cmd'       => 'favorite_videos',
                    'rnd'       => rand(),
                );
                $retMediaItems[] = array(
                    'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . http_build_query($dataString, '', '&amp;'),
                    'dc:title'       => 'Favorites',
                    'upnp:class'     => 'object.container',
                    'upnp:album_art' => $thumb_url,
                    'protocolInfo'   => '*:*:*:*',
                );
            }
            if (isset($queryData['likes'])) {
                $thumb_url       = 'http://lh4.googleusercontent.com/_CsOEwmjx9p8/TZ1njyVr_QI/AAAAAAAAO7M/_RqgYCoSKpM/yt-favorites.png';
                $dataString      = array(
                    'channelId' => $queryData['channelId'],
                    'likes'     => $queryData['likes'],
                    'cmd'       => 'liked_videos',
                    'rnd'       => rand(),
                );
                $retMediaItems[] = array(
                    'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . http_build_query($dataString, '', '&amp;'),
                    'dc:title'       => 'Liked videos',
                    'upnp:class'     => 'object.container',
                    'upnp:album_art' => $thumb_url,
                    'protocolInfo'   => '*:*:*:*',
                );
            }

            /* No way to get recommended videos yet
            *
            $thumb_url = "http://lh6.googleusercontent.com/_CsOEwmjx9p8/TZ1nj1ORTaI/AAAAAAAAO7I/36qU1JJkNjA/yt-recommended.png";
            $dataString = array('channelId' => $queryData['channelId'],'cmd'=>'recommended_videos','rnd'=>rand());
            $retMediaItems[] = array (
            'id'          => 'umsp://plugins/'.PLUGIN_NAME.'/'.PLUGIN_NAME.'?' . http_build_query($dataString,'','&amp;'),
            'dc:title'       => "Recommended",
            'upnp:class'   => 'object.container',
            'upnp:album_art'=> $thumb_url,
            'protocolInfo'   => '*:*:*:*'
            );
            *
            */

            if (isset($queryData['uploads'])) {
                $thumb_url       = 'http://lh3.googleusercontent.com/_CsOEwmjx9p8/TZ2635hKI5I/AAAAAAAAO7g/KBt3XLUVi5A/yt-uploaded.png';
                $dataString      = array(
                    'channelId' => $queryData['channelId'],
                    'uploads'   => $queryData['uploads'],
                    'cmd'       => 'uploaded_videos',
                    'rnd'       => rand(),
                );
                $retMediaItems[] = array(
                    'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . http_build_query($dataString, '', '&amp;'),
                    'dc:title'       => 'Uploaded',
                    'upnp:class'     => 'object.container',
                    'upnp:album_art' => $thumb_url,
                    'protocolInfo'   => '*:*:*:*',
                );
            }
            break;
        case 'all_subscriptions':
            /* Get a list of channels a user subscribed to */

            /* TODO: Create a meta entry to hold new subscription videos -- complicated to get at the moment... */
            $param         = array(
                'part'      => 'id,snippet',
                'channelId' => $queryData['channelId'],
                'order'     => 'alphabetical',
                'pageToken' => $queryData['nextPage'],
            );
            $searchItems   = getItems('subscriptions', $param);
            $retMediaItems = array_merge($retMediaItems, $searchItems);
            break;
        case 'new_subscription_videos':
            /* TODO: Get new subscription videos -- complicated to get at the moment... */
            break;
        case 'subscription_videos':
            /* Get metadata for this channel to find out what the Upload playlist is */
            /* NOTE: we can also easily get the channel's own subscriptions, likes, favorites, etc,
             * but we don't do it for this plugin
            */
            $param    = array(
                'part' => 'id,snippet,contentDetails',
                'id'   => $queryData['channelId'],
            );
            $response = youtubeQuery('channels', $param);
            /* For the channel returned, query and retreive the uploaded videos (like a playlist) */
            $json = json_decode($response, true);
            _logDebug('Decoded json:' . print_r($json, true));
            if ($json != null) {
                //1. see if it's an error message
                if (isset($json['error'])) {
                    _logError('API error message: ' . print_r($json, true));
                    showErrorMessage('Youtube API error: Code ' . $json['error']['code'] . ', Message: ' . $json['error']['message'] . ', Reason: ' . $json['error']['errors'][0]['reason'], $retMediaItems);
                } else {
                    $uploads = 0;
                    foreach ($json['items'] as $item) {
                        if (isset($item['contentDetails']['relatedPlaylists']['uploads'])) {
                            $uploads = $item['contentDetails']['relatedPlaylists']['uploads'];
                            break;
                        }
                    }
                    $param         = array(
                        'part'       => 'id,snippet',
                        'playlistId' => $uploads,
                        'pageToken'  => $queryData['nextPage'],
                    );
                    $retMediaItems = getItems('playlistItems', $param);
                }
            } else {
                //yep, it's not JSON. Log error or alert someone or do nothing
                showErrorMessage("Invalid reply from Youtube API: $response", $retMediaItems);
            }
            break;
        case 'playlists':
            $param         = array(
                'part'      => 'id,snippet',
                'channelId' => $queryData['channelId'],
                'pageToken' => isset($queryData['nextPage']) ? $queryData['nextPage'] : '',
            );
            $searchItems   = getItems('playlists', $param);
            $retMediaItems = array_merge($retMediaItems, $searchItems);
            break;
        case 'playlist_videos':
            $param         = array(
                'part'       => 'id,snippet',
                'playlistId' => $queryData['playlistId'],
                'pageToken'  => isset($queryData['nextPage']) ? $queryData['nextPage'] : '',
            );
            $retMediaItems = getItems('playlistItems', $param);
            break;

        case 'favorite_videos':
            $param         = array(
                'part'       => 'id,snippet',
                'playlistId' => $queryData['favorites'],
                'pageToken'  => $queryData['nextPage'],
            );
            $retMediaItems = getItems('playlistItems', $param);
            break;
        case 'liked_videos':
            $param         = array(
                'part'       => 'id,snippet',
                'playlistId' => $queryData['likes'],
                'pageToken'  => $queryData['nextPage'],
            );
            $retMediaItems = getItems('playlistItems', $param);
            break;
        case 'watch_later_videos':
            /* Needs Oauth Authentication which we don't have:  */

            break;
        case 'recommended_videos':
            //TODO? http://stackoverflow.com/questions/16649693/get-recommended-video-with-api-3-0

            break;
        case 'uploaded_videos':
            $param         = array(
                'part'       => 'id,snippet',
                'playlistId' => $queryData['uploads'],
                'pageToken'  => $queryData['nextPage'],
            );
            $retMediaItems = getItems('playlistItems', $param);
            break;
        case 'customSearch':
            //Search for specific videos
            $param = array(
                'part'      => 'id,snippet',
                'chart'     => 'mostPopular',
                'type'      => 'video',
                'pageToken' => $queryData['nextPage'],
            );
            if (isset($queryData['channelId'])) {
                $param['id'] = $queryData['channelId'];
            }
            if (isset($queryData['categoryId'])) {
                $param['videoCategoryId'] = $queryData['categoryId'];
            }
            $searchItems   = getItems('search', $param);
            $retMediaItems = array_merge($retMediaItems, $searchItems);
            break;
        case 'search':
            //Search for specific videos
            $param = array(
                'part'      => 'id,snippet',
                'chart'     => 'mostPopular',
                'q'         => $queryData['q'],
                'pageToken' => $queryData['nextPage'],
            );
            if (isset($queryData['channelId'])) {
                $param['id'] = $queryData['channelId'];
            }
            if (isset($queryData['categoryId'])) {
                $param['videoCategoryId'] = $queryData['categoryId'];
            }
            $searchItems   = getItems('search', $param);
            $retMediaItems = array_merge($retMediaItems, $searchItems);
            break;
        case 'categories':
            // Search for categories in your area
            $param         = array(
                'part' => 'id,snippet',
                'id'   => '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50',
            );
            $searchItems   = getItems('videoCategories', $param);
            $retMediaItems = array_merge($retMediaItems, $searchItems);
            break;
        case 'channelVideos':
            // Get videos from specific channel
            $param = array(
                'part'      => 'id,snippet',
                'pageToken' => $queryData['nextPage'],
            );
            if (isset($queryData['channelId'])) {
                $param['id'] = $queryData['channelId'];
            }
            if (isset($queryData['categoryId'])) {
                $param['categoryId'] = $queryData['categoryId'];
            }
            $searchItems   = getItems('channels', $param);
            $retMediaItems = array_merge($retMediaItems, $searchItems);
            break;

        case 'search':
            exec('sudo chmod 666 /tmp/ir_injection && sudo echo E > /tmp/ir_injection &');
            // no break

        default:
            /* Add links to registered user channels */
            $retMediaItems = getUserList();
            _logDebug('retMediaItems after getUserList:' . print_r($retMediaItems, true));

            /* Add link to categories */
            $thumb_url       = 'http://lh4.googleusercontent.com/-VdLAgMha-58/TrD0v2PY7AI/AAAAAAAAPGY/7qRLn6k8O7Q/s200/yt-search.png';
            $dataString      = array(
                'cmd' => 'categories',
                'rnd' => rand(),
            );
            $retMediaItems[] = array(
                'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . http_build_query($dataString, '', '&amp;'),
                'dc:title'       => 'Categories',
                'upnp:class'     => 'object.container',
                'upnp:album_art' => $thumb_url,
                'protocolInfo'   => '*:*:*:*',
            );

            /* Add link to search */

            $thumb_url       = 'http://lh4.googleusercontent.com/-VdLAgMha-58/TrD0v2PY7AI/AAAAAAAAPGY/7qRLn6k8O7Q/s200/yt-search.png';
            $dataString      = array(
                'cmd' => 'search',
                'rnd' => rand(),
            );
            $retMediaItems[] = array(
                'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . http_build_query($dataString, '', '&amp;'),
                'dc:title'       => 'Search',
                'upnp:class'     => 'object.container',
                'upnp:album_art' => $thumb_url,
                'protocolInfo'   => '*:*:*:*',
            );

            /* Do a most popular search and return results */
            $param         = array(
                'part'      => 'id,snippet',
                'chart'     => 'mostPopular',
                'pageToken' => isset($queryData['nextPage']) ? $queryData['nextPage'] : '',
            );
            $searchItems   = getItems('search', $param);
            $retMediaItems = array_merge($retMediaItems, $searchItems);
    }

    // After listing all items, add a Next Page entry
    if (isset($nextPage) && $nextPage != '') {
        $dataString             = $queryData;
        $dataString['nextPage'] = "$nextPage";
        $dataString['rnd']      = rand();
        $retMediaItems[]        = array(
            'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . http_build_query($dataString, '', '&amp;'),
            'dc:title'       => 'Next Page',
            'desc'           => 'Get next page of results',
            'upnp:album_art' => 'http://lh3.googleusercontent.com/_CsOEwmjx9p8/TcgFrjDceRI/AAAAAAAAPAk/KUZKd6e4PnA/NextPage.png',
            'upnp:class'     => 'object.container',
        );
    }
    return $retMediaItems;
}

function _pluginSearch($prmQuery)
{
    _logDebug("_pluginSearch($prmQuery)");
    $queryData = array();
    $prmQuery2 = htmlspecialchars_decode($prmQuery);
    parse_str(urldecode($prmQuery2), $queryData);
    //get the search term. $prmQuery is (upnp:class derivedfrom "object.item.videoItem") and dc:title contains "search term"
    preg_match('/dc:title contains "(.*)"/', $prmQuery, $tokens);
    //$retMediaItems = array();
    if (isset($tokens[1])) {
        return search($tokens[1]);
    } else {
        return array(
            array(
                'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME,
                'dc:title'       => 'No search term provided',
                'upnp:album_art' => 'http://127.0.0.1/umsp/media/YouTube.png',
                'upnp:class'     => 'object.container',
                'protocolInfo'   => '*:*:*:*',
            ),
        );
    }
}

function search($term)
{
    global $nextPage;
    /* Do a youtube search; include videos, channels, playlists, etc */
    $param         = array(
        'part'          => 'id,snippet',
        'chart'         => 'mostPopular',
        'q'             => $term,
        'nextPageToken' => $nextPage,
    );
    $retMediaItems = getItems('search', $param);

    // After listing all items, add a Next Page entry
    if (isset($nextPage) && $nextPage != '') {
        $dataString             = array(
            'cmd' => 'search',
            'q'   => $term,
        );
        $dataString['nextPage'] = "$nextPage";
        $dataString['rnd']      = rand();
        $retMediaItems[]        = array(
            'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . http_build_query($dataString, '', '&amp;'),
            'dc:title'       => 'Next Page',
            'desc'           => 'Get next page of results',
            'upnp:album_art' => 'http://lh3.googleusercontent.com/_CsOEwmjx9p8/TcgFrjDceRI/AAAAAAAAPAk/KUZKd6e4PnA/NextPage.png',
            'upnp:class'     => 'object.container',
        );
    }

    return $retMediaItems;
}

function getUserList()
{
    $xml           = simplexml_load_file('/conf/account_list.xml');
    $accounts      = $xml->xpath('//service[@name="YOUTUBE"]/account');
    $retMediaItems = array();

    /* Get users from /conf/account_list.xml */
    foreach ($accounts as $account) {
        $name = (string) $account->username;
        //We need to strip "." from username, because youtube API does not recognizes them
        $name          = preg_replace('/\./', '', $name);
        $retMediaItems = array_merge($retMediaItems, youtubeGetUserChannel($name));
    }

    /* Get users from YOUTUBE_USERS configuration variable */
    $extraUsers = _getWDConf('YOUTUBE_USERS');
    if ($extraUsers != '') {
        foreach (explode(',', $extraUsers) as $account) {
            $name = $account;
            //We need to strip "." from username, because youtube API does not recognizes them
            $name          = preg_replace('/\./', '', $name);
            $retMediaItems = array_merge($retMediaItems, youtubeGetUserChannel(trim($account)));
        }
    }

    /* Get specific channels by ID (just one) */
    $extraChannels = _getWDConf('YOUTUBE_CHANNELS');
    if ($extraChannels != '') {
        foreach (explode(',', $extraChannels) as $channelID) {
            $name          = "ChannelID: $channelID";
            $retMediaItems = array_merge($retMediaItems, youtubeGetUserChannelByChannel($channelID));
        }
    }
    return $retMediaItems;
}

function youtubeGetUserChannel($username)
{
    $param  = array(
        'part'        => 'id,snippet,contentDetails',
        'forUsername' => $username,
    );
    $result = getItems('channels', $param);
    /* Can a user account have multiple channels? If yes, we return all of them */
    return $result;
}

function youtubeGetUserChannelByChannel($channelID)
{
    $param  = array(
        'part' => 'id,snippet,contentDetails',
        'id'   => $channelID,
    );
    $result = getItems('channels', $param);
    /* Can a user account have multiple channels? If yes, we return all of them */
    return $result;
}

function getItems($mainPage, $params)
{
    /* $params is an array that holds data we need to send to youtube to get a list of items */
    global $nextPage;
    $retMediaItems = array();

    $response = youtubeQuery($mainPage, $params);
    $json     = json_decode($response, true);
    _logDebug('Decoded json:' . print_r($json, true));
    if ($json != null) {
        //1. see if it's an error message
        if (isset($json['error'])) {
            _logError('API error message: ' . print_r($json, true));
            showErrorMessage('Youtube API error: Code ' . $json['error']['code'] . ', Message: ' . $json['error']['message'] . ', Reason: ' . $json['error']['errors'][0]['reason'], $retMediaItems);
        } else {
            //No error
            //2. get the next Page token
            if (isset($json['nextPageToken'])) {
                $nextPage = $json['nextPageToken'];
            }

            //3. iterate over "items"
            foreach ($json['items'] as $item) {
                if ($item['id']['kind'] == 'youtube#video' || ( isset($item['resourceId']) && $item['resourceId']['kind'] == 'youtube#video' )) {
                    $videoId = 0;

                    if (isset($item['resourceId'])) {
                        $videoId = $item['resourceId']['videoId'];
                    } else {
                        $videoId = $item['id']['videoId'];
                    }

                    $dataString = array(
                        'video_id' => $videoId,
                        'ClipName' => $item['snippet']['title'],
                    );
                    //create an encoded string of the parameters
                    $encodedParams = '';
                    foreach ($dataString as $key => $val) {
                        $encodedParams .= $key . '=' . rawurlencode($val) . '&';
                    }
                    //cut the final &
                    $encodedParams = chop($encodedParams, '&');
                    $thumb_url     = $item['snippet']['thumbnails']['high']['url']; //derive the thumbnail from the video id
                    $thumb_url     = str_replace('https://', 'http://', $thumb_url);
                    _logDebug("Adding video $videoId, title " . $item['snippet']['title']);

                    $retMediaItems[] = array(
                        'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . htmlspecialchars($encodedParams),
                        'res'            => PROXY_URL . '?' . htmlspecialchars($encodedParams),
                        'dc:title'       => $item['snippet']['title'],
                        'desc'           => $item['snippet']['description'],
                        'upnp:class'     => 'object.item.videoItem',
                        'upnp:album_art' => $thumb_url,
                        'protocolInfo'   => 'http-get:*:video/*:*',
                    );
                } elseif ($item['id']['kind'] == 'youtube#channel' || $item['kind'] == 'youtube#channel') {
                    $channelId = 0;
                    if (is_array($item['id'])) {
                        $channelId = $item['id']['channelId'];
                    } else {
                        $channelId = $item['id'];
                    }
                    /* Also add data for favorites, related, uploaded, etc */

                    $dataString = array(
                        'cmd'       => 'overview',
                        'channelId' => "$channelId",
                    );
                    if (isset($item['contentDetails']['relatedPlaylists']['likes'])) {
                        $dataString['likes'] = $item['contentDetails']['relatedPlaylists']['likes'];
                    }
                    if (isset($item['contentDetails']['relatedPlaylists']['favorites'])) {
                        $dataString['favorites'] = $item['contentDetails']['relatedPlaylists']['favorites'];
                    }
                    if (isset($item['contentDetails']['relatedPlaylists']['uploads'])) {
                        $dataString['uploads'] = $item['contentDetails']['relatedPlaylists']['uploads'];
                    }
                    //create an encoded string of the parameters
                    $encodedParams = '';
                    foreach ($dataString as $key => $val) {
                        $encodedParams .= $key . '=' . rawurlencode($val) . '&';
                    }
                    //cut the final &
                    $encodedParams = chop($encodedParams, '&');
                    $thumb_url     = $item['snippet']['thumbnails']['high']['url']; //derive the thumbnail from the video id
                    //$thumb_url = str_replace("https://", "http://", $thumb_url);
                    _logDebug("Adding channel $channelId, title " . $item['snippet']['title']);

                    $retMediaItems[] = array(
                        'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . htmlspecialchars($encodedParams),
                        'dc:title'       => $item['snippet']['title'],
                        'desc'           => $item['snippet']['description'],
                        'upnp:album_art' => $thumb_url,
                        'upnp:class'     => 'object.container',
                        'protocolInfo'   => '*:*:*:*',
                    );
                } elseif ($item['kind'] == 'youtube#playlistItem') {
                    if ($item['snippet']['resourceId']['kind'] == 'youtube#video') {
                        $videoId = $item['snippet']['resourceId']['videoId'];

                        $dataString = array(
                            'video_id' => $videoId,
                            'ClipName' => $item['snippet']['title'],
                        );
                        //create an encoded string of the parameters
                        $encodedParams = '';
                        foreach ($dataString as $key => $val) {
                            $encodedParams .= $key . '=' . rawurlencode($val) . '&';
                        }
                        //cut the final &
                        $encodedParams = chop($encodedParams, '&');
                        $thumb_url     = $item['snippet']['thumbnails']['high']['url']; //derive the thumbnail from the video id
                        $thumb_url     = str_replace('https://', 'http://', $thumb_url);
                        _logDebug("Adding video $videoId, title " . $item['snippet']['title']);

                        $retMediaItems[] = array(
                            'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . htmlspecialchars($encodedParams),
                            'res'            => PROXY_URL . '?' . htmlspecialchars($encodedParams),
                            'dc:title'       => $item['snippet']['title'],
                            'desc'           => $item['snippet']['description'],
                            'upnp:class'     => 'object.item.videoItem',
                            'upnp:album_art' => $thumb_url,
                            'protocolInfo'   => 'http-get:*:video/*:*',
                        );
                    } else {
                        // Silently ignore other playlist content
                        _logWarning('Ignoring unknown playlist content: ' . $item['snippet']['resourceId']['kind']);
                    }
                } elseif ($item['kind'] == 'youtube#playlist' || $item['id']['kind'] == 'youtube#playlist') {
                    $channelId = 0;
                    if (is_array($item['id'])) {
                        $channelId = $item['id']['playlistId'];
                    } else {
                        $channelId = $item['id'];
                    }

                    $dataString = array(
                        'cmd'        => 'playlist_videos',
                        'playlistId' => "$channelId",
                    );
                    //create an encoded string of the parameters
                    $encodedParams = '';
                    foreach ($dataString as $key => $val) {
                        $encodedParams .= $key . '=' . rawurlencode($val) . '&';
                    }
                    //cut the final &
                    $encodedParams = chop($encodedParams, '&');
                    $thumb_url     = $item['snippet']['thumbnails']['high']['url']; //derive the thumbnail from the video id
                    $thumb_url     = str_replace('https://', 'http://', $thumb_url);
                    _logDebug("Adding playlist $channelId, title " . $item['snippet']['title']);

                    $retMediaItems[] = array(
                        'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . htmlspecialchars($encodedParams),
                        'dc:title'       => $item['snippet']['title'],
                        'desc'           => $item['snippet']['description'],
                        'upnp:album_art' => $thumb_url,
                        'upnp:class'     => 'object.container',
                        'protocolInfo'   => '*:*:*:*',
                    );
                } elseif ($item['kind'] == 'youtube#subscription') {
                    $data      = array(
                        'cmd'       => 'subscription_videos',
                        'channelId' => $item['snippet']['resourceId']['channelId'],
                    );
                    $thumb_url = $item['snippet']['thumbnails']['high']['url'];
                    $thumb_url = str_replace('https://', 'http://', $thumb_url);

                    $retMediaItems[] = array(
                        'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . http_build_query($data, '', '&amp;'),
                        'dc:title'       => $item['snippet']['title'],
                        'desc'           => $item['snippet']['description'],
                        'upnp:album_art' => $thumb_url,
                        'upnp:class'     => 'object.container',
                        'protocolInfo'   => '*:*:*:*',
                    );
                } elseif ($item['kind'] == 'youtube#videoCategory') {
                    $data = array(
                        'cmd'        => 'customSearch',
                        'categoryId' => $item['id'],
                    );

                    $retMediaItems[] = array(
                        'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?' . http_build_query($data, '', '&amp;'),
                        'dc:title'       => $item['snippet']['title'],
                        'upnp:album_art' => 'http://127.0.0.1/umsp/media/YouTube.png',
                        'upnp:class'     => 'object.container',
                        'protocolInfo'   => '*:*:*:*',
                    );
                } else {
                    //This is an unknown item - ignore it
                    _logWarning('Unknown item: ' . print_r($item, true));
                }
            }

            //The next page link is added _pluginMain()
        }
    } else {
        //yep, it's not JSON. Log error or alert someone or do nothing
        showErrorMessage("Invalid reply from Youtube API: $response", $retMediaItems);
    }

    return $retMediaItems;
}

function youtubeQuery($mainPage, $params)
{
    /* $params is a hash that holds data we need to send to youtube to get a list of items
     * The parameters are from youtube's API v3. Also add the key and ask for 50 items per page
    */
    $params['key']        = DEVELOPER_KEY;
    $params['maxResults'] = 50;
    //Build the URL
    $url = "https://www.googleapis.com/youtube/v3/$mainPage?";
    foreach ($params as $item => $value) {
        $url .= $item . '=' . ( $item == 'q' ? htmlspecialchars(urlencode($value)) : htmlspecialchars($value) ) . '&';
    }
    _logInfo("Calling API URL: $url");
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //Ignore broken SSL certificate warnings
    $output = curl_exec($ch);
    curl_close($ch);
    //Output should be json formatted
    return $output;
}

function showErrorMessage($message, &$retMediaItems)
{
    _logWarning("$message");
    $retMediaItems[] = array(
        'id'             => 'umsp://plugins/' . PLUGIN_NAME . '/' . PLUGIN_NAME . '?error=1',
        'dc:title'       => htmlspecialchars("$message"),
        'upnp:class'     => 'object.container',
        'upnp:album_art' => 'http://lh5.googleusercontent.com/-oehaIv-ybxE/ThnfXJd_IyI/AAAAAAAAPCU/EBi8Gyns8zA/stop.png',
    );
}
