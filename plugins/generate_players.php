<?php
include_once __DIR__ . '/auth.php';
pluginRequireAdmin(__FILE__);

ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=generator
format=any
security=superadmin
customization=all

[DESCRIPTION]
title = "Player generator"
description = "Generate players and add them into team roster."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()) {
    die('Insufficient user rights');
}

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$html = "";
$title = ("Player generator");
$seasonId = "";
$feedback = "";

if (!empty($_POST['season'])) {
    $seasonId = $_POST['season'];
}

if (isset($_POST['generate'])) {
    $seriesId = isset($_POST['seriesid']) ? (int) $_POST['seriesid'] : 0;
    $amount = intval($_POST['amount']);
    $seriesInfo = $seriesId > 0 ? SeriesInfo($seriesId) : null;

    if (empty($seriesInfo) || $seriesInfo['season'] !== $seasonId) {
        $feedback .= "<p class='warning'>" . ("Please select a valid division.") . "</p>\n";
    } elseif ($amount < 1) {
        $feedback .= "<p class='warning'>" . ("Number of players to generate must be a positive integer.") . "</p>\n";
    } else {
        $teams = SeriesTeams($seriesId);
        $added = 0;
        $skipped = 0;
        $rows = "";

        foreach ($teams as $t) {
            // Skip names already on the roster so a resubmitted form does not duplicate players.
            $existing = [];
            foreach (TeamPlayerList($t['team_id']) as $player) {
                $existing[$player['firstname'] . "\n" . $player['lastname']] = true;
            }
            $teamAdded = 0;
            for ($i = 1; $i <= $amount; $i++) {
                if (isset($existing["Player $i\nUltimate"])) {
                    $skipped++;
                    continue;
                }
                AddPlayer($t['team_id'], "Player $i", "Ultimate", "", $i);
                $teamAdded++;
            }
            $added += $teamAdded;
            $rows .= "<tr class='admintablerow'><td>" . utf8entities($t['name']) . "</td><td class='right'>" . $teamAdded . "</td><td class='right'>" . count(TeamPlayerList($t['team_id'])) . "</td></tr>\n";
        }

        if (empty($teams)) {
            $feedback .= "<p class='warning'>" . sprintf(("Division %s has no teams."), utf8entities($seriesInfo['name'])) . "</p>\n";
        } else {
            $feedback .= "<p>" . sprintf(("Added %d players to %d teams in %s."), $added, count($teams), utf8entities($seriesInfo['name'])) . "</p>\n";
            if ($skipped > 0) {
                $feedback .= "<p class='warning'>" . sprintf(("Skipped %d players already on the roster."), $skipped) . "</p>\n";
            }
            $feedback .= "<table class='admintable'>\n";
            $feedback .= "<tr><th>" . ("Team") . "</th><th class='right'>" . ("Added") . "</th><th class='right'>" . ("Players") . "</th></tr>\n";
            $feedback .= $rows;
            $feedback .= "</table>\n";
        }
    }
}

//season selection
$html .= "<form method='post' enctype='multipart/form-data' action='?view=plugins/generate_players'>\n";
$html .= $feedback;

if (empty($seasonId)) {
    $html .= "<p>" . ("Select event") . ": <select class='dropdown' name='season'>\n";

    $seasons = Seasons();

    foreach ($seasons as $row) {
        $html .= "<option class='dropdown' value='" . utf8entities($row['season_id']) . "'>" . utf8entities($row['name']) . "</option>";
    }

    $html .= "</select></p>\n";
    $html .= "<p><input class='button' type='submit' name='select' value='" . ("Select") . "'/></p>";
} else {

    $html .= "<p>" . ("Select division") . ":	<select class='dropdown' name='seriesid'>\n";
    $series = SeasonSeries($seasonId);
    foreach ($series as $row) {
        $selected = (isset($_POST['seriesid']) && (int) $_POST['seriesid'] === (int) $row['series_id']) ? " selected='selected'" : "";
        $html .= "<option class='dropdown' value='" . utf8entities($row['series_id']) . "'$selected>" . utf8entities($row['name']) . "</option>";
    }
    $html .= "</select></p>\n";

    $html .= "<p>" . ("Number of players to generate for a team") . ": <input class='input' maxlength='2' size='2' name='amount' value='20'/></p>\n";

    $html .= "<p><input class='button' type='submit' name='generate' value='" . ("Generate") . "'/></p>";
    $html .= "<div>";
    $html .= "<input type='hidden' name='season' value='$seasonId' />\n";
    $html .= "</div>\n";
}

$html .= "</form>";

showPage($title, $html);
?>
