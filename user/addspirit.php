<?php
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/game.functions.php';
include_once $include_prefix.'lib/standings.functions.php';
include_once $include_prefix.'lib/pool.functions.php';
include_once $include_prefix.'lib/configuration.functions.php';

$html = "";

$gameId = intval($_GET["game"]);
$gameinfo = GameResult($gameId);

$title = _("Spirit");

//process itself if save button was pressed
if(!empty($_POST['save'])) {
  $points = array();
  $points[0] = intval($_POST['homecat1']);
  $points[1] = intval($_POST['homecat2']);
  $points[2] = intval($_POST['homecat3']);
  $points[3] = intval($_POST['homecat4']);
  $points[4] = intval($_POST['homecat5']);
  GameSetSpiritPoints($gameId,$gameinfo['hometeam'],1,$points);
  
  $points[0] = intval($_POST['awaycat1']);
  $points[1] = intval($_POST['awaycat2']);
  $points[2] = intval($_POST['awaycat3']);
  $points[3] = intval($_POST['awaycat4']);
  $points[4] = intval($_POST['awaycat5']);
  GameSetSpiritPoints($gameId,$gameinfo['visitorteam'],0,$points);
  $gameinfo = GameResult($gameId);
}


$menutabs[_("Result")]= "?view=user/addresult&game=$gameId";
$menutabs[_("Players")]= "?view=user/addplayerlists&game=$gameId";
$menutabs[_("Score sheet")]= "?view=user/addscoresheet&game=$gameId";
$menutabs[_("Spirit points")]= "?view=user/addspirit&game=$gameId";
$html .= pageMenu($menutabs,"",false);

$html .= "<form  method='post' action='?view=user/addspirit&amp;game=".$gameId."'>";

$html .= "<h3>"._("Spirit points given for").": ". utf8entities($gameinfo['hometeamname'])."</h3>\n";

$points = GameGetSpiritPoints($gameId, $gameinfo['hometeam']);

$html .= "<table>\n";
$html .= "<tr>";
$html .= "<th style='width:70%;text-align: right;'></th>";
$html .= "<th class='center' style='width:5%'>0</th>";
$html .= "<th class='center' style='width:5%'>1</th>";
$html .= "<th class='center' style='width:5%'>2</th>";
$html .= "<th class='center' style='width:5%'>3</th>";
$html .= "<th class='center' style='width:5%'>4</th>";
$html .= "</tr>\n";

$html .= "<tr>";
$html .= "<td style='width:70%'>1. "._("Rules Knowledge and Use")."</td>";
for($i=0;$i<5;$i++){
  $checked = $points['cat1']==$i ? "checked='checked'" : "";
  $html .= "<td class='center' style='width:5%'><input type='radio' name='homecat1' value='$i'  $checked/></td>";
}
$html .= "</tr>";

$html .= "<tr>";
$html .= "<td style='width:70%'>2. "._("Fouls and Body Contact")."</td>";
for($i=0;$i<5;$i++){
	$checked = $points['cat2']==$i ? "checked='checked'" : "";
	$html .= "<td class='center' style='width:5%'><input type='radio' name='homecat2' value='$i'  $checked/></td>";
}
$html .= "</tr>";

$html .= "<tr>";
$html .= "<td style='width:70%'>3. "._("Fair-Mindedness")."</td>";
for($i=0;$i<5;$i++){
	$checked = $points['cat3']==$i ? "checked='checked'" : "";
	$html .= "<td class='center' style='width:5%'><input type='radio' name='homecat3' value='$i'  $checked/></td>";
}
$html .= "</tr>";

$html .= "<tr>";
$html .= "<td style='width:70%'>4. "._("Positive Attitude and Self-Control")."</td>";
for($i=0;$i<5;$i++){
	$checked = $points['cat4']==$i ? "checked='checked'" : "";
	$html .= "<td class='center' style='width:5%'><input type='radio' name='homecat4' value='$i'  $checked/></td>";
}
$html .= "</tr>";

$html .= "<tr>";
$html .= "<td style='width:70%'>5. "._("Our Spirit compared to theirs")."</td>";
for($i=0;$i<5;$i++){
	$checked = $points['cat5']==$i ? "checked='checked'" : "";
	$html .= "<td class='center' style='width:5%'><input type='radio' name='homecat5' value='$i'  $checked/></td>";
}
$html .= "</tr>";

$html .= "<tr>";
$html .= "<td class='highlight' style='width:70%'>"._("Total points")."</td>";
$html .= "<td class='highlight right' colspan='5'>".$gameinfo['homesotg']."</td>";
$html .= "</tr>";

$html .= "</table>\n";

$html .= "<h3>"._("Spirit points given for").": ". utf8entities($gameinfo['visitorteamname'])."</h3>\n";

$points = GameGetSpiritPoints($gameId, $gameinfo['visitorteam']);

$html .= "<table>\n";
$html .= "<tr>";
$html .= "<th style='width:70%;text-align: right;'></th>";
$html .= "<th class='center' style='width:5%'>0</th>";
$html .= "<th class='center' style='width:5%'>1</th>";
$html .= "<th class='center' style='width:5%'>2</th>";
$html .= "<th class='center' style='width:5%'>3</th>";
$html .= "<th class='center' style='width:5%'>4</th>";
$html .= "</tr>\n";

$html .= "<tr>";
$html .= "<td style='width:70%'>1. "._("Rules Knowledge and Use")."</td>";
for($i=0;$i<5;$i++){
  $checked = $points['cat1']==$i ? "checked='checked'" : "";
  $html .= "<td class='center' style='width:5%'><input type='radio' name='awaycat1' value='$i'  $checked/></td>";
}
$html .= "</tr>";

$html .= "<tr>";
$html .= "<td style='width:70%'>2. "._("Fouls and Body Contact")."</td>";
for($i=0;$i<5;$i++){
	$checked = $points['cat2']==$i ? "checked='checked'" : "";
	$html .= "<td class='center' style='width:5%'><input type='radio' name='awaycat2' value='$i'  $checked/></td>";
}
$html .= "</tr>";

$html .= "<tr>";
$html .= "<td style='width:70%'>3. "._("Fair-Mindedness")."</td>";
for($i=0;$i<5;$i++){
	$checked = $points['cat3']==$i ? "checked='checked'" : "";
	$html .= "<td class='center' style='width:5%'><input type='radio' name='awaycat3' value='$i'  $checked/></td>";
}
$html .= "</tr>";

$html .= "<tr>";
$html .= "<td style='width:70%'>4. "._("Positive Attitude and Self-Control")."</td>";
for($i=0;$i<5;$i++){
	$checked = $points['cat4']==$i ? "checked='checked'" : "";
	$html .= "<td class='center' style='width:5%'><input type='radio' name='awaycat4' value='$i'  $checked/></td>";
}
$html .= "</tr>";

$html .= "<tr>";
$html .= "<td style='width:70%'>5. "._("Our Spirit compared to theirs")."</td>";
for($i=0;$i<5;$i++){
	$checked = $points['cat5']==$i ? "checked='checked'" : "";
	$html .= "<td class='center' style='width:5%'><input type='radio' name='awaycat5' value='$i'  $checked/></td>";
}
$html .= "</tr>";

$html .= "<tr>";
$html .= "<td class='highlight' style='width:70%'>"._("Total points")."</td>";
$html .= "<td class='highlight right' colspan='5'>".$gameinfo['visitorsotg']."</td>";
$html .= "</tr>";

$html .= "</table>\n";

$html .= "<p>";
$html .= "<input class='button' type='submit' name='save' value='"._("Save")."'/>";
$html .= "</p>";
$html .= "</form>\n";

showPage($title, $html);

?>
