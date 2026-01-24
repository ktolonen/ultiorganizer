<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/player.functions.php';
$html = "";

$gameId = intval(iget("game"));

mobilePageTop(_("Score&nbsp;sheet"));

$season = SeasonInfo(GameSeason($gameId));
if ($season['spiritmode'] > 0) {
  $game_result = GameResult($gameId);
  $mode = SpiritMode($season['spiritmode']);
  $categories = SpiritCategories($mode['mode']);
  $comment_feedback = "";
  $home_type = SpiritCommentTypeForTeam($game_result, $game_result['hometeam']);
  $visitor_type = SpiritCommentTypeForTeam($game_result, $game_result['visitorteam']);
  $home_comment = CommentRaw($home_type, $gameId);
  $visitor_comment = CommentRaw($visitor_type, $gameId);
  $home_meta_html = CommentMetaHtml(GameCommentMeta($gameId, $home_type));
  $visitor_meta_html = CommentMetaHtml(GameCommentMeta($gameId, $visitor_type));
  $can_create_home = CanCreateSpiritComment($game_result, $game_result['hometeam']);
  $can_manage_home = CanManageSpiritComment($gameId, $home_type);
  $can_create_visitor = CanCreateSpiritComment($game_result, $game_result['visitorteam']);
  $can_manage_visitor = CanManageSpiritComment($gameId, $visitor_type);

  //process itself if save button was pressed
  if (!empty($_POST['save'])) {
    $points = array();
    foreach ($_POST['homevalueId'] as $cat) {
      if (isset($_POST['homecat' . $cat]))
        $points[$cat] = $_POST['homecat' . $cat];
      else
        $missing = sprintf(_("Missing score for %s. "), $game_result['hometeamname']);
    }
    GameSetSpiritPoints($gameId, $game_result['hometeam'], 1, $points, $categories);

    $points = array();
    foreach ($_POST['visvalueId'] as $cat) {
      if (isset($_POST['viscat' . $cat]))
        $points[$cat] = $_POST['viscat' . $cat];
      else
        $missing = sprintf(_("Missing score for %s. "), $game_result['visitorteamname']);
    }
    GameSetSpiritPoints($gameId, $game_result['visitorteam'], 0, $points, $categories);

    $game_result = GameResult($gameId);
    $delete_home = !empty($_POST['delete_spirit_comment_home']);
    if (isset($_POST['spiritcomment_home']) || $delete_home) {
      $saved = SetSpiritComment($game_result, $game_result['hometeam'], $_POST['spiritcomment_home'], $delete_home);
      if (!$saved) {
        $comment_feedback = "<p class='warning'>" . _("Comment not saved.") . "</p>\n";
      }
      $home_comment = CommentRaw($home_type, $gameId);
      $home_meta_html = CommentMetaHtml(GameCommentMeta($gameId, $home_type));
    }
    $delete_visitor = !empty($_POST['delete_spirit_comment_visitor']);
    if (isset($_POST['spiritcomment_visitor']) || $delete_visitor) {
      $saved = SetSpiritComment($game_result, $game_result['visitorteam'], $_POST['spiritcomment_visitor'], $delete_visitor);
      if (!$saved) {
        $comment_feedback = "<p class='warning'>" . _("Comment not saved.") . "</p>\n";
      }
      $visitor_comment = CommentRaw($visitor_type, $gameId);
      $visitor_meta_html = CommentMetaHtml(GameCommentMeta($gameId, $visitor_type));
    }
  }

  $html .= "<form  method='post' action='?view=mobile/addspiritpoints&amp;game=" . $gameId . "'>";

  $html .= "<h3>" . sprintf(_("Spirit points given for %s"), utf8entities($game_result['hometeamname'])) . "</h3>\n";

  $points = GameGetSpiritPoints($gameId, $game_result['hometeam']);
  $html .= SpiritTable($game_result, $points, $categories, true, false);
  if ($can_create_home || $can_manage_home) {
    $html .= "<p><b>" . _("Spirit note for") . " " . utf8entities($game_result['hometeamname']) . "</b></p>";
    $html .= "<p>" . $home_meta_html . "</p>";
    $html .= "<textarea class='input' name='spiritcomment_home' rows='4' cols='30' maxlength='" . COMMENT_MAX_LENGTH . "' placeholder='" . _("Optional - add context for spirit points given (no blame).") . "'>" . htmlentities($home_comment) . "</textarea>";
    if ($can_manage_home && !empty($home_comment)) {
      $html .= "<p><label><input type='checkbox' name='delete_spirit_comment_home' value='1'/> " . _("Delete comment") . "</label></p>";
    }
    $html .= $comment_feedback;
  }

  $html .= "<h3>" . sprintf(_("Spirit points given for %s"), utf8entities($game_result['visitorteamname'])) . "</h3>\n";

  $points = GameGetSpiritPoints($gameId, $game_result['visitorteam']);
  $html .= SpiritTable($game_result, $points, $categories, false, false);
  if ($can_create_visitor || $can_manage_visitor) {
    $html .= "<p><b>" . _("Spirit note for") . " " . utf8entities($game_result['visitorteamname']) . "</b></p>";
    $html .= "<p>" . $visitor_meta_html . "</p>";
    $html .= "<textarea class='input' name='spiritcomment_visitor' rows='4' cols='30' maxlength='" . COMMENT_MAX_LENGTH . "' placeholder='" . _("Optional - add context for spirit points given (no blame).") . "'>" . htmlentities($visitor_comment) . "</textarea>";
    if ($can_manage_visitor && !empty($visitor_comment)) {
      $html .= "<p><label><input type='checkbox' name='delete_spirit_comment_visitor' value='1'/> " . _("Delete comment") . "</label></p>";
    }
    $html .= $comment_feedback;
  }

  $html .= "<p>";
  $html .= "<input class='button' type='submit' name='save' value='" . _("Save") . "'/>";
  if (isset($missing))
    $html .= " $missing";
  $html .= "</p>";
  $html .= "<p><a href='?view=mobile/addscoresheet&amp;game=" . $gameId . "'>" . _("Back to score sheet") . "</a></p>";
  $html .= "</form>\n";
} else {
  $html .= "<p>" . sprintf(_("Spirit points not given for %s."), utf8entities($season['name'])) . "</p>";
}

echo $html;

pageEnd();
