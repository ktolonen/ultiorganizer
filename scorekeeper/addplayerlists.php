<?php
include_once __DIR__ . '/auth.php';
$html = "";


$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$teamId = isset($_GET['team']) ? $_GET['team'] : $_SESSION['team'];

$_SESSION['game'] = $gameId;
$_SESSION['team'] = $teamId;

$game_result = GameResult($gameId);

if (isset($_POST['save'])) {

  $played_players = GamePlayers($gameId, $teamId);

  //delete unchecked players
  foreach ($played_players as $player) {
    $found = false;
    if (!empty($_POST["check"])) {
      foreach ($_POST["check"] as $playerId) {
        if ($player['player_id'] == $playerId) {
          $found = true;
          break;
        }
      }
    }
    if (!$found)
      GameRemovePlayer($gameId, $player['player_id']);
  }

  //handle checked players
  if (!empty($_POST["check"])) {
    foreach ($_POST["check"] as $playerId) {
      $number = $_POST["p$playerId"];
      //if number
      if (is_numeric($number)) {
        //check if already in list with correct number
        $played_players = GamePlayers($gameId, $teamId);
        $found = false;
        foreach ($played_players as $player) {

          //if exist
          if ($player['player_id'] == $playerId && $player['num'] == $number) {
            $found = true;
            break;
          }
          //if found, but with different number
          if ($player['player_id'] == $playerId && $player['num'] != $number) {
            GameSetPlayerNumber($gameId, $playerId, $number);
            $found = true;
            break;
          }
          //if two players with same number
          if ($player['player_id'] != $playerId && $player['num'] == $number) {
            $playerinfo1 = PlayerInfo($playerId);
            $playerinfo2 = PlayerInfo($player['player_id']);
            $html .= "<p  class='warning'><i>" . utf8entities($playerinfo1['firstname'] . " " . $playerinfo1['lastname']) . "</i> " . _("and")
              . " <i>" . utf8entities($playerinfo2['firstname'] . " " . $playerinfo2['lastname']) . "</i> " . _("same number") . " '$number'.</p>";
            $found = true;
            break;
          }
        }

        if (!$found)
          GameAddPlayer($gameId, $playerId, $number);
      } else {
        $playerinfo = PlayerInfo($playerId);
        $html .= "<p  class='warning'><i>" . utf8entities($playerinfo['firstname'] . " " . $playerinfo['lastname']) . "</i> " . _("erroneous number") . " '$number'.</p>";
      }
    }
  }

  if (empty($html)) {
    if ($teamId == $game_result['hometeam']) {
      header("location:?view=addplayerlists&game=" . $gameId . "&team=" . $game_result['visitorteam']);
    } elseif ($teamId == $game_result['visitorteam']) {
      header("location:?view=addscoresheet&game=" . $gameId);
    }
  }
}

$html .= "<div data-role='header'>\n";
$html .= "<h1>" . _("Roster") . ": " . utf8entities(TeamName($teamId)) . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";


$playerlist = TeamPlayerList($teamId);

$html .= "<form action='?view=addplayerlists' method='post' data-ajax='false'>\n";

$played_players = GamePlayers($gameId, $teamId);

$html .= "<div class='player-list'>\n";
$html .= "<div class='player-row player-row--header'>\n";
$html .= "<div class='player-check'></div>\n";
$html .= "<div class='player-name'><h3>" . _("Player") . "</h3></div>\n";
$html .= "<div class='player-number'><h3>" . _("Jersey") . "</h3></div>\n";
$html .= "</div>\n";
$i = 0;
while ($player = mysqli_fetch_assoc($playerlist)) {
  $i++;
  $playerinfo = PlayerInfo($player['player_id']);
  $number = PlayerNumber($player['player_id'], $gameId);
  if ($number < 0) {
    $number = "";
  }

  $found = false;
  foreach ($played_players as $played_player) {
    if ($player['player_id'] == $played_player['player_id']) {
      $found = true;
      break;
    }
  }

  $checkboxId = "player-check-" . intval($player['player_id']);
  $html .= "<div class='player-row'>\n";
  if ($found || count($played_players) == 0) {
    $html .= "<label class='player-check' for='" . $checkboxId . "'><input type='checkbox' id='" . $checkboxId . "' name='check[]' value='" . utf8entities($player['player_id']) . "' checked='checked'/></label>";
  } else {
    $html .= "<label class='player-check' for='" . $checkboxId . "'><input type='checkbox' id='" . $checkboxId . "' name='check[]' value='" . utf8entities($player['player_id']) . "' /></label>";
  }
  $html .= "<label class='player-name' for='" . $checkboxId . "'>" . utf8entities($playerinfo['firstname'] . " " . $playerinfo['lastname']) . "</label>";
  $html .= "<div class='player-number'>\n";
  $html .= "<select name='p" . $player['player_id'] . "' id='p" . $player['player_id'] . "'>";
  for ($i = 0; $i <= 99; $i++) {
    if ($i == $number) {
      $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
    } else {
      $html .= "<option value='" . $i . "'>" . $i . "</option>";
    }
  }
  $html .= "</select>";

  $html .= "</div>\n";
  $html .= "</div>\n";
}
$html .= "</div>";

$html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save") . "'/>";
$html .= "<div class='action-row action-row--half'>\n";
if ($teamId == $game_result['visitorteam']) {
  $html .= "<a href='?view=addplayerlists&game=" . $gameId . "&team=" . $game_result['hometeam'] . "' data-role='button' data-ajax='false'>" . utf8entities($game_result['hometeamname']) . " " . _("playerlist") . "</a>";
} else {
  $html .= "<a href='?view=addplayerlists&game=" . $gameId . "&team=" . $game_result['visitorteam'] . "' data-role='button' data-ajax='false'>" . utf8entities($game_result['visitorteamname']) . " " . _("playerlist") . "</a>";
}
$html .= "<a href='?view=addscoresheet&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Back to score sheet") . "</a>";
$html .= "</div>\n";
$html .= "<a class='back-resp-button' href='?view=respgames' data-role='button' data-ajax='false'>" . _("Back to game responsibilities") . "</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";
echo $html;
