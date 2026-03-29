<?php
include_once __DIR__ . '/auth.php';

function ScorekeeperSpiritTimeoutValues($gameId, $home, $maxslots)
{
  $values = array();
  foreach (GameSpiritTimeoutsArray($gameId) as $timeout) {
    if ((int)$timeout['ishome'] === (int)$home && count($values) < $maxslots) {
      $time = explode(".", SecToMin($timeout['time']));
      $values[] = array(
        "mm" => intval($time[0]),
        "ss" => intval($time[1])
      );
    }
  }
  for ($i = count($values); $i < $maxslots; $i++) {
    $values[] = array("mm" => 0, "ss" => 0);
  }
  return $values;
}

$html = "";
$maxSpiritTimeouts = 4;

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

$seasoninfo = SeasonInfo(GameSeason($gameId));
if (empty($seasoninfo['spiritmode']) || !empty($seasoninfo['hide_time_on_scoresheet'])) {
  header("location:?view=addtimeouts&game=" . $gameId);
  exit;
}

$game_result = GameResult($gameId);

if (isset($_POST['save'])) {
  GameRemoveAllSpiritTimeouts($gameId);

  $j = 0;
  for ($i = 0; $i < $maxSpiritTimeouts; $i++) {
    $timemm = $_POST['htomm' . $i];
    $timess = $_POST['htoss' . $i];
    $time = $timemm . "." . $timess;
    if (($timemm + $timess) > 0) {
      $j++;
      GameAddSpiritTimeout($gameId, $j, TimeToSec($time), 1);
    }
  }

  $j = 0;
  for ($i = 0; $i < $maxSpiritTimeouts; $i++) {
    $timemm = $_POST['atomm' . $i];
    $timess = $_POST['atoss' . $i];
    $time = $timemm . "." . $timess;
    if (($timemm + $timess) > 0) {
      $j++;
      GameAddSpiritTimeout($gameId, $j, TimeToSec($time), 0);
    }
  }

  header("location:?view=addscoresheet&game=" . $gameId);
}

$html .= "<div data-role='header'>\n";
$html .= "<h1>" . _("Spirit timeouts") . ": " . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
$html .= "<form action='?view=addspirittimeouts' method='post' data-ajax='false'>\n";

$html .= "<label for='htomm0' class='select'><b>" . utf8entities($game_result['hometeamname']) . "</b> " . _("spirit timeouts") . " (" . _("min") . ":" . _("sec") . "):</label>";
$html .= "<div class='timeout-list'>";
foreach (ScorekeeperSpiritTimeoutValues($gameId, 1, $maxSpiritTimeouts) as $j => $time) {
  $html .= "<div class='timeout-pair'>\n";
  $html .= "<div class='ui-block-a'>\n";
  $html .= "<select id='htomm$j' name='htomm$j' >";
  for ($i = 0; $i <= 180; $i++) {
    if ($i == $time['mm']) {
      $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
    } else {
      $html .= "<option value='" . $i . "'>" . $i . "</option>";
    }
  }
  $html .= "</select>";
  $html .= "</div>";
  $html .= "<span class='timeout-separator'>:</span>";
  $html .= "<div class='ui-block-b'>\n";
  $html .= "<select id='htoss$j' name='htoss$j' >";
  for ($i = 0; $i <= 55; $i = $i + 5) {
    if ($i == $time['ss']) {
      $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
    } else {
      $html .= "<option value='" . $i . "'>" . $i . "</option>";
    }
  }
  $html .= "</select>";
  $html .= "</div>";
  $html .= "</div>";
}
$html .= "</div>";

$html .= "<label for='atomm0' class='select'><b>" . utf8entities($game_result['visitorteamname']) . "</b> " . _("spirit timeouts") . " (" . _("min") . ":" . _("sec") . "):</label>";
$html .= "<div class='timeout-list'>";
foreach (ScorekeeperSpiritTimeoutValues($gameId, 0, $maxSpiritTimeouts) as $j => $time) {
  $html .= "<div class='timeout-pair'>\n";
  $html .= "<div class='ui-block-a'>\n";
  $html .= "<select id='atomm$j' name='atomm$j' >";
  for ($i = 0; $i <= 180; $i++) {
    if ($i == $time['mm']) {
      $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
    } else {
      $html .= "<option value='" . $i . "'>" . $i . "</option>";
    }
  }
  $html .= "</select>";
  $html .= "</div>";
  $html .= "<span class='timeout-separator'>:</span>";
  $html .= "<div class='ui-block-b'>\n";
  $html .= "<select id='atoss$j' name='atoss$j' >";
  for ($i = 0; $i <= 55; $i = $i + 5) {
    if ($i == $time['ss']) {
      $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
    } else {
      $html .= "<option value='" . $i . "'>" . $i . "</option>";
    }
  }
  $html .= "</select>";
  $html .= "</div>";
  $html .= "</div>";
}
$html .= "</div>";

$html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save") . "'/>";
$html .= "<a href='?view=addtimeouts&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Back to timeouts") . "</a>";
$html .= "<a class='back-score-button' href='?view=addscoresheet&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Back to scoresheet") . "</a>";

$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
