<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';

$html="";
$gameId = intval(iget("game"));
$result = GameGoals($gameId);
$scores = array();
while ($row = mysqli_fetch_assoc($result)) {
	$scores[] = $row;
}

if(isset($_POST['delete'])) {
	if(count($scores)>0){
		$lastscore = $scores[count($scores)-1];
		GameRemoveScore($gameId,$lastscore['num']);
		header("location:?view=mobile/addscoresheet&game=".$gameId);
	}
}
mobilePageTop(_("Delete score"));	
$html .= "<form action='?".utf8entities($_SERVER['QUERY_STRING'])."' method='post'>\n"; 
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= _("Delete").":";
$html .= "</td></tr><tr><td>\n";
//last score
if(count($scores)>0){
	$lastscore = $scores[count($scores)-1];
	$html .= "#".($lastscore['num']+1) ." "._("Score").": ".$lastscore['homescore']." - ". $lastscore['visitorscore'];
	$html .= " [<i>".SecToMin($lastscore['time']);
	if (intval($lastscore['iscallahan'])){
		$lastpass = "xx";
	}else{
		$lastpass = PlayerNumber($lastscore['assist'],$gameId);
	}
	$lastgoal = PlayerNumber($lastscore['scorer'],$gameId);
	if($lastgoal==-1){$lastgoal="";}
	if($lastpass==-1){$lastpass="";}
	$html .= " ".$lastpass." --> ".$lastgoal."</i>]";
}else{
$html .= _("Score").": 0 - 0";
}	
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='button' type='submit' name='delete' value='"._("Delete")."'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/addscoresheet&amp;game=".$gameId."'>"._("Back to score sheet")."</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>"; 
echo $html;
pageEnd();
?>
