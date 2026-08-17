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

    $pools = $_POST["pools"];

    foreach ($pools as $poolId) {
        PoolRecolor(intval($poolId));
    }
}

//season selection
$html .= "<form method='post' id='tables' action='?view=plugins/update_pool_colors'>\n";

if (empty($seasonId)) {
    $html .= "<p>" . ("Select event") . ": <select class='dropdown' name='season'>\n";

    $seasons = Seasons();

    foreach ($seasons as $row) {
        $html .= "<option class='dropdown' value='" . utf8entities($row['season_id']) . "'>" . utf8entities(U_($row['name'])) . "</option>";
    }

    $html .= "</select></p>\n";
    $html .= "<p><input class='button' type='submit' name='select' value='" . ("Select") . "'/></p>";
} else {

    $html .= "<p>" . ("Select pools to change color") . ":</p>\n";
    $html .= "<p>" . ("Pools that cannot be told apart from another pool of the same division are preselected."
        . " Colors are shown the way the pool standings pages draw them.") . "</p>\n";
    $html .= "<table>";
    $html .= "<tr><th><input type='checkbox' onclick='checkAll(\"tables\");'/></th>";
    $html .= "<th>" . ("Pool") . "</th>";
    $html .= "<th>" . ("Division") . "</th>";
    $html .= "<th>" . ("Too similar to") . "</th>";
    $html .= "</tr>\n";

    $conflictCount = 0;
    $series = SeasonSeries($seasonId);
    foreach ($series as $row) {

        $pools = SeriesPools($row['series_id']);
        foreach ($pools as $pool) {
            $poolinfo = PoolInfo($pool['pool_id']);
            $conflicts = PoolColorConflicts($pool['pool_id']);

            $similar = [];
            foreach ($conflicts as $conflict) {
                $similar[] = utf8entities(U_($conflict['name']));
            }
            if (!empty($similar)) {
                $conflictCount++;
            }

            $html .= "<tr style='" . PoolColorStyle($poolinfo['color']) . "'>";
            $html .= "<td class='center'><input type='checkbox' name='pools[]' value='" . utf8entities($pool['pool_id']) . "'"
                . (empty($similar) ? "" : " checked='checked'") . " /></td>";
            $html .= "<td>" . utf8entities(U_($pool['name'])) . "</td>";
            $html .= "<td>" . utf8entities(U_($row['name'])) . "</td>";
            $html .= "<td>" . implode(", ", $similar) . "</td>";
            $html .= "</tr>\n";
        }
    }
    $html .= "</table>\n";
    $html .= "<p>" . ($conflictCount > 0
        ? sprintf(("Pools that share their color with another pool of the same division: %d"), $conflictCount)
        : ("Every pool has a color of its own.")) . "</p>\n";
    $html .= "<p><input class='button' type='submit' name='simulate' value='" . ("Update") . "'/></p>";
    $html .= "<div>";
    $html .= "<input type='hidden' name='season' value='$seasonId' />\n";
    $html .= "</div>\n";
}

$html .= "</form>";

showPage($title, $html);
?>
