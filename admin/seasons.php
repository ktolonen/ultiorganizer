<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/season.functions.php';
include_once 'lib/statistical.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/configuration.functions.php';

$LAYOUT_ID = SEASONS;

$title = _("Events");
$html = "";


if (!isSuperAdmin()) {
    Forbidden(isset($_SESSION['uid']) ? $_SESSION['uid'] : 'anonymous');
}

//season parameters
$sp = [
    "season_id" => "",
    "name" => "",
    "type" => "",
    "starttime" => "",
    "endtime" => "",
    "iscurrent" => 0,
];

if (!empty($_GET["season"])) {
    $info = SeasonInfo($_GET["season"]);
    $sp['season_id'] = $info['season_id'];
    $sp['name'] = $info['name'];
    $sp['type'] = $info['type'];
    $sp['starttime'] = $info['starttime'];
    $sp['endtime'] = $info['endtime'];
    $sp['iscurrent'] = $info['iscurrent'];
}

//process itself on submit
if (!empty($_POST['remove_x']) && !empty($_POST['hiddenDeleteId'])) {
    $id = $_POST['hiddenDeleteId'];
    $ok = true;
    //run some test to for safe deletion
    $series = SeasonSeries($id);
    if (count($series)) {
        $html .= "<p class='warning'>" . _("Event has") . " " . count($series) . " " . _("Division(s)") . ". " . _("Divisions must be removed before removing the event") . ".</p>";
        $ok = false;
    }
    $cur = CurrentSeason();

    if ($cur == $id) {
        $html .= "<p class='warning'>" . _("You cannot remove a current event") . ".</p>";
        $ok = false;
    }
    if ($ok) {
        DeleteSeason($id);
        //remove rights from deleted season
        $propId = getPropId($_SESSION['uid'], 'editseason', $id);
        RemoveEditSeason($_SESSION['uid'], $propId);
        $propId = getPropId($_SESSION['uid'], 'userrole', 'seasonadmin:' . $id);
        RemoveUserRole($_SESSION['uid'], $propId);
    }
}
//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
	function setId(id) {
		var input = document.getElementById("hiddenDeleteId");

		var answer = confirm('<?php echo _("Are you sure you want to delete the event?"); ?>');
		if (answer) {
			input.value = id;
		} else {
			input.value = "";
		}
	}
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .=  "<form method='post' action='?view=admin/seasons'>";

$html .=  "<h2>" . _("Events") . "</h2>\n";

$html .=  "<table style='white-space: nowrap;width:90%' border='0' cellpadding='4px'>\n";

$html .=  "<tr>
	<th>" . _("Name") . "</th>
	<th>" . _("Type") . "</th>
	<th>" . _("Starts") . "</th>
	<th>" . _("Enrollment") . "</th>
	<th>" . _("Public") . "</th>
	<th>" . _("Visibility") . "</th>
	<th>" . _("Maintenance") . "</th>
	<th>" . _("Operations") . "</th>
	<th></th>
	</tr>\n";

$seasons = Seasons();

foreach ($seasons as $row) {
    $info = SeasonInfo($row['season_id']);
    $warnings = [];
    if (empty($info['event_readonly']) && !IsSelfRegistrationDisabled() && (int) $info['scorekeeping_app'] === SCOREKEEPING_APP_USERS) {
        $warnings[] = _("Anyone can register and change results using the scorekeeping app.");
    }
    if (empty($info['event_readonly']) && !empty($info['anonymous_scorekeeping'])) {
        $warnings[] = _("Anyone with a scorekeeping link can change results without logging in.");
    }

    $html .=  "<tr>";
    $html .= "<td>";
    if ($warnings) {
        $tipId = 'scorekeeping-warning-' . utf8entities($info['season_id']);
        $html .= "<button type='button' class='warning warning-icon' popovertarget='" . $tipId . "' aria-label='" . utf8entities(implode(' ', $warnings)) . "'>&#9888;</button> ";
        $html .= "<div id='" . $tipId . "' class='warning-tip' popover>";
        foreach ($warnings as $warning) {
            $html .= "<p>" . utf8entities($warning) . "</p>";
        }
        $html .= "</div>";
    }
    $html .= "<a href='?view=admin/addseasons&amp;season=" . $info['season_id'] . "'>" . utf8entities(U_($info['name'])) . "</a>";
    $html .= "</td>";

    if (!empty($info['type'])) {
        $html .=  "<td>" . U_($info['type']) . "</td>";
    } else {
        $html .=  "<td>?</td>";
    }

    if (!empty($info['starttime'])) {
        $html .=  "<td>" . ShortDate($info['starttime']) . "</td>";
    } else {
        $html .=  "<td>-</td>";
    }

    $enrollment = intval($info['enrollopen']) ? _("open") : _("closed");
    $html .=  "<td>" . $enrollment . "</td>";

    $public = !empty($info['public_event']) ? _("yes") : "<span class='warning'>" . _("no") . "</span>";
    $html .=  "<td>" . $public . "</td>";

    $visible = intval($info['iscurrent']) ? _("yes") : "<span class='warning'>" . _("no") . "</span>";
    $apiVisible = !empty($info['api_public']) ? _("yes") : "<span class='warning'>" . _("no") . "</span>";
    $html .=  "<td>" . $visible . "/" . $apiVisible . "</td>";

    $maintenance = !empty($info['maintenance_mode']) ? _("yes") : _("no");
    $html .=  "<td>" . $maintenance . "</td>";

    $html .=  "<td>";
    if (!CanDeleteSeason($row['season_id'])) {
        if (IsSeasonStatsCalculated($row['season_id'])) {
            $html .=  "<a href='?view=admin/stats&amp;season=" . $info['season_id'] . "'>" . _("Re-calc. stats") . "</a>";
        } else {
            $html .=  "<a href='?view=admin/stats&amp;season=" . $info['season_id'] . "'><b>" . _("Calc. stats") . "</b></a>";
        }
    }
    $html .= " | <a href='?view=admin/addseasonusers&amp;season=" . $info['season_id'] . "'>" . _("Users") . "</a>";
    $html .= " | <a href='?view=admin/eventdataexport&amp;season=" . $info['season_id'] . "'>" . _("Export") . "</a>";
    $html .=  "</td>";

    if (CanDeleteSeason($row['season_id'])) {
        $html .=  "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"setId('" . $row['season_id'] . "');\"/></td>";
    }
    $html .=  "</tr>\n";
}

$html .=  "</table>";
$html .=  "<div>";
$html .= "<a href='?view=admin/eventdataimport'>" . _("Import event") . "</a> | ";
$html .= "<a href='?view=admin/seasonstats'>" . _("All event statistics") . "</a></div>";
$html .=  "<p><input class='button' name='add' type='button' value='" . _("Add") . "' onclick=\"window.location.href='?view=admin/addseasons'\"/></p>";
$html .=  "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
$html .=  "</form>\n";

echo $html;

contentEnd();
pageEnd();
?>
