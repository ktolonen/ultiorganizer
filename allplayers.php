<?php
include_once 'lib/player.functions.php';

$title = _("All players");
$html = "";
$filter = "A";

if(iget("list")) {
  $filter = strtoupper(iget("list"));
}

$html .= "<h1>".$title."</h1>\n";

$players = PlayerListAll($filter);

$firstchar = " ";
$listletter = " ";
$validletters = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
$counter = 0;
$maxcols = 4;

$html .= "<table style='white-space: nowrap;width:100%'><tr>\n";
foreach($validletters as $let){
  if($let==$filter){
    $html .= "<td class='selgroupinglink'>&nbsp;".utf8entities($let)."&nbsp;</td>";
  }else{
    $html .= "<td>&nbsp;<a class='groupinglink' href='?view=allplayers&amp;list=".urlencode($let)."'>".utf8entities($let)."</a>&nbsp;</td>";
  }
}
if($filter=="ALL"){
  $html .= "<td class='selgroupinglink'>&nbsp;"._("ALL")."</td>";
}else{
  $html .= "<td>&nbsp;<a class='groupinglink' href='?view=allplayers&amp;list=all'>"._("ALL")."</a></td>";
}
$html .= "</tr></table>\n";

$html .= "<table width='90%' style='white-space: nowrap;'>\n";

while($player = mysqli_fetch_assoc($players)){

  if($filter == "ALL"){
    $firstchar = strtoupper(substr(utf8_decode($player['lastname']),0,1));
    if($listletter != $firstchar && in_array($firstchar,$validletters)){
      $listletter = $firstchar;
      if($counter>0 && $counter<=$maxcols){$html .= "</tr>\n";}
      $html .= "<tr><td></td></tr>\n";
      $html .= "<tr><td class='list_letter' colspan='$maxcols'>".utf8_encode("$listletter")."</td></tr>\n";
      $counter = 0;
    }
  }

  if($counter==0){
    $html .= "<tr>\n";
  }

  if(!empty($player['profile_id'])){
    $html .= "<td style='width:".(100/$maxcols)."%'><a href='?view=playercard&amp;series=0&amp;player=". $player['player_id']."'>".
    utf8entities($player['lastname'] ." ". $player['firstname']) ."</a></td>\n";
  }else{
    $html .= "<td style='width:".(100/$maxcols)."%'>".utf8entities($player['lastname'] ." ". $player['firstname']) ."</td>\n";
  }
  $counter++;

  if($counter>=$maxcols){
    $html .= "</tr>\n";
    $counter = 0;
  }
}
if($counter>0 && $counter<=$maxcols){$html .= "</tr>\n";};
$html .= "</table>\n";

showPage($title, $html);

?>
