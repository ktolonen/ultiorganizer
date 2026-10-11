<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/season.functions.php';
include_once 'lib/statistical.functions.php';
include_once 'lib/scoresheethistory.functions.php';

$title = _("Event statistics");
$LAYOUT_ID = CALCSEASONSTATISTICS;
$html = "";

$season = $_GET["season"];
$missing_profiles_count = SeasonMissingPlayerProfilesCount($season);
$seasons = [];
$series = [];
$teams = [];

if (!empty($_POST['calc'])) {
    set_time_limit(120);
    CalcSeasonStats($season);
    CalcSeriesStats($season);
    CalcTeamStats($season);
    CalcTeamSpiritStats($season);
    CalcPlayerStats($season);
    SetEventReadonly($season);
    if (isSuperAdmin() && !empty($_POST['deleteeventroles'])) {
        $deletedRoles = DeleteEventUserRoles($season);
        if ($deletedRoles > 0) {
            $html .= "<p>" . sprintf(_("Deleted %d event access rights."), $deletedRoles) . "</p>";
        } else {
            $html .= "<p>" . _("No event access rights found.") . "</p>";
        }
    }
    if (isSuperAdmin() && !empty($_POST['deletescoresheethistory'])) {
        $deletedHistory = DeleteEventScoresheetHistory($season);
        if ($deletedHistory > 0) {
            $html .= "<p>" . sprintf(_("Deleted %d scoresheet change history entries."), $deletedHistory) . "</p>";
        } else {
            $html .= "<p>" . _("No scoresheet change history found.") . "</p>";
        }
    }
}
if (!empty($_POST['undo'])) {
    set_time_limit(120);
    DeleteSeasonStats($season);
}

//common page
pageTopHeadOpen($title);
?>

<style type="text/css">
    div.workarea {
        padding-left: 25px;
        float: left
    }

    ol.draglist {
        position: relative;
        width: 100px;
        background: #f7f7f7;
        border: 1px solid gray;
        margin: 0;
        padding: 0;
    }

    ol.draglist li {
        margin: 1px;
        cursor: move;
        zoom: 1;
    }

    li.list {
        background-color: #D1E6EC;
        border: 1px solid #7EA6B2;
    }
</style>


<script type="text/javascript" src="script/sortablejs/Sortable.min.js"></script>
<script type="text/javascript" src="script/sortable-lists.js"></script>
<link rel="stylesheet" type="text/css" href="script/sortable-lists.css" />
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
$html .= "<form method='post' action='?view=admin/stats&amp;season=$season'>\n";
$html .= "<p>" . _("Calculation of statistics takes some time; please wait without closing the browser.") . "</p>\n";
$finalStandingsStatus = FinalStandingsSeasonStatus($season);
if ($finalStandingsStatus['published'] > 0) {
    $html .= "<p>" . sprintf(_("Confirmed final standings will be used for %d divisions."), $finalStandingsStatus['published']) . "</p>\n";
}
if ($finalStandingsStatus['unpublished'] > 0) {
    $html .= "<p class='warning'>" . sprintf(_("Final standings are not defined for %d divisions; live standings will be used."), $finalStandingsStatus['unpublished']) . "</p>\n";
}

$deleteRolesOption = "";
if (isSuperAdmin()) {
    $deleteRolesOption = "<p><label><input type='checkbox' name='deleteeventroles' value='1' checked='checked'/> " . _("Delete event access rights after archiving statistics") . "</label></p>\n";
    $deleteRolesOption .= "<p>" . _("This prevents accidental changes after the event is archived. User accounts, global administrator rights, player profile rights, pool visibility, and administration menu visibility are not changed.") . "</p>\n";
}

// Left unchecked, unlike the access rights above: deleted rights can be
// granted again, but the change history is the only copy of what it holds.
$deleteHistoryOption = "";
if (isSuperAdmin()) {
    $deleteHistoryOption = "<p><label><input type='checkbox' name='deletescoresheethistory' value='1'/> " . _("Delete scoresheet change history after archiving statistics") . "</label></p>\n";
    $deleteHistoryOption .= "<p>" . _("This frees the space the change history takes. Results, rosters and scoresheets are not changed, but the record of who changed them and the option to restore an earlier version are lost permanently.") . "</p>\n";
}

if (!IsSeasonStatsCalculated($season)) {
    if ($missing_profiles_count > 0) {
        $html .= "<p><strong>" . sprintf(_("There are %d players without a profile id. Player statistics will be skipped for them."), $missing_profiles_count) . "</strong></p>\n";
    }
    $confirm_attr = "";
    if ($missing_profiles_count > 0) {
        $confirm_msg = sprintf(_("There are %d players without a profile id. Run statistics anyway?"), $missing_profiles_count);
        $confirm_attr = " onclick='return confirm(\"" . addslashes($confirm_msg) . "\")'";
    }
    $html .= $deleteRolesOption;
    $html .= $deleteHistoryOption;
    $html .= "<p><input class='button' name='calc' type='submit' value='" . _("Calculate") . "'" . $confirm_attr . "/></p>\n";
} else {
    $seasons = SeasonStatistics($season);
    if (!$seasons) {
        $seasons = [
            'teams' => 0,
            'players' => 0,
            'games' => 0,
        ];
    }
    $series = SeriesStatistics($season);
    $teams = SeasonTeamStatistics($season);

    $html .= "<ul>";
    $html .= "<li>" . _("Teams") . ": " . $seasons['teams'] . "</li>\n";
    $html .= "<li>" . _("Players") . ": " . $seasons['players'] . "</li>\n";
    $html .= "<li>" . _("Games") . ": " . $seasons['games'] . "</li>\n";
    $html .= "<li>" . _("Division") . ": " . count($series) . "</li>\n";
    $html .= "</ul>";
    if ($missing_profiles_count > 0) {
        $html .= "<p><strong>" . sprintf(_("There are %d players without a profile id. Player statistics will be skipped for them."), $missing_profiles_count) . "</strong></p>\n";
    }
    $confirm_attr = "";
    if ($missing_profiles_count > 0) {
        $confirm_msg = sprintf(_("There are %d players without a profile id. Run statistics anyway?"), $missing_profiles_count);
        $confirm_attr = " onclick='return confirm(\"" . addslashes($confirm_msg) . "\")'";
    }
    $html .= $deleteRolesOption;
    $html .= $deleteHistoryOption;
    $html .= "<p><input class='button' name='calc' type='submit' value='" . _("Recalculate") . "'" . $confirm_attr . "/></p>\n";
    $html .= "<p><input class='button' name='undo' type='submit' value='" . _("Undo") . "'/></p>\n";

    $prevseries = -1;
    $html .= "<h1>" . _("Final standings") . "</h1>";
    $html .= "<p>" . _("If the final standings are not correct, use the mouse to drag teams into the correct order and press Save final standings.") . "\n";
    $html .= "<input type='button' id='saveButton' value='" . _("Save final standings") . "'/></p>\n";
    $html .= "<div id='responseStatus'></div>\n";

    foreach ($teams as $team) {
        if ($team['series'] != $prevseries) {
            if ($prevseries != -1) {
                $html .= "</ol>";
                $html .= "</div>";
            }
            $html .= "<div class='workarea'>";
            $html .= "<h3>" . utf8entities(U_($team['seriesname'])) . "</h3>";
            $html .= "<ol id='ol" . $team['series'] . "' class='draglist'>\n";
            $html .= "<li class='list' id='li" . $team['team_id'] . "'>" . utf8entities($team['teamname']) . "</li>\n";

            $prevseries = $team['series'];
        } else {
            $html .= "<li class='list' id='li" . $team['team_id'] . "'>" . utf8entities($team['teamname']) . "</li>\n";
        }
    }

    if ($prevseries != -1) {
        $html .= "</ol>";
        $html .= "</div>";
    }
}

$html .= "</form>\n";
echo $html;
?>
<script type="text/javascript">
    (function() {
        var lists = document.querySelectorAll("ol.draglist");
        Array.prototype.forEach.call(lists, function(list) {
            makeSortableLists([list]);
        });
        var saveButton = document.getElementById("saveButton");
        if (!saveButton) {
            return;
        }
        saveButton.addEventListener("click", function() {
            var request = Array.prototype.map.call(lists, function(list) {
                return sortedListIds(list, "li").join(":");
            }).join("|");
            postSortedLists("index.php?view=admin/saveteamstandings", request, document.getElementById("responseStatus"));
        });
    })();
</script>
<?php
contentEnd();
pageEnd();

?>
