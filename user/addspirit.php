<?php
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/standings.functions.php';
include_once $include_prefix . 'lib/pool.functions.php';
include_once $include_prefix . 'lib/configuration.functions.php';

$html = "";

$gameId = intval($_GET["game"]);

$title = _("Spirit");

$season = SeasonInfo(GameSeason($gameId));
if ($season['spiritmode'] > 0) {
  $game_result = GameResult($gameId);
  $mode = SpiritMode($season['spiritmode']);
  $categories = SpiritCategories($mode['mode']);

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
  }


  $menutabs[_("Result")] = "?view=user/addresult&game=$gameId";
  $menutabs[_("Players")] = "?view=user/addplayerlists&game=$gameId";
  $menutabs[_("Score sheet")] = "?view=user/addscoresheet&game=$gameId";
  $menutabs[_("Spirit points")] = "?view=user/addspirit&game=$gameId";
  if (ShowDefenseStats()) {
    $menutabs[_("Defense sheet")] = "?view=user/adddefensesheet&game=$gameId";
  }
  $html .= pageMenu($menutabs, "", false);

  $html .= "<form  method='post' action='?view=user/addspirit&amp;game=" . $gameId . "'>";

  $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['hometeamname']) . "</h3>\n";

  $points = GameGetSpiritPoints($gameId, $game_result['hometeam']);
  $html .= SpiritTable($game_result, $points, $categories, true);

  $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['visitorteamname']) . "</h3>\n";

  $points = GameGetSpiritPoints($gameId, $game_result['visitorteam']);
  $html .= SpiritTable($game_result, $points, $categories, false);

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
