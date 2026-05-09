<?php
include_once __DIR__ . '/auth.php';
spiritkeeperRequireAuth(__FILE__, 'home');

$pageTitle = _("Spiritkeeper");
$pageHtml = "";
$teams = SpiritkeeperCurrentAccessibleTeams();
$currentSeasons = SpiritkeeperCurrentSeasons();

if (empty($currentSeasons)) {
	$pageHtml .= "<div class='card'>";
	$pageHtml .= "<p>" . _("Spiritkeeper is the dedicated mobile surface for spirit score entry and review.") . "</p>";
	$pageHtml .= "<p>" . _("No current events are available.") . "</p>";
	$pageHtml .= "</div>";
	return;
}

$selectedSeasonId = GetString('season');
$selectedTeamId = GetInt('team');

if ($selectedTeamId > 0) {
	foreach ($teams as $team) {
		if ((int)$team['team_id'] === $selectedTeamId) {
			header("location:" . SpiritkeeperTeamGamesUrl($selectedTeamId, $team['season_id'], ''));
			exit();
		}
	}
}

if ($selectedSeasonId === '' || !isset($currentSeasons[$selectedSeasonId])) {
	$seasonIds = array_keys($currentSeasons);
	$selectedSeasonId = (string)$seasonIds[0];
}

if (count($currentSeasons) === 1 && count($teams) === 1 && !empty($teams[0]['team_id'])) {
	header("location:" . SpiritkeeperTeamGamesUrl((int)$teams[0]['team_id'], $teams[0]['season_id'], ''));
	exit();
}

$seasonTeams = SpiritkeeperSeasonAccessibleTeams($selectedSeasonId);
$seasonGroups = SpiritkeeperSeasonTeamGroups($selectedSeasonId);

$pageHtml .= "<div class='card'>";
$pageHtml .= "<p>" . _("Spiritkeeper is the dedicated mobile surface for spirit score entry and review.") . "</p>";
if (count($currentSeasons) > 1) {
	$pageHtml .= "<form action='?view=home' method='get' data-ajax='false'>\n";
	$pageHtml .= "<input type='hidden' name='view' value='home'/>";
	$pageHtml .= "<label for='season'>" . _("Select event") . "</label>";
	$pageHtml .= "<select id='season' name='season' onchange='this.form.submit()'>";
	foreach ($currentSeasons as $season) {
		$selected = ($season['season_id'] === $selectedSeasonId) ? " selected='selected'" : "";
		$pageHtml .= "<option value='" . utf8entities($season['season_id']) . "'" . $selected . ">" . utf8entities($season['name']) . "</option>";
	}
	$pageHtml .= "</select>";
	$pageHtml .= "<noscript><div class='mobile-actions'><input type='submit' value='" . _("Show") . "'/></div></noscript>";
	$pageHtml .= "</form>";
} elseif (!empty($currentSeasons[$selectedSeasonId])) {
	$pageHtml .= "<p><strong>" . _("Event") . ":</strong> " . utf8entities($currentSeasons[$selectedSeasonId]['name']) . "</p>";
}

$pageHtml .= "<p>" . _("Teams in selected event") . ":</p>";

if (empty($seasonTeams)) {
	$pageHtml .= "<p class='mobile-status'>" . _("No teams are available for spirit entry in the selected event with your current user rights.") . "</p>";
} else {
	foreach ($seasonGroups as $group) {
		$pageHtml .= "<details class='resp-location-toggle' open>";
		$pageHtml .= "<summary class='resp-location-title'>" . utf8entities($group['seriesname']) . "</summary>";
		$pageHtml .= "<div class='mobile-actions'>";
		foreach ($group['teams'] as $team) {
			$pageHtml .= "<a href='" . SpiritkeeperTeamGamesUrl((int)$team['team_id'], $selectedSeasonId, '') . "' data-role='button' data-ajax='false'>" . utf8entities($team['name']) . "</a>";
		}
		$pageHtml .= "</div>";
		$pageHtml .= "</details>";
	}
}
$pageHtml .= "</div>";
?>
