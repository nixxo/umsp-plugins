<?php

include 'info.php';

// Does this WEC version support custom hooks?
if ((defined('WECVERSION')) && (WECVERSION >= 3)) {

	include_once $_SERVER['DOCUMENT_ROOT'] . '/umsp/funcs-config.php';
	// Insert badge if we have one
	if ((isset($pluginInfo['thumb'])) && ($pluginInfo['thumb'] != '')) {
		$desc = '<div style="float: left; padding: 4px 10px 4px 4px;"><img src="' . $pluginInfo['thumb'] . '" width="60" height="60" alt="logo"></div>'
		. '<div>' . $pluginInfo['name'] . " v" . $pluginInfo['version'] . " (" . $pluginInfo['date'] . ") by "
		. $pluginInfo['author'] . ".<br>" . $pluginInfo['desc'] . "<br>Information: <a href='" . $pluginInfo['url'] . "'>" . $pluginInfo['url'] . "</a>"
		. '</div>';
	} elseif ((isset($pluginInfo['art'])) && ($pluginInfo['art'] != '')) {
		$desc = '<div style="float: left; padding: 4px 10px 4px 4px;"><img src="' . $pluginInfo['art'] . '" width="60" height="60" alt="logo"></div>'
		. '<div>' . $pluginInfo['name'] . " v" . $pluginInfo['version'] . " (" . $pluginInfo['date'] . ") by "
		. $pluginInfo['author'] . ".<br>" . $pluginInfo['desc'] . "<br>Information: <a href='" . $pluginInfo['url'] . "'>" . $pluginInfo['url'] . "</a>"
		. '</div>';
	} else {
		$desc = $pluginInfo['name'] . ' v' . $pluginInfo['version'] . ' (' . $pluginInfo['date'] . ') by '
		. $pluginInfo['author'] . '.<br>' . $pluginInfo['desc'] . "<br>Information: <a href='" . $pluginInfo['url'] . "'>" . $pluginInfo['url'] . '</a>';
	}

	$key = strtoupper("{$pluginInfo['id']}_DESC");
	$wec_options[$key] = array(
		'configname' => $key,
		'configdesc' => $desc,
		'longdesc' => '',
		'group' => $pluginInfo['name'],
		'type' => WECT_DESC,
		'page' => WECP_UMSP,
		'displaypri' => -50,
		'availval' => array(),
		'availvalname' => array(),
		'defaultval' => '',
		'currentval' => '',
	);

	$wec_options[$pluginInfo['id']] = array(
		'configname' => $pluginInfo['id'],
		'configdesc' => 'Enable ' . $pluginInfo['name'] . ' UMSP plugin',
		'longdesc' => '',
		'group' => $pluginInfo['name'],
		'type' => WECT_BOOL,
		'page' => WECP_UMSP,
		'displaypri' => -45,
		'availval' => array('off', 'on'),
		'availvalname' => array(),
		'defaultval' => '',
		'currentval' => '',
		'readhook' => wec_umspwrap_read,
		'writehook' => wec_umspwrap_write,
		'backuphook' => NULL,
		'restorehook' => NULL,
	);

	$wec_options['RAIREPLAY_PREFER_HD'] = array(
		'configname' => 'RAIREPLAY_PREFER_HD',
		'configdesc' => 'Prefer HD quality',
		'longdesc' => '',
		'group' => $pluginInfo['name'],
		'type' => WECT_BOOL,
		'page' => WECP_UMSP,
		'displaypri' => -20,
		'availval' => array('0', '1'),
		'availvalname' => array(),
		'defaultval' => '1',
		'currentval' => '',
	);
}

?>
