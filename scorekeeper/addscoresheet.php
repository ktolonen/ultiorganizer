<?php

$html = "";
$errors = "";
$errors = false;
$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

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
    "assist"=>0,
    "scorer"=>0,
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

  $uo_goal['time'] = TimeToSec($timemm.".".$timess);

  if($uo_goal['time'] <= $prevtime){
    $errors .= "<p class='warning'>"._("Time can not be the same or earlier than the previous point")."!</p>\n";
  }

  if(strcasecmp($uo_goal['assist'],'xx')==0 || strcasecmp($uo_goal['assist'],'x')==0)
    $uo_goal['iscallahan'] = 1;
   
  if(!empty($team) && $team=='H'){
    $uo_goal['homescore']++;
    $uo_goal['ishomegoal']=1;

    if($uo_goal['assist']==0 && !$uo_goal['iscallahan']){
      $errors .= "<p class='warning'>"._("Assisting player not on the roster")."!</p>\n";
    }

    if($uo_goal['scorer']==0){
      $errors .= "<p class='warning'>"._("Scorer's player not on the roster")."!</p>\n";
    }

  }elseif(!empty($team) && $team=='A'){
    $uo_goal['visitorscore']++;
    $uo_goal['ishomegoal']=0;
    
    if($uo_goal['assist']==0 && !$uo_goal['iscallahan']){
      $errors .= "<p class='warning'>"._("Assisting player not on the roster")."!</p>\n";
    }

    if($uo_goal['scorer']==0){
      $errors .= "<p class='warning'>"._("Scorer's player not on the roster")."!</p>\n";
    }
  }

  if(($uo_goal['assist']!=-1 || $uo_goal['scorer']!=-1) && $uo_goal['assist']==$uo_goal['scorer']){
    $errors .= "<p class='warning'>"._("Scorer and assist are the same player!")."</p>\n";
  }
  if(empty($team)){
    $errors .=  "<p class='warning'>"._("Select team scored")."!</p>\n";
  }

  if(empty($errors) || isset($_POST['forceadd'])){
    GameAddScoreEntry($uo_goal);
    $result = GameResult($gameId );
    //save as result, if result is not already set
    if(($uo_goal['homescore'] + $uo_goal['visitorscore']) > ($result['homescore']+$result['visitorscore'])){
      GameUpdateResult($gameId, $uo_goal['homescore'], $uo_goal['visitorscore']);
    }
    header("location:?view=addscoresheet&game=".$gameId);
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
  header("location:?view=gameplay&game=".$gameId);
}


$html .= "<div data-role='header'>\n";
$html .= "<h1>"._("Scoresheet").": ".utf8entities($game_result['hometeamname'])." - ".utf8entities($game_result['visitorteamname'])."</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";

$html .= "<form action='?view=addscoresheet' method='post' data-ajax='false'>\n";


//last score
$lastscore;
//$html .= "<div class='ui-grid-b'>";
if(count($scores)>0){
  $lastscore = $scores[count($scores)-1];
  //$html .= "<div class='ui-block-a'>\n";

  $html .= "#".($lastscore['num']+1) ." "._("Score").": ".$lastscore['homescore']." - ". $lastscore['visitorscore']." ";
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
  //$html .= "</div>";
  //$html .= "<div class='ui-block-b'>\n";
  $html .=  " <a href='?view=deletescore&amp;game=".$gameId."' data-ajax='false'>"._("Delete the goal")."</a>";
  //$html .= "</div>";
}else{
  //$html .= "<div class='ui-block-a'>\n";
  $html .= _("Score").": 0 - 0";
  //$html .= "<div class='ui-block-b'>\n";
  //$html .= "</div>";
}
//$html .= "</div>";

$vgoal="";
$hgoal="";
if($team=='H'){
  $hgoal="checked='checked'";
}elseif($team=='A'){
  $vgoal="checked='checked'";
}
$html .= "<h3>"._("New goal")."</h3>";
$html .= "<div id='radiot' name='radiot'>";
$html .= "<fieldset data-role='controlgroup' id='teamselection'>";
$html .= "<input type='radio' name='team' id='hteam' value='H' $hgoal />";
$html .= "<label for='hteam'>".utf8entities($game_result['hometeamname'])."</label>";
$html .= "<input type='radio' name='team' id='ateam' value='A' $vgoal  />";
$html .= "<label for='ateam'>".utf8entities($game_result['visitorteamname'])."</label>";
$html .= "</fieldset>";
$html .= "</div>";

$played_players = array();

if($team=='H'){
	$played_players = GamePlayers($gameId, $game_result['hometeam']);
}elseif($team=='A'){
	$played_players = GamePlayers($gameId, $game_result['visitorteam']);
}

$html .= "<label for='pass' class='select'>"._("Assist")."</label>";
$html .= "<select id='pass' name='pass' >";
$html .= "<option value='0' selected='selected'>-</option>";

foreach($played_players as $player){
  
  $selected="";
  if($uo_goal['assist']==$player['player_id']){
    $selected="selected='selected'";
  }
  
	$html .= "<option value='".utf8entities($player['player_id'])."' $selected>#".$player['num']." ".utf8entities($player['firstname'] ." ". $player['lastname'])."</option>";
}

$html .= "<option value='xx'>XX "._("Callahan Goal")."</option>";
$html .= "</select>";

$html .= "<label for='goal' class='select'>"._("Scorer")."</label>";
$html .= "<select id='goal' name='goal' >";
$html .= "<option value='0' selected='selected'>-</option>";
foreach($played_players as $player){
  $selected="";
  if($uo_goal['scorer']==$player['player_id']){
  	$selected="selected='selected'";
  }
  
	$html .= "<option value='".utf8entities($player['player_id'])."' $selected>#".$player['num']." ".utf8entities($player['firstname'] ." ". $player['lastname'])."</option>";
}
$html .= "</select>";

if(isset($lastscore)){
  $time = explode(".", SecToMin($lastscore['time']));
  $timemm = $time[0];
  $timess = $time[1];
}

$html .= "<label for='timemm' class='select'>". _("In time"). " " ._("min").":". _("sec")."</label>";
$html .= "<div class='ui-grid-b'>";
$html .= "<div class='ui-block-a'>\n";
$html .= "<select id='timemm' name='timemm' >";
for($i=0;$i<=180;$i++){
  if($i==$timemm){
    $html .= "<option value='".$i."' selected='selected'>".$i."</option>";
  }else{
    $html .= "<option value='".$i."'>".$i."</option>";
  }
}
$html .= "</select>";
$html .= "</div>";
$html .= "<div class='ui-block-b'>\n";
$html .= "<select id='timess' name='timess' >";
for($i=0;$i<=55;$i=$i+5){
  if($i==$timess){
    $html .= "<option value='".$i."' selected='selected'>".$i."</option>";
  }else{
    $html .= "<option value='".$i."'>".$i."</option>";
  }
}
$html .= "</select>";
$html .= "</div>";
$html .= "</div>";


if(empty($errors)){
  $html .= "<input type='submit' name='add' data-ajax='false' value='"._("Save goal")."'/>";
  $html .= "<h3>"._("Additional game data")."</h3>";
  $html .= "<a href='?view=addtimeouts&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("Time-outs")."</a>";
  $html .= "<a href='?view=addhalftime&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("Half time")."</a>";
  $html .= "<a href='?view=addfirstoffence&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("First offence")."</a>";
  $html .= "<a href='?view=addofficial&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("Game official")."</a>";
  if(IsTwitterEnabled()){
    $html .= "<a href='?view=tweet&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("Tweet")."</a>";
  }

  $html .= "<h3>"._("Game has ended")."</h3>";
  if($lastscore){
    $home = $lastscore['homescore'];
    $away = $lastscore['visitorscore'];
    $html .= "<input type='submit' name='save' data-ajax='false' value='"._("Save as result")." $home - $away'/>";
    $html .= "<a href='?view=addplayerlists&amp;game=".$gameId."&amp;team=".$game_result['hometeam']."' data-role='button' data-ajax='false'>"._("Players")."</a>";
    if(intval($seasoninfo['spiritmode']>0)&& isSeasonAdmin($seasoninfo['season_id'])){
  	  $html .= "<a href='?view=addspiritpoints&amp;game=".$gameId."&amp;team=".$game_result['hometeam']."' data-role='button' data-ajax='false'>"._("Spirit points")."</a>";
    }
  }
}else{
  $html .= $errors;
  $html .= _("Correct the errors or save goal with errors");
  $html .= "<input class='button' type='submit' name='forceadd' value='"._("Save goal with errors")."'/>";
  $html .= "<input class='button' type='submit' name='cancel' value='"._("Cancel")."'/>";
}
$html .= "<a href='?view=respgames' data-role='button' data-ajax='false'>"._("Back to game responsibilities")."</a>";

$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
?>
<script type="text/javascript">
var homelist = <?php
    echo "\"";
    echo "<option value='0'>-</option>";
	$played_players = GamePlayers($gameId, $game_result['hometeam']);
	foreach($played_players as $player){
      echo "<option value='".utf8entities($player['player_id'])."'>#".$player['num']." ".utf8entities($player['firstname'] ." ". $player['lastname'])."</option>";
    }
    echo "<option value='xx'>XX "._("Callahan Goal")."</option>";
    echo "\"";
	?>;

var awaylist = <?php
    echo "\"";
	$played_players = GamePlayers($gameId, $game_result['visitorteam']);
	echo "<option value='0'>-</option>";
	foreach($played_players as $player){
      echo "<option value='".utf8entities($player['player_id'])."'>#".$player['num']." ".utf8entities($player['firstname'] ." ". $player['lastname'])."</option>";
    }
    echo "<option value='xx'>XX "._("Callahan Goal")."</option>";
    echo "\"";
	?>;
			
	$("input[name=team]:radio").bind( "change", function(event, ui) {
		if($(this).val() == "H"){
			document.getElementById('pass').innerHTML = homelist;
			document.getElementById('goal').innerHTML = homelist;
		}else{
			document.getElementById('pass').innerHTML = awaylist;
			document.getElementById('goal').innerHTML = awaylist;
		}
			
	});

</script>
