<?php
include_once 'lib/database.php';
include_once 'lib/pool.functions.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/reservation.functions.php';

$LAYOUT_ID = POOLGAMES;

$seriesId = $_GET["Series"];
$seriesinfo = SeriesInfo($seriesId);
$season = $_GET["Season"];
$rounds = 1;
$nomutual=0;
$matches = 1;

$title = utf8entities(U_($seriesinfo['name'])).": "._("Games");
$html = "";

if(!empty($_POST['generate'])){
	if(!empty($_POST['rounds'])){
		$rounds = $_POST['rounds'];
	}
	if(!empty($_POST['matches'])){
		$matches = $_POST['matches'];
	}
	$nomutual = isset($_POST["nomutual"]);
	
	$pools = SeriesPools($seriesId);
	
	foreach($pools as $pool){
		$info = PoolInfo($pool['pool_id']);
		if($info['type']==1){
			
			if($info['mvgames']==2){
				GenerateGames($pool['pool_id'],$rounds,true,$nomutual);
			}else{
				GenerateGames($pool['pool_id'],$rounds,true);
			}
		}elseif($info['type']==2){
			GenerateGames($pool['pool_id'],$matches,true);
			//generate pools needed to solve standings
			$generatedpools = GeneratePlayoffPools($pool['pool_id'], true);
		
			//generate games into generated pools
			foreach($generatedpools as $gpool){
				GenerateGames($gpool['pool_id'],$matches,true);
			}
		}
	}
	session_write_close();
	header("location:?view=admin/seasonpools&Season=$season");
}

//common page
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<form method='post' action='?view=admin/seriesgames&amp;Season=$season&amp;Series=$seriesId'>";

$html .= "<h2>"._("Creation of games")."</h2>\n";
$html .= "<p><b>"._("Round Robin -type of pool")."</b></p>\n";
$html .= "<p>"._("Game rounds").": <input class='input' size='2' name='rounds' value='$rounds'/></p>\n";
$html .= "<p><input class='input' type='checkbox' name='nomutual'";
	if ($nomutual) {
		$html .= "checked='checked'";
	}
$html .="/> "._("Do not generate mutual games for teams moved from same pool, if pool format includes mutual games").".</p>";
$html .= "<p><b>"._("Play off -type of pool")."</b></p>\n";
$html .= "<p>"._("best")." <input class='input' size='2' name='matches' value='$matches'/> "._("matches")."</p>\n";
$html .= "<p><input type='submit' name='generate' value='"._("Generate all games")."'/></p>";
$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();
?>