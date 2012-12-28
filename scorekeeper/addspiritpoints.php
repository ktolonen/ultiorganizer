<?php
$html = "";

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

$teamId = isset($_GET['team']) ? $_GET['team'] : $_SESSION['team'];
$_SESSION['team'] = $teamId;

$game_result = GameResult($gameId);

$ishome = $teamId == $game_result['hometeam'] ? 1:0;
	
if(!empty($_POST['save'])) {
  $points = array();
  $points[0] = intval($_POST['homecat1']);
  $points[1] = intval($_POST['homecat2']);
  $points[2] = intval($_POST['homecat3']);
  $points[3] = intval($_POST['homecat4']);
  $points[4] = intval($_POST['homecat5']);
  
  
  GameSetSpiritPoints($gameId,$teamId,$ishome,$points);
  
  $game_result = GameResult($gameId);
}

$html .= "<div data-role='header'>\n";
$html .= "<h1>"._("Spirit points").": ".utf8entities($game_result['hometeamname'])." - ".utf8entities($game_result['visitorteamname'])."</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
$html .= "<form action='?view=addspiritpoints' method='post' data-ajax='false'>\n";

$html .= "<h3>"._("Spirit points given for").": ". utf8entities(TeamName($teamId))."</h3>\n";

$points = GameGetSpiritPoints($gameId, $teamId);

$html .=  "<label for='homecat1'>1. "._("Rules Knowledge and Use")."</label>";
$html .= "<fieldset id='homecat1' data-role='controlgroup' data-type='horizontal' >";
for($i=0;$i<5;$i++){
	$checked = $points['cat1']==$i ? "checked='checked'" : "";
	$html .= "<label for='homecat1$i'>$i</label>";
	$html .= "<input type='radio' id='homecat1$i' name='homecat1' value='$i' $checked/>";
}
$html .= "</fieldset>\n";

$html .=  "<label for='homecat2'>2. "._("Fouls and Body Contact")."</label>";
$html .= "<fieldset id='homecat2' data-role='controlgroup' data-type='horizontal' >";
for($i=0;$i<5;$i++){
	$checked = $points['cat2']==$i ? "checked='checked'" : "";
	$html .= "<label for='homecat2$i'>$i</label>";
	$html .= "<input type='radio' id='homecat2$i' name='homecat2' value='$i' $checked/>";
}
$html .= "</fieldset>\n";

$html .=  "<label for='homecat3'>3. "._("Fair-Mindedness")."</label>";
$html .= "<fieldset id='homecat3' data-role='controlgroup' data-type='horizontal' >";
for($i=0;$i<5;$i++){
	$checked = $points['cat3']==$i ? "checked='checked'" : "";
	$html .= "<label for='homecat3$i'>$i</label>";
	$html .= "<input type='radio' id='homecat3$i' name='homecat3' value='$i' $checked/>";
}
$html .= "</fieldset>\n";

$html .=  "<label for='homecat4'>4. "._("Positive Attitude and Self-Control")."</label>";
$html .= "<fieldset id='homecat4' data-role='controlgroup' data-type='horizontal' >";
for($i=0;$i<5;$i++){
	$checked = $points['cat4']==$i ? "checked='checked'" : "";
	$html .= "<label for='homecat4$i'>$i</label>";
	$html .= "<input type='radio' id='homecat4$i' name='homecat4' value='$i' $checked/>";
}
$html .= "</fieldset>\n";

$html .=  "<label for='homecat5'>5. "._("Our Spirit compared to theirs")."</label>";
$html .= "<fieldset id='homecat5' data-role='controlgroup' data-type='horizontal' >";
for($i=0;$i<5;$i++){
	$checked = $points['cat5']==$i ? "checked='checked'" : "";
	$html .= "<label for='homecat5$i'>$i</label>";
	$html .= "<input type='radio' id='homecat5$i' name='homecat5' value='$i' $checked/>";
}
$html .= "</fieldset>\n";

if($ishome){
  $html .= "<p>"._("Total points").": ".$game_result['homesotg']."</p>";
}else{
  $html .= "<p>"._("Total points").": ".$game_result['visitorsotg']."</p>";
}


$html .= "<input type='submit' name='save' data-ajax='false' value='"._("Save")."'/>";
if($ishome){
	$html .= "<a href='?view=addspiritpoints&game=".$gameId."&team=".$game_result['visitorteam']."' data-role='button' data-ajax='false'>"._("Spirit points for")." ".utf8entities($game_result['visitorteamname'])."</a>";
}else{
    $html .= "<a href='?view=addspiritpoints&game=".$gameId."&team=".$game_result['hometeam']."' data-role='button' data-ajax='false'>"._("Spirit points for")." ".utf8entities($game_result['hometeamname'])."</a>";	
}
$html .= "<a href='?view=addscoresheet&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("Back to score sheet")."</a>";
$html .= "<a href='?view=respgames' data-role='button' data-ajax='false'>"._("Back to game responsibilities")."</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
?>
