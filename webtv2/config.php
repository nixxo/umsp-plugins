<?php
include "info.php";
if (!defined("_DONT_RUN_CONFIG_")) {
	include_once "/usr/share/umsp/funcs-config.php";
	if (isset($_GET["pluginStatus"])) {
		$writeResult = _writePluginStatus($pluginInfo["id"], $_GET["pluginStatus"]);
	}

	$pluginStatus = _readPluginStatus($pluginInfo["id"]);
	if ($pluginStatus === null) {
		$pluginStatus = "off";
	}

	echo _configMainHTML($pluginInfo, $pluginStatus);
	echo "</body>";
	echo "</html>";
}
?>
