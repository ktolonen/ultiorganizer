<?php

include_once __DIR__ . '/auth.php';

function ScorekeeperPlayerRoleSelectionValue($isCaptain, $isSpiritCaptain)
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

function ScorekeeperPlayerRoleSelectedIds($postedRoles, $selectedValue)
{
    $playerIds = [];
    foreach ((array) $postedRoles as $playerId => $role) {
        if ($role === $selectedValue || ($selectedValue !== "" && $role === "both")) {
            $playerIds[] = (int) $playerId;
        }
    }

    return $playerIds;
}

$html = "";


$gameId = scorekeeperRequestGameId();
$teamId = scorekeeperRequestTeamId();

$_SESSION['game'] = $gameId;
$_SESSION['team'] = $teamId;

$game_result = GameResult($gameId);
$seasoninfo = SeasonInfo(GameSeason($gameId));
$requireAccreditation = !empty($seasoninfo['require_accreditation']);

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
        if (!$found) {
            GameRemovePlayer($gameId, $player['player_id']);
        }
    }

    // Roster as it stood before this save, used to tell an attempted addition
    // apart from a player who is already on the roster.
    $rosteredPlayerIds = [];
    foreach ($played_players as $player) {
        $rosteredPlayerIds[(int) $player['player_id']] = true;
    }

    //handle checked players
    if (!empty($_POST["check"])) {
        foreach ($_POST["check"] as $playerId) {
            $number = $_POST["p$playerId"];

            // The form disables unaccredited players, but a disabled checkbox
            // is trivially bypassed, so enforce it here as well.
            if ($requireAccreditation && empty($rosteredPlayerIds[(int) $playerId])) {
                $playerinfo = PlayerInfo($playerId);
                if (empty($playerinfo['accredited'])) {
                    $html .= "<p class='warning'><i>" . utf8entities($playerinfo['firstname'] . " " . $playerinfo['lastname']) . "</i> "
                      . _("is not accredited and cannot be added to the roster.") . "</p>";
                    continue;
                }
            }

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
                          . " <i>" . utf8entities($playerinfo2['firstname'] . " " . $playerinfo2['lastname']) . "</i> " . _("have the same jersey number") . " '" . utf8entities($number) . "'.</p>";
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    GameAddPlayer($gameId, $playerId, $number);
                }
            } else {
                $playerinfo = PlayerInfo($playerId);
                $html .= "<p  class='warning'><i>" . utf8entities($playerinfo['firstname'] . " " . $playerinfo['lastname']) . "</i> " . _("has an invalid jersey number") . " '" . utf8entities($number) . "'.</p>";
            }
        }
    }

    GameSetCaptains($gameId, $teamId, ScorekeeperPlayerRoleSelectedIds($_POST['role'] ?? [], "captain"));
    GameSetSpiritCaptains($gameId, $teamId, ScorekeeperPlayerRoleSelectedIds($_POST['role'] ?? [], "spirit_captain"));

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
$captains = array_flip(GameCaptains($gameId, $teamId));
$spiritCaptains = array_flip(GameSpiritCaptains($gameId, $teamId));

$html .= "<form action='?view=addplayerlists&amp;game=" . $gameId . "&amp;team=" . $teamId . "' method='post' data-ajax='false'>\n";

$played_players = GamePlayers($gameId, $teamId);

$html .= "<script type='text/javascript'>
function scorekeeperToggleField(checkbox, fieldIds) {
  var ids = fieldIds.split(',');
  for (var i = 0; i < ids.length; i++) {
    var input = document.getElementById(ids[i]);
    if (input) {
      input.disabled = !checkbox.checked;
    }
  }
}
</script>\n";

$html .= "<div class='player-list'>\n";
$html .= "<div class='player-row player-row--header'>\n";
$html .= "<div class='player-check'></div>\n";
$html .= "<div class='player-name'><h3>" . _("Name") . "</h3></div>\n";
$html .= "<div class='player-number'><h3>" . _("Jersey") . "</h3></div>\n";
$html .= "<div class='player-role'><h3>" . _("Info") . "</h3></div>\n";
$html .= "</div>\n";
$i = 0;
foreach ($playerlist as $player) {
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
    $playerId = (int) $player['player_id'];
    $numberFieldId = "p" . $playerId;
    $roleFieldId = "role" . $playerId;
    $fieldIds = $numberFieldId . "," . $roleFieldId;
    $selectedRole = ScorekeeperPlayerRoleSelectionValue(isset($captains[$playerId]), isset($spiritCaptains[$playerId]));

    // An unaccredited player who is not on the roster yet cannot be added.
    // One who is already on it keeps working controls, so the scorekeeper can
    // deliberately remove them instead of the row silently dropping a player
    // who may already have recorded goals.
    $blocked = $requireAccreditation && empty($player['accredited']) && !$found;
    $rowActive = ($found || count($played_players) == 0) && !$blocked;

    $html .= "<div class='player-row'>\n";
    if ($rowActive) {
        $html .= "<label class='player-check' for='" . $checkboxId . "'><input class='played-toggle' data-fields='" . $fieldIds . "' onchange=\"scorekeeperToggleField(this,'" . $fieldIds . "');\" type='checkbox' id='" . $checkboxId . "' name='check[]' value='" . utf8entities($playerId) . "' checked='checked'/></label>";
    } elseif ($blocked) {
        $html .= "<label class='player-check' for='" . $checkboxId . "'><input class='played-toggle' data-fields='" . $fieldIds . "' type='checkbox' id='" . $checkboxId . "' name='check[]' value='" . utf8entities($playerId) . "' disabled='disabled'/></label>";
    } else {
        $html .= "<label class='player-check' for='" . $checkboxId . "'><input class='played-toggle' data-fields='" . $fieldIds . "' onchange=\"scorekeeperToggleField(this,'" . $fieldIds . "');\" type='checkbox' id='" . $checkboxId . "' name='check[]' value='" . utf8entities($playerId) . "' /></label>";
    }
    $html .= "<label class='player-name' for='" . $checkboxId . "'>" . utf8entities($playerinfo['firstname'] . " " . $playerinfo['lastname']);
    if ($requireAccreditation && empty($player['accredited'])) {
        $html .= "<span class='player-not-accredited'>" . _("Not accredited") . "</span>";
    }
    $html .= "</label>";
    $html .= "<div class='player-number'>\n";
    if ($rowActive) {
        $html .= "<select name='p" . $playerId . "' id='" . $numberFieldId . "'>";
    } else {
        $html .= "<select name='p" . $playerId . "' id='" . $numberFieldId . "' disabled='disabled'>";
    }
    for ($i = 0; $i <= 99; $i++) {
        if ($i == $number) {
            $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
        } else {
            $html .= "<option value='" . $i . "'>" . $i . "</option>";
        }
    }
    $html .= "</select>";
    $html .= "</div>\n";
    $html .= "<div class='player-role'>\n";
    if ($rowActive) {
        $html .= "<select class='dropdown dropdown--compact' name='role[" . $playerId . "]' id='" . $roleFieldId . "'>";
        $html .= "<option value=''" . ($selectedRole === "" ? " selected='selected'" : "") . "></option>";
        $html .= "<option value='captain'" . ($selectedRole === "captain" ? " selected='selected'" : "") . ">" . _("C") . "</option>";
        $html .= "<option value='spirit_captain'" . ($selectedRole === "spirit_captain" ? " selected='selected'" : "") . ">" . _("SC") . "</option>";
        $html .= "<option value='both'" . ($selectedRole === "both" ? " selected='selected'" : "") . ">" . _("C&SC") . "</option>";
        $html .= "</select>";
    } else {
        $html .= "<select class='dropdown dropdown--compact' name='role[" . $playerId . "]' id='" . $roleFieldId . "' disabled='disabled'>";
        $html .= "<option value='' selected='selected'></option>";
        $html .= "<option value='captain'>" . _("C") . "</option>";
        $html .= "<option value='spirit_captain'>" . _("SC") . "</option>";
        $html .= "<option value='both'>" . _("C&SC") . "</option>";
        $html .= "</select>";
    }
    $html .= "</div>\n";
    $html .= "</div>\n";
}
$html .= "</div>";

$html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save") . "'/>";
$html .= "<div class='action-row action-row--half'>\n";
if ($teamId == $game_result['visitorteam']) {
    $html .= "<a href='?view=addplayerlists&game=" . $gameId . "&team=" . $game_result['hometeam'] . "' data-role='button' data-ajax='false'>" . utf8entities($game_result['hometeamname']) . " " . _("Roster") . "</a>";
} else {
    $html .= "<a href='?view=addplayerlists&game=" . $gameId . "&team=" . $game_result['visitorteam'] . "' data-role='button' data-ajax='false'>" . utf8entities($game_result['visitorteamname']) . " " . _("Roster") . "</a>";
}
$html .= "<a href='?view=addscoresheet&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Back to scoresheet") . "</a>";
$html .= "</div>\n";
$html .= "<a class='back-resp-button' href='?view=respgames' data-role='button' data-ajax='false'>" . _("Back to game responsibilities") . "</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";
echo $html;
