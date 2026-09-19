<?php

include_once __DIR__ . '/auth.php';
include_once 'menufunctions.php';
include_once 'lib/season.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/gamehistory.functions.php';

$LAYOUT_ID = SEASONADMIN;
$season = $_GET["season"];
$title = utf8entities(SeasonName($season)) . ": " . _("Scoresheet history");
$html = "";

if (!isSeasonAdmin($season)) {
    showPage($title, "<p>" . _("Insufficient user rights") . "</p>");
    return;
}

$filters = ['from' => "", 'to' => "", 'offday' => false];
foreach (['from', 'to'] as $key) {
    if (isset($_POST[$key])) {
        $filters[$key] = trim((string) $_POST[$key]);
    }
}
$filters['offday'] = !empty($_POST['offday']);

//common page
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<h2>" . $title . "</h2>\n";

$html .= "<form method='post' action='?view=admin/seasongamehistory&amp;season=" . utf8entities($season) . "'>";
$html .= "<p>";
// One label for the pair: the "From" and "To" msgids are translated as a bare
// colon in some catalogs, which renders the range as "::".
$html .= _("Last modified") . ": ";
$html .= "<input class='input' type='date' name='from' value='" . utf8entities($filters['from']) . "'/> ";
$html .= "&ndash; <input class='input' type='date' name='to' value='" . utf8entities($filters['to']) . "'/> ";
$html .= "<label><input type='checkbox' name='offday' value='1'" . ($filters['offday'] ? " checked='checked'" : "") . "/> "
    . _("Changed outside the scheduled day") . "</label> ";
$html .= "<input class='button' type='submit' name='update' value='" . _("Refresh") . "'/>";
$html .= "</p>\n";
$html .= "</form>\n";

$rows = SeasonGameHistorySummary($season, $filters);

if (empty($rows)) {
    $html .= "<p>" . _("No changes recorded") . ".</p>";
} else {
    $html .= "<table class='admintable'>\n<tr>";
    $html .= "<th style='width:8%'>" . _("Game") . "</th>";
    $html .= "<th style='width:15%'>" . _("Division") . "</th>";
    $html .= "<th style='width:25%'>" . _("Teams") . "</th>";
    $html .= "<th style='width:15%'>" . _("Scheduled") . "</th>";
    $html .= "<th style='width:15%'>" . _("Last modified") . "</th>";
    $html .= "<th style='width:12%'>" . _("User") . "</th>";
    $html .= "<th style='width:5%'>" . _("Source") . "</th>";
    $html .= "<th style='width:5%' class='right'>" . _("Changes") . "</th>";
    $html .= "</tr>\n";

    foreach ($rows as $row) {
        $gameId = intval($row['game_id']);
        $home = !empty($row['hometeam'])
            ? utf8entities($row['hometeamname'])
            : "<span class='schedulingname'>" . utf8entities(U_($row['phometeamname'])) . "</span>";
        $visitor = !empty($row['visitorteam'])
            ? utf8entities($row['visitorteamname'])
            : "<span class='schedulingname'>" . utf8entities(U_($row['pvisitorteamname'])) . "</span>";
        $scheduled = empty($row['scheduled'])
            ? ""
            : ShortDate($row['scheduled']) . " " . DefHourFormat($row['scheduled']);

        $html .= "<tr class='admintablerow'>";
        $html .= "<td><a href='?view=user/gamehistory&amp;game=" . $gameId . "'>" . $gameId . "</a></td>";
        $html .= "<td>" . utf8entities(U_($row['seriesname'])) . "</td>";
        $html .= "<td>" . $home . " - " . $visitor . "</td>";
        $html .= "<td>" . utf8entities($scheduled) . "</td>";
        $html .= "<td" . (!empty($row['offday']) ? " class='warning'" : "") . ">"
            . utf8entities(DefTimeFormat($row['lastmodified'])) . "</td>";
        $html .= "<td>" . utf8entities($row['user_id']) . "</td>";
        $html .= "<td>" . utf8entities($row['source']) . "</td>";
        $html .= "<td class='right'>" . intval($row['changes']) . "</td>";
        $html .= "</tr>\n";
    }
    $html .= "</table>\n";
    $html .= "<p class='lowlight'>" . _("Highlighted: the last change falls outside the scheduled day.") . "</p>\n";
}

echo $html;
contentEnd();
pageEnd();
