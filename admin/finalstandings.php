<?php

include_once __DIR__ . '/auth.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/standings.functions.php';

$LAYOUT_ID = SEASONADMIN;
$html = "";
$message = "";
$menutabs = [];
$season = isset($_GET['season']) ? $_GET['season'] : (isset($_POST['season']) ? $_POST['season'] : "");

if (empty($season)) {
    $title = _("Final standings");
    pageTopHeadOpen($title);
    pageTopHeadClose($title);
    leftMenu($LAYOUT_ID);
    contentStart();

    $html .= "<h2>" . _("Final standings") . "</h2>\n";
    $html .= "<form method='get' action='?'>\n";
    $html .= "<input type='hidden' name='view' value='admin/finalstandings'/>\n";
    $html .= "<p>" . _("Select event") . ": <select class='dropdown' name='season'>\n";
    foreach (Seasons() as $row) {
        $html .= "<option class='dropdown' value='" . utf8entities($row['season_id']) . "'>" . utf8entities($row['name']) . "</option>";
    }
    $html .= "</select> <input class='button' type='submit' value='" . _("Select") . "'/></p>\n";
    $html .= "</form>\n";
    echo $html;
    contentEnd();
    pageEnd();
    exit();
}

if (!isSeasonAdmin($season)) {
    die('Insufficient rights');
}

$seriesList = SeasonSeries($season);
if (empty($seriesList)) {
    $title = utf8entities(SeasonName($season)) . ": " . _("Final standings");
    showPage($title, "<p>" . _("No divisions defined. Define at least one division first.") . "</p>");
    exit();
}

$seriesId = isset($_GET['series']) ? (int) $_GET['series'] : (isset($_POST['series']) ? (int) $_POST['series'] : 0);
$validSeriesIds = [];
foreach ($seriesList as $series) {
    $validSeriesIds[] = (int) $series['series_id'];
}
if (!in_array($seriesId, $validSeriesIds, true)) {
    $seriesId = (int) $seriesList[0]['series_id'];
}

$unplayedGames = SeriesUnplayedGamesCount($seriesId);
$manualCount = count(ManualFinalStandings($seriesId));
$isPublished = HasCompleteManualFinalStandings($seriesId);

if (!empty($_POST['clear_final_standings'])) {
    if (ClearFinalStandingsOrder($season, $seriesId)) {
        $message .= "<p>" . _("Final standings cleared.") . "</p>";
    } else {
        $message .= "<p class='warning'>" . _("Failed to clear final standings.") . "</p>";
    }
}

if (!empty($_POST['save_final_standings'])) {
    $postedAssignments = isset($_POST['team_standing']) ? $_POST['team_standing'] : [];
    $incomplete = false;
    foreach (SeriesTeams($seriesId) as $team) {
        $value = isset($postedAssignments[$team['team_id']]) ? $postedAssignments[$team['team_id']] : 0;
        if ($value !== 'dq' && (int) $value < 1) {
            $incomplete = true;
            break;
        }
    }
    if ($incomplete) {
        $message .= "<p class='warning'>" . _("Assign a placement or disqualification to every team before saving.") . "</p>";
    } elseif (SaveFinalStandingsAssignments($season, $seriesId, $postedAssignments)) {
        $message .= "<p>" . _("Final standings saved.") . "</p>";
        if ($unplayedGames > 0) {
            $message .= "<p class='warning'>" . sprintf(_("Warning: %d games in this division are not completed."), $unplayedGames) . "</p>";
        }
    } else {
        $message .= "<p class='warning'>" . _("Failed to save final standings.") . "</p>";
    }
}

$unplayedGames = SeriesUnplayedGamesCount($seriesId);
$manualCount = count(ManualFinalStandings($seriesId));
$isPublished = HasCompleteManualFinalStandings($seriesId);

$adminOrder = FinalStandingsAdminOrder($season, $seriesId);
$suggestedTeams = $adminOrder['teams'];
$source = $adminOrder['source'];

$sourceText = _("Team list");
if ($source === 'manual') {
    $sourceText = $isPublished ? _("Published final standings") : _("Partially saved final standings");
} elseif ($source === 'seasonpoints') {
    $sourceText = _("Suggested by season points");
} elseif ($source === 'live') {
    $sourceText = _("Suggested by placement standings");
}

$title = utf8entities(SeasonName($season)) . ": " . _("Final standings");
pageTopHeadOpen($title);
?>
<style type="text/css">
    table.finalstandings-table select {
        min-width: 260px;
    }
</style>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<h2>" . utf8entities(SeasonName($season)) . ": " . _("Final standings") . "</h2>\n";
$html .= $message;

foreach ($seriesList as $series) {
    $menutabs[U_($series['name'])] = "?view=admin/finalstandings&season=" . $season . "&series=" . $series['series_id'];
}
$menutabs[_("...")] = "?view=admin/seasonseries&season=" . $season;
$html .= pageMenu($menutabs, "?view=admin/finalstandings&season=" . $season . "&series=" . $seriesId, false);

if ($isPublished) {
    $html .= "<p><strong>" . _("Status") . ":</strong> " . _("Final standings are published for this division.") . "</p>\n";
} else {
    $html .= "<p><strong>" . _("Status") . ":</strong> " . _("Final standings are not defined; automatic live standings are shown.") . "</p>\n";
}
if ($unplayedGames > 0) {
    $html .= "<p class='warning'>" . sprintf(_("Warning: %d games in this division are not completed."), $unplayedGames) . "</p>\n";
}
$html .= "<p>" . _("Saving affects only the selected division.") . "</p>\n";
$html .= "<p>" . _("Assign a placement or disqualification to every team. Saved placements replace the automatic live standings for this division.") . "</p>\n";
$html .= "<p>" . _("Multiple teams may share the same placement. Disqualified teams are shown last without a placement.") . "</p>\n";
$html .= "<p>" . _("Clearing reverts this division to automatic live standings.") . "</p>\n";

$allTeams = SeriesTeams($seriesId);

$html .= "<p><strong>" . _("Source") . ":</strong> " . utf8entities($sourceText) . "</p>\n";
$saveConfirm = "";
if ($unplayedGames > 0) {
    $confirmMessage = sprintf(_("There are %d games in this division that are not completed. Save final standings anyway?"), $unplayedGames);
    $saveConfirm = " onclick='return confirm(\"" . addslashes($confirmMessage) . "\")'";
}
if ($manualCount > 0) {
    $html .= "<form method='post' action='?view=admin/finalstandings&amp;season=" . utf8entities($season) . "&amp;series=" . (int) $seriesId . "'>\n";
    $html .= "<input type='hidden' name='season' value='" . utf8entities($season) . "'/>\n";
    $html .= "<input type='hidden' name='series' value='" . (int) $seriesId . "'/>\n";
    $clearConfirm = _("Clear saved final standings for this division?");
    $html .= "<p><input class='button' type='submit' name='clear_final_standings' value='" . _("Clear standings") . "' onclick='return confirm(\"" . addslashes($clearConfirm) . "\")'/></p>\n";
    $html .= "</form>\n";
}
$html .= "<form method='post' action='?view=admin/finalstandings&amp;season=" . utf8entities($season) . "&amp;series=" . (int) $seriesId . "'>\n";
$html .= "<input type='hidden' name='season' value='" . utf8entities($season) . "'/>\n";
$html .= "<input type='hidden' name='series' value='" . (int) $seriesId . "'/>\n";
$html .= "<table class='list finalstandings-table' border='0' cellspacing='0' cellpadding='4'>\n";
$html .= "<tr><th>" . _("Placement") . "</th><th>" . _("Team") . "</th><th>" . _("Set") . "</th></tr>\n";
$manualByTeam = [];
foreach (ManualFinalStandings($seriesId) as $team) {
    $manualByTeam[(int) $team['team_id']] = $team;
}
$suggestedStandingByTeam = [];
$displayTeams = [];
$seenTeams = [];
foreach ($suggestedTeams as $index => $team) {
    if (!is_array($team) || !isset($team['team_id'])) {
        continue;
    }
    $teamId = (int) $team['team_id'];
    $suggestedStandingByTeam[$teamId] = $index + 1;
    $displayTeams[] = $team;
    $seenTeams[$teamId] = true;
}
foreach ($allTeams as $team) {
    $teamId = (int) $team['team_id'];
    if (!isset($seenTeams[$teamId])) {
        $displayTeams[] = $team;
        $seenTeams[$teamId] = true;
    }
}
usort($displayTeams, function ($a, $b) use ($manualByTeam, $suggestedStandingByTeam) {
    $aId = (int) $a['team_id'];
    $bId = (int) $b['team_id'];
    $aManual = isset($manualByTeam[$aId]) ? $manualByTeam[$aId] : null;
    $bManual = isset($manualByTeam[$bId]) ? $manualByTeam[$bId] : null;
    $aDisqualified = is_array($aManual) && (int) $aManual['disqualified'] === 1;
    $bDisqualified = is_array($bManual) && (int) $bManual['disqualified'] === 1;
    if ($aDisqualified !== $bDisqualified) {
        return $aDisqualified ? 1 : -1;
    }
    $aStanding = is_array($aManual) && !$aDisqualified ? (int) $aManual['standing'] : ($suggestedStandingByTeam[$aId] ?? 9999);
    $bStanding = is_array($bManual) && !$bDisqualified ? (int) $bManual['standing'] : ($suggestedStandingByTeam[$bId] ?? 9999);
    if ($aStanding !== $bStanding) {
        return $aStanding <=> $bStanding;
    }
    return strcasecmp($a['name'], $b['name']);
});

foreach ($displayTeams as $team) {
    $teamId = (int) $team['team_id'];
    $manual = isset($manualByTeam[$teamId]) ? $manualByTeam[$teamId] : null;
    $selectedValue = "0";
    if (is_array($manual)) {
        if ((int) $manual['disqualified'] === 1) {
            $selectedValue = "dq";
        } else {
            $selectedValue = (string) ((int) $manual['standing']);
        }
    } elseif ($manualCount === 0 && isset($suggestedStandingByTeam[$teamId])) {
        $selectedValue = (string) $suggestedStandingByTeam[$teamId];
    }

    $placement = _("Undecided");
    if (is_array($manual)) {
        $placement = FinalStandingLabel((int) $manual['standing'], (int) $manual['disqualified'] === 1);
    }
    $html .= "<tr>";
    $html .= "<td>" . utf8entities($placement) . "</td>";
    $html .= "<td>" . utf8entities($team['name']) . "</td>";
    $html .= "<td><select class='dropdown finalstandings-team' name='team_standing[" . $teamId . "]'>\n";
    $selected = ($selectedValue === "0") ? " selected='selected'" : "";
    $html .= "<option class='dropdown' value='0'$selected>" . _("Undecided") . "</option>\n";
    for ($standing = 1; $standing <= count($allTeams); $standing++) {
        $selected = ($selectedValue === (string) $standing) ? " selected='selected'" : "";
        $html .= "<option class='dropdown' value='" . $standing . "'$selected>" . utf8entities(FinalStandingLabel($standing)) . "</option>\n";
    }
    $selected = ($selectedValue === "dq") ? " selected='selected'" : "";
    $html .= "<option class='dropdown' value='dq'$selected>" . _("Disqualified") . "</option>\n";
    $html .= "</select></td>";
    $html .= "</tr>\n";
}
$html .= "</table>\n";
$html .= "<p><input class='button' type='submit' name='save_final_standings' value='" . _("Save standings") . "'$saveConfirm/></p>\n";
$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();
