<?php
$html="";

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

$result = GameGoals($gameId);
$game_result = GameResult($gameId);

$scores = array();
while ($row = mysqli_fetch_assoc($result)) {
	$scores[] = $row;
}

if(isset($_POST['delete'])) {
	if(count($scores)>0){
		$lastscore = $scores[count($scores)-1];
		GameRemoveScore($gameId,$lastscore['num']);
		header("location:?view=addscoresheet&game=".$gameId);
	}
}

$html .= "<div data-role='header'>\n";
$html .= "<h1>"._("Delete last goal").": ".utf8entities($game_result['hometeamname'])." - ".utf8entities($game_result['visitorteamname'])."</h1>\n";
$html .= "</div><!-- /header -->\n\n";


$html .= "<div data-role='content'>\n";

$html .= "<form action='?view=deletescore' method='post' data-ajax='false'>\n";

//last score
if(count($scores)>0){
	$lastscore = $scores[count($scores)-1];
	$html .= _("Delete goal number")." ".($lastscore['num']+1).": ";
	$html .= $lastscore['homescore']." - ". $lastscore['visitorscore']." ";
	$html .= "[".SecToMin($lastscore['time'])."] ";
	if (intval($lastscore['iscallahan'])){
		$lastpass = "xx";
	}else{
		$lastpass = "#".PlayerNumber($lastscore['assist'],$gameId)." ";
		$lastpass .= PlayerName($lastscore['assist']);
	}
	$lastgoal = "#".PlayerNumber($lastscore['scorer'],$gameId)." ";
	$lastgoal .= PlayerName($lastscore['scorer']);
	$html .= $lastpass." --> ".$lastgoal."";
}else{
$html .= _("Score").": 0 - 0";
}	

$html .= "<input type='submit' name='delete' data-ajax='false' value='"._("Delete")."'/>";
$html .= "<a href='?view=addscoresheet&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("Back to score sheet")."</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;

?>
