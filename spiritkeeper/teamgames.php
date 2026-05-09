<?php

include_once __DIR__ . '/auth.php';
spiritkeeperRequireAuth(__FILE__, 'teamgames', 'either');

// $token and $teamId are set up by spiritkeeper/index.php before this file
// is included; spiritkeeperRequireAuth() above blocks direct access. With
// 'either' auth mode $token may be empty when reached via full login.
/** @var string $token */
/** @var int $teamId */

$pageHtml = "";
$renderGameCard = function ($game, $contextTeamId, $actionUrl, $buttonLabel, $statusNote = "") {
    $contextTeamId = (int) $contextTeamId;
    $gameId = isset($game['game_id']) ? (int) $game['game_id'] : 0;
    $ratedTeamId = SpiritTokenRatedTeamId($game, $contextTeamId);
    if ($gameId <= 0 || $ratedTeamId <= 0) {
        return '';
    }

    $categories = SpiritCategories((int) $game['spiritmode']);
    $opponentName = ($ratedTeamId === (int) $game['hometeam']) ? $game['hometeamname'] : $game['visitorteamname'];
    $ownSubmitted = SpiritTokenHasOwnSubmission($gameId, $contextTeamId, $game);
    $receivedSubmitted = SpiritTokenHasReceivedSubmission($gameId, $contextTeamId, $game);
    $canViewReceived = SpiritTokenCanViewReceivedPoints($gameId, $contextTeamId, $game);
    $givenPoints = $ownSubmitted ? GameGetSpiritPoints($gameId, $ratedTeamId) : [];
    $receivedPoints = $canViewReceived ? GameGetSpiritPoints($gameId, $contextTeamId) : [];

    $html = "<section class='card'>";
    $html .= "<h2>" . utf8entities($game['hometeamname']) . " - " . utf8entities($game['visitorteamname']) . "</h2>";
    $html .= "<p class='mobile-meta'>" . utf8entities(SpiritkeeperGameTimeLabel($game));
    if (!empty($game['poolname'])) {
        $html .= " | " . utf8entities($game['poolname']);
    }
    $html .= "</p>";
    $html .= "<p><strong>" . _("Score") . ":</strong> " . utf8entities(SpiritkeeperGameScoreLabel($game)) . "</p>";

    if ($ownSubmitted) {
        $html .= "<p class='mobile-summary'><strong>" . _("Spirit score given for") . " " . utf8entities($opponentName) . ":</strong> " . utf8entities(SpiritPointsSummary($givenPoints, $categories)) . "</p>";
        if ($canViewReceived) {
            $html .= "<p class='mobile-summary'><strong>" . _("Spirit score received") . ":</strong> " . utf8entities(SpiritPointsSummary($receivedPoints, $categories)) . "</p>";
        } elseif ($receivedSubmitted) {
            $html .= "<p class='mobile-status'>" . _("The opponent spirit score is available, but not visible through this page.") . "</p>";
        } else {
            $html .= "<p class='mobile-status'>" . _("The opponent has not submitted a spirit score for this game yet.") . "</p>";
        }
    } elseif ((int) $game['hasstarted'] <= 0) {
        $html .= "<p class='mobile-status'>" . _("Game not started yet.") . "</p>";
    } else {
        $html .= "<p class='mobile-status'>" . _("You have not submitted a spirit score for this game yet.") . "</p>";
    }

    if ($actionUrl !== '' || $statusNote !== '') {
        $html .= "<div class='mobile-actions'>";
        if ($actionUrl !== '') {
            $html .= "<a href='" . $actionUrl . "' data-role='button' data-ajax='false'>" . $buttonLabel . "</a>";
        }
        if ($statusNote !== '') {
            $html .= "<p class='mobile-status'>" . $statusNote . "</p>";
        }
        $html .= "</div>";
    }
    $html .= "</section>";

    return $html;
};

if ($token !== '') {
    $games = SpiritTokenGameRows($teamId);

    if (empty($games)) {
        $pageHtml .= "<div class='card'><p>" . _("No spirit-scored games found for this team.") . "</p></div>";
        return;
    }

    foreach ($games as $game) {
        $gameId = (int) $game['game_id'];
        $canSubmit = SpiritTokenCanSubmit($gameId, $teamId, $game);
        $ownSubmitted = SpiritTokenHasOwnSubmission($gameId, $teamId, $game);
        $actionUrl = $canSubmit ? '?view=submitsotg&amp;token=' . urlencode($token) . '&amp;game=' . $gameId : '';
        $buttonLabel = $ownSubmitted ? _("Edit Spirit Score") : _("Submit Spirit Score");
        $statusNote = (!$canSubmit && $ownSubmitted) ? _("Your spirit submission is locked.") : '';
        $pageHtml .= $renderGameCard($game, $teamId, $actionUrl, $buttonLabel, $statusNote);
    }

    $pageHtml .= "<div class='card'>";
    $pageHtml .= "<p>" . _("Spirit notes can be added and edited on the token submission page.") . "</p>";
    $pageHtml .= "<p>" . _("Opponent spirit notes are not shown in the public token flow.") . "</p>";
    $pageHtml .= "</div>";
    return;
}

$selectedTeamId = GetInt('team');
$selectedSeasonId = GetString('season');
$selectedTeam = [];
foreach (SpiritkeeperCurrentAccessibleTeams() as $team) {
    if ((int) $team['team_id'] !== $selectedTeamId) {
        continue;
    }
    $selectedTeam = $team;
    $selectedSeasonId = isset($team['season_id']) ? (string) $team['season_id'] : $selectedSeasonId;
    break;
}

if (empty($selectedTeam) || $selectedTeamId <= 0) {
    $pageHtml .= "<div class='mobile-notice mobile-notice--error'><p>" . _("Invalid team.") . "</p></div>";
    $pageHtml .= "<div class='mobile-actions'><a href='" . SpiritkeeperHomeUrl($selectedSeasonId, '') . "' data-role='button' data-ajax='false'>" . _("Back") . "</a></div>";
    return;
}

$pageTitle = _("Spiritkeeper") . " - " . $selectedTeam['name'];
$games = SpiritTokenGameRows($selectedTeamId);

$pageHtml .= "<div class='card spiritkeeper-team-card'>";
$pageHtml .= "<p>" . _("Spiritkeeper is the dedicated mobile surface for spirit score entry and review.") . "</p>";
if (!empty($selectedTeam['seasonname'])) {
    $pageHtml .= "<p><strong>" . _("Event") . ":</strong> " . utf8entities($selectedTeam['seasonname']) . "</p>";
}
$label = $selectedTeam['name'];
if (!empty($selectedTeam['seriesname'])) {
    $label .= " [" . $selectedTeam['seriesname'] . "]";
}
$pageHtml .= "<p><strong>" . _("Selected team") . ":</strong> " . utf8entities($label) . "</p>";
$pageHtml .= "<div class='mobile-actions'><a href='" . SpiritkeeperHomeUrl($selectedSeasonId, '') . "' data-role='button' data-ajax='false'>" . _("Change team") . "</a></div>";
$pageHtml .= "</div>";

if (empty($games)) {
    $pageHtml .= "<div class='card'><p>" . _("No spirit-scored games found for the selected team.") . "</p></div>";
    return;
}

foreach ($games as $game) {
    $gameId = (int) $game['game_id'];
    $ratedTeamId = SpiritTokenRatedTeamId($game, $selectedTeamId);
    if ($ratedTeamId <= 0) {
        continue;
    }

    $submitted = TeamSpiritSubmissionComplete($gameId, $ratedTeamId, (int) $game['spiritmode']);
    $canEdit = CanEditSpiritSubmission($gameId, $ratedTeamId);
    $buttonLabel = $canEdit ? ($submitted ? _("Edit Spirit Score") : _("Enter Spirit Score")) : _("Review Spirit Score");
    $statusNote = !$canEdit ? _("This spirit submission is currently read-only.") : '';
    $pageHtml .= $renderGameCard($game, $selectedTeamId, SpiritkeeperEditGameUrl($gameId, $selectedTeamId), $buttonLabel, $statusNote);
}
