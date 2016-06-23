<?php
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/game.functions.php';
include_once $include_prefix.'lib/standings.functions.php';
include_once $include_prefix.'lib/pool.functions.php';
include_once $include_prefix.'lib/configuration.functions.php';

if (version_compare(PHP_VERSION, '5.0.0', '>')) {
	include_once 'lib/twitter.functions.php';
}
$html = "";
$html2 = "";
$gameId = intval($_GET["game"]);
$game_result = GameInfo($gameId);
$seasoninfo = SeasonInfo($game_result['season']);

$LAYOUT_ID = ADDRESULT;
$title = _("Result");

//process itself if save button was pressed
if(!empty($_POST['save'])) {
	$home = intval($_POST['home']);
	$away = intval($_POST['away']);
	$ok=GameSetResult($gameId, $home, $away);
	if($ok)	{
		$html2 .= "<p>". sprintf(_("Final result saved: %s - %s."), $home, $away) ." ";
        if($home>$away){
	    	$html2 .=  sprintf(_("Winner is <span style='font-weight:bold'>%s</span>."), utf8entities($game_result['hometeamname']));
        }elseif ($away>$home){
	    	$html2 .=  sprintf(_("Winner is <span style='font-weight:bold'>%s</span>."), utf8entities($game_result['visitorteamname']));
        }
        $html2 .= "</p>";
	}
	$game_result = GameInfo($gameId);
}elseif(isset($_POST['update'])) {
	$home = intval($_POST['home']);
	$away = intval($_POST['away']);
	$ok=GameUpdateResult($gameId, $home, $away);
	$html2 .= "<p>".sprintf(_("Game ongoing. Current score: %s - %s."), $home, $away)."</p>";
	$game_result = GameInfo($gameId);
}elseif(isset($_POST['clear'])) {
  $ok=GameClearResult($gameId);
  if($ok)	{
    $html2 .= "<p>"._("Game reset").".</p>";
  }
  $game_result = GameInfo($gameId);
}

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
//content
$menutabs[_("Result")]= "?view=user/addresult&game=$gameId";
$menutabs[_("Players")]= "?view=user/addplayerlists&game=$gameId";
$menutabs[_("Score sheet")]= "?view=user/addscoresheet&game=$gameId";
if($seasoninfo['spiritmode']>0 && isSeasonAdmin($seasoninfo['season_id'])){
  $menutabs[_("Spirit points")]= "?view=user/addspirit&game=$gameId";
}
if(ShowDefenseStats())
{
  $menutabs[_("Defense sheet")]= "?view=user/adddefensesheet&game=$gameId";
}


pageMenu($menutabs);

$html .= "<form  method='post' action='?view=user/addresult&amp;game=".$gameId."'>
<table cellpadding='2'>
<tr><td><b>". utf8entities($game_result['hometeamname']) ."</b></td><td><b> - </b></td><td><b>". utf8entities($game_result['visitorteamname']) ."</b></td></tr>";

$html .= "<tr><td>";
if ($game_result['isongoing'])
	$html .= _("Game is running.");	
else if ($game_result['hasstarted'])
	$html .= _("Game is finished.");	
$html .= "<tr><td>";

$html .= "<tr>
<td><input class='input' name='home' value='".utf8entities($game_result['homescore'])."' maxlength='4' size='5'/></td>
<td> - </td>
<td><input class='input' name='away' value='".utf8entities($game_result['visitorscore'])."' maxlength='4' size='5'/></td></tr>
</table>";

if($game_result['homevalid']==2) {
	$poolInfo=PoolInfo($game_result['pool']);
	$html .= "<p>"."The home team is the BYE team. You should use the suggested result: ".$poolInfo['forfeitagainst']." - ".$poolInfo['forfeitscore']."</p>";	
} elseif($game_result['visitorvalid']==2){
	$poolInfo=PoolInfo($game_result['pool']);
	$html .= "<p>"."The visitor team is the BYE team. You should use the suggested result: ".$poolInfo['forfeitscore']." - ".$poolInfo['forfeitagainst']."</p>";	
}

$html .= "<p>"._("If game ongoing, update as current result: ")."    
	<input class='button' type='submit' name='update' value='"._("update")."'/></p>";

$html .= "<p>"._("If this is all wrong, clear the result: ")."    
	<input class='button' type='submit' name='clear' value='"._("Clear")."'/></p>";

$html .= $html2;

$html .= "<p>    
		<input class='button' type='submit' name='save' value='"._("Save as final result")."'/>
	</p></form>";


echo $html;

//common end
contentEnd();
pageEnd();
?>
