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

if (!empty($_POST['season'])) {
    $seasonId = $_POST['season'];
}

if (isset($_POST['simulate']) && !empty($_POST['pools'])) {

    $pools = array_values(array_unique(array_map('intval', (array) $_POST["pools"])));
    sort($pools);

    $selected = [];
    foreach ($pools as $poolId) {
        $poolinfo = PoolInfo($poolId);
        if (!empty($poolinfo)) {
            $selected[(int) $poolinfo['series']][] = $poolId;
        }
    }

    foreach ($selected as $seriesId => $seriesPools) {
        // Part of a division re-rolls, because the deterministic pick would hand
        // each pool back the color it already has.
        $pickRandomColor = count($seriesPools) < count(SeriesPools($seriesId));

        // Written one at a time so each pick sees the colors written before it.
        foreach ($seriesPools as $poolId) {
            SetPoolDetails($poolId, [
                "color" => PoolPickColor($seriesId, $poolId, $pickRandomColor),
            ]);
        }
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

    $html .= "<p><input type='checkbox' onclick='checkAll(\"tables\");'/> " . ("Select pools to change color") . ":</p>\n";

    $series = SeasonSeries($seasonId);
    foreach ($series as $row) {

        $pools = SeriesPools($row['series_id']);
        if (empty($pools)) {
            continue;
        }

        $groupId = "series" . (int) $row['series_id'];
        $html .= "<h2>" . utf8entities(U_($row['name'])) . "</h2>\n";
        $html .= "<table id='" . $groupId . "'>";
        $html .= "<tr><th><input type='checkbox' onclick='checkPoolGroup(\"" . $groupId . "\");'/></th>";
        $html .= "<th>" . ("Pool") . "</th>";
        $html .= "</tr>\n";

        foreach ($pools as $pool) {
            $poolinfo = PoolInfo($pool['pool_id']);
            // Tinted the way pool status pages draw it.
            $colorcoding = "background-color:#" . $poolinfo['color'] . ";background-color:" . RGBtoRGBa($poolinfo['color'], 0.3) . ";color:#" . textColor($poolinfo['color']);
            $html .= "<tr style='" . $colorcoding . "'>";
            $html .= "<td class='center'><input class='pool' type='checkbox' name='pools[]' value='" . utf8entities($pool['pool_id']) . "' /></td>";
            $html .= "<td>" . utf8entities(U_($pool['name'])) . "</td>";
            $html .= "</tr>\n";
        }
        $html .= "</table>\n";
    }
    $html .= "<p><input class='button' type='submit' name='simulate' value='" . ("Update") . "'/></p>";
    $html .= "<div>";
    $html .= "<input type='hidden' name='season' value='$seasonId' />\n";
    $html .= "</div>\n";
}

$html .= "</form>";

// checkAll() flips a whole form; the division headers need one table.
$html .= "<script type='text/javascript'>\n";
$html .= "function checkPoolGroup(id) {\n";
$html .= "  var group = document.getElementById(id);\n";
$html .= "  var inputs = group.getElementsByTagName('input');\n";
$html .= "  for (var i = 0; i < inputs.length; i++) {\n";
$html .= "    if (inputs[i].className === 'pool') {\n";
$html .= "      inputs[i].checked = !inputs[i].checked;\n";
$html .= "    }\n";
$html .= "  }\n";
$html .= "}\n";
$html .= "</script>\n";

showPage($title, $html);
?>
