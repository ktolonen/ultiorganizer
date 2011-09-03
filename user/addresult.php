<?php
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/game.functions.php';
include_once $include_prefix.'lib/standings.functions.php';
include_once $include_prefix.'lib/pool.functions.php';
include_once $include_prefix.'lib/configuration.functions.php';

if (version_compare(PHP_VERSION, '5.0.0', '>')) {
	include_once 'lib/twitter.functions.php';
}
$gameId = intval($_GET["Game"]);
$season = GameSeason($gameId);
if(isset($_SERVER['HTTP_REFERER'])){
	$backurl = utf8entities($_SERVER['HTTP_REFERER']);
}else{
	$backurl = "?view=user/respgames&Season=$season";
}
$LAYOUT_ID = ADDRESULT;
$title = _("Result");
//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
//content

//process itself if save button was pressed
if(!empty($_POST['save'])) {
	$backurl = $_POST['backurl'];
	$home = intval($_POST['home']);
	$away = intval($_POST['away']);
	LogGameUpdate($gameId,"result: $home - $away", "addresult");
	$ok=GameSetResult($gameId, $home, $away);
	if($ok)	{
		echo "<p>"._("Final result saved: $home - $away").".</p>";
		ResolvePoolStandings(GamePool($gameId));
		PoolResolvePlayed(GamePool($gameId));
		if(IsTwitterEnabled()){
			TweetGameResult($gameId);
		}
	}
}elseif(isset($_POST['update'])) {
	$home = intval($_POST['home']);
	$away = intval($_POST['away']);
	$ok=GameUpdateResult($gameId, $home, $away);
	echo "<p>"._("Game ongoing. Current score: $home - $away").".</p>";
}
	
$result = GameResult($gameId );

echo "<form  method='post' action='?view=user/addresult&amp;Game=".$gameId."'>
<table cellpadding='2'>
<tr><td><b>". utf8entities($result['hometeamname']) ."</b></td><td><b> - </b></td><td><b>". utf8entities($result['visitorteamname']) ."</b></td></tr>
<tr>
<td><input class='input' name='home' value='". $result['homescore'] ."' maxlength='2' size='5'/></td>
<td> - </td>
<td><input class='input' name='away' value='". $result['visitorscore'] ."' maxlength='2' size='5'/></td></tr>
</table>";
echo "<div><input type='hidden' name='backurl' value='$backurl'/></div>";
if($result['homevalid']==2) {
	$poolId=GamePool($gameId);
	$poolInfo=PoolInfo($poolId);
	echo "<p>"."The home team is the BYE team. You should use the suggested result: ".$poolInfo['forfeitagainst']." - ".$poolInfo['forfeitscore']."</p>";	
} elseif($result['visitorvalid']==2){
	$poolId=GamePool($gameId);
	$poolInfo=PoolInfo($poolId);
	echo "<p>"."The visitor team is the BYE team. You should use the suggested result: ".$poolInfo['forfeitscore']." - ".$poolInfo['forfeitagainst']."</p>";	
}

echo "<p>"._("If game ongoing, update as current result: ")."    
	<input class='button' type='submit' name='update' value='"._("update")."'/></p>";

echo "<p>    
		<input class='button' type='submit' name='save' value='"._("Save as final result")."'/>
		<input class='button' type='button' name='return'  value='"._("Return")."' onclick=\"window.location.href='$backurl'\"/>
	</p></form>";

echo "<p><a href='?view=user/addplayerlists&amp;Game=".$gameId."'>"._("Feed in the players in the game")."</a></p>";

//common end
contentEnd();
pageEnd();
?>
