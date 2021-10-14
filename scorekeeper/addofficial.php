<?php
$html = "";

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

$game_result = GameResult($gameId);

if (isset($_POST['save'])) {
	GameSetScoreSheetKeeper($gameId, $_POST['official']);
	header("location:?view=addscoresheet&game=" . $gameId);
}

$html .= "<div data-role='header'>\n";
$html .= "<h1>" . _("Officials") . ": " . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
$html .= "<form action='?view=addofficial' method='post' data-ajax='false'>\n";
$html .= "<label for='official'>" . _("Game official") . ":</label>";
$html .= "<input type='text' name='official' id='official' value='" . utf8entities($game_result['official']) . "'/>";
$html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save") . "'/>";
$html .= "<a href='?view=addscoresheet&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Back to score sheet") . "</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
