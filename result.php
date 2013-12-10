<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/statistical.functions.php';
include_once 'lib/configuration.functions.php';

if (version_compare(PHP_VERSION, '5.0.0', '>')) {
  include_once 'lib/twitter.functions.php';
}
$html = "";
$errors = false;

if(!empty($_POST['save'])) {
  $game = intval($_POST['game']);
  $home = intval($_POST['home']);
  $away = intval($_POST['away']);
  $gameId = substr($game, 0, -1);
  if ($game==0 || !checkChkNum($game)) {
    $html .= "<p class='warning'>". _("Erroneous scoresheet number.")."</p>";
    $errors = true;
  }
  if(IsSeasonStatsCalculated(GameSeason($gameId))){
    $html .= "<p class='warning'>". _("Event played.")."</p>";
    $errors = true;
  }
  if(!($home+$away)){
    $html .= "<p class='warning'>". _("No goals.")."</p>";
    $errors = true;
  }
}
if(!empty($_POST['confirm'])) {
  $game = intval($_POST['game']);
  $home = intval($_POST['home']);
  $away = intval($_POST['away']);
  LogGameUpdate($game,"result: $home - $away", "addresult");
  $ok = GameSetResult($game, $home, $away);
  
  if($ok){
    ResolvePoolStandings(GamePool($game));
    PoolResolvePlayed(GamePool($game));
    if(IsTwitterEnabled()){
      TweetGameResult($game);
    }
  }
   header("location:?".$_SERVER['QUERY_STRING']);
  
}
if(!empty($_POST['cancel'])) {
  $html .= "<p class='warning'>". _("Result not saved!")."</p>";
}
mobilePageTop(_("Add result"));

$html .= "<div style='font-size:14px;'>";

$html .= "<form action='?".utf8entities($_SERVER['QUERY_STRING'])."' method='post'>\n";
if(!empty($_POST['save']) && !$errors) {
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

  if(GameHasStarted($gameInfo)){
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
  $html .= "<br/><br/><input class='button' type='submit' name='confirm' value='"._("Confirm")."'/> ";
  $html .= "<input class='button' type='submit' name='cancel' value='"._("Cancel")."'/>";
  $html .=  "</p>";

}else{
  $html .= "<table cellpadding='2'>\n";
  $html .= "<tr><td class='infocell'>\n";
  $html .= _("Scoresheet #").":";
  $html .= "</td><td>\n";
  $html .= "<input class='input' type='text' id='game' name='game' size='6' maxlength='5' onkeyup='validNumber(this);'/> ";
  $html .= "</td></tr><tr><td class='infocell'>\n";
  $html .= _("Home Goals").":";
  $html .= "</td><td>\n";
  $html .= "<input class='input' type='text' id='home' name='home' size='3' maxlength='2' onkeyup='validNumber(this);'/> ";
  $html .= "</td></tr><tr><td class='infocell'>\n";
  $html .= _("Away Goals").":";
  $html .= "</td><td>\n";
  $html .= "<input class='input' type='text' id='away' name='away' size='3' maxlength='2' onkeyup='validNumber(this);'/> ";
  $html .= "</td></tr><tr><td style='padding-top:15px' colspan='2'>\n";
  $html .= "<input style='width:100%;' class='button' type='submit' name='save' value='"._("Save")."'/>";
  $html .= "</td></tr>\n";
  $html .= "</table>\n";
}
$html .= "</form>";
$html .= "<p><a href='?view=played'>"._("Played games")."</a></p>";
$html .= "</div>";
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
<?php
pageEnd();
?>