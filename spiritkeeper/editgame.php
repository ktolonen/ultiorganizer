<?php
include_once __DIR__ . '/auth.php';
spiritkeeperRequireAuth(__FILE__, 'editgame');

$pageHtml = "";
$gameId = GetInt('game');
$teamId = GetInt('team');
$pageTitle = _("Spiritkeeper");

if ($gameId <= 0) {
	$pageHtml .= "<div class='mobile-notice mobile-notice--error'><p>" . _("Invalid game.") . "</p></div>";
	return;
}

$season = SeasonInfo(GameSeason($gameId));
$game_result = GameResult($gameId);
$entryTeamId = SpiritEntryTeamForUser($gameId);
$hasFullSpiritView = HasFullGameSpiritViewRight($gameId);
$homeTeamId = isset($game_result['hometeam']) ? (int)$game_result['hometeam'] : 0;
$visitorTeamId = isset($game_result['visitorteam']) ? (int)$game_result['visitorteam'] : 0;

if ($entryTeamId < 0) {
	$pageHtml .= "<div class='mobile-notice mobile-notice--error'><p>" . _("Insufficient user rights") . "</p></div>";
	return;
}

if ($teamId <= 0 && $entryTeamId > 0) {
	$teamId = $entryTeamId;
} elseif ($teamId <= 0 && ($hasFullSpiritView || $entryTeamId === 0)) {
	$teamId = $homeTeamId;
}

if ($teamId !== $homeTeamId && $teamId !== $visitorTeamId) {
	$pageHtml .= "<div class='mobile-notice mobile-notice--error'><p>" . _("Invalid team.") . "</p></div>";
	return;
}

if (
	!$hasFullSpiritView &&
	!hasEditPlayersRight((int)$teamId)
) {
	$pageHtml .= "<div class='mobile-notice mobile-notice--error'><p>" . _("Insufficient user rights") . "</p></div>";
	return;
}

if ($season['spiritmode'] <= 0) {
	$pageHtml .= "<div class='card'><p>" . sprintf(_("Spirit points not given for %s."), utf8entities($season['name'])) . "</p></div>";
	return;
}

$pageTitle = _("Spiritkeeper") . " - " . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']);
$responsibleTeamId = (int)$teamId;
$ratedTeamId = ($responsibleTeamId === $homeTeamId) ? $visitorTeamId : $homeTeamId;
$ratesHomeTeam = ($ratedTeamId === $homeTeamId);
$ratedTeamName = $ratesHomeTeam ? $game_result['hometeamname'] : $game_result['visitorteamname'];
$categories = SpiritCategories($season['spiritmode']);
$spiritType = SpiritCommentTypeForTeam($game_result, $ratedTeamId);
$commentFeedback = "";
$spiritComment = CommentRaw($spiritType, $gameId);
$spiritCommentMetaHtml = CommentMetaHtml(GameCommentMeta($gameId, $spiritType));
$canCreateComment = CanCreateSpiritComment($game_result, $ratedTeamId);
$canManageComment = CanManageSpiritComment($gameId, $spiritType);
$showCommentForm = ($canCreateComment || $canManageComment);
$saveFeedback = "";
$saveSuccess = false;

if (!empty($_POST['save'])) {
	$points = array();
	$fieldPrefix = $ratesHomeTeam ? 'homecat' : 'viscat';
	$valueField = $ratesHomeTeam ? 'homevalueId' : 'visvalueId';
	foreach ((array)($_POST[$valueField] ?? array()) as $cat) {
		if (isset($_POST[$fieldPrefix . $cat])) {
			$points[$cat] = $_POST[$fieldPrefix . $cat];
		} else {
			$saveFeedback = sprintf(_("Missing score for %s. "), $ratedTeamName);
		}
	}
	if (empty($saveFeedback) && !GameSetSpiritPoints($gameId, $ratedTeamId, $ratesHomeTeam, $points, $categories)) {
		$saveFeedback = _("Spirit score not saved. ");
	}

	$deleteComment = !empty($_POST['delete_spirit_comment']);
	if (isset($_POST['spiritcomment']) || $deleteComment) {
		$saved = SetSpiritComment($game_result, $ratedTeamId, $_POST['spiritcomment'], $deleteComment);
		if (!$saved) {
			$commentFeedback = "<p class='warning'>" . _("Comment not saved.") . "</p>\n";
		}
		$spiritComment = CommentRaw($spiritType, $gameId);
		$spiritCommentMetaHtml = CommentMetaHtml(GameCommentMeta($gameId, $spiritType));
	}

	$game_result = GameResult($gameId);
	if (empty($saveFeedback)) {
		$saveSuccess = true;
	}
}

$pageHtml .= "<section class='card'>";
$pageHtml .= "<h2>" . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . "</h2>";
$pageHtml .= "<p class='mobile-meta'>" . utf8entities(SpiritkeeperGameTimeLabel($game_result)) . "</p>";
$pageHtml .= "<p><strong>" . _("Score") . ":</strong> " . utf8entities(SpiritkeeperGameScoreLabel($game_result)) . "</p>";
if ($saveSuccess) {
	$pageHtml .= "<div class='mobile-notice mobile-notice--success'><p>" . _("Spirit score successfully submitted.") . "</p></div>";
}
$pageHtml .= "<form action='" . SpiritkeeperEditGameUrl($gameId, $teamId) . "' method='post' data-ajax='false'>\n";

$pageHtml .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($ratedTeamName) . "</h3>\n";
$points = GameGetSpiritPoints($gameId, $ratedTeamId);
$pageHtml .= SpiritTable($game_result, $points, $categories, $ratesHomeTeam, false);

if ($showCommentForm) {
	$pageHtml .= "<p><b>" . _("Spirit note") . "</b></p>";
	$pageHtml .= "<p>" . $spiritCommentMetaHtml . "</p>";
	$pageHtml .= "<textarea name='spiritcomment' rows='4' cols='40' maxlength='" . COMMENT_MAX_LENGTH . "' placeholder='" . _("Optional - add context for spirit points given (no blame).") . "'>" . htmlentities($spiritComment) . "</textarea>";
	if ($canManageComment && !empty($spiritComment)) {
		$pageHtml .= "<label><input type='checkbox' name='delete_spirit_comment' value='1'/> " . _("Delete comment") . "</label>";
	}
	$pageHtml .= $commentFeedback;
}

$canSaveSpirit = CanEditSpiritSubmission($gameId, $ratedTeamId);
$pageHtml .= "<div class='mobile-actions'>";
if ($canSaveSpirit) {
	$pageHtml .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save") . "'/>";
	if (!empty($saveFeedback)) {
		$pageHtml .= "<p class='mobile-status'>" . $saveFeedback . "</p>";
	}
} else {
	$pageHtml .= "<span class='warning'>" . _("Read-only spirit review") . "</span>";
}
$pageHtml .= "</div>";
$pageHtml .= "</form>\n";

if ($hasFullSpiritView || $entryTeamId === 0) {
	$otherResponsibleTeamId = ($responsibleTeamId === $homeTeamId) ? $visitorTeamId : $homeTeamId;
	$otherRatedTeamName = ($otherResponsibleTeamId === $homeTeamId) ? $game_result['visitorteamname'] : $game_result['hometeamname'];
	$pageHtml .= "<div class='mobile-actions'><a href='" . SpiritkeeperEditGameUrl($gameId, $otherResponsibleTeamId) . "' data-role='button' data-ajax='false'>" . _("Spirit points for") . " " . utf8entities($otherRatedTeamName) . "</a></div>";
}

$pageHtml .= "<div class='mobile-actions'><a href='" . SpiritkeeperTeamGamesUrl($responsibleTeamId, $season['season_id'], '') . "' data-role='button' data-ajax='false'>" . _("Back") . "</a></div>";
$pageHtml .= "</section>";
?>
