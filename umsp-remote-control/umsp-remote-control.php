<?php

function _pluginMain($prmQuery) {
  $url = 'http://' . $_SERVER['HTTP_HOST'] . '/umsp/plugins/umsp-remote-control/web/';
  $Items[] = array(
    'id' => 'umsp://plugins/umsp-remote-control/1',
    'res' => $url,
    'dc:title' => 'From a web browser visit:',
    'upnp:class' => 'object.item.videoitem',
    'protocolInfo' => 'http-get:*:video/*:*',
  );
  $Items[] = array(
    'id' => 'umsp://plugins/umsp-remote-control/2',
    'res' => $url,
    'dc:title' => $url,
    'upnp:class' => 'object.item.videoitem',
    'protocolInfo' => 'http-get:*:video/*:*',
  );
  return $Items;
}

?>
