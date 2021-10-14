<?php
$html = "";

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

$game_result = GameResult($gameId);
$timemm = "";
$timess = "";

if (isset($_POST['save'])) {
  $timemm = "0";
  $timess = "0";

  if (!empty($_POST['timemm'])) {
    $timemm = intval($_POST['timemm']);
  }
  if (!empty($_POST['timess'])) {
    $timess = intval($_POST['timess']);
  }
  $htime = TimeToSec($timemm . "." . $timess);
  GameSetHalftime($gameId, $htime);

  header("location:?view=addscoresheet&game=" . $gameId);
}


$html .= "<div data-role='header'>\n";
$html .= "<h1>" . _("Halftime ends") . ": " . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";

$html .= "<form action='?view=addhalftime' method='post' data-ajax='false'>\n";

$html .= "<label for='timemm' class='select'>" . _("Halftime ends at") . " " . _("min") . ":" . _("sec") . "</label>";
$html .= "<div class='ui-grid-b'>";
$html .= "<div class='ui-block-a'>\n";

$time = explode(".", SecToMin($game_result['halftime']));
$timemm = $time[0];
$timess = $time[1];

$html .= "<select id='timemm' name='timemm' >";
for ($i = 0; $i <= 180; $i++) {
  if ($i == $timemm) {
    $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
  } else {
    $html .= "<option value='" . $i . "'>" . $i . "</option>";
  }
}
$html .= "</select>";
$html .= "</div>";
$html .= "<div class='ui-block-b'>\n";
$html .= "<select id='timess' name='timess' >";
for ($i = 0; $i <= 55; $i = $i + 5) {
  if ($i == $timess) {
    $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
  } else {
    $html .= "<option value='" . $i . "'>" . $i . "</option>";
  }
}
$html .= "</select>";
$html .= "</div>";
$html .= "</div>";

$html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save") . "'/>";
$html .= "<a href='?view=addscoresheet&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Back to score sheet") . "</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
