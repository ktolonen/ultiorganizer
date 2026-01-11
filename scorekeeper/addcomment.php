<?php
include_once __DIR__ . '/auth.php';
$html = "";

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

$game_result = GameResult($gameId);
$comment_feedback = "";
$game_comment = CommentRaw(COMMENT_TYPE_GAME, $gameId);
$game_comment_meta_html = CommentMetaHtml(GameCommentMeta($gameId, COMMENT_TYPE_GAME));
$can_create_comment = CanCreateGameComment($gameId);
$can_manage_comment = CanManageGameComment($gameId, COMMENT_TYPE_GAME);
$show_comment_form = ($can_create_comment || $can_manage_comment);

if (isset($_POST['save']) && $show_comment_form) {
	$delete_comment = !empty($_POST['delete_game_comment']);
	$saved = SetGameComment(COMMENT_TYPE_GAME, $gameId, $_POST['gamecomment'], $delete_comment);
	if (!$saved) {
		$comment_feedback = "<p class='warning'>" . _("Comment not saved.") . "</p>\n";
	}
	$game_comment = CommentRaw(COMMENT_TYPE_GAME, $gameId);
	$game_comment_meta_html = CommentMetaHtml(GameCommentMeta($gameId, COMMENT_TYPE_GAME));
}

$html .= "<div data-role='header'>\n";
$html .= "<h1>" . _("Game note") . ": " . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
$html .= "<form action='?view=addcomment' method='post' data-ajax='false'>\n";
if ($show_comment_form) {
	if (!empty($game_comment_meta_html)) {
		$html .= "<div>" . $game_comment_meta_html . "</div>";
	}
$html .= "<textarea name='gamecomment' rows='4' cols='40' maxlength='" . COMMENT_MAX_LENGTH . "' placeholder='" . _("Optional - note unusual events or interrupts.") . "'>" . htmlentities($game_comment) . "</textarea>";
	if ($can_manage_comment && !empty($game_comment)) {
		$html .= "<label><input type='checkbox' name='delete_game_comment' value='1'/> " . _("Delete comment") . "</label>";
	}
	$html .= "<div class='form-actions'>";
	$html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save") . "'/>";
	$html .= "</div>";
	$html .= $comment_feedback;
} else {
	$html .= "<p class='warning'>" . _("Insufficient rights to edit comment.") . "</p>\n";
}
$html .= "<a class='back-score-button' href='?view=addscoresheet&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Back to score sheet") . "</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
