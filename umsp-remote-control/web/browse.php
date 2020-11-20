<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
  <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
  <meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=no;" />
  <title>Web-based UMSP Remote Control</title>
  <style type="text/css">
    a {
      color: white;
    }
    a.item {
      text-decoration: none;
      color: #BBB;
    }
    img.item {
      max-width: 150px;
      max-height: 150px;
      padding-right: 5px;
    }
    div.item {
      background: none repeat scroll 0 0 rgba(0, 0, 0, 0.6);
      border: 1px solid #808080;
      border-radius: 10px;
      padding: 10px;
      margin: 5px;
    }
    div.item:hover {
      background: none repeat scroll 0 0 rgba(24, 24, 208, 0.3);
      color: white;
    }
    div.cell {
      display: table-cell;
      vertical-align: middle;
    }
  </style>
</head>
<body style="font-family:verdana, arial, sans-serif; font-size:76%; background-color: #333; color: #BBB;">

<?php

if (!isset($_GET['plugin']) && isset($_GET['URI']) && isset($_GET['URIMetaData'])) {
  if (urc_startsWith($_GET['URI'], 'file://')) {
    debugExec('sudo play ' . escapeshellarg(str_replace('file://', '', $_GET['URI'])));
  } else {
    debugExec('sudo upnp-cmd SetAVTransportURI ' . escapeshellarg($_GET['URI']) . ' ' . escapeshellarg($_GET['URIMetaData']));
    debugExec('sudo upnp-cmd Play');
  }
  debugExec('sleep 2');
  debugExec('sudo upnp-cmd GetMediaInfo');
  echo '<a target="_blank" href="/addons/remote/">Remote</a><br>';
  echo '<a target="_blank" href="/addons/systools/nowplaying.php">Device State</a><br>';
  exit;
}

include_once($_SERVER['DOCUMENT_ROOT'] . '/umsp/funcs-misc.php');
include_once($_SERVER['DOCUMENT_ROOT'] . '/umsp/funcs-upnp.php');

$arrItems = NULL;
if (isset($_GET['plugin'])) {
  if ($_GET['plugin'] == 'local') {
    include_once($_SERVER['DOCUMENT_ROOT'] . '/umsp/funcs-local.php');
    $arrItems = _localMain($_GET['path'], http_build_query($_GET));
  } else {
    $arrItems = _callPlugin('umsp/' . $_GET['plugin'], http_build_query($_GET));
  }
} elseif (isset($_POST['search_string'])) {
  $arrItems = _callPluginSearch('and dc:title contains "' . $_POST['search_string'] . '"');
}

if (function_exists('_pluginSearch')) {
  $search_string = isset($_POST['search_string']) ? $_POST['search_string'] : '';
  echo
'<form method="post" action="' . $_SERVER['PHP_SELF'] . '">
  Search:
  <input type="text" size=30 name="search_string" value="' . $search_string . '"/>
  <input type="submit" value="OK"/>
</form>
';
}

if (is_null($arrItems)) {
  if (!empty($_POST) || !empty($_GET)) {
    echo 'No results.';
  } else {
    include ($_SERVER['DOCUMENT_ROOT'] . '/umsp/media-items.php');
    $arrItems = $myMediaItems;
  }
}

if (!empty($arrItems)) {
  foreach ($arrItems as $item) {
    if (isset($item['res']) && isset($item['upnp:class']) && strtolower(trim($item['upnp:class'])) !== 'object.container') {
      $item['res'] = urc_convertToLocalAddress($item['res']);
      $metadata = urc_xmlString(_createDIDL(array($item)));
      $metadata = str_replace('&amp;', '&', $metadata);
      $url = $_SERVER['SCRIPT_NAME'] . '?' . http_build_query(array('URI' => $item['res'], 'URIMetaData' => $metadata));
    } else {
      if (urc_startsWith($item['id'], 'umsp://plugins/')) {
        $umspUrl = parse_url($item['id']);
        $url = $_SERVER['SCRIPT_NAME'] . '?plugin=' . basename($umspUrl['path']) . (isset($umspUrl['query']) && $umspUrl['query'] != '' ? '&amp;' . $umspUrl['query'] : '');
      } else if (urc_startsWith($item['id'], 'umsp://local/')) {
        $umspUrl = parse_url($item['id']);
        $url = $_SERVER['SCRIPT_NAME'] . '?plugin=local&path=' . $umspUrl['path'] . (isset($umspUrl['query']) && $umspUrl['query'] != '' ? '&amp;' . $umspUrl['query'] : '');
      } else {
        $url = $item['id'];
      }
    }
    $url = str_replace('&amp;', '&', $url);
    $url = str_replace('&', '&amp;', $url);
    $img = '';
    if (isset($item['upnp:album_art'])) {
      $imgUrl = $item['upnp:album_art'];
      if (urc_startsWith($imgUrl, '/')) {
        $imgUrl = 'img-proxy.php?url=' . urlencode($imgUrl);
      } else {
        $imgUrl = urc_convertToRemoteHost($imgUrl);
      }
      $img = '<img class="item" src="' . $imgUrl . '"/>';
    }
    $title = $item['dc:title'];
    $desc = '<b>' . $title . '</b>';
    if (isset($item['desc']) && $item['desc'] != $title) {
      $desc .= '<br>' . str_replace("\n", "<br>", $item['desc']);
    }
    echo
'<a class="item" href="' . $url . '">
  <div class="item">
    <div class="cell">' . $img . '</div>
    <div class="cell">' . $desc . '</div>
  </div>
</a>
';
  }
}

function debugExec($cmd) {
  echo 'Executing:<br>';
  echo htmlspecialchars($cmd) . '</br>';
  $output = shell_exec($cmd);
  echo 'Output:<br>';
  echo htmlspecialchars($output) . '</br>';
}

function urc_xmlString($doc) {
  $result = '';
  $doc->formatOutput = false;
  $doc->preserveWhiteSpace = false;
  foreach($doc->childNodes as $node) {
    $result .= $doc->saveXML($node);
  }
  return $result;
}

function urc_startsWith($haystack, $needle) {
  return $needle === "" || strpos($haystack, $needle) === 0;
}

function urc_convertToLocalAddress($url) {
  return str_replace(
      Array('/' . $_SERVER['HTTP_HOST'] . '/',
            '/' . $_SERVER['SERVER_ADDR'] . ':' . $_SERVER['SERVER_PORT'] . '/'),
      '/127.0.0.1/',
      $url);
}

function urc_convertToRemoteHost($url) {
  return str_replace(
      Array('/127.0.0.1/',
            '/localhost/'),
      '/' . $_SERVER['HTTP_HOST'] . '/',
      $url);
}

?>

</body>
</html>
