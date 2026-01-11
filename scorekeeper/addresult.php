<?php
include_once __DIR__ . '/auth.php';

$html = "";
$info = "";

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

if (isset($_POST['save'])) {
	$home = intval($_POST['home']);
	$away = intval($_POST['away']);
	$ok = GameSetResult($gameId, $home, $away);
	if ($ok) {
		$game_result = GameResult($gameId);
		$info = "<p>" . sprintf(_("Game result %s - %s saved!"), $home, $away) . "</p>";
	}
} elseif (isset($_POST['update'])) {
	$home = intval($_POST['home']);
	$away = intval($_POST['away']);
	$ok = GameUpdateResult($gameId, $home, $away);
	$info = "<p>" . sprintf(_("Game result %s - %s updated!"), $home, $away) . "</p>";
}

$html .= "<div data-role='header'>\n";
$html .= "<h1>" . _("Result") . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";

$result = GameResult($gameId);

$html .= "<form action='?view=addresult' method='post' data-ajax='false'>\n";

$html .= "<label for='home'>" . utf8entities($result['hometeamname']) . ":</label>";

$html .= "<div class='ui-grid-b'>";
$html .= "<div class='ui-block-a'>\n";
$html .= "<input type='number' id='home' name='home' value='" . intval($result['homescore']) . "' maxlength='4' size='5'/>";
$html .= "</div>";
$html .= "<div class='ui-block-b'>\n";
$html .= "<a href='#' data-role='button' id='homeplus' data-icon='plus'>+1</a>";
$html .= "</div>";
$html .= "<div class='ui-block-c'>\n";
$html .= "<a href='#' data-role='button' id='homeminus' data-icon='minus'>-1</a>";
$html .= "</div>";
$html .= "</div>";

$html .= "<label for='away'>" . utf8entities($result['visitorteamname']) . ":</label>";
$html .= "<div class='ui-grid-b'>";
$html .= "<div class='ui-block-a'>\n";
$html .= "<input type='number' id='away' name='away' value='" . intval($result['visitorscore']) . "' maxlength='4' size='5'/>";
$html .= "</div>";
$html .= "<div class='ui-block-b'>\n";
$html .= "<a href='#' data-role='button' id='awayplus' data-icon='plus'>+1</a>";
$html .= "</div>";
$html .= "<div class='ui-block-c'>\n";
$html .= "<a href='#' data-role='button' id='awayminus' data-icon='minus'>-1</a>";
$html .= "</div>";
$html .= "</div>";

$html .= $info;

if (isset($_POST['save'])) {
	$html .= "<input type='submit' name='save'  data-ajax='false' value='" . _("Save again") . "'/>";
	$html .= "<a href='?view=addplayerlists&game=" . $gameId . "&team=" . $game_result['hometeam'] . "' data-role='button' data-ajax='false'>" . _("Fill Playerlists") . "</a>";
} else {
	$html .= "<div class='action-row action-row--stacked action-row--spaced'>\n";
	$html .= "<input type='submit' name='update' data-ajax='false' value='" . _("Game ongoing, update scores") . "'/>";
	$html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save as final result") . "'/>";
	$html .= "</div>\n";
}
$html .= "<a class='back-resp-button' href='?view=respgames' data-role='button' data-ajax='false'>" . _("Back to game responsibilities") . "</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;

?>
<script type="text/javascript">
	function adjustScore(inputId, delta) {
		var input = document.getElementById(inputId);
		if (!input) {
			return;
		}
		var goals = parseInt(input.value, 10);
		if (isNaN(goals)) {
			goals = 0;
		}
		goals = goals + delta;
		input.value = goals;
	}

	function bindScoreButton(buttonId, inputId, delta) {
		var button = document.getElementById(buttonId);
		if (!button) {
			return;
		}
		button.addEventListener("click", function(event) {
			event.preventDefault();
			adjustScore(inputId, delta);
		});
	}

	bindScoreButton("homeplus", "home", 1);
	bindScoreButton("homeminus", "home", -1);
	bindScoreButton("awayplus", "away", 1);
	bindScoreButton("awayminus", "away", -1);
</script>
