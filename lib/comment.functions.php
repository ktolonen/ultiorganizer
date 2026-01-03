<?php

/**
 * Returns the raw form of a comment field.
 * 
 * @param int $type The type of entity. 1: season, 2: series, 3: pool.
 * @param string $id The id of the season, series, or pool.
 * @return string the comment or an empty string if no comment exists.
 */
function CommentRaw($type, $id)
{
	$query = sprintf(
		"SELECT comment FROM uo_comment
		WHERE type='%d' AND id='%s'",
		(int) $type,
		DBEscapeString($id)
	);
	$comment = DBQueryToValue($query);
	if ($comment != -1)
		return $comment;
	else
		return "";
}

if (!defined('COMMENT_TYPE_SEASON')) {
	define('COMMENT_TYPE_SEASON', 1);
}
if (!defined('COMMENT_TYPE_SERIES')) {
	define('COMMENT_TYPE_SERIES', 2);
}
if (!defined('COMMENT_TYPE_POOL')) {
	define('COMMENT_TYPE_POOL', 3);
}
if (!defined('COMMENT_TYPE_GAME')) {
	define('COMMENT_TYPE_GAME', 4);
}
if (!defined('COMMENT_TYPE_SPIRIT_HOME')) {
	define('COMMENT_TYPE_SPIRIT_HOME', 5);
}
if (!defined('COMMENT_TYPE_SPIRIT_VISITOR')) {
	define('COMMENT_TYPE_SPIRIT_VISITOR', 6);
}
if (!defined('COMMENT_MAX_LENGTH')) {
	define('COMMENT_MAX_LENGTH', 4000);
}

function CommentNormalize($comment)
{
	$comment = trim((string)$comment);
	if (strlen($comment) > COMMENT_MAX_LENGTH) {
		$comment = substr($comment, 0, COMMENT_MAX_LENGTH);
	}
	return $comment;
}

function CommentKeyForType($type)
{
	switch ((int)$type) {
		case COMMENT_TYPE_GAME:
			return "game";
		case COMMENT_TYPE_SPIRIT_HOME:
			return "spirit_home";
		case COMMENT_TYPE_SPIRIT_VISITOR:
			return "spirit_visitor";
		default:
			return "comment";
	}
}

function CommentLabelForType($type)
{
	switch ((int)$type) {
		case COMMENT_TYPE_GAME:
			return "Game note";
		case COMMENT_TYPE_SPIRIT_HOME:
			return "Spirit note (home)";
		case COMMENT_TYPE_SPIRIT_VISITOR:
			return "Spirit note (visitor)";
		default:
			return "Comment";
	}
}

function GameCommentMeta($gameId, $type)
{
	$key = CommentKeyForType($type);
	$created = null;
	$updated = null;
	$cutoffSql = "";
	$query = sprintf(
		"SELECT time FROM uo_event_log
		WHERE category='game' AND source='comments' AND type='comment_delete'
		AND id1='%s' AND id2='%s' ORDER BY time DESC LIMIT 1",
		DBEscapeString($gameId),
		DBEscapeString($key)
	);
	$result = DBQuery($query);
	if ($result) {
		$deleted = mysqli_fetch_assoc($result);
		if (!empty($deleted['time'])) {
			$cutoffSql = " AND time > '" . DBEscapeString($deleted['time']) . "'";
		}
	}
	$query = sprintf(
		"SELECT user_id, time FROM uo_event_log
		WHERE category='game' AND source='comments' AND type='comment_create'
		AND id1='%s' AND id2='%s'%s ORDER BY time ASC LIMIT 1",
		DBEscapeString($gameId),
		DBEscapeString($key),
		$cutoffSql
	);
	$result = DBQuery($query);
	if ($result) {
		$created = mysqli_fetch_assoc($result);
	}

	$query = sprintf(
		"SELECT user_id, time FROM uo_event_log
		WHERE category='game' AND source='comments'
		AND type='comment_update'
		AND id1='%s' AND id2='%s'%s ORDER BY time DESC LIMIT 1",
		DBEscapeString($gameId),
		DBEscapeString($key),
		$cutoffSql
	);
	$result = DBQuery($query);
	if ($result) {
		$updated = mysqli_fetch_assoc($result);
	}

	return array(
		'created_by' => isset($created['user_id']) ? $created['user_id'] : "",
		'created_at' => isset($created['time']) ? $created['time'] : "",
		'updated_by' => isset($updated['user_id']) ? $updated['user_id'] : "",
		'updated_at' => isset($updated['time']) ? $updated['time'] : "",
	);
}

function CommentMetaHtml($meta)
{
	$parts = array();
	if (!empty($meta['created_by']) && !empty($meta['created_at'])) {
		$parts[] = sprintf(
			_("Comment by %s at %s"),
			utf8entities($meta['created_by']),
			DefTimeFormat($meta['created_at'])
		);
	}
	if (!empty($meta['updated_by']) && !empty($meta['updated_at'])) {
		$parts[] = sprintf(
			_("Edited by %s at %s"),
			utf8entities($meta['updated_by']),
			DefTimeFormat($meta['updated_at'])
		);
	}

	if (empty($parts)) {
		return "";
	}

	return "<span class='commentmeta'><em>" . implode(" | ", $parts) . "</em></span>\n";
}

function GameCommentHtml($gameId, $type)
{
	$comment = CommentRaw($type, $gameId);
	if ($comment === "") {
		return "";
	}
	$meta = GameCommentMeta($gameId, $type);
	$html = "<div class='comment'>" . someHTML($comment) . "</div>\n";
	$html .= CommentMetaHtml($meta);
	return $html;
}

function CanCreateGameComment($gameId)
{
	if (!function_exists('hasEditGameEventsRight') || !function_exists('isLoggedIn')) {
		return false;
	}
	return isLoggedIn() && hasEditGameEventsRight($gameId);
}

function SpiritCommentTypeForTeam($gameResult, $teamId)
{
	if (!isset($gameResult['hometeam']) || !isset($gameResult['visitorteam'])) {
		return 0;
	}
	if ((int)$teamId === (int)$gameResult['hometeam']) {
		return COMMENT_TYPE_SPIRIT_HOME;
	}
	if ((int)$teamId === (int)$gameResult['visitorteam']) {
		return COMMENT_TYPE_SPIRIT_VISITOR;
	}
	return 0;
}

function CanCreateSpiritComment($gameResult, $spiritTeamId)
{
	if (!function_exists('hasEditGameEventsRight') || !function_exists('hasEditPlayersRight') || !function_exists('isLoggedIn')) {
		return false;
	}
	if (!isLoggedIn()) {
		return false;
	}
	if (hasEditGameEventsRight($gameResult['game_id'])) {
		return true;
	}
	$homeTeam = isset($gameResult['hometeam']) ? (int)$gameResult['hometeam'] : 0;
	$visitorTeam = isset($gameResult['visitorteam']) ? (int)$gameResult['visitorteam'] : 0;
	if ((int)$spiritTeamId === $homeTeam) {
		return hasEditPlayersRight($visitorTeam);
	}
	if ((int)$spiritTeamId === $visitorTeam) {
		return hasEditPlayersRight($homeTeam);
	}
	return false;
}

function CanManageGameComment($gameId, $type)
{
	if (!function_exists('hasEditGameEventsRight') || !function_exists('isLoggedIn')) {
		return false;
	}
	if (!isLoggedIn()) {
		return false;
	}
	if (hasEditGameEventsRight($gameId)) {
		return true;
	}
	$meta = GameCommentMeta($gameId, $type);
	return (!empty($meta['created_by']) && isset($_SESSION['uid']) && $_SESSION['uid'] === $meta['created_by']);
}

function CanManageSpiritComment($gameId, $type)
{
	return CanManageGameComment($gameId, $type);
}

function LogGameCommentEvent($gameId, $type, $eventType)
{
	if (!function_exists('Log1')) {
		return;
	}
	$key = CommentKeyForType($type);
	$label = CommentLabelForType($type);
	Log1("game", $eventType, $gameId, $key, $label, "comments");
}

function SetGameComment($type, $gameId, $comment, $delete = false)
{
	$comment = CommentNormalize($comment);
	$existing = CommentRaw($type, $gameId);

	if ($delete || $comment === "") {
		if ($existing === "") {
			return true;
		}
		if (!CanManageGameComment($gameId, $type)) {
			return false;
		}
		SetComment($type, $gameId, "");
		LogGameCommentEvent($gameId, $type, "comment_delete");
		return true;
	}

	if ($existing === "") {
		if (!CanCreateGameComment($gameId)) {
			return false;
		}
		SetComment($type, $gameId, $comment);
		LogGameCommentEvent($gameId, $type, "comment_create");
		return true;
	}

	if ($existing !== $comment) {
		if (!CanManageGameComment($gameId, $type)) {
			return false;
		}
		SetComment($type, $gameId, $comment);
		LogGameCommentEvent($gameId, $type, "comment_update");
	}

	return true;
}

function SetSpiritComment($gameResult, $spiritTeamId, $comment, $delete = false)
{
	$type = SpiritCommentTypeForTeam($gameResult, $spiritTeamId);
	if (!$type) {
		return false;
	}
	$comment = CommentNormalize($comment);
	$existing = CommentRaw($type, $gameResult['game_id']);

	if ($delete || $comment === "") {
		if ($existing === "") {
			return true;
		}
		if (!CanManageSpiritComment($gameResult['game_id'], $type)) {
			return false;
		}
		SetComment($type, $gameResult['game_id'], "");
		LogGameCommentEvent($gameResult['game_id'], $type, "comment_delete");
		return true;
	}

	if ($existing === "") {
		if (!CanCreateSpiritComment($gameResult, $spiritTeamId)) {
			return false;
		}
		SetComment($type, $gameResult['game_id'], $comment);
		LogGameCommentEvent($gameResult['game_id'], $type, "comment_create");
		return true;
	}

	if ($existing !== $comment) {
		if (!CanManageSpiritComment($gameResult['game_id'], $type)) {
			return false;
		}
		SetComment($type, $gameResult['game_id'], $comment);
		LogGameCommentEvent($gameResult['game_id'], $type, "comment_update");
	}

	return true;
}
