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
title = "Team generator"
description = "Generate Teams and add them into series."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()) {
    die('Insufficient user rights');
}

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$html = "";
$title = ("Team generator");
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
        $feedback .= "<p class='warning'>" . _("Please select a valid division.") . "</p>\n";
    } elseif ($amount < 1) {
        $feedback .= "<p class='warning'>" . _("Number of teams to generate must be a positive integer.") . "</p>\n";
    } else {
        $countries = CountryList(true);
        $max = count($countries) - 1;

        if ($max < 0) {
            $feedback .= "<p class='warning'>" . _("No countries available for team generation.") . "</p>\n";
        } else {
            for ($i = 1; $i <= $amount; $i++) {
                $id = AddSeriesEnrolledTeam($seriesId, $_SESSION['uid'], "Team $i", "Club $i", $countries[rand(0, $max)]['name']);
                ConfirmEnrolledTeam($seriesId, $id);
            }
        }
    }
}

//season selection
$html .= "<form method='post' enctype='multipart/form-data' action='?view=plugins/generate_teams'>\n";
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
    $series = SeasonSeries($seasonId);
    if (empty($series)) {
        $html .= "<p class='warning'>" . _("The selected event has no divisions.") . "</p>\n";
    } else {
        $html .= "<p>" . ("Select division") . ":	<select class='dropdown' name='seriesid'>\n";
        foreach ($series as $row) {
            $html .= "<option class='dropdown' value='" . utf8entities($row['series_id']) . "'>" . utf8entities($row['name']) . "</option>";
        }
        $html .= "</select></p>\n";

        $html .= "<p>" . ("Number of Teams to generate") . ": <input class='input' maxlength='2' size='2' name='amount' value='20'/></p>\n";

        $html .= "<p>";
        $html .= "<p><input class='button' type='submit' name='generate' value='" . ("Generate") . "'/></p>";
    }
    $html .= "<div>";
    $html .= "<input type='hidden' name='season' value='$seasonId' />\n";
    $html .= "</div>\n";
}

$html .= "</form>";

showPage($title, $html);
?>
