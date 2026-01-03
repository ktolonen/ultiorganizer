<?php
include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/standings.functions.php';
include_once $include_prefix . 'lib/pool.functions.php';
include_once $include_prefix . 'lib/configuration.functions.php';

$html = "";

$gameId = isset($_GET['game']) ? intval($_GET['game']) : 0;
$teamId = isset($_GET['team']) ? intval($_GET['team']) : 0;
$title = _("Spirit");

function RenderSpiritCommentForTeam($teamId, $spirit_comments, $comment_feedback)
{
  $html = "";
  foreach ($spirit_comments as $comment_info) {
    if ($comment_info['team_id'] != $teamId) {
      continue;
    }
    if ($comment_info['can_create'] || $comment_info['can_manage']) {
      $html .= "<p><b>" . $comment_info['label'] . "</b></p>";
      $html .= "<div>" . $comment_info['meta_html'] . "</div>";
      $html .= "<textarea class='input' rows='4' cols='92' name='" . $comment_info['field'] . "' maxlength='" . COMMENT_MAX_LENGTH . "' placeholder='" . _("Optional - add context for spirit points given (no blame).") . "'>" . htmlentities($comment_info['comment']) . "</textarea>";
      if ($comment_info['can_manage'] && !empty($comment_info['comment'])) {
        $html .= "<p><label><input type='checkbox' name='" . $comment_info['delete_field'] . "' value='1'/> " . _("Delete comment") . "</label></p>";
      }
      $html .= $comment_feedback;
    }
  }
  return $html;
}

$season = SeasonInfo(GameSeason($gameId));
if ($season['spiritmode'] > 0) {
  $game_result = GameResult($gameId);
  $mode = SpiritMode($season['spiritmode']);
  $categories = SpiritCategories($mode['mode']);
  $comment_feedback = "";
  $spirit_comments = array();
  if ($teamId > 0) {
    $rated_team_id = ($teamId == $game_result['hometeam']) ? $game_result['visitorteam'] : $game_result['hometeam'];
    $rated_team_name = ($rated_team_id == $game_result['hometeam']) ? $game_result['hometeamname'] : $game_result['visitorteamname'];
    $type = SpiritCommentTypeForTeam($game_result, $rated_team_id);
    if ($type) {
      $spirit_comments[] = array(
        'team_id' => $rated_team_id,
        'type' => $type,
        'label' => _("Spirit note for") . " " . utf8entities($rated_team_name),
        'field' => 'spiritcomment',
        'delete_field' => 'delete_spirit_comment',
        'comment' => CommentRaw($type, $gameId),
        'meta_html' => CommentMetaHtml(GameCommentMeta($gameId, $type)),
        'can_create' => CanCreateSpiritComment($game_result, $rated_team_id),
        'can_manage' => CanManageSpiritComment($gameId, $type)
      );
    }
  } else {
    $home_type = SpiritCommentTypeForTeam($game_result, $game_result['hometeam']);
    $visitor_type = SpiritCommentTypeForTeam($game_result, $game_result['visitorteam']);
    $spirit_comments[] = array(
      'team_id' => $game_result['hometeam'],
      'type' => $home_type,
      'label' => _("Spirit note for") . " " . utf8entities($game_result['hometeamname']),
      'field' => 'spiritcomment_home',
      'delete_field' => 'delete_spirit_comment_home',
      'comment' => CommentRaw($home_type, $gameId),
      'meta_html' => CommentMetaHtml(GameCommentMeta($gameId, $home_type)),
      'can_create' => CanCreateSpiritComment($game_result, $game_result['hometeam']),
      'can_manage' => CanManageSpiritComment($gameId, $home_type)
    );
    $spirit_comments[] = array(
      'team_id' => $game_result['visitorteam'],
      'type' => $visitor_type,
      'label' => _("Spirit note for") . " " . utf8entities($game_result['visitorteamname']),
      'field' => 'spiritcomment_visitor',
      'delete_field' => 'delete_spirit_comment_visitor',
      'comment' => CommentRaw($visitor_type, $gameId),
      'meta_html' => CommentMetaHtml(GameCommentMeta($gameId, $visitor_type)),
      'can_create' => CanCreateSpiritComment($game_result, $game_result['visitorteam']),
      'can_manage' => CanManageSpiritComment($gameId, $visitor_type)
    );
  }

  //process itself if save button was pressed
  if (!empty($_POST['save'])) {
    if (isset($_POST['homevalueId'])) {
    $points = array();
    foreach ($_POST['homevalueId'] as $cat) {
      if (isset($_POST['homecat' . $cat]))
        $points[$cat] = $_POST['homecat' . $cat];
      else
        $missing = sprintf(_("Missing score for %s. "), $game_result['hometeamname']);
    }
    GameSetSpiritPoints($gameId, $game_result['hometeam'], 1, $points, $categories);
    }
    if (isset($_POST['visvalueId'])) {
    $points = array();
    foreach ($_POST['visvalueId'] as $cat) {
      if (isset($_POST['viscat' . $cat]))
        $points[$cat] = $_POST['viscat' . $cat];
      else
        $missing = sprintf(_("Missing score for %s. "), $game_result['visitorteamname']);
    }
    GameSetSpiritPoints($gameId, $game_result['visitorteam'], 0, $points, $categories);
    }
    $game_result = GameResult($gameId);
  }
  foreach ($spirit_comments as &$comment_info) {
    $delete_comment = !empty($_POST[$comment_info['delete_field']]);
    if (isset($_POST[$comment_info['field']]) || $delete_comment) {
      $saved = SetSpiritComment($game_result, $comment_info['team_id'], $_POST[$comment_info['field']], $delete_comment);
      if (!$saved) {
        $comment_feedback = "<p class='warning'>" . _("Comment not saved.") . "</p>\n";
      }
      $comment_info['comment'] = CommentRaw($comment_info['type'], $gameId);
      $comment_info['meta_html'] = CommentMetaHtml(GameCommentMeta($gameId, $comment_info['type']));
    }
  }
  unset($comment_info);


  $menutabs[_("Result")] = "?view=user/addresult&game=$gameId";
  $menutabs[_("Players")] = "?view=user/addplayerlists&game=$gameId";
  $menutabs[_("Score sheet")] = "?view=user/addscoresheet&game=$gameId";
  $menutabs[_("Spirit points")] = "?view=user/addspirit&game=$gameId";
  if (ShowDefenseStats()) {
    $menutabs[_("Defense sheet")] = "?view=user/adddefensesheet&game=$gameId&amp;team=$teamId";
  }
  $html .= pageMenu($menutabs, "", false);

  $html .= "<form  method='post' action='?view=user/addspirit&amp;game=" . $gameId . "&amp;team=$teamId'>";

  if ($teamId > 0) {
    if ($teamId == $game_result['visitorteam']) {
  $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['hometeamname']) . "</h3>\n";

    $points = GameGetSpiritPoints($gameId, $game_result['hometeam']);
    $html .= SpiritTable($game_result, $points, $categories, true);
    $html .= RenderSpiritCommentForTeam($game_result['hometeam'], $spirit_comments, $comment_feedback);
    }
    if ($teamId == $game_result['hometeam']) {
  $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['visitorteamname']) . "</h3>\n";

  $points = GameGetSpiritPoints($gameId, $game_result['visitorteam']);
  $html .= SpiritTable($game_result, $points, $categories, false);
    $html .= RenderSpiritCommentForTeam($game_result['visitorteam'], $spirit_comments, $comment_feedback);
    }
  } else {
    $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['hometeamname']) . "</h3>\n";

    $points = GameGetSpiritPoints($gameId, $game_result['hometeam']);
    $html .= SpiritTable($game_result, $points, $categories, true);
    $html .= RenderSpiritCommentForTeam($game_result['hometeam'], $spirit_comments, $comment_feedback);
    $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['visitorteamname']) . "</h3>\n";
    $points = GameGetSpiritPoints($gameId, $game_result['visitorteam']);
    $html .= SpiritTable($game_result, $points, $categories, false);
    $html .= RenderSpiritCommentForTeam($game_result['visitorteam'], $spirit_comments, $comment_feedback);
  }
  $html .= "<p>";
  $html .= "<input class='button' type='submit' name='save' value='" . _("Save") . "'/>";
  if (isset($missing))
    $html .= " $missing";
  $html .= "</p>";
  $html .= "</form>\n";
} else {
  $html .= "<p>" . sprintf(_("Spirit points not given for %s."), utf8entities($season['name'])) . "</p>";
}
showPage($title, $html);
