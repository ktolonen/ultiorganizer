<?php
include_once __DIR__ . '/auth.php';
spiritkeeperRequireAuth(__FILE__, 'submitsotg', 'token');

$pageHtml = "";
$gameId = GetInt('game');
$game = SpiritTokenGame($gameId, $teamId);

if ($gameId <= 0 || empty($game)) {
	$pageHtml .= "<div class='mobile-notice mobile-notice--error'><p>" . _("Invalid game.") . "</p></div>";
	$pageHtml .= "<p><a class='button-secondary' href='?view=teamgames&amp;token=" . urlencode($token) . "' data-role='button'>" . _("Back to game list") . "</a></p>";
	return;
}

$ratedTeamId = SpiritTokenRatedTeamId($game, $teamId);
$categories = SpiritCategories((int)$game['spiritmode']);
$orderedCategories = SpiritOrderedCategories($categories);
$opponentName = ($ratedTeamId === (int)$game['hometeam']) ? $game['hometeamname'] : $game['visitorteamname'];
$existingPoints = GameGetSpiritPoints($gameId, $ratedTeamId);
$defaultPoints = empty($existingPoints) ? SpiritDefaultPoints($categories) : $existingPoints;
$canSubmit = SpiritTokenCanSubmit($gameId, $teamId, $game);
$commentType = SpiritCommentTypeForTeam($game, $ratedTeamId);
$spiritComment = $commentType > 0 ? CommentRaw($commentType, $gameId) : "";
$commentFeedback = "";

if (!empty($_POST['save']) && $canSubmit) {
	$submittedPoints = isset($_POST['cat']) && is_array($_POST['cat']) ? $_POST['cat'] : array();
	if (SpiritTokenSaveSubmission($gameId, $teamId, $submittedPoints, $categories)) {
		$submittedComment = isset($_POST['spiritcomment']) ? (string)$_POST['spiritcomment'] : $spiritComment;
		$deleteComment = !empty($_POST['delete_spirit_comment']);
		if (!$deleteComment && trim($submittedComment) === '' && $spiritComment !== '') {
			$submittedComment = $spiritComment;
		}
		if (($deleteComment || trim($submittedComment) !== '' || $spiritComment !== '') &&
			!SpiritTokenSaveComment($gameId, $teamId, $submittedComment, $deleteComment, $game)) {
			$commentFeedback = "<p class='warning'>" . _("Spirit note not saved.") . "</p>";
		}
		$existingPoints = GameGetSpiritPoints($gameId, $ratedTeamId);
		$defaultPoints = $existingPoints;
		$spiritComment = $commentType > 0 ? CommentRaw($commentType, $gameId) : "";
		$canSubmit = SpiritTokenCanSubmit($gameId, $teamId, $game);
		$pageHtml .= "<div class='mobile-notice mobile-notice--success'><p>" . _("Spirit score successfully submitted.") . "</p></div>";
	} else {
		$defaultPoints = $submittedPoints + $defaultPoints;
		if (isset($_POST['spiritcomment'])) {
			$spiritComment = (string)$_POST['spiritcomment'];
		}
		$pageHtml .= "<div class='mobile-notice mobile-notice--error'><p>" . _("Spirit score not saved. Check that all values are valid and the submission is still open.") . "</p></div>";
	}
}

$pageHtml .= "<section class='card'>";
$pageHtml .= "<h2>" . _("Spirit score for") . " " . utf8entities($opponentName) . "</h2>";
$pageHtml .= "<p class='mobile-meta'>" . utf8entities(SpiritkeeperGameTimeLabel($game));
if (!empty($game['poolname'])) {
	$pageHtml .= " | " . utf8entities($game['poolname']);
}
$pageHtml .= "</p>";
$pageHtml .= "<p><strong>" . utf8entities($game['hometeamname']) . " - " . utf8entities($game['visitorteamname']) . "</strong> (" . utf8entities(SpiritkeeperGameScoreLabel($game)) . ")</p>";

if (empty($orderedCategories)) {
	$pageHtml .= "<div class='mobile-notice mobile-notice--error'><p>" . _("Spirit scoring is not configured correctly for this event.") . "</p></div>";
	$pageHtml .= "<p><a class='button-secondary' href='?view=teamgames&amp;token=" . urlencode($token) . "' data-role='button'>" . _("Back to game list") . "</a></p>";
} elseif ($canSubmit) {
	$pageHtml .= "<form method='post' action='?view=submitsotg&amp;token=" . urlencode($token) . "&amp;game=" . $gameId . "'>";
	foreach ($orderedCategories as $category) {
		$categoryId = (int)$category['category_id'];
		$value = isset($defaultPoints[$categoryId]) ? (int)$defaultPoints[$categoryId] : (int)$category['min'];

		$pageHtml .= "<div>";
		$pageHtml .= "<label>" . (int)$category['index'] . ". " . utf8entities(_($category['text'])) . "</label>";
		if (((int)$category['max'] - (int)$category['min']) <= 10) {
			$pageHtml .= "<fieldset class='spirit-controlgroup'>";
			for ($score = (int)$category['min']; $score <= (int)$category['max']; $score++) {
				$checked = ($value === $score) ? " checked='checked'" : "";
				$pageHtml .= "<span class='spirit-choice'>";
				$pageHtml .= "<label for='sotgcat" . $categoryId . "_" . $score . "'>$score</label>";
				$pageHtml .= "<input class='sotg-score-input' type='radio' id='sotgcat" . $categoryId . "_" . $score . "' name='cat[" . $categoryId . "]' value='" . $score . "' data-factor='" . (int)$category['factor'] . "' data-min='" . (int)$category['min'] . "' data-max='" . (int)$category['max'] . "'" . $checked . "/>";
				$pageHtml .= "</span>";
			}
			$pageHtml .= "</fieldset>";
		} else {
			$pageHtml .= "<input class='sotg-score-input' type='number' name='cat[" . $categoryId . "]' value='" . $value . "' min='" . (int)$category['min'] . "' max='" . (int)$category['max'] . "' step='1' data-factor='" . (int)$category['factor'] . "' data-min='" . (int)$category['min'] . "' data-max='" . (int)$category['max'] . "'/>";
		}
		$pageHtml .= "</div>";
	}

	$pageHtml .= "<p class='mobile-total'>" . _("Total") . ": <span id='sotg-total-points'>" . (int)SpiritTotal($defaultPoints, $categories) . "</span></p>";
	$pageHtml .= "<label for='spiritcomment'>" . _("Spirit note") . "</label>";
	$pageHtml .= "<textarea id='spiritcomment' name='spiritcomment' rows='4' cols='40' maxlength='" . COMMENT_MAX_LENGTH . "' placeholder='" . _("Optional - add context for spirit points given (no blame).") . "'>" . htmlentities($spiritComment) . "</textarea>";
	if (!empty($spiritComment)) {
		$pageHtml .= "<label><input type='checkbox' name='delete_spirit_comment' value='1'/> " . _("Delete comment") . "</label>";
	}
	$pageHtml .= $commentFeedback;
	$pageHtml .= "<div class='mobile-actions'>";
	$pageHtml .= "<button type='submit' name='save' value='1'>" . _("Submit Scores") . "</button>";
	$pageHtml .= "<a class='button-secondary' href='?view=teamgames&amp;token=" . urlencode($token) . "' data-role='button'>" . _("Back to game list") . "</a>";
	$pageHtml .= "</div>";
	$pageHtml .= "</form>";
	$pageHtml .= "<script>
	(function () {
		function scoreValue(input) {
			if (!input) {
				return 0;
			}
			if (input.type === 'radio' && !input.checked) {
				return 0;
			}
			var value = parseInt(input.value, 10);
			var factor = parseInt(input.getAttribute('data-factor') || '1', 10);
			if (isNaN(value) || isNaN(factor)) {
				return 0;
			}
			return value * factor;
		}

		function updateTotal() {
			var total = 0;
			var inputs = document.querySelectorAll('.sotg-score-input');
			for (var i = 0; i < inputs.length; i++) {
				total += scoreValue(inputs[i]);
			}
			var totalNode = document.getElementById('sotg-total-points');
			if (totalNode) {
				totalNode.textContent = total;
			}
		}

		var inputs = document.querySelectorAll('.sotg-score-input');
		for (var i = 0; i < inputs.length; i++) {
			inputs[i].addEventListener('change', updateTotal);
			inputs[i].addEventListener('input', updateTotal);
		}
		updateTotal();
	})();
	</script>";
} elseif ((int)$game['hasstarted'] <= 0) {
	$pageHtml .= "<div class='mobile-notice mobile-notice--info'><p>" . _("This game has not started yet. Return after the game begins.") . "</p></div>";
	$pageHtml .= "<p><a class='button-secondary' href='?view=teamgames&amp;token=" . urlencode($token) . "' data-role='button'>" . _("Back to game list") . "</a></p>";
} elseif (SpiritTokenHasOwnSubmission($gameId, $teamId, $game)) {
	$pageHtml .= "<div class='mobile-notice mobile-notice--info'><p>" . _("You already submitted the spirit score for this game.") . "</p></div>";
	$pageHtml .= "<p><strong>" . _("Score given") . ":</strong> " . utf8entities(SpiritPointsSummary($existingPoints, $categories)) . "</p>";
	if (!empty($spiritComment)) {
		$pageHtml .= "<p><strong>" . _("Spirit note") . ":</strong></p>";
		$pageHtml .= "<div class='comment'>" . someHTML($spiritComment) . "</div>";
	}
	$pageHtml .= "<p><a class='button-secondary' href='?view=teamgames&amp;token=" . urlencode($token) . "' data-role='button'>" . _("Back to game list") . "</a></p>";
} else {
	$pageHtml .= "<div class='mobile-notice mobile-notice--error'><p>" . _("Spirit submission is not available for this game.") . "</p></div>";
	$pageHtml .= "<p><a class='button-secondary' href='?view=teamgames&amp;token=" . urlencode($token) . "' data-role='button'>" . _("Back to game list") . "</a></p>";
}

$pageHtml .= "</section>";
?>
