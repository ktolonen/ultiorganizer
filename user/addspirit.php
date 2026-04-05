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

function QuickSpiritCategories($categories)
{
  $quick = SpiritOrderedCategories($categories);
  return count($quick) === 5 ? $quick : array();
}

function ParseQuickSpiritScore($score, $quickCategories)
{
  $score = trim((string)$score);
  if ($score === "") {
    return null;
  }
  if (count($quickCategories) !== 5 || strlen($score) !== 5 || !preg_match('/^[0-4]{5}$/', $score)) {
    return false;
  }

  $points = array();
  for ($i = 0; $i < 5; $i++) {
    $value = (int)$score[$i];
    $points[(int)$quickCategories[$i]['category_id']] = $value;
  }
  return $points;
}

function RenderSpiritDeleteControl($gameId, $teamId, $hasSpiritScore)
{
  if (!$hasSpiritScore || !CanDeleteSpiritSubmission($gameId, $teamId)) {
    return "";
  }
  $confirmText = htmlspecialchars(addslashes(_("Delete this spirit score?")), ENT_QUOTES);
  return "<p><button class='button' type='submit' name='delete_spirit_team' value='" . (int)$teamId . "' onclick='return confirm(\"" . $confirmText . "\");'>" . _("Delete spirit score") . "</button></p>";
}

$season = SeasonInfo(GameSeason($gameId));
$game_result = GameResult($gameId);
$entryTeamId = SpiritEntryTeamForUser($gameId);
$hasFullSpiritView = HasFullGameSpiritViewRight($gameId);
$hasFullSpiritEdit = HasFullGameSpiritEditRight($gameId);

if ($entryTeamId < 0) {
  showPage($title, "<p>" . _("Insufficient user rights") . "</p>");
  return;
}

if ($entryTeamId > 0 && $teamId <= 0) {
  $teamId = $entryTeamId;
}

if (
  !$hasFullSpiritView &&
  (
    ($teamId > 0 && !hasEditPlayersRight($teamId)) ||
    ($teamId <= 0 && $entryTeamId > 0)
  )
) {
  showPage($title, "<p>" . _("Insufficient user rights") . "</p>");
  return;
}

if ($season['spiritmode'] > 0) {
  $categories = SpiritCategories($season['spiritmode']);
  $quickCategories = QuickSpiritCategories($categories);
  $allowQuickEntry = (count($quickCategories) === 5) && $hasFullSpiritEdit;
  $score_feedback = "";
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

  if (!empty($_POST['delete_spirit_team'])) {
    $deleteTeamId = (int)$_POST['delete_spirit_team'];
    if (GameDeleteSpiritPoints($gameId, $deleteTeamId)) {
      $score_feedback = _("Spirit score deleted. ");
    } else {
      $score_feedback = _("Spirit score not deleted. ");
    }
  } elseif (!empty($_POST['save'])) {
    $homeSavedFromQuick = false;
    $visitorSavedFromQuick = false;

    if ($allowQuickEntry && isset($_POST['homespirit']) && trim($_POST['homespirit']) !== "") {
      $quickHomePoints = ParseQuickSpiritScore($_POST['homespirit'], $quickCategories);
      if ($quickHomePoints === false) {
        $score_feedback = sprintf(_("Invalid quick score for %s. "), $game_result['hometeamname']);
      } else {
        $homeSavedFromQuick = GameSetSpiritPoints($gameId, $game_result['hometeam'], 1, $quickHomePoints, $categories);
        if (!$homeSavedFromQuick) {
          $score_feedback = _("Spirit score not saved. ");
        }
      }
    }

    if ($allowQuickEntry && isset($_POST['awayspirit']) && trim($_POST['awayspirit']) !== "") {
      $quickVisitorPoints = ParseQuickSpiritScore($_POST['awayspirit'], $quickCategories);
      if ($quickVisitorPoints === false) {
        $score_feedback = sprintf(_("Invalid quick score for %s. "), $game_result['visitorteamname']);
      } else {
        $visitorSavedFromQuick = GameSetSpiritPoints($gameId, $game_result['visitorteam'], 0, $quickVisitorPoints, $categories);
        if (!$visitorSavedFromQuick) {
          $score_feedback = _("Spirit score not saved. ");
        }
      }
    }

    if (isset($_POST['homevalueId']) && !$homeSavedFromQuick) {
    $points = array();
    foreach ($_POST['homevalueId'] as $cat) {
      if (isset($_POST['homecat' . $cat]))
        $points[$cat] = $_POST['homecat' . $cat];
      else
        $score_feedback = sprintf(_("Missing score for %s. "), $game_result['hometeamname']);
    }
    if (!GameSetSpiritPoints($gameId, $game_result['hometeam'], 1, $points, $categories)) {
      $score_feedback = _("Spirit score not saved. ");
    }
    }
    if (isset($_POST['visvalueId']) && !$visitorSavedFromQuick) {
    $points = array();
    foreach ($_POST['visvalueId'] as $cat) {
      if (isset($_POST['viscat' . $cat]))
        $points[$cat] = $_POST['viscat' . $cat];
      else
        $score_feedback = sprintf(_("Missing score for %s. "), $game_result['visitorteamname']);
    }
    if (!GameSetSpiritPoints($gameId, $game_result['visitorteam'], 0, $points, $categories)) {
      $score_feedback = _("Spirit score not saved. ");
    }
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
  $menutabs[_("Scoresheet")] = "?view=user/addscoresheet&game=$gameId";
  $menutabs[_("Spirit points")] = SpiritEntryUrl($gameId);
  if (ShowDefenseStats()) {
    $menutabs[_("Defense sheet")] = "?view=user/adddefensesheet&game=$gameId&amp;team=$teamId";
  }
  $html .= pageMenu($menutabs, "", false);

  $html .= "<form  method='post' action='?view=user/addspirit&amp;game=" . $gameId . "&amp;team=$teamId'>";
  if ($allowQuickEntry) {
    $homeAbbr = TeamAbbreviation($game_result['hometeam']);
    $visitorAbbr = TeamAbbreviation($game_result['visitorteam']);
    if (empty($homeAbbr)) {
      $homeAbbr = $game_result['hometeamname'];
    }
    if (empty($visitorAbbr)) {
      $visitorAbbr = $game_result['visitorteamname'];
    }
    $html .= "<h3>" . _("Quick data entry:") . "</h3>\n";
    $html .= "<table>";
    $html .= "<tr><td class='center'>" . _("Given for") . "</td><td width='25px'></td><td class='center'>" . _("Given for") . "</td><td></td></tr>";
    $html .= "<tr><td class='center'><strong>" . utf8entities($homeAbbr) . "</strong></td><td></td><td class='center'><strong>" . utf8entities($visitorAbbr) . "</strong></td><td></td></tr>";
    $html .= "<tr>";
    $html .= "<td class='center'><input class='input quickspirit-input' type='text' size='5' maxlength='5' name='homespirit' id='homespirit' inputmode='numeric' pattern='[0-4]{5}'/></td>";
    $html .= "<td></td>";
    $html .= "<td class='center'><input class='input quickspirit-input' type='text' size='5' maxlength='5' name='awayspirit' id='awayspirit' inputmode='numeric' pattern='[0-4]{5}'/></td>";
    $html .= "<td class='center'><button class='button' type='submit' name='save' value='save'>" . _("Update & Save") . "</button></td>";
    $html .= "</tr>";
    $html .= "<tr><td colspan='2' class='center'>" . _("(eg. '21322')") . "</td></tr></table>";
    $html .= "<script>
      (function () {
        function updateQuickInputState(el) {
          var v = (el.value || '').trim();
          var ok = /^[0-4]{5}$/.test(v);
          if (ok || v === '') {
            el.style.borderColor = '';
            el.style.backgroundColor = '';
          } else {
            el.style.borderColor = '#c00';
            el.style.backgroundColor = '#ffeaea';
          }
        }
        var ids = ['homespirit', 'awayspirit'];
        for (var i = 0; i < ids.length; i++) {
          var el = document.getElementById(ids[i]);
          if (!el) {
            continue;
          }
          updateQuickInputState(el);
          el.addEventListener('input', function () {
            updateQuickInputState(this);
          });
        }
      })();
    </script>";
  }

  if ($teamId > 0) {
    if ($teamId == $game_result['visitorteam']) {
    $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['hometeamname']) . "</h3>\n";

    $points = GameGetSpiritPoints($gameId, $game_result['hometeam']);
    $html .= SpiritTable($game_result, $points, $categories, true);
    $html .= RenderSpiritDeleteControl($gameId, $game_result['hometeam'], !empty($points));
    $html .= RenderSpiritCommentForTeam($game_result['hometeam'], $spirit_comments, $comment_feedback);
    }
    if ($teamId == $game_result['hometeam']) {
  $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['visitorteamname']) . "</h3>\n";

  $points = GameGetSpiritPoints($gameId, $game_result['visitorteam']);
  $html .= SpiritTable($game_result, $points, $categories, false);
    $html .= RenderSpiritDeleteControl($gameId, $game_result['visitorteam'], !empty($points));
    $html .= RenderSpiritCommentForTeam($game_result['visitorteam'], $spirit_comments, $comment_feedback);
    }
  } else {
    $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['hometeamname']) . "</h3>\n";

    $points = GameGetSpiritPoints($gameId, $game_result['hometeam']);
    $html .= SpiritTable($game_result, $points, $categories, true);
    $html .= RenderSpiritDeleteControl($gameId, $game_result['hometeam'], !empty($points));
    $html .= RenderSpiritCommentForTeam($game_result['hometeam'], $spirit_comments, $comment_feedback);
    $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['visitorteamname']) . "</h3>\n";
    $points = GameGetSpiritPoints($gameId, $game_result['visitorteam']);
    $html .= SpiritTable($game_result, $points, $categories, false);
    $html .= RenderSpiritDeleteControl($gameId, $game_result['visitorteam'], !empty($points));
    $html .= RenderSpiritCommentForTeam($game_result['visitorteam'], $spirit_comments, $comment_feedback);
  }
  $canSaveSpirit = CanEditSpiritSubmission($gameId, $game_result['hometeam']) || CanEditSpiritSubmission($gameId, $game_result['visitorteam']);
  $html .= "<p>";
  if ($canSaveSpirit) {
    $html .= "<input class='button' type='submit' name='save' value='" . _("Save") . "'/>";
    if (!empty($score_feedback)) {
      $html .= " $score_feedback";
    }
  } else {
    $html .= "<span class='warning'>" . _("Read-only spirit review") . "</span>";
  }
  $html .= "</p>";
  $html .= "</form>\n";
} else {
  $html .= "<p>" . sprintf(_("Spirit points not given for %s."), utf8entities($season['name'])) . "</p>";
}
showPage($title, $html);
