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

$season = SeasonInfo(GameSeason($gameId));
if ($season['spiritmode']>0) {
  $mode = SpiritMode($season['spiritmode']);
  $categories = SpiritCategories($mode['mode']);
  
//process itself if save button was pressed
if(!empty($_POST['save'])) {
  $points = array();
  foreach ($_POST['homevalueId'] as $cat) {
    $points[$cat] = $_POST['homecat'.$cat]; 
  }
  var_dump($points);
  GameSetSpiritPoints($gameId,$gameinfo['hometeam'],1,$points, $categories);
  
  $points = array();
  foreach ($_POST['visvalueId'] as $cat) {
    $points[$cat] = $_POST['viscat'.$cat];
  }
  GameSetSpiritPoints($gameId,$gameinfo['visitorteam'],0,$points, $categories);
  
  $gameinfo = GameResult($gameId);
}


$menutabs[_("Result")]= "?view=user/addresult&game=$gameId";
$menutabs[_("Players")]= "?view=user/addplayerlists&game=$gameId";
$menutabs[_("Score sheet")]= "?view=user/addscoresheet&game=$gameId";
$menutabs[_("Spirit points")]= "?view=user/addspirit&game=$gameId";
if(ShowDefenseStats())
{
  $menutabs[_("Defense sheet")]= "?view=user/adddefensesheet&game=$gameId";
}
$html .= pageMenu($menutabs,"",false);

$html .= "<form  method='post' action='?view=user/addspirit&amp;game=".$gameId."'>";

$html .= "<h3>"._("Spirit points given for").": ". utf8entities($gameinfo['hometeamname'])."</h3>\n";

$points = GameGetSpiritPoints($gameId, $gameinfo['hometeam']);
$html .= spiritTable($gameinfo, $points, $categories, true);

$html .= "<h3>"._("Spirit points given for").": ". utf8entities($gameinfo['visitorteamname'])."</h3>\n";

$points = GameGetSpiritPoints($gameId, $gameinfo['visitorteam']);
$html .= spiritTable($gameinfo, $points, $categories, false);

$html .= "<p>";
$html .= "<input class='button' type='submit' name='save' value='"._("Save")."'/>";
$html .= "</p>";
$html .= "</form>\n";

} else {
  $html .= "<p>"._("Spiritpoints not given for") . utf8entities($season['name']) . "</p>";
}
showPage($title, $html);

function spiritTable($gameinfo, $points, $categories, $home) {
  $home = $home?"home":"vis";
  $html = "<table>\n";
  $html .= "<tr>";
  $html .= "<th style='width:70%;text-align: right;'></th>";
  $vmin = 99999;
  $vmax = -99999;
  foreach ($categories as $cat) {
    if ($vmin > $cat['min']) $vmin = $cat['min'];
    if ($vmax < $cat['max']) $vmax = $cat['max'];
  }
  
  if ($vmax - $vmin < 12) {
    for($i=$vmin; $i<=$vmax; ++$i) {
      $html .= "<th class='center'>$i</th>";
    }
    $html .= "</tr>\n";
  
    foreach ($categories as $cat) {
      if ($cat['index']== 0)
        continue;
      $id = $cat['category_id'];
      $html .= "<tr>";
      $html .= "<td style='width:70%'>"._($cat['text']);
      $html .= "<input type='hidden' id='".$home."valueId$id' name='".$home."valueId[]' value='$id'/></td>";
      
      for($i=$vmin; $i<= $vmax; ++$i){
        if ($i < $cat['min']) {
          $html .= "<td></td>";
        } else {
          $id=$cat['category_id'];
          $checked = (isset($points[$id]) && !is_null($points[$id]) && $points[$id]==$i) ? "checked='checked'" : "";
          $html .= "<td class='center'>
          <input type='radio' id='".$home."cat$id' name='".$home."cat". $id . "' value='$i'  $checked/></td>";
        }
      }
      $html .= "</tr>\n";
    }
  } else {
    $html .= "<th></th></tr>\n";
  
    foreach ($categories as $cat) {
      $html .= "<tr>";
      $html .= "<td style='width:70%'>"._($cat['text']);
      $html .= "<input type='hidden' id='valueId$id' name='valueId[]' value='$id'/></td>";
      $html .= "<td class='center'>
      <input type='text' id='".$home."cat". $id . "0' name='".$home."cat[]' value='".$points[$id]."'/></td>";
      $html .= "</tr>\n";
    }
  }
  
  $html .= "<tr>";
  $html .= "<td class='highlight' style='width:70%'>"._("Total points")."</td>";
  $html .= "<td class='highlight right' colspan='5'>".$gameinfo['homesotg']."</td>";
  $html .= "</tr>";
  
  $html .= "</table>\n";

  return $html;
}
?>
