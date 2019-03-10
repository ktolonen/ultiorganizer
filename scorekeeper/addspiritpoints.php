<?php
$html = "";

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

$teamId = isset($_GET['team']) ? $_GET['team'] : $_SESSION['team'];
$_SESSION['team'] = $teamId;


$season = SeasonInfo(GameSeason($gameId));
if ($season['spiritmode'] > 0) {
  $game_result = GameResult($gameId);
  $ishome = $teamId == $game_result['hometeam'] ? 1 : 0;
  $mode = SpiritMode($season['spiritmode']);
  $categories = SpiritCategories($mode['mode']);
  
  // process itself if save button was pressed
  if (!empty($_POST['save'])) {
    if ($ishome) {
      $points = array ();
      foreach ($_POST['homevalueId'] as $cat) {
        if (isset($_POST['homecat' . $cat]))
          $points[$cat] = $_POST['homecat' . $cat];
        else
          $missing = sprintf(_("Missing score for %s. "), $game_result['hometeamname']);
      }
      GameSetSpiritPoints($gameId, $game_result['hometeam'], 1, $points, $categories);
    } else {
      $points = array ();
      foreach ($_POST['visvalueId'] as $cat) {
        if (isset($_POST['viscat' . $cat]))
          $points[$cat] = $_POST['viscat' . $cat];
        else
          $missing = sprintf(_("Missing score for %s. "), $game_result['visitorteamname']);
      }
      GameSetSpiritPoints($gameId, $game_result['visitorteam'], 0, $points, $categories);
      
      $game_result = GameResult($gameId);
    }
  }
  
  $html .= "<form action='?view=addspiritpoints' method='post' data-ajax='false'>\n";
  if ($ishome) {
    $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['hometeamname']) . "</h3>\n";
    
    $points = GameGetSpiritPoints($gameId, $game_result['hometeam']);
    $html .= SpiritTable($game_result, $points, $categories, true, false);
  } else {
    $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['visitorteamname']) . "</h3>\n";
    
    $points = GameGetSpiritPoints($gameId, $game_result['visitorteam']);
    $html .= SpiritTable($game_result, $points, $categories, false, false);
  }
  
  $html .= "<p>";
  $html .= "<input type='submit' name='save' data-ajax='false' value='"._("Save")."'/>";
  if (isset($missing))
    $html .= " $missing";
  $html .= "</p>";
  $html .= "</form>\n";
  if ($ishome) {
    $html .= "<a href='?view=addspiritpoints&game=" . $gameId . "&team=" . $game_result['visitorteam'] .
         "' data-role='button' data-ajax='false'>" . _("Spirit points for") . " " .
         utf8entities($game_result['visitorteamname']) . "</a>";
  } else {
    $html .= "<a href='?view=addspiritpoints&game=" . $gameId . "&team=" . $game_result['hometeam'] .
         "' data-role='button' data-ajax='false'>" . _("Spirit points for") . " " .
         utf8entities($game_result['hometeamname']) . "</a>";
  }
} else {
  $html .= "<p>".sprintf(_("Spirit points not given for %s."), utf8entities($season['name'])) . "</p>";
}

$html .= " <a href='?view=addscoresheet&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("Back to score sheet")."</a>";
$html .= " <a href='?view=respgames' data-role='button' data-ajax='false'>"._("Back to game responsibilities")."</a>";

echo $html;
?>
