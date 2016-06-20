<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/player.functions.php';
$html = "";

$gameId = intval(iget("game"));

mobilePageTop(_("Score&nbsp;sheet"));

$season = SeasonInfo(GameSeason($gameId));
if ($season['spiritmode']>0) {
  $game_result = GameResult($gameId);
  $mode = SpiritMode($season['spiritmode']);
  $categories = SpiritCategories($mode['mode']);
  
//process itself if save button was pressed
if(!empty($_POST['save'])) {
  $points = array();
  foreach ($_POST['homevalueId'] as $cat) {
    if (isset($_POST['homecat'.$cat]))
      $points[$cat] = $_POST['homecat'.$cat];
    else
      $missing = _("Missing score for ") . $game_result['hometeamname'];
  }
  GameSetSpiritPoints($gameId, $game_result['hometeam'], 1, $points, $categories);
  
  $points = array();
  foreach ($_POST['visvalueId'] as $cat) {
    if (isset($_POST['viscat'.$cat]))
      $points[$cat] = $_POST['viscat'.$cat];
    else
      $missing = _("Missing score for ") . $game_result['visitorteamname'];
  }
  GameSetSpiritPoints($gameId,$game_result['visitorteam'],0,$points, $categories);
  
  $game_result = GameResult($gameId);
}

$html .= "<form  method='post' action='?view=user/addspirit&amp;game=".$gameId."'>";

$html .= "<h3>"._("Spirit points given for").": ". utf8entities($game_result['hometeamname'])."</h3>\n";

$points = GameGetSpiritPoints($gameId, $game_result['hometeam']);
$html .= SpiritTable($game_result, $points, $categories, true, false);

$html .= "<h3>"._("Spirit points given for").": ". utf8entities($game_result['visitorteamname'])."</h3>\n";

$points = GameGetSpiritPoints($gameId, $game_result['visitorteam']);
$html .= SpiritTable($game_result, $points, $categories, false, false);

$html .= "<p>";
$html .= "<input class='button' type='submit' name='save' value='"._("Save")."'/>";
if (isset($missing))
  $html .= " $missing";
$html .= "</p>";
$html .= "<p><a href='?view=mobile/addscoresheet&amp;game=".$gameId."'>"._("Back to score sheet")."</a></p>";
$html .= "</form>\n";

} else {
  $html .= "<p>"._("Spiritpoints not given for") . utf8entities($season['name']) . "</p>";
}

echo $html;
		
pageEnd();
?>
