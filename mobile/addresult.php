<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/standings.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/configuration.functions.php';

if (version_compare(PHP_VERSION, '5.0.0', '>')) {
	include_once 'lib/twitter.functions.php';
}

$html = "";

$gameId = intval(iget("game"));

if(isset($_POST['save'])) {
	$home = intval($_POST['home']);
	$away = intval($_POST['away']);
	LogGameUpdate($gameId,"result: $home - $away", "Mobile");
	$ok=GameSetResult($gameId, $home, $away);
	if($ok)	{
		ResolvePoolStandings(GamePool($gameId));
		PoolResolvePlayed(GamePool($gameId));
		$game_result = GameResult($gameId);
		if(IsTwitterEnabled()){
			TweetGameResult($gameId);
		}
		header("location:?view=mobile/addplayerlists&game=".$gameId."&team=".$game_result['hometeam']);
	}
}elseif(isset($_POST['update'])) {
	$home = intval($_POST['home']);
	$away = intval($_POST['away']);
	$ok=GameUpdateResult($gameId, $home, $away);
}

mobilePageTop(_("Game result"));

$result = GameResult($gameId );

$html .= "<form action='?".utf8entities($_SERVER['QUERY_STRING'])."' method='post'>\n"; 
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= utf8entities($result['hometeamname']) ." - ". utf8entities($result['visitorteamname']);
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='input' name='home' value='". intval($result['homescore']) ."' maxlength='3' size='5'/>";
$html .= " - ";
$html .= "<input class='input' name='away' value='". intval($result['visitorscore']) ."' maxlength='3' size='5'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "</td></tr><tr><td>\n";
$html .= _("If game ongoing:");
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='button' type='submit' name='update' value='"._("Update as current result")."'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "</td></tr><tr><td>\n";
$html .= _("If game ended:");
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='button' type='submit' name='save' value='"._("Save as final result")."'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/respgames'>"._("Back to game responsibilities")."</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>"; 

echo $html;
		
pageEnd();
?>
