<?php
include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/player.functions.php';

function PlayerRoleSelectionValue($isCaptain, $isSpiritCaptain)
{
  if ($isCaptain && $isSpiritCaptain) {
    return "both";
  }
  if ($isCaptain) {
    return "captain";
  }
  if ($isSpiritCaptain) {
    return "spirit_captain";
  }

  return "";
}

function PlayerRoleSelectedIds($postedRoles, $selectedValue)
{
  $playerIds = array();
  foreach ((array)$postedRoles as $playerId => $role) {
    if ($role === $selectedValue || ($selectedValue !== "" && $role === "both")) {
      $playerIds[] = (int)$playerId;
    }
  }

  return $playerIds;
}

$LAYOUT_ID = ADDPLAYERLISTS;
$title = _("Rosters");
$gameId = intval($_GET["game"]);

if (!hasEditGameEventsRight($gameId))
  die('Insufficient rights to edit game');

$game_result = GameResult($gameId);

$season = GameSeason($gameId);
if (isset($_SERVER['HTTP_REFERER'])) {
  $backurl = utf8entities($_SERVER['HTTP_REFERER']);
} else {
  $backurl = "?view=user/respgames&season=$season";
}
$seasoninfo = SeasonInfo($season);
$home_playerlist = TeamPlayerList($game_result['hometeam']);
$away_playerlist = TeamPlayerList($game_result['visitorteam']);

$html = "";
$html2 = "";

//process itself if submit was pressed
if (!empty($_POST['save'])) {
  $backurl = $_POST['backurl'];
  LogGameUpdate($gameId, "playerlist saved", "addplayerlist");
  //HOME PLAYERS
  $played_players = GamePlayers($gameId, $game_result['hometeam']);

  //delete unchecked players
  foreach ($played_players as $player) {
    $found = false;
    if (!empty($_POST["homecheck"])) {
      foreach ($_POST["homecheck"] as $playerId) {
        if ($player['player_id'] == $playerId) {
          $found = true;
          break;
        }
      }
    }
    if (!$found) {
      GameRemovePlayer($gameId, $player['player_id']);
    }
  }

  //handle checked players
  if (!empty($_POST["homecheck"])) {
    foreach ($_POST["homecheck"] as $playerId) {
      $number = $_POST["p$playerId"];
      //if number
      if (is_numeric($number)) {
        //check if already in list with correct number
        $played_players = GamePlayers($gameId, $game_result['hometeam']);
        $found = false;
        foreach ($played_players as $player) {
          //$html .= "<p>".$player['player_id']."==".$playerId ."&&". $player['num']."==".$number."</p>";

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
            $html2 .= "<p  class='warning'><i>" . utf8entities($playerinfo1['firstname'] . " " . $playerinfo1['lastname']) . "</i> " . _("and")
              . " <i>" . utf8entities($playerinfo2['firstname'] . " " . $playerinfo2['lastname']) . "</i> " . _("same number") . " '$number'.</p>";
            $found = true;
            break;
          }
        }

        if (!$found) {
          GameAddPlayer($gameId, $playerId, $number);
        }
      } else {
        $playerinfo = PlayerInfo($playerId);
        $html2 .= "<p  class='warning'><i>" . utf8entities($playerinfo['firstname'] . " " . $playerinfo['lastname']) . "</i> " . _("erroneous number") . " '$number'.</p>";
      }
    }
  }
  //AWAY PLAYERS
  $played_players = GamePlayers($gameId, $game_result['visitorteam']);

  //delete unchecked players
  foreach ($played_players as $player) {
    $found = false;
    if (!empty($_POST["awaycheck"])) {
      foreach ($_POST["awaycheck"] as $playerId) {
        if ($player['player_id'] == $playerId) {
          $found = true;
          break;
        }
      }
    }
    if (!$found) {
      GameRemovePlayer($gameId, $player['player_id']);
    }
  }

  if (!empty($_POST["awaycheck"])) {
    //handle checked players
    foreach ($_POST["awaycheck"] as $playerId) {
      $number = $_POST["p$playerId"];
      //if number
      if (is_numeric($number)) {
        //check if already in list with correct number
        $played_players = GamePlayers($gameId, $game_result['visitorteam']);
        $found = false;
        foreach ($played_players as $player) {
          //$html .= "<p>".$player['player_id']."==".$playerId ."&&". $player['num']."==".$number."</p>";

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
            $html2 .= "<p  class='warning'><i>" . utf8entities($playerinfo1['firstname'] . " " . $playerinfo1['lastname']) . "</i> " . _("and")
              . " <i>" . utf8entities($playerinfo2['firstname'] . " " . $playerinfo2['lastname']) . "</i> " . _("same number") . "'$number'.</p>";
            $found = true;
            break;
          }
        }

        if (!$found) {
          GameAddPlayer($gameId, $playerId, $number);
        }
      } else {
        $playerinfo = PlayerInfo($playerId);
        $html2 .= "<p  class='warning'><i>" . utf8entities($playerinfo['firstname'] . " " . $playerinfo['lastname']) . "</i> " . _("erroneous number") . " '$number'.</p>";
      }
    }
  }
  GameSetCaptains($gameId, $game_result['hometeam'], PlayerRoleSelectedIds($_POST['homerole'] ?? array(), "captain"));
  GameSetCaptains($gameId, $game_result['visitorteam'], PlayerRoleSelectedIds($_POST['awayrole'] ?? array(), "captain"));
  GameSetSpiritCaptains($gameId, $game_result['hometeam'], PlayerRoleSelectedIds($_POST['homerole'] ?? array(), "spirit_captain"));
  GameSetSpiritCaptains($gameId, $game_result['visitorteam'], PlayerRoleSelectedIds($_POST['awayrole'] ?? array(), "spirit_captain"));
  $html2 .= "<p>" . _("Player lists saved!") . "</p>";
}

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
?>
<script type="text/javascript">
  function toggleField(checkbox, fieldids) {
    var ids = fieldids.split(",");
    for (var i = 0; i < ids.length; i++) {
      var input = document.getElementById(ids[i]);
      if (input) {
        input.disabled = !checkbox.checked;
        if (!checkbox.checked && input.type == "checkbox") {
          input.checked = false;
        }
      }
    }
  }

  function checkAll(field) {
    var div = document.getElementById(field);
    var elems = div.getElementsByTagName("input");
    var playedCheckboxes = [];
    for (var i = 0; i < elems.length; i++) {
      if (elems[i].className.indexOf("played-toggle") !== -1) {
        playedCheckboxes.push(elems[i]);
      }
    }

    for (var j = 0; j < playedCheckboxes.length; j++) {
      playedCheckboxes[j].checked = !playedCheckboxes[j].checked;
      toggleField(playedCheckboxes[j], playedCheckboxes[j].getAttribute("data-fields"));
    }
  }
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$menutabs[_("Result")] = "?view=user/addresult&game=$gameId";
$menutabs[_("Players")] = "?view=user/addplayerlists&game=$gameId";
$menutabs[_("Scoresheet")] = "?view=user/addscoresheet&game=$gameId";
if (!empty($seasoninfo['spiritmode'])) {
  $spiritUrl = SpiritEntryUrl($gameId);
  if (!empty($spiritUrl)) {
    $menutabs[_("Spirit score")] = $spiritUrl;
  }
}
if (ShowDefenseStats()) {
  $menutabs[_("Defence sheet")] = "?view=user/adddefensesheet&game=$gameId";
}

pageMenu($menutabs);



$html .= "<form method='post' action='?view=user/addplayerlists&amp;game=" . $gameId . "'>";

$html .= "<table width='600px'><tr><td valign='top' style='width:45%'>\n";

$html .= "<table width='100%' cellspacing='0' cellpadding='0' border='0'>\n";
$html .= "<tr style='height=20'><td align='center'><b>";
$html .= utf8entities($game_result['hometeamname']) . "</b></td></tr>\n";
$html .= "</table><div id='home'><table width='100%' cellspacing='0' cellpadding='2' border='0' style='table-layout: fixed'>";
$html .= "<tr><th class='home center' style='width:32px'><div style='line-height:1.05; text-align:center'>" . _("In") . "<br/><input type='checkbox' style='margin: 1px 0 0 0; vertical-align: middle' onclick='checkAll(\"home\");'/></div></th><th class='home'>" . _("Name") . "</th><th class='home center' style='width:44px'>" . _("Jersey") . "</th><th class='home center' style='width:50px'>" . _("Info") . "</th></tr>\n";

$played_players = GamePlayers($gameId, $game_result['hometeam']);
$homeCaptains = array_flip(GameCaptains($gameId, $game_result['hometeam']));
$homeSpiritCaptains = array_flip(GameSpiritCaptains($gameId, $game_result['hometeam']));

$i = 0;
foreach ($home_playerlist as $player) {
  $i++;
  $playerinfo = PlayerInfo($player['player_id']);
  $playerId = (int)$player['player_id'];
  $numberFieldId = "p" . $playerId;
  $roleFieldId = "homerole" . $playerId;
  $fieldIds = $numberFieldId . "," . $roleFieldId;
  $selectedRole = PlayerRoleSelectionValue(isset($homeCaptains[$playerId]), isset($homeSpiritCaptains[$playerId]));
  $html .= "<tr>";
  $number = PlayerNumber($playerId, $gameId);
  if ($number < 0) {
    $number = "";
  }

  $found = false;
  foreach ($played_players as $playedPlayer) {
    if ($player['player_id'] == $playedPlayer['player_id']) {
      $found = true;
      break;
    }
  }

  if ($found) {
    $html .= "<td class='center' style='width:32px'>
			<input class='played-toggle' data-fields='" . $fieldIds . "' onchange=\"toggleField(this,'" . $fieldIds . "');\" type='checkbox' name='homecheck[]' value='" . utf8entities($playerId) . "' checked='checked'/></td>";
    $html .= "<td>" . utf8entities($playerinfo['firstname'] . " " . $playerinfo['lastname']) . "</td>";
    $html .= "<td class='left' style='width:44px'><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p" . $playerId . "' id='" . $numberFieldId . "' style='width: 24px' maxlength='3' size='2' value='$number'/></td>";
    $html .= "<td class='center' style='width:50px'><select class='dropdown' style='width: 46px' name='homerole[" . $playerId . "]' id='" . $roleFieldId . "'>";
    $html .= "<option value=''" . ($selectedRole === "" ? " selected='selected'" : "") . "></option>";
    $html .= "<option value='captain'" . ($selectedRole === "captain" ? " selected='selected'" : "") . ">" . _("C") . "</option>";
    $html .= "<option value='spirit_captain'" . ($selectedRole === "spirit_captain" ? " selected='selected'" : "") . ">" . _("SC") . "</option>";
    $html .= "<option value='both'" . ($selectedRole === "both" ? " selected='selected'" : "") . ">" . _("C&SC") . "</option>";
    $html .= "</select></td>";
  } else {
    $html .= "<td class='center' style='width:32px'>
			<input class='played-toggle' data-fields='" . $fieldIds . "' onchange=\"toggleField(this,'" . $fieldIds . "');\" type='checkbox' name='homecheck[]' value='" . utf8entities($playerId) . "'/></td>";
    $html .= "<td>" . utf8entities($playerinfo['firstname'] . " " . $playerinfo['lastname']) . "</td>";
    $html .= "<td class='left' style='width:44px'><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p" . $playerId . "' id='" . $numberFieldId . "' style='width: 24px' maxlength='3' size='2' value='$number' disabled='disabled'/></td>";
    $html .= "<td class='center' style='width:50px'><select class='dropdown' style='width: 46px' name='homerole[" . $playerId . "]' id='" . $roleFieldId . "' disabled='disabled'>";
    $html .= "<option value='' selected='selected'></option>";
    $html .= "<option value='captain'>" . _("C") . "</option>";
    $html .= "<option value='spirit_captain'>" . _("SC") . "</option>";
    $html .= "<option value='both'>" . _("C&SC") . "</option>";
    $html .= "</select></td>";
  }
  $html .= "</tr>\n";
}
$html .= "<tr><td colspan='4'>";
$html .= _("Total number of players:") . " " . count($home_playerlist);
$html .= "</td></tr>";

$html .= "</table></div></td>\n<td style='width:10%'>&nbsp;</td><td valign='top' style='width:45%'>";

$html .= "<table width='100%' cellspacing='0' cellpadding='0' border='0'>";
$html .= "<tr><td><b>";
$html .= utf8entities($game_result['visitorteamname']) . "</b></td></tr>\n";
$html .= "</table><div id='away'><table width='100%' cellspacing='0' cellpadding='2' border='0' style='table-layout: fixed'>";
$html .= "<tr><th class='guest center' style='width:32px'><div style='line-height:1.05; text-align:center'>" . _("In") . "<br/><input type='checkbox' style='margin: 1px 0 0 0; vertical-align: middle' onclick='checkAll(\"away\");'/></div></th><th class='guest'>" . _("Name") . "</th><th class='guest center' style='width:44px'>" . _("Jersey") . "</th><th class='guest center' style='width:50px'>" . _("Info") . "</th></tr>\n";

$played_players = GamePlayers($gameId, $game_result['visitorteam']);
$awayCaptains = array_flip(GameCaptains($gameId, $game_result['visitorteam']));
$awaySpiritCaptains = array_flip(GameSpiritCaptains($gameId, $game_result['visitorteam']));

$i = 0;
foreach ($away_playerlist as $player) {
  $i++;
  $playerinfo = PlayerInfo($player['player_id']);
  $playerId = (int)$player['player_id'];
  $numberFieldId = "p" . $playerId;
  $roleFieldId = "awayrole" . $playerId;
  $fieldIds = $numberFieldId . "," . $roleFieldId;
  $selectedRole = PlayerRoleSelectionValue(isset($awayCaptains[$playerId]), isset($awaySpiritCaptains[$playerId]));
  $html .= "<tr>";
  $number = PlayerNumber($playerId, $gameId);
  if ($number < 0) {
    $number = "";
  }

  $found = false;
  foreach ($played_players as $playedPlayer) {
    if ($player['player_id'] == $playedPlayer['player_id']) {
      $found = true;
      break;
    }
  }

  if ($found) {
    $html .= "<td class='center' style='width:32px'>
			<input class='played-toggle' data-fields='" . $fieldIds . "' onchange=\"toggleField(this,'" . $fieldIds . "');\" type='checkbox' name='awaycheck[]' value='" . utf8entities($playerId) . "' checked='checked'/></td>";
    $html .= "<td>" . utf8entities($playerinfo['firstname'] . " " . $playerinfo['lastname']) . "</td>";
    $html .= "<td style='width:44px'><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p" . $playerId . "' id='" . $numberFieldId . "' style='width: 24px' maxlength='3' size='2' value='$number'/></td>";
    $html .= "<td class='center' style='width:50px'><select class='dropdown' style='width: 46px' name='awayrole[" . $playerId . "]' id='" . $roleFieldId . "'>";
    $html .= "<option value=''" . ($selectedRole === "" ? " selected='selected'" : "") . "></option>";
    $html .= "<option value='captain'" . ($selectedRole === "captain" ? " selected='selected'" : "") . ">" . _("C") . "</option>";
    $html .= "<option value='spirit_captain'" . ($selectedRole === "spirit_captain" ? " selected='selected'" : "") . ">" . _("SC") . "</option>";
    $html .= "<option value='both'" . ($selectedRole === "both" ? " selected='selected'" : "") . ">" . _("C&SC") . "</option>";
    $html .= "</select></td>";
  } else {
    $html .= "<td class='center' style='width:32px'>
			<input class='played-toggle' data-fields='" . $fieldIds . "' onchange=\"toggleField(this,'" . $fieldIds . "');\" type='checkbox' name='awaycheck[]' value='" . utf8entities($playerId) . "'/></td>";
    $html .= "<td>" . utf8entities($playerinfo['firstname'] . " " . $playerinfo['lastname']) . "</td>";
    $html .= "<td style='width:44px'><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p" . $playerId . "' id='" . $numberFieldId . "' style='width: 24px' maxlength='3' size='2' value='$number' disabled='disabled'/></td>";
    $html .= "<td class='center' style='width:50px'><select class='dropdown' style='width: 46px' name='awayrole[" . $playerId . "]' id='" . $roleFieldId . "' disabled='disabled'>";
    $html .= "<option value='' selected='selected'></option>";
    $html .= "<option value='captain'>" . _("C") . "</option>";
    $html .= "<option value='spirit_captain'>" . _("SC") . "</option>";
    $html .= "<option value='both'>" . _("C&SC") . "</option>";
    $html .= "</select></td>";
  }
  $html .= "</tr>\n";
}
$html .= "<tr><td colspan='4'>";
$html .= _("Total number of players:") . " " . count($away_playerlist);
$html .= "</td></tr>";

$html .= "</table></div></td></tr></table>\n";

$html .= $html2;

$html .= "<div><input type='hidden' name='backurl' value='$backurl'/></div>";
$html .= "<p><input class='button' type='submit' name='save' value='" . _("Save") . "'/></p></form>";

echo $html;

//common end
contentEnd();
pageEnd();
?>
