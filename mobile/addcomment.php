<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';

$html = "";
$gameId = intval(iget("game"));

mobilePageTop(_("Game comment"));

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

$html .= "<h3>" . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . "</h3>\n";
$html .= "<form method='post' action='?view=mobile/addcomment&amp;game=" . $gameId . "'>";
if ($show_comment_form) {
	$html .= "<p>";
	if (!empty($game_comment_meta_html)) {
		$html .= $game_comment_meta_html;
	}
	$html .= "</p>";
	$html .= "<p><textarea class='input' style='width:98%' name='gamecomment' rows='4' maxlength='" . COMMENT_MAX_LENGTH . "' placeholder='" . _("Optional - note unusual events or interrupts.") . "'>" . htmlentities($game_comment) . "</textarea></p>";
	if ($can_manage_comment && !empty($game_comment)) {
		$html .= "<p><label><input type='checkbox' name='delete_game_comment' value='1'/> " . _("Delete comment") . "</label></p>";
	}
	$html .= "<p><input class='button' type='submit' name='save' value='" . _("Save") . "'/></p>";
	$html .= $comment_feedback;
} else {
	$html .= "<p class='warning'>" . _("Insufficient rights to edit comment.") . "</p>\n";
}
$html .= "</form>";
$html .= "<p><a href='?view=mobile/addscoresheet&amp;game=" . $gameId . "'>" . _("Back to score sheet") . "</a></p>";

echo $html;

pageEnd();
