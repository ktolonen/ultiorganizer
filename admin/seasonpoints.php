<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/seasonpoints.functions.php';
include_once 'lib/common.functions.php';

$LAYOUT_ID = SEASONADMIN;
$html = "";
$message = "";
$season = isset($_GET['season']) ? $_GET['season'] : (isset($_POST['season']) ? $_POST['season'] : "");

if (empty($season)) {
	$title = _("Season points");
	pageTopHeadOpen($title);
	pageTopHeadClose($title);
	leftMenu($LAYOUT_ID);
	contentStart();

	$html .= "<h2>" . _("Season points") . "</h2>\n";
	$html .= "<form method='get' action='?'>\n";
	$html .= "<input type='hidden' name='view' value='admin/seasonpoints'/>\n";
	$html .= "<p>" . _("Select event") . ": <select class='dropdown' name='season'>\n";
	$seasons = Seasons();
	while ($row = mysqli_fetch_assoc($seasons)) {
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

$seasonInfo = SeasonInfo($season);

$seriesList = SeasonSeries($season);
$seriesId = isset($_GET['series']) ? (int)$_GET['series'] : (isset($_POST['series']) ? (int)$_POST['series'] : 0);
if (empty($seriesList)) {
	$title = utf8entities(SeasonName($season)) . ": " . _("Season points");
	showPage($title, "<p>" . _("No divisions defined. Define at least one division first.") . "</p>");
	exit();
}

$validSeriesIds = array();
foreach ($seriesList as $series) {
	$validSeriesIds[] = (int)$series['series_id'];
}
if (!in_array($seriesId, $validSeriesIds, true)) {
	$seriesId = (int)$seriesList[0]['series_id'];
}

$roundId = isset($_GET['round']) ? (int)$_GET['round'] : (isset($_POST['round']) ? (int)$_POST['round'] : 0);

if (!empty($_POST['delete_round'])) {
	$deleteRoundId = isset($_POST['round_id']) ? (int)$_POST['round_id'] : 0;
	if ($deleteRoundId > 0) {
		if (DeleteSeasonPointsRound($deleteRoundId)) {
			$message .= "<p>" . _("Round deleted.") . "</p>";
			if ($roundId === $deleteRoundId) {
				$roundId = 0;
			}
		} else {
			$message .= "<p class='warning'>" . _("Failed to delete round.") . "</p>";
		}
	}
}

if (!empty($_POST['add_round'])) {
	$roundNo = isset($_POST['round_no']) ? (int)$_POST['round_no'] : 0;
	$roundName = isset($_POST['round_name']) ? trim($_POST['round_name']) : "";
	if ($roundNo <= 0) {
		$message .= "<p class='warning'>" . _("Round number must be a positive integer.") . "</p>";
	} elseif ($roundName === "") {
		$message .= "<p class='warning'>" . _("Round name can not be empty.") . "</p>";
	} else {
		if (AddSeasonPointsRound($season, $seriesId, $roundNo, $roundName)) {
			$message .= "<p>" . _("Round added.") . "</p>";
		} else {
			$message .= "<p class='warning'>" . _("Failed to add round.") . "</p>";
		}
	}
}

$rounds = SeasonPointsRounds($season, $seriesId);
if ($roundId <= 0 && count($rounds)) {
	$roundId = (int)$rounds[0]['round_id'];
}

$roundIds = array();
foreach ($rounds as $round) {
	$roundIds[] = (int)$round['round_id'];
}
if ($roundId > 0 && !in_array($roundId, $roundIds, true)) {
	$roundId = count($rounds) ? (int)$rounds[0]['round_id'] : 0;
}

$teams = SeriesTeams($seriesId);
if (!empty($_POST['save_points']) && $roundId > 0) {
	$postedPoints = isset($_POST['points']) ? $_POST['points'] : array();
	$pointsByTeam = array();
	$errors = array();
	foreach ($teams as $team) {
		$teamId = (int)$team['team_id'];
		$value = isset($postedPoints[$teamId]) ? trim($postedPoints[$teamId]) : "";
		if ($value === "") {
			$value = "0";
		}
		if (!preg_match('/^\d+$/', $value)) {
			$errors[] = sprintf(_("Invalid points for %s."), utf8entities($team['name']));
			continue;
		}
		$points = (int)$value;
		if ($points > 1000) {
			$errors[] = sprintf(_("Points for %s must be 0-1000."), utf8entities($team['name']));
			continue;
		}
		$pointsByTeam[$teamId] = $points;
	}
	if (count($errors)) {
		$message .= "<p class='warning'>" . $errors[0] . "</p>";
	} else {
		if (SaveSeasonPointsRoundPoints($roundId, $pointsByTeam)) {
			$message .= "<p>" . _("Points saved.") . "</p>";
		} else {
			$message .= "<p class='warning'>" . _("Failed to save points.") . "</p>";
		}
	}
}

$roundPoints = array();
if ($roundId > 0) {
	$roundPoints = SeasonPointsRoundPoints($roundId);
}
$totals = SeasonPointsSeriesTotals($season, $seriesId);
$sortKey = isset($_GET['sort']) ? $_GET['sort'] : "";
$sortDir = (isset($_GET['dir']) && $_GET['dir'] === "desc") ? "desc" : "asc";
if ($roundId > 0 && in_array($sortKey, array("name", "points", "total"), true)) {
	usort($teams, function ($a, $b) use ($sortKey, $sortDir, $roundPoints, $totals) {
		if ($sortKey === "name") {
			$left = $a['name'];
			$right = $b['name'];
			$cmp = strcasecmp($left, $right);
		} elseif ($sortKey === "points") {
			$left = isset($roundPoints[$a['team_id']]) ? (int)$roundPoints[$a['team_id']] : 0;
			$right = isset($roundPoints[$b['team_id']]) ? (int)$roundPoints[$b['team_id']] : 0;
			$cmp = $left <=> $right;
		} else {
			$left = isset($totals[$a['team_id']]) ? (int)$totals[$a['team_id']] : 0;
			$right = isset($totals[$b['team_id']]) ? (int)$totals[$b['team_id']] : 0;
			$cmp = $left <=> $right;
		}
		if ($cmp === 0) {
			$cmp = strcasecmp($a['name'], $b['name']);
		}
		return ($sortDir === "desc") ? -$cmp : $cmp;
	});
}

$title = utf8entities(SeasonName($season)) . ": " . _("Season points");
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<h2>" . utf8entities(SeasonName($season)) . ": " . _("Season points") . "</h2>\n";
$html .= $message;
if (empty($seasonInfo['use_season_points'])) {
	$html .= "<p class='warning'>" . _("Season points are not enabled for this event.") . "</p>\n";
}

$html .= "<form method='get' action='?'>\n";
$html .= "<input type='hidden' name='view' value='admin/seasonpoints'/>\n";
$html .= "<input type='hidden' name='season' value='" . utf8entities($season) . "'/>\n";
$html .= "<p>" . _("Division") . ": <select class='dropdown' name='series'>\n";
foreach ($seriesList as $series) {
	$selected = ((int)$series['series_id'] === $seriesId) ? " selected='selected'" : "";
	$html .= "<option class='dropdown' value='" . (int)$series['series_id'] . "'$selected>" . utf8entities(U_($series['name'])) . "</option>";
}
$html .= "</select> <input class='button' type='submit' value='" . _("Select") . "'/></p>\n";
$html .= "</form>\n";

$html .= "<h3>" . _("Rounds") . "</h3>\n";
if (!count($rounds)) {
	$html .= "<p>" . _("No rounds yet.") . "</p>\n";
} else {
	$html .= "<table class='list' border='0' cellspacing='0' cellpadding='4'>\n";
	$html .= "<tr><th>" . _("Round") . "</th><th>" . _("Name") . "</th><th></th></tr>\n";
	foreach ($rounds as $round) {
		$html .= "<tr>";
		$html .= "<td class='right'>" . (int)$round['round_no'] . "</td>";
		$html .= "<td>" . utf8entities($round['name']) . "</td>";
		$html .= "<td>";
		$html .= "<form method='post' action='?view=admin/seasonpoints&amp;season=" . utf8entities($season) . "&amp;series=" . (int)$seriesId . "'>\n";
		$html .= "<input type='hidden' name='season' value='" . utf8entities($season) . "'/>\n";
		$html .= "<input type='hidden' name='series' value='" . (int)$seriesId . "'/>\n";
		$html .= "<input type='hidden' name='round_id' value='" . (int)$round['round_id'] . "'/>\n";
		$html .= "<input class='button' type='submit' name='delete_round' value='" . _("Delete") . "' onclick=\"return confirm('" . _("Delete this round?") . "');\"/>\n";
		$html .= "</form>";
		$html .= "</td>";
		$html .= "</tr>\n";
	}
	$html .= "</table>\n";
}

$nextRoundNo = 1;
foreach ($rounds as $round) {
	if ((int)$round['round_no'] >= $nextRoundNo) {
		$nextRoundNo = (int)$round['round_no'] + 1;
	}
}

$html .= "<form method='post' action='?view=admin/seasonpoints&amp;season=" . utf8entities($season) . "&amp;series=" . (int)$seriesId . "'>\n";
$html .= "<input type='hidden' name='season' value='" . utf8entities($season) . "'/>\n";
$html .= "<input type='hidden' name='series' value='" . (int)$seriesId . "'/>\n";
$html .= "<table border='0'>\n";
$html .= "<tr><td class='infocell'>" . _("Round number") . ":</td><td><input class='input' size='4' name='round_no' value='" . (int)$nextRoundNo . "'/></td></tr>\n";
$html .= "<tr><td class='infocell'>" . _("Round name") . ":</td><td><input class='input' size='30' name='round_name' value=''/></td></tr>\n";
$html .= "<tr><td></td><td><input class='button' type='submit' name='add_round' value='" . _("Add round") . "'/></td></tr>\n";
$html .= "</table>\n";
$html .= "</form>\n";

if ($roundId > 0) {
	$html .= "<h3>" . _("Round points") . "</h3>\n";
	$html .= "<p>" . _("Points must be between 0 and 1000.") . "</p>\n";
	if (count($rounds)) {
		$html .= "<form method='get' action='?'>\n";
		$html .= "<input type='hidden' name='view' value='admin/seasonpoints'/>\n";
		$html .= "<input type='hidden' name='season' value='" . utf8entities($season) . "'/>\n";
		$html .= "<input type='hidden' name='series' value='" . (int)$seriesId . "'/>\n";
		$html .= "<p>" . _("Round") . ": <select class='dropdown' name='round'>\n";
		foreach ($rounds as $round) {
			$selected = ((int)$round['round_id'] === $roundId) ? " selected='selected'" : "";
			$label = $round['round_no'] . " - " . $round['name'];
			$html .= "<option class='dropdown' value='" . (int)$round['round_id'] . "'$selected>" . utf8entities($label) . "</option>";
		}
		$html .= "</select> <input class='button' type='submit' value='" . _("Select") . "'/></p>\n";
		$html .= "</form>\n";
	}
	$baseSortUrl = "?view=admin/seasonpoints&amp;season=" . utf8entities($season) . "&amp;series=" . (int)$seriesId . "&amp;round=" . (int)$roundId;
	$nextNameDir = ($sortKey === "name" && $sortDir === "asc") ? "desc" : "asc";
	$nextPointsDir = ($sortKey === "points" && $sortDir === "asc") ? "desc" : "asc";
	$nextTotalDir = ($sortKey === "total" && $sortDir === "asc") ? "desc" : "asc";
	$html .= "<form method='post' action='?view=admin/seasonpoints&amp;season=" . utf8entities($season) . "&amp;series=" . (int)$seriesId . "&amp;round=" . (int)$roundId . "'>\n";
	$html .= "<input type='hidden' name='season' value='" . utf8entities($season) . "'/>\n";
	$html .= "<input type='hidden' name='series' value='" . (int)$seriesId . "'/>\n";
	$html .= "<input type='hidden' name='round' value='" . (int)$roundId . "'/>\n";
	$html .= "<table class='list' border='0' cellspacing='0' cellpadding='4'>\n";
	$html .= "<tr>";
	$html .= "<th><a class='thsort' href='" . $baseSortUrl . "&amp;sort=name&amp;dir=" . $nextNameDir . "'>" . _("Team") . "</a></th>";
	$html .= "<th><a class='thsort' href='" . $baseSortUrl . "&amp;sort=points&amp;dir=" . $nextPointsDir . "'>" . _("Round points") . "</a></th>";
	$html .= "<th><a class='thsort' href='" . $baseSortUrl . "&amp;sort=total&amp;dir=" . $nextTotalDir . "'>" . _("Total points") . "</a></th>";
	$html .= "</tr>\n";
	foreach ($teams as $team) {
		$teamId = (int)$team['team_id'];
		$current = isset($roundPoints[$teamId]) ? (int)$roundPoints[$teamId] : 0;
		$total = isset($totals[$teamId]) ? (int)$totals[$teamId] : 0;
		$html .= "<tr>";
		$html .= "<td>" . utf8entities($team['name']) . "</td>";
		$html .= "<td><input class='input' size='6' name='points[" . $teamId . "]' value='" . $current . "'/></td>";
		$html .= "<td class='right'>" . $total . "</td>";
		$html .= "</tr>\n";
	}
	$html .= "</table>\n";
	$html .= "<p><input class='button' type='submit' name='save_points' value='" . _("Save") . "'/></p>\n";
	$html .= "</form>\n";
}

echo $html;
contentEnd();
pageEnd();
