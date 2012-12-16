<?php
$html = "";
$errors = "";
$saved = isset($_GET['saved']) ? 1 : 0;

if(!empty($_POST['save'])) {
  $game = intval($_POST['game']);
  $home = intval($_POST['home']);
  $away = intval($_POST['away']);
  $gameId = substr($game, 0, -1);
  if ($game==0 || !checkChkNum($game)) {
    $errors .= "<p class='warning'>". _("Erroneous scoresheet number.")."</p>";
  }
  if(IsSeasonStatsCalculated(GameSeason($gameId))){
    $errors .= "<p class='warning'>". _("Event played.")."</p>";

  }
  if(!($home+$away)){
    $errors .= "<p class='warning'>". _("No goals.")."</p>";
  }
}
if(!empty($_POST['confirm'])) {
  $game = intval($_POST['game']);
  $home = intval($_POST['home']);
  $away = intval($_POST['away']);
  LogGameUpdate($game,"result: $home - $away", "addresult");
  $query = sprintf("UPDATE uo_game SET homescore='%s', visitorscore='%s' WHERE game_id=%d",
			$home,
			$away,
			$game);
			
  $ok = DBQuery($query);
  
  if($ok){
    ResolvePoolStandings(GamePool($game));
    PoolResolvePlayed(GamePool($game));
    if(IsTwitterEnabled()){
      TweetGameResult($game);
    }
  }
   header("location:?view=result&saved=1");
  
}


$html .= "<div data-role='header'>\n";
$html .= "<h1>"._("Add result with game id")."</h1>\n";
$html .= "</div><!-- /header -->\n\n";
$html .= "<div data-role='content'>\n";

$html .= "<form action='?view=result' method='post' data-ajax='false'>\n";
$html .= $errors;
if(!empty($_POST['cancel'])) {
	$html .= "<p class='warning'>". _("Result not saved!")."</p>";
}
if($saved){
  $html .= "<p>". _("Result saved!")."</p>";
}

if(!empty($_POST['save']) && empty($errors)) {
  $html .= "<p>";
  $html .= "<input class='input' type='hidden' id='game' name='game' value='$gameId'/> ";
  $html .= "<input class='input' type='hidden' id='home' name='home' value='$home'/> ";
  $html .= "<input class='input' type='hidden' id='away' name='away' value='$away'/> ";
  $gameinfo = GameInfo($gameId);
  $html .= "<p>";
  $html .= ShortDate($gameinfo['time']) ." ". DefHourFormat($gameinfo['time']). " ";
  if(!empty($gameinfo['fieldname'])){
    $html .=  _("on field")." ".utf8entities($gameinfo['fieldname']);
  }
  $html .=  "<br/>";
  $html .=  U_($gameinfo['seriesname']).", ".U_($gameinfo['poolname']);
  $html .=  "</p>";
  $html .= "<p>";
  $html .= utf8entities($gameinfo['hometeamname']);
  $html .= " - ";
  $html .= utf8entities($gameinfo['visitorteamname']);
  $html .=  " ";

  if(intval($gameinfo['homescore'])+intval($gameinfo['visitorscore'])>0){
    $html .=  "<br/>";
    $html .= _("Game is already played. Result:"). " ". intval($gameinfo['homescore'])." - ".$gameinfo['visitorscore'].".";
    $html .=  "<br/><br/>";
    $html .=  "<span style='font-weight:bold'>". _("Change result to"). " $home - $away?" ."</span>";
  }else{
    $html .=  "<span style='font-weight:bold'> $home - $away</span>";
  }

  $html .=  "<br/><br/>";
  $html .=  _("Winner is"). " <span style='font-weight:bold'>";
  if($home>$away){
    $html .= utf8entities($gameinfo['hometeamname']);
  }else{
    $html .= utf8entities($gameinfo['visitorteamname']);
  }
  $html .=  "?</span> ";
  $html .= "<br/><br/><input type='submit' name='confirm' data-ajax='false' value='"._("Confirm")."'/> ";
  $html .= "<input type='submit' name='cancel' data-ajax='false' value='"._("Cancel")."'/>";
  $html .=  "</p>";

}else{
  $html .= "<label for='game'>"._("Game number from Scoresheet").":</label>";
  $html .= "<input type='number' id='game' name='game' size='6' maxlength='5' onkeyup='validNumber(this);'/> ";

  $html .= "<label for='home'>"._("Home team goals").":</label>";
  $html .= "<input type='number' id='home' name='home' size='3' maxlength='2' onkeyup='validNumber(this);'/> ";
  
  $html .= "<label for='away'>"._("Visitor team goals").":</label>";
  $html .= "<input type='number' id='away' name='away' size='3' maxlength='2' onkeyup='validNumber(this);'/> ";
  
  $html .= "<input type='submit' name='save' data-ajax='false' value='"._("Save")."'/>";
  $html .= "<a href='?view=login' data-role='button' data-ajax='false'>"._("Back")."</a>";
}


$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";
echo $html;
?>
<script type="text/javascript">
<!--
document.getElementById('game').setAttribute( "autocomplete","off" );
document.getElementById('home').setAttribute( "autocomplete","off" );
document.getElementById('away').setAttribute( "autocomplete","off" );


function validNumber(field) 
	{
	field.value=field.value.replace(/[^0-9]/g, '');
	}
//-->
</script>
