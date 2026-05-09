<?php
include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/player.functions.php';
include_once $include_prefix . 'lib/location.functions.php';
include_once $include_prefix . 'lib/configuration.functions.php';

if (empty($_GET["game"])) {
	showPage(_("Fill in scoresheet"), "<p class='warning'>" . _("Game not found") . ".</p>");
	return;
}

function ScoreSheetSpiritTimeoutValues($gameId, $home, $maxslots)
{
  $values = array();
  foreach (GameSpiritTimeoutsArray($gameId) as $timeout) {
    if ((int)$timeout['ishome'] === (int)$home && count($values) < $maxslots) {
      $values[] = SecToMin($timeout['time']);
    }
  }
  for ($i = count($values); $i < $maxslots; $i++) {
    $values[] = "";
  }
  return $values;
}

$gameId = intval($_GET["game"]);

if (!hasEditGameEventsRight($gameId))
  die('Insufficient rights to edit game');

$LAYOUT_ID = ADDSCORESHEET;
$title = _("Fill in scoresheet");
$maxtimeouts = 6;
$maxspirittimeouts = 4;
$maxscores = 41;
$season = GameSeason($gameId);
$seasoninfo = SeasonInfo($season);
$hideTimeOnScoresheet = !empty($seasoninfo['hide_time_on_scoresheet']);
ob_start();
//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
include_once 'lib/yui.functions.php';
echo yuiLoad(array("yahoo-dom-event"));
?>
<script type="text/javascript">
  var hideTimeOnScoresheet = <?php echo !empty($seasoninfo['hide_time_on_scoresheet']) ? "true" : "false"; ?>;
function moveNext(i,evt) {
  if (evt.which == 9) {
    id = 'pass'+i;
    document.getElementById(id).focus();
  }
}

  function validTime(field) {
    field.value = field.value.replace(/[^0-9]/g, '.');
  }

  function validNumber(field) {
    field.value = field.value.replace(/[^0-9]/g, '');
  }

  function validNumberX(field) {
    field.value = field.value.replace(/[^0-9|^xX]/g, '');
  }

  function highlightError(id) {
    var errorDiv = YAHOO.util.Dom.get(id);
    YAHOO.util.Dom.setStyle(errorDiv, "background-color", "#FF0000");
  }

  function updateScores(index) {
    var i = 0;
    var h = 0;
    var a = 0;

    for (i = 0; i < <?php echo $maxscores; ?>; i++) {
      var hradio = document.getElementById("hteam" + i);
      var aradio = document.getElementById("ateam" + i);

      if (hradio.checked) {
        h++;
      } else if (aradio.checked) {
        a++;
      } else {
        break;
      }

      var input = document.getElementById("sit" + i);
      input.value = h + " - " + a;
    }
  }

  function eraseLast() {
    var answer = confirm('<?php echo _("Are you sure you want to delete last score?"); ?>');
    if (answer) {

      var i = (<?php echo $maxscores; ?> - 1);i

      for (i; i >= 0; i = i - 1) {
        var hradio = document.getElementById("hteam" + i);
        var aradio = document.getElementById("ateam" + i);

        if (aradio.checked || hradio.checked) {
          var input = document.getElementById("sit" + i);
          input.value = "";
          var input = document.getElementById("pass" + i);
          input.value = "";
          var input = document.getElementById("goal" + i);
          input.value = "";
          if (!hideTimeOnScoresheet) {
            var input = document.getElementById("time" + i);
            if (input) {
              input.value = "";
            }
          }
          aradio.checked = false;
          hradio.checked = false;
          break;
        }
      }
    }
  }

  var focused;
  onload = function() {
    var el = document.getElementById('scoresheet').elements;
    for (var i = 0; i < el.length; i++) {
      el[i].onfocus = function() {
        focused = this
      };
    }
  };

  function chgFocus(event) {
    var code = event.keyCode ? event.keyCode : event.charCode;
    //alert(code);
    switch (code) {

      case 43:
        var elem = document.getElementById('scoresheet').elements;
        if (!focused) {
          focused = elem[0];
        }
        for (var i = 0; i < elem.length; i++) {
          if (elem[i] == focused) {

            i++;
            while (elem[i].disabled || elem[i].type == 'submit' || elem[i].type == 'reset') {
              i++;
            }
            elem[i].focus();
            focused = elem[i];
            break;
          }
        }
        break;
      case 13:
        var elem = document.getElementById('scoresheet').elements;
        if (!focused) {
          focused = elem[0];
        }
        if (focused.type == 'radio') {
          focused.checked = true;
          updateScores(0);
        }
        break;
    }
  }

  function keyfilter(e) {
    var evt = window.event ? event : e;
    var code = evt.keyCode ? evt.keyCode : evt.charCode;
    //alert(code);
    if (code == 43) {
      return false;
    }
    var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null);
    if ((code == 13) && ((node.type == "text") || (node.type == "checkbox") || (node.type == "radio"))) {
    evt.preventDefault();
    if (node.type=="text") {
      t = node.id.substring(0,4);
      n = node.id.substring(4);
      switch (t) {
        case "pass":
          document.getElementById("goal"+n).focus();
          break;
        case "goal":
          document.getElementById("time"+n).focus();
          break;
        case "time":
          document.getElementById("pass"+(+n+1)).focus();
          break;
      }
    }
  }
}
  document.onkeypress = keyfilter;
function clickButton(id){
  document.getElementById(id).click();
}
  //-->
</script>
<?php
$scrolling = "onkeypress='chgFocus(event);'";
pageTopHeadClose($title, false, $scrolling);
leftMenu($LAYOUT_ID);
contentStart();
//content
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




$game_result = GameResult($gameId);

$errIds = array();
$comment_feedback = "";
$game_comment = CommentRaw(COMMENT_TYPE_GAME, $gameId);
$game_comment_meta = GameCommentMeta($gameId, COMMENT_TYPE_GAME);
$game_comment_meta_html = CommentMetaHtml($game_comment_meta);
$can_create_comment = CanCreateGameComment($gameId);
$can_manage_comment = CanManageGameComment($gameId, COMMENT_TYPE_GAME);
$show_comment_form = ($can_create_comment || $can_manage_comment);
//process itself if submit was pressed
if (!empty($_POST['save'])) {
  $delete_comment = !empty($_POST['delete_game_comment']);
  if (isset($_POST['gamecomment']) || $delete_comment) {
    $saved = SetGameComment(COMMENT_TYPE_GAME, $gameId, $_POST['gamecomment'], $delete_comment);
    if (!$saved) {
      $comment_feedback = "<p class='warning'>" . _("Comment not saved.") . "</p>\n";
    }
    $game_comment = CommentRaw(COMMENT_TYPE_GAME, $gameId);
    $game_comment_meta = GameCommentMeta($gameId, COMMENT_TYPE_GAME);
    $game_comment_meta_html = CommentMetaHtml($game_comment_meta);
  }
  LogGameUpdate($gameId, "scoresheet saved", "addscoresheet");
  $time_delim = array(",", ";", ":");
  //set scoresheet keeper
  GameSetScoreSheetKeeper($gameId, $_POST['secretary']);

  $htime = 0;
  if (!$hideTimeOnScoresheet) {
    //set halftime
    $htime = $_POST['halftime'];
    $htime = str_replace($time_delim, ".", $htime);
    $htime = TimeToSec($htime);
    GameSetHalftime($gameId, $htime);
  }

  if (!empty($_POST['starting'])) {
    $starting = $_POST['starting'];
    if ($starting == "H") {
      GameSetStartingTeam($gameId, 1);
    } elseif ($starting == "V") {
      GameSetStartingTeam($gameId, 0);
    }
  }

  if (!$hideTimeOnScoresheet) {
    //remove all old timeouts (if any)
    GameRemoveAllTimeouts($gameId);

    //insert home timeouts
    $j = 0;
    for ($i = 0; $i < $maxtimeouts; $i++) {
      $time = $_POST['hto' . $i];
      $time = str_replace($time_delim, ".", $time);

      if (!empty($time)) {
        $j++;
        GameAddTimeout($gameId, $j, TimeToSec($time), 1);
      }
    }

    //insert away timeouts
    $j = 0;
    for ($i = 0; $i < $maxtimeouts; $i++) {
      $time = $_POST['ato' . $i];
      $time = str_replace($time_delim, ".", $time);

      if (!empty($time)) {
        $j++;
        GameAddTimeout($gameId, $j, TimeToSec($time), 0);
      }
    }

    if (!empty($seasoninfo['spiritmode'])) {
      GameRemoveAllSpiritTimeouts($gameId);

      $j = 0;
      for ($i = 0; $i < $maxspirittimeouts; $i++) {
        $time = $_POST['shto' . $i];
        $time = str_replace($time_delim, ".", $time);
        if (!empty($time)) {
          $j++;
          GameAddSpiritTimeout($gameId, $j, TimeToSec($time), 1);
        }
      }

      $j = 0;
      for ($i = 0; $i < $maxspirittimeouts; $i++) {
        $time = $_POST['sato' . $i];
        $time = str_replace($time_delim, ".", $time);
        if (!empty($time)) {
          $j++;
          GameAddSpiritTimeout($gameId, $j, TimeToSec($time), 0);
        }
      }
    }
  }

  //remove all old scores (if any)
  GameRemoveAllScores($gameId);

  //insert scores
  $h = 0;
  $a = 0;
  $prevtime = 0;
  for ($i = 0; $i < $maxscores; $i++) {
    $iscallahan = 0;
    $team = "";
    $pass = -1;
    $goal = -1;
    $time = "";
    if (!empty($_POST['team' . $i]))
      $team = $_POST['team' . $i];
    if (!empty($_POST['pass' . $i]) || $_POST['pass' . $i] == "0")
      $pass = $_POST['pass' . $i];
    if (!empty($_POST['goal' . $i])  || $_POST['goal' . $i] == "0")
      $goal = $_POST['goal' . $i];
    if ($hideTimeOnScoresheet) {
      $time = $prevtime + 1;
    } else {
      if (!empty($_POST['time' . $i])) {
        $time = $_POST['time' . $i];
      }

      $time = str_replace($time_delim, ".", $time);
      $time = TimeToSec($time);
      if (!empty($team) && $time == $htime) {
        echo "<p class='warning'>" . _("Point") . " ", $i + 1, ": " . _("time cannot be the same as halftime ending") . "!</p>";
        $errIds[] = "time$i";
      }

      if (!empty($team) && $time <= $prevtime) {
        echo "<p class='warning'>" . _("Point") . " ", $i + 1, ": " . _("time cannot be the same or earlier than the previous point") . "!</p>";
        $errIds[] = "time$i";
      }
    }

    if (strcasecmp($pass, 'xx') == 0 || strcasecmp($pass, 'x') == 0)
      $iscallahan = 1;

    $prevtime = $time;

    if (!empty($team) && $team == 'H') {
      $h++;
      if (!$iscallahan) {
        $pass = GamePlayerFromNumber($gameId, $game_result['hometeam'], $pass);
        if ($pass == -1) {
          echo "<p class='warning'>" . _("Point") . " ", $i + 1, ": " . _("assisting player's number") . " '" . $_POST['pass' . $i] . "' " . _("Not on the roster") . "!</p>";
          $errIds[] = "pass$i";
        }
      } else
        $pass = -1;

      $goal = GamePlayerFromNumber($gameId, $game_result['hometeam'], $goal);
      if ($goal == -1) {
        echo "<p class='warning'>" . _("Point") . " ", $i + 1, ": " . _("scorer's number") . " '" . $_POST['goal' . $i] . "' " . _("Not on the roster") . "!</p>";
        $errIds[] = "goal$i";
      }

      if ($pass == $goal) {
        echo "<p class='warning'>"._("Point")." ",$i+1,": "._("Scorer and assist have the same number")." '".$_POST['goal'.$i]."'!</p>";
        $errIds[] = "pass$i";
        $errIds[] = "goal$i";
      }

      GameAddScore($gameId, $pass, $goal, $time, $i + 1, $h, $a, 1, $iscallahan);
    } elseif (!empty($team) && $team == 'A') {
      $a++;
      if (!$iscallahan) {
        $pass = GamePlayerFromNumber($gameId, $game_result['visitorteam'], $pass);
        if ($pass == -1) {
          echo "<p class='warning'>" . _("Point") . " ", $i + 1, ": " . _("assisting player's number") . " '" . $_POST['pass' . $i] . "' " . _("Not on the roster") . "!</p>";
          $errIds[] = "pass$i";
        }
      } else
        $pass = -1;

      $goal = GamePlayerFromNumber($gameId, $game_result['visitorteam'], $goal);
      if ($goal == -1) {
        echo "<p class='warning'>" . _("Point") . " ", $i + 1, ": " . _("scorer's number") . " '" . $_POST['goal' . $i] . "' " . _("Not on the roster") . "!</p>";
        $errIds[] = "goal$i";
      }

      GameAddScore($gameId, $pass, $goal, $time, $i + 1, $h, $a, 0, $iscallahan);
    }
  }
  $isongoing = isset($_POST['isongoing']) ? 1 : 0;
  if ($isongoing) {
    echo "<p>" . sprintf(_("Game ongoing. Current score: %s - %s."), $h, $a) . ".</p>";
    $ok = GameUpdateResult($gameId, $h, $a);
  } elseif ($game_result['isongoing']) {
    $ok = GameSetResult($gameId, $h, $a);
    if ($ok) {
      echo "<p>" . sprintf(_("Final result saved: %s - %s."), $h, $a) . ".</p>";
    }
  }
  echo "<p>"._("Scoresheet saved")." (". _("at")." ".DefTimestamp().")!</p>";
  echo "<a href='?view=gameplay&amp;game=$gameId'>" . _("Gameplay") . "</a>";
}
$game_result = GameResult($gameId);
$place = ReservationInfo($game_result['reservation']);
$home_playerlist = GamePlayers($gameId, $game_result['hometeam']);
$away_playerlist = GamePlayers($gameId, $game_result['visitorteam']);

// get team abbreviations
if (!($homeshortname = utf8entities(TeamAbbreviation($game_result['hometeam'])))) {
  $homeshortname = _("Home");
}
if (!($visitorshortname = utf8entities(TeamAbbreviation($game_result['visitorteam'])))) {
  $visitorshortname = _("Away");
}

if(count($home_playerlist)==0){
  echo "<p class='warning'>".utf8entities($game_result['hometeamname'])." "._("has no played players for this game.")." <a href='?view=user/addplayerlists&amp;game=".$gameId."'>"._("Enter the players for this game.")."</a></p>";
}
if(count($away_playerlist)==0){
  echo "<p class='warning'>".utf8entities($game_result['visitorteamname'])." "._("has no played players for this game.")." <a href='?view=user/addplayerlists&amp;game=".$gameId."'>"._("Enter the players for this game.")."</a></p>";
}


echo "<form id='scoresheet' action='?view=user/addscoresheet&amp;game=$gameId' method='post'>";
echo "<table cellspacing='5' cellpadding='5'>";

echo "<tr><td colspan='2'><h1>" . _("Game scoresheet") . " #$gameId</h1></td></tr>";
echo "<tr><td valign='top'>\n";

//team, place, time info and scoresheet keeper's name
echo "<table cellspacing='0' width='100%' border='1'>";
echo "<tr><th>" . _("Home team") . "</th></tr>";
echo "<tr><td>". utf8entities($game_result['hometeamname'])." (".$homeshortname.")</td></tr>";
echo "<tr><th>" . _("Away team") . "</th></tr>";
echo "<tr><td>". utf8entities($game_result['visitorteamname'])." (".$visitorshortname.")</td></tr>";
echo "<tr><th>" . _("Field") . "</th></tr>";
echo "<tr><td>" . utf8entities(ReservationPlaceText($place['name'], $place['fieldname'])) . "</td></tr>";
echo "<tr><th>" . _("Scheduled start date and time") . "</th></tr>";
echo "<tr><td>" . ShortDate($game_result['time']) . " " . DefHourFormat($game_result['time']) . "</td></tr>";
echo "<tr><th>" . _("Game official(s)") . "</th></tr>";
echo "<tr><td><input class='input' style='width: 97%' type='text' name='secretary' id='secretary' value='" . utf8entities($game_result['official']) . "'/></td></tr>";
echo "</table>\n";

if ($show_comment_form) {
  echo "<table cellspacing='0' width='100%' border='1'>";
  echo "<tr><th>" . _("Game note") . "</th></tr>";
  if (!empty($game_comment_meta_html)) {
    echo "<tr><td>" . $game_comment_meta_html . "</td></tr>";
  }
  echo "<tr><td><textarea class='input' style='width: 98%' rows='5' name='gamecomment' maxlength='" . COMMENT_MAX_LENGTH . "' placeholder='" . _("Optional - note unusual events or interruptions.") . "'>" . htmlentities($game_comment) . "</textarea></td></tr>";
  if ($can_manage_comment && !empty($game_comment)) {
    echo "<tr><td><label><input type='checkbox' name='delete_game_comment' value='1'/> " . _("Delete comment") . "</label></td></tr>";
  }
  echo "</table>\n";
  echo $comment_feedback;
}

//starting team
$hoffence = "";
$voffence = "";
$ishome = GameIsFirstOffenceHome($gameId);
if ($ishome == 1) {
  $hoffence = "checked='checked'";
} elseif ($ishome == 0) {
  $voffence = "checked='checked'";
}

echo "<table cellspacing='0' width='100%' border='1'>\n";
echo "<tr><th colspan='2'>" . _("Starting offensive team") . "</th></tr>";
echo "<tr><td style='width: 40px' class='center'><input id='hstart' name='starting' type='radio' $hoffence value='H' /></td>";

echo "<td>" . utf8entities($game_result['hometeamname']) . "</td></tr>";
echo "<tr><td style='width: 40px' class='center'><input id='vstart' name='starting' type='radio' $voffence value='V' /></td>";
echo "<td>" . utf8entities($game_result['visitorteamname']) . "</td></tr>";
echo "</table>\n";

if (!$hideTimeOnScoresheet) {
  //timeouts
  echo "<table cellspacing='0' width='100%' border='1'>";
  echo "<tr><th colspan='", $maxtimeouts + 1, "'>" . _("Timeouts") . "</th></tr>\n";

echo "<tr><th>".$homeshortname."</th>\n";

  //home team used timeouts
  $i = 0;
  $timeouts = GameTimeouts($gameId);
  foreach ($timeouts as $timeout) {
    if (intval($timeout['ishome'])) {
      echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='4' maxlength='8' id='hto$i' name='hto$i' value='" . SecToMin($timeout['time']) . "' /></td>\n";
      $i++;
    }
  }

  //empty slots
  for ($i; $i < $maxtimeouts; $i++) {
    //two last slot are smaller for visual reasons
    if ($i > ($maxtimeouts - 3)) {
      echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='1' maxlength='8' id='hto$i' name='hto$i' value='' /></td>\n";
    } else {
      echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='4' maxlength='8' id='hto$i' name='hto$i' value='' /></td>\n";
    }
  }
  echo "</tr>\n";

echo "<tr><th>".$visitorshortname."</th>\n";

  //away team used timeouts
  $i = 0;
  foreach ($timeouts as $timeout) {
    if (!intval($timeout['ishome'])) {
      echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='4' maxlength='8' id='ato$i' name='ato$i' value='" . SecToMin($timeout['time']) . "' /></td>\n";
      $i++;
    }
  }

  //empty slots
  for ($i; $i < $maxtimeouts; $i++) {
    //two last slot are smaller for visual reasons
    if ($i > ($maxtimeouts - 3)) {
      echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='1' maxlength='8' id='ato$i' name='ato$i' value='' /></td>\n";
    } else {
      echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='4' maxlength='8' id='ato$i' name='ato$i' value='' /></td>\n";
    }
  }

  echo "</tr>";
  echo "</table>";

  //halftime
  echo "<table cellspacing='0' width='100%' border='1'>\n";
  echo "<tr><th>" . _("Halftime ended at") . "</th></tr>";
  echo "<tr><td><input class='input' onkeyup=\"validTime(this);\"
	maxlength='8' type='text' name='halftime' id='halftime' value='" . SecToMin($game_result['halftime']) . "'/></td></tr>";
  echo "</table>\n";
}

//result
echo "<table cellspacing='0' width='100%' border='1'>\n";
if ($game_result['isongoing']) {
  echo "<tr><th>" . _("Current score") . "</th></tr>";
} else {
  echo "<tr><th>" . _("Final score") . "</th></tr>";
}
echo "<tr><td>" . $game_result['homescore'] . " - " . $game_result['visitorscore'] . "</td></tr>";
echo "</table>\n";

if (!$hideTimeOnScoresheet && !empty($seasoninfo['spiritmode'])) {
  $homeSpiritTimeouts = ScoreSheetSpiritTimeoutValues($gameId, 1, $maxspirittimeouts);
  $awaySpiritTimeouts = ScoreSheetSpiritTimeoutValues($gameId, 0, $maxspirittimeouts);

  echo "<table cellspacing='0' width='100%' border='1'>";
  echo "<tr><th colspan='" . ($maxspirittimeouts + 1) . "'>" . _("Spirit stoppages") . "</th></tr>\n";

  echo "<tr><th>" . $homeshortname . "</th>\n";
  for ($i = 0; $i < $maxspirittimeouts; $i++) {
    if ($i > ($maxspirittimeouts - 3)) {
      echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='4' maxlength='8' id='shto$i' name='shto$i' value='" . utf8entities($homeSpiritTimeouts[$i]) . "' /></td>\n";
    } else {
      echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='8' maxlength='8' id='shto$i' name='shto$i' value='" . utf8entities($homeSpiritTimeouts[$i]) . "' /></td>\n";
    }
  }
  echo "</tr>\n";

  echo "<tr><th>" . $visitorshortname . "</th>\n";
  for ($i = 0; $i < $maxspirittimeouts; $i++) {
    if ($i > ($maxspirittimeouts - 3)) {
      echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='4' maxlength='8' id='sato$i' name='sato$i' value='" . utf8entities($awaySpiritTimeouts[$i]) . "' /></td>\n";
    } else {
      echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='8' maxlength='8' id='sato$i' name='sato$i' value='" . utf8entities($awaySpiritTimeouts[$i]) . "' /></td>\n";
    }
  }
  echo "</tr>\n";
  echo "</table>\n";
}

//buttons
echo "<table cellspacing='0' cellpadding='10' width='100%'>\n";
echo "<tr><td><input class='input' type='checkbox' name='isongoing' ";
if ($game_result['isongoing']) {
  echo "checked='checked'";
}
echo "/> " . _("Game ongoing") . "</td><td></td></tr>";
echo "<tr>";
echo "<td><input class='button' type='submit' value='" . _("Save scores") . "' name='save'/></td>";
echo "<td><input class='button' type='reset' value='" . _("Cancel") . "' name='reset'/></td>";
echo "</tr>";
echo "<tr><td colspan='2'>
		<a href='javascript://' onclick=\"eraseLast()\">" . _("Delete the last goal") . "</a></td></tr>";

echo "<tr><td colspan='2'>
<p>"._("Fill in the scoresheet").":</p>
<ul>
<li>"._("In addition to the Tab key, you can use the + key to move between fields and the Enter key to select a radio button.")."</li>
<li>"._("As a separator in the time field you can use ")." .,:; "._("characters").".</li>
<li>"._("Give XX as the assist in Callahan goals").".</li>
<li>"._("You can save the scoresheet at any time while entering it")."</li></ul></td></tr>";
echo "<tr><td colspan='2'><p><a href='?view=user/respgames'>"._("Back to game responsibilities")."</a></p></td></tr>";
echo "</table>\n";

//scores
$style_left = "border-left-style:solid;border-left-width:1px;border-left-color:#000000;";
$style_left .= "border-right-style:dashed;border-right-width:1px;border-right-color:#E0E0E0;";
$style_left .= "border-top-style:solid;border-top-width:1px;border-top-color:#000000;";
$style_left .= "border-bottom-style:solid;border-bottom-width:1px;border-bottom-color:#000000;";

$style_mid = "border-top-style:solid;border-top-width:1px;border-top-color:#000000;";
$style_mid .= "border-bottom-style:solid;border-bottom-width:1px;border-bottom-color:#000000;";
$style_mid .= "border-left-style:dashed;border-left-width:1px;border-left-color:#E0E0E0;";
$style_mid .= "border-right-style:dashed;border-right-width:1px;border-right-color:#E0E0E0;";

$style_right = "border-right-style:solid;border-right-width:1px;border-right-color:#000000;";
$style_right .= "border-top-style:solid;border-top-width:1px;border-top-color:#000000;";
$style_right .= "border-bottom-style:solid;border-bottom-width:1px;border-bottom-color:#000000;";
$style_right .= "border-left-style:dashed;border-left-width:1px;border-left-color:#E0E0E0;";

echo "</td><td>";
echo "<table style='border-collapse:collapse' cellspacing='0' cellpadding='2' border='0'>\n";
echo "<tr><th style='background-color:#FFFFFF;border-style:none;border-width:0;border-color:#FFFFFF'></th>";
echo "<th style='$style_left'>".$homeshortname."</th><th style='$style_mid'>".$visitorshortname."</th>";
echo "<th style='$style_mid'>" . _("Assist") . "</th><th style='$style_mid'>" . _("Goal") . "</th>";
if (!$hideTimeOnScoresheet) {
  echo "<th style='$style_mid'>" . _("Time") . "</th>";
}
echo "<th style='$style_right'>" . _("Score") . "</th></tr>\n";

$scores = GameGoals($gameId);

$i = 0;
foreach ($scores as $row) {

  echo "<tr>";
  echo "<td class='center' style='width: 25px;color:#B0B0B0;'>", $i + 1, "</td>\n";

  if (intval($row['ishomegoal']))
  {
    echo "<td style='width:40px;$style_left' class='center' onclick=\"clickButton('hteam$i');\"><input onclick=\"updateScores($i);\" onkeyup=\"moveNext($i,event);\" id='hteam$i' name='team$i' type='radio' checked='checked' value='H' /></td>";
    echo "<td style='width:40px;$style_mid' class='center' onclick=\"clickButton('ateam$i');\"><input onclick=\"updateScores($i);\" onkeyup=\"moveNext($i,event);\" id='ateam$i' name='team$i' type='radio' value='A' /></td>";
  }
  else
  {
    echo "<td style='width:40px;$style_left' class='center' onclick=\"clickButton('hteam$i');\"><input onclick=\"updateScores($i);\" onkeyup=\"moveNext($i,event);\" id='hteam$i' name='team$i' type='radio' value='H' /></td>";
    echo "<td style='width:40px;$style_mid' class='center' onclick=\"clickButton('ateam$i');\"><input onclick=\"updateScores($i);\" onkeyup=\"moveNext($i,event);\" id='ateam$i' name='team$i' type='radio' checked='checked' value='A' /></td>";
  }

  if (intval($row['iscallahan'])) {
    echo "<td class='center' style='width:50px;$style_mid'><input class='input' onkeyup=\"validNumberX(this);\" id='pass$i' name='pass$i' maxlength='3' size='4' value='XX'/></td>";
  } else {
    $n = PlayerNumber($row['assist'], $gameId);
    if ($n < 0)
      $n = "";

    echo "<td class='center' style='width:50px;$style_mid'><input class='input' onkeyup=\"validNumberX(this);\" id='pass$i' name='pass$i' maxlength='3' size='4' value='$n'/></td>";
  }

  $n = PlayerNumber($row['scorer'], $gameId);
  if ($n < 0)
    $n = "";

  echo "<td class='center' style='width:50px;$style_mid'><input class='input' onkeyup=\"validNumber(this);\" id='goal$i' name='goal$i' maxlength='2' size='3' value='$n'/></td>";
  if (!$hideTimeOnScoresheet) {
    echo "<td style='width:60px;$style_mid'><input class='input' onkeyup=\"validTime(this);\" id='time$i' name='time$i' maxlength='8' size='8' value='" . SecToMin($row['time']) . "'/></td>";
  }
  echo "<td class='center' style='width:60px;$style_right'><input class='fakeinput center' id='sit$i' name='sit$i' size='7' disabled='disabled'
	value='" . utf8entities($row['homescore']) . " - " . $row['visitorscore'] . "'/></td>";

  echo "</tr>\n";
  $i++;
}

for ($i; $i < $maxscores; $i++) {
  echo "<tr>";
  echo "<td class='center' style='width:25px;color:#B0B0B0;'>", $i + 1, "</td>\n";
  echo "<td class='center' style='width:40px;$style_left' onclick=\"clickButton('hteam$i');\"><input onclick=\"updateScores($i);\" onkeyup=\"moveNext($i,event);\" id='hteam$i' name='team$i' type='radio' value='H' /></td>";
  echo "<td class='center' style='width:40px;$style_mid' onclick=\"clickButton('ateam$i');\"><input onclick=\"updateScores($i);\" onkeyup=\"moveNext($i,event);\" id='ateam$i' name='team$i' type='radio' value='A' /></td>";
  echo "<td class='center' style='width:50px;$style_mid'><input class='input' onkeyup=\"validNumberX(this);\" id='pass$i' name='pass$i' size='3' maxlength='2'/></td>";
  echo "<td  class='center' style='width:50px;$style_mid'><input class='input' onkeyup=\"validNumber(this);\" id='goal$i' name='goal$i' size='3' maxlength='2'/></td>";
  if (!$hideTimeOnScoresheet) {
    echo "<td style='width:60px;$style_mid'><input class='input' onkeyup=\"validTime(this);\" id='time$i' name='time$i' maxlength='8' size='8'/></td>";
  }
  echo "<td class='center' style='width:60px;$style_right'><input class='fakeinput center' id='sit$i' name='sit$i' size='7' disabled='disabled'/></td>";
  echo "</tr>\n";
}
echo "</table>\n";
echo "</td></tr></table></form>\n";

foreach ($errIds as $id) {
  echo "<script type=\"text/javascript\">highlightError(\"$id\");</script>";
}

//common end
contentEnd();
pageEnd();
ob_end_flush();
?>
