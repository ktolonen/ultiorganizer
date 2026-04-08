<?php
include_once __DIR__ . '/auth.php';
pluginRequireAdmin(__FILE__);

ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=updater
format=any
security=superadmin
customization=all

[DESCRIPTION]
title = "Pool color updater"
description = "Automatically updates pool colors based on predefined list."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()) {
	die('Insufficient user rights');
}

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/standings.functions.php';
if (!function_exists('PoolColors')) {
	include_once 'lib/pool.functions.php';
}

$html = "";
$title = ("Pool color updater");
$seasonId = "";
$colors = PoolColors();

if (!empty($_POST['season'])) {
	$seasonId = $_POST['season'];
}

if (isset($_POST['simulate']) && !empty($_POST['pools'])) {

	$pools = $_POST["pools"];

	foreach ($pools as $poolId) {
		$color = $colors[array_rand($colors)];
		$query = "UPDATE uo_pool SET color='" . $color . "' WHERE pool_id=" . $poolId;
		DBQuery($query);
	}
}

//season selection
$html .= "<form method='post' id='tables' action='?view=plugins/update_pool_colors'>\n";

if (empty($seasonId)) {
	$html .= "<p>" . ("Select event") . ": <select class='dropdown' name='season'>\n";

	$seasons = Seasons();

	foreach ($seasons as $row) {
		$html .= "<option class='dropdown' value='" . utf8entities($row['season_id']) . "'>" . utf8entities($row['name']) . "</option>";
	}

	$html .= "</select></p>\n";
	$html .= "<p><input class='button' type='submit' name='select' value='" . ("Select") . "'/></p>";
} else {

	$html .= "<p>" . ("Select pools to change color") . ":</p>\n";
	$html .= "<table>";
	$html .= "<tr><th><input type='checkbox' onclick='checkAll(\"tables\");'/></th>";
	$html .= "<th>" . ("Pool") . "</th>";
	$html .= "<th>" . ("Series") . "</th>";
	$html .= "</tr>\n";

	$series = SeasonSeries($seasonId);
	foreach ($series as $row) {

		$pools = SeriesPools($row['series_id']);
		foreach ($pools as $pool) {
			$poolinfo = PoolInfo($pool['pool_id']);
			$html .= "<tr style='background-color:#" . $poolinfo['color'] . "'>";
			$html .= "<td class='center'><input type='checkbox' name='pools[]' value='" . utf8entities($pool['pool_id']) . "' /></td>";
			$html .= "<td>" . $pool['name'] . "</td>";
			$html .= "<td>" . $row['name'] . "</td>";
			$html .= "</tr>\n";
		}
	}
	$html .= "</table>\n";
	$html .= "<p><input class='button' type='submit' name='simulate' value='" . ("Update") . "'/></p>";
	$html .= "<div>";
	$html .= "<input type='hidden' name='season' value='$seasonId' />\n";
	$html .= "</div>\n";
}

$html .= "</form>";

showPage($title, $html);
?>
