<?php
include_once __DIR__ . '/auth.php';
include_once 'menufunctions.php';
include_once 'lib/privacy.functions.php';

$LAYOUT_ID = DBADMIN;
$title = _("Player privacy tools");
$html = "";
$message = "";
$search = isset($_POST['player_search']) ? trim((string)$_POST['player_search']) : "";
$matches = array();
$selectedPlayerId = isset($_POST['selected_player_id']) ? (int)$_POST['selected_player_id'] : 0;
$selectedSubject = null;

if (!isSuperAdmin()) {
	Forbidden(isset($_SESSION['uid']) ? $_SESSION['uid'] : 'anonymous');
}

if ($selectedPlayerId > 0) {
	$selectedSubject = PrivacyGetPlayerSubject($selectedPlayerId);
}

if (!empty($_POST['search_players'])) {
	$matches = PrivacyPlayerMatches($search);
} elseif ($search !== '') {
	$matches = PrivacyPlayerMatches($search);
}

if (!empty($_POST['download_report'])) {
	if (!empty($selectedSubject)) {
		try {
			PrivacyLogPlayerReportExport($selectedPlayerId, $_SESSION['uid']);
			$report = PrivacyRenderPlayerReportText($selectedPlayerId, $_SESSION['uid']);
			PrivacyDownloadTextFile(PrivacyPlayerReportFilename($selectedPlayerId), $report);
		} catch (Exception $e) {
			$message = "<p class='warning'>" . _("Player privacy report could not be generated.") . "</p>";
		}
	} else {
		$message = "<p class='warning'>" . _("Select one player before downloading the privacy report.") . "</p>";
	}
} elseif (!empty($_POST['anonymize_player'])) {
	if (!empty($selectedSubject)) {
		try {
			PrivacyAnonymizePlayer($selectedPlayerId, $_SESSION['uid']);
			$message = "<p class='success'>" . _("Player data was anonymized.") . "</p>";
			$selectedSubject = PrivacyGetPlayerSubject($selectedPlayerId);
		} catch (Exception $e) {
			$message = "<p class='warning'>" . _("Player data could not be anonymized.") . "</p>";
		}
	} else {
		$message = "<p class='warning'>" . _("Select one player before anonymizing data.") . "</p>";
	}
}

pageTopHeadOpen($title);
?>
<script type="text/javascript">
	function confirmAnonymizePlayer() {
		return confirm('<?php echo addslashes(_("Are you sure you want to anonymize the selected player? This removes identifying player data but keeps competition history.")); ?>');
	}
</script>
<?php
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<p><a href='?view=admin/dbadmin'>&laquo; " . _("Return to Database administration") . "</a></p>";
$html .= "<h2>" . $title . "</h2>";
$html .= $message;

$html .= "<form method='post' action='?view=admin/privacyplayer'>";
$html .= "<table>";
$html .= "<tr><td>" . _("Player name") . ":</td><td><input class='input' type='text' name='player_search' value='" . utf8entities($search) . "'/></td></tr>";
$html .= "<tr><td colspan='2'><input class='button' type='submit' name='search_players' value='" . _("Search") . "'/></td></tr>";
$html .= "</table>";
$html .= "</form>";

if ($search !== '') {
	$html .= "<h3>" . _("Search results") . "</h3>";
	if (empty($matches)) {
		$html .= "<p>" . _("No players matched the search.") . "</p>";
	} else {
		$html .= "<form method='post' action='?view=admin/privacyplayer'>";
		$html .= "<input type='hidden' name='player_search' value='" . utf8entities($search) . "'/>";
		$html .= "<table>";
		$html .= "<tr><th></th><th>" . _("Name") . "</th><th>" . _("Event") . "</th><th>" . _("Division") . "</th><th>" . _("Team") . "</th><th>" . _("Email") . "</th></tr>";
		foreach ($matches as $match) {
			$checked = $selectedPlayerId === (int)$match['player_id'] ? " checked='checked'" : "";
			$html .= "<tr>";
			$html .= "<td><input type='radio' name='selected_player_id' value='" . (int)$match['player_id'] . "'" . $checked . "/></td>";
			$html .= "<td>" . utf8entities(trim($match['player_name'])) . "</td>";
			$html .= "<td>" . utf8entities((string)$match['season_name']) . "</td>";
			$html .= "<td>" . utf8entities((string)$match['series_name']) . "</td>";
			$html .= "<td>" . utf8entities((string)$match['team_name']) . "</td>";
			$html .= "<td>" . utf8entities((string)$match['email']) . "</td>";
			$html .= "</tr>";
		}
		$html .= "</table>";
		$html .= "<p><input class='button' type='submit' name='select_player' value='" . _("Select") . "'/></p>";
		$html .= "</form>";
	}
}

if (!empty($selectedSubject)) {
	$html .= "<h3>" . _("Selected player") . "</h3>";
	$html .= "<p>" . utf8entities(PrivacyPlayerIdentityLabel($selectedSubject)) . "</p>";
	$html .= "<p>" . sprintf(_("Linked player rows: %d"), count($selectedSubject['players'])) . "</p>";
	$html .= "<form method='post' action='?view=admin/privacyplayer'>";
	$html .= "<input type='hidden' name='player_search' value='" . utf8entities($search) . "'/>";
	$html .= "<input type='hidden' name='selected_player_id' value='" . (int)$selectedPlayerId . "'/>";
	$html .= "<input class='button' type='submit' name='download_report' value='" . _("Download player privacy report") . "'/> ";
	$html .= "<input class='button' type='submit' name='anonymize_player' value='" . _("Anonymize player data") . "' onclick='return confirmAnonymizePlayer();'/>";
	$html .= "</form>";
}

echo $html;
contentEnd();
pageEnd();
?>
