<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/player.functions.php';
include_once 'lib/standings.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/configuration.functions.php';

if (version_compare(PHP_VERSION, '5.0.0', '>')) {
	include_once 'lib/twitter.functions.php';
}

$html = "";
$errors = false;
$gameId = intval(iget("game"));
$seasoninfo = SeasonInfo(GameSeason($gameId));
$game_result = GameResult($gameId);
$result = GameGoals($gameId);
$scores = array();
while ($row = mysqli_fetch_assoc($result)) {
	$scores[] = $row;
}
$uo_goal = array(
	"game"=>$gameId,
	"num"=>0,
	"assist"=>-1,
	"scorer"=>-1,
	"time"=>"",
	"homescore"=>0,
	"visitorscore"=>0,
	"ishomegoal"=>0,
	"iscallahan"=>0);
	$timemm="";
	$timess="";
	$pass="";
	$goal="";
	$team="";

if(isset($_POST['add']) || isset($_POST['forceadd'])) {
	
	$prevtime=0;
	$time_delim = array(",", ";", ":", "#", "*");
	$timemm = "0";
	$timess = "0";
		
	if(count($scores)>0){
		$lastscore = $scores[count($scores)-1];
		$prevtime=$lastscore['time'];
		$uo_goal['num'] = $lastscore['num'] + 1;
		$uo_goal['homescore'] = $lastscore['homescore'];
		$uo_goal['visitorscore'] = $lastscore['visitorscore'];
	}
	
	if(!empty($_POST['team']))
		$team = $_POST['team'];
	if(!empty($_POST['pass'])){
		$uo_goal['assist'] = $_POST['pass'];
		$pass = $_POST['pass'];
	}
	if(!empty($_POST['goal'])){
		$uo_goal['scorer'] = $_POST['goal'];
		$goal = $_POST['goal'];
	}
	if(!empty($_POST['timemm'])){
		$timemm = intval($_POST['timemm']);
	}
	if(!empty($_POST['timess'])){
		$timess = intval($_POST['timess']);
	}
		
		
	//$time = str_replace($time_delim,".",$time);
	$uo_goal['time'] = TimeToSec($timemm.".".$timess);
		
	if($uo_goal['time'] <= $prevtime){
		$html .= "<p class='warning'>"._("time can not be the same or earlier than the previous point")."!</p>\n";
	}
		
	if(strcasecmp($uo_goal['assist'],'xx')==0 || strcasecmp($uo_goal['assist'],'x')==0)
		$uo_goal['iscallahan'] = 1;
			
	if(!empty($team) && $team=='H'){
		$uo_goal['homescore']++;
		$uo_goal['ishomegoal']=1;
		if(!$uo_goal['iscallahan']){
			$uo_goal['assist'] = GamePlayerFromNumber($gameId, $game_result['hometeam'], $uo_goal['assist']);
			if($uo_goal['assist']==-1){
				$html .= "<p class='warning'>"._("assisting player's number")." '".$_POST['pass']."' "._("Not on the roster")."!</p>\n";
			}
		}else{
			$uo_goal['assist']=-1;
		}
		$uo_goal['scorer'] = GamePlayerFromNumber($gameId, $game_result['hometeam'], $uo_goal['scorer']);
		if($uo_goal['scorer']==-1){
			$html .= "<p class='warning'>"._("scorer's number")." '".$_POST['goal']."' "._("Not on the roster")."!</p>\n";
		}
				
	}elseif(!empty($team) && $team=='A'){
		$uo_goal['visitorscore']++;
		$uo_goal['ishomegoal']=0;
			if(!$uo_goal['iscallahan'])
				{
				$uo_goal['assist'] = GamePlayerFromNumber($gameId, $game_result['visitorteam'], $uo_goal['assist']);
				if($uo_goal['assist']==-1){
					$html .= "<p class='warning'>"._("assisting player's number")." '".$_POST['pass']."' "._("Not on the roster")."!</p>\n";
					}
				}
			else
				$uo_goal['assist']=-1;
				
			$uo_goal['scorer'] = GamePlayerFromNumber($gameId, $game_result['visitorteam'], $uo_goal['scorer']);
			if($uo_goal['scorer']==-1){
				$html .= "<p class='warning'>"._("scorer's number")." '".$_POST['goal']."' "._("Not on the roster")."!</p>\n";
			}
	}
	if(($uo_goal['assist']!=-1 || $uo_goal['scorer']!=-1) && $uo_goal['assist']==$uo_goal['scorer']){
		$html .= "<p class='warning'>"._("Scorer and assist are the same player!")." '".$_POST['goal']."'!</p>\n";
	}
	if(empty($team)){
		$html .=  "<p class='warning'>"._("Select scoring team!")."</p>\n";
	}
 	
	if(empty($html) || isset($_POST['forceadd'])){
		GameAddScoreEntry($uo_goal);
		$result = GameResult($gameId );
		//save as result, if result is not already set
		if(($uo_goal['homescore'] + $uo_goal['visitorscore']) > ($result['homescore']+$result['visitorscore'])){
			GameUpdateResult($gameId, $uo_goal['homescore'], $uo_goal['visitorscore']);
		}
		header("location:?view=mobile/addscoresheet&game=".$gameId);
	}else{
		$errors=true;
	}
}elseif(isset($_POST['save'])) {
	$home = 0;
	$away = 0;
	if(count($scores)>0){
		$lastscore = $scores[count($scores)-1];
		
		$home = $lastscore['homescore'];
		$away = $lastscore['visitorscore'];
	}
	GameSetResult($gameId, $home, $away);
	header("location:?view=mobile/gameplay&game=".$gameId);	
}

mobilePageTop(_("Score&nbsp;sheet"));

$html .= "<form action='?".utf8entities($_SERVER['QUERY_STRING'])."' method='post'>\n"; 
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";


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

$vgoal="";
$hgoal="";
if($team=='H'){
$hgoal="checked='checked'";
}elseif($team=='A'){
$vgoal="checked='checked'";
}

$html .= "</td></tr><tr><td>\n";
$html .= "<input id='hteam' name='team' type='radio' $hgoal value='H' />". utf8entities($game_result['hometeamname']);
$html .= "<input id='ateam' name='team' type='radio' $vgoal value='A' />". utf8entities($game_result['visitorteamname']);			
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='input' id='pass' name='pass' maxlength='3' size='3' value='".utf8entities($pass)."'/> ". _("Assist");
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='input' id='goal' name='goal' maxlength='3' size='3' value='".utf8entities($goal)."'/> ". _("Goal");
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='input' id='timemm' name='timemm' maxlength='3' size='3' value='".utf8entities($timemm)."'/>:";	
$html .= "<input class='input' id='timess' name='timess' maxlength='2' size='2' value='".utf8entities($timess)."'/> ". _("Time"). " " ._("min").":". _("sec");	
$html .= "</td></tr><tr><td>\n";
if(!$errors){
	$html .= "<input class='button' type='submit' name='add' value='"._("Save goal")."'/>";
	$html .= "</td></tr><tr><td>\n";
	$html .=  "<a href='?view=mobile/addtimeouts&amp;game=".$gameId."'>"._("Time-outs")."</a> | ";
	$html .=  "<a href='?view=mobile/addhalftime&amp;game=".$gameId."'>"._("Half time")."</a>";
	$html .= "</td></tr><tr><td>\n";
	$html .=  "<a href='?view=mobile/addfirstoffence&amp;game=".$gameId."'>"._("First offence")."</a> | ";
	$html .=  "<a href='?view=mobile/addofficial&amp;game=".$gameId."'>"._("Game official")."</a>";
	$html .= "</td></tr><tr><td>\n";
	if(IsTwitterEnabled()){
		$html .=  "<a href='?view=mobile/tweet&amp;game=".$gameId."'>"._("Tweet")."</a> | ";
	}
	if(intval($seasoninfo['spiritmode'])>0){
		$html .=  "<a href='?view=mobile/addspiritpoints&amp;game=".$gameId."'>"._("Spirit points")."</a> | ";
	}
	$html .=  "<a href='?view=mobile/deletescore&amp;game=".$gameId."'>"._("Delete the last goal")."</a>";
	$html .= "</td></tr><tr><td>\n";
	$html .= "<input class='button' type='submit' name='save' value='"._("Save as result")."'/>";
}else{
	$html .= _("Correct the errors or save goal with errors");
	$html .= "</td></tr><tr><td>\n";
	$html .= "<input class='button' type='submit' name='forceadd' value='"._("Save goal")."'/>";
	$html .= "</td></tr><tr><td>\n";
	$html .= "<input class='button' type='submit' name='cancel' value='"._("Cancel")."'/>";
}
$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/respgames'>"._("Back to game responsibilities")."</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>"; 

echo $html;
		
pageEnd();
?>
