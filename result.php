<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/statistical.functions.php';
include_once 'lib/configuration.functions.php';

if (version_compare(PHP_VERSION, '5.0.0', '>')) {
  include_once 'lib/twitter.functions.php';
}
$html = "";

$errors = "";
if(!empty($_POST['save'])) {
  $game = intval($_POST['game']);
  $home = intval($_POST['home']);
  $away = intval($_POST['away']);
  $errors = CheckGameResult($game, $home, $away);
  $gameId = (int) substr($game, 0, -1);
}
if(!empty($_POST['confirm'])) {
  $game = intval($_POST['game']);
  $home = intval($_POST['home']);
  $away = intval($_POST['away']);
  $errors = CheckGameResult($game, $home, $away);
  if (empty($errors)) {
    $gameId = (int) substr($game, 0, -1);
    GameSetResult($gameId, $home, $away, true, false);
    header("location:?" . $_SERVER['QUERY_STRING']);
  }
}
if(!empty($_POST['cancel'])) {
  $html .= "<p class='warning'>". _("Result not saved!")."</p>";
}
PageTop(_("Add result"));

$html .= $errors;

$html .= "<div style='font-size:14px;'>";

$html .= "<form action='?".utf8entities($_SERVER['QUERY_STRING'])."' method='post'>\n";
if(!empty($_POST['save']) && empty($errors)) {
  $html .= "<p>";
  $html .= "<input class='input' type='hidden' id='game' name='game' value='$game'/> ";
  $html .= "<input class='input' type='hidden' id='home' name='home' value='$home'/> ";
  $html .= "<input class='input' type='hidden' id='away' name='away' value='$away'/> ";
  $game_result = GameInfo($gameId);
  $html .= "<p>";
  $html .= ShortDate($game_result['time']) ." ". DefHourFormat($game_result['time']). " ";
  if(!empty($game_result['fieldname'])){
    $html .=  _("on field")." ".utf8entities($game_result['fieldname']);
  }
  $html .=  "<br/>";
  $html .=  U_($game_result['seriesname']).", ".U_($game_result['poolname']);
  $html .=  "</p>";
  $html .= "<p>";
  $html .= utf8entities($game_result['hometeamname']);
  $html .= " - ";
  $html .= utf8entities($game_result['visitorteamname']);
  $html .=  " ";

  if(GameHasStarted($game_result)){
    $html .=  "<br/>";
    $html .= _("Game is already played. Result:"). " ". intval($game_result['homescore'])." - ".$game_result['visitorscore'].".";
    $html .=  "<br/><br/>";
    $html .=  "<span style='font-weight:bold'>". _("Change result to"). " $home - $away?" ."</span>";
  }else{
    $html .=  "<span style='font-weight:bold'> $home - $away</span>";
  }

  $html .=  "<br/><br/>";
  $html .=  _("Winner is"). " <span style='font-weight:bold'>";
  if($home>$away){
    $html .= utf8entities($game_result['hometeamname']);
  }else{
    $html .= utf8entities($game_result['visitorteamname']);
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
  $html .= "<input class='input' type='text' id='home' name='home' size='3' maxlength='3' onkeyup='validNumber(this);'/> ";
  $html .= "</td></tr><tr><td class='infocell'>\n";
  $html .= _("Away Goals").":";
  $html .= "</td><td>\n";
  $html .= "<input class='input' type='text' id='away' name='away' size='3' maxlength='3' onkeyup='validNumber(this);'/> ";
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