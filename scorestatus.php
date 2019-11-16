<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/team.functions.php';

$title = _("Scoreboard");
$html = "";

$poolId = 0;
$poolIds = array();
$seriesId = 0;
$teamId = 0;
$sort="total";

if(iget("pool")) {
  $poolId = intval(iget("pool"));
  $title = $title.": ".utf8entities(U_(PoolName($poolId)));
}
if(iget("pools")) {
  $poolIds = explode(",",iget("pools"));
  $title = $title.": ".utf8entities(U_(PoolName($poolId)));
}
if(iget("series")) {
  $seriesId = intval(iget("series"));
  $title = $title.": ".utf8entities(U_(SeriesName($seriesId)));
}
if(iget("team")) {
  $teamId = intval(iget("team"));
  $title = $title.": ".utf8entities(TeamName($teamId));
}
if(iget("sort")){
  $sort = iget("sort");
}

$html .= "<h1>"._("Scoreboard")."</h1>\n";
$html .= "<table style='width:100%' cellpadding='1' border='1'>";

$viewUrl="?view=scorestatus&amp;";
if($teamId){$viewUrl.= "Team=$teamId&amp;";}
if($poolId){$viewUrl.= "Pool=$poolId&amp;";}
if(count($poolIds)){$viewUrl.= "Pools=".implode(",",$poolIds)."&amp;";}
if($seriesId){$viewUrl.= "Series=$seriesId&amp;";}

$html .= "<tr>\n";
$html .= "<th style='width:5%'>#</th>";
if($sort == "name"){
  $html .= "<th style='width:30%'>"._("Player")."</th>";
}else{
  $html .= "<th style='width:30%'><a class='thsort' href='".$viewUrl."sort=name'>"._("Player")."</a></th>";
}
if($sort == "team"){
  $html .= "<th style='width:25%'><b>"._("Team")."</b></th>";
}else{
  $html .= "<th style='width:25%'><a class='thsort' href='".$viewUrl."sort=team'>"._("Team")."</a></th>";
}
if($sort == "games"){
  $html .= "<th class='center' style='width:8%'><b>"._("Games")."</b></th>";
}else{
  $html .= "<th class='center' style='width:8%'><a class='thsort' href='".$viewUrl."sort=games'>"._("Games")."</a></th>";
}
if($sort == "pass"){
  $html .= "<th class='center' style='width:8%'><b>"._("Assists")."</b></th>";
}else{
  $html .= "<th class='center' style='width:8%'><a class='thsort' href='".$viewUrl."sort=pass'>"._("Assists")."</a></th>";
}
if($sort == "goal") {
  $html .= "<th class='center' style='width:8%'><b>"._("Goals")."</b></th>";
}else{
  $html .= "<th class='center' style='width:8%'><a class='thsort' href='".$viewUrl."sort=goal'>"._("Goals")."</a></th>";
}

if($sort == "callahan") {
  $html .= "<th class='center' style='width:8%'><b>"._("Cal.")."</b></th>";
}else{
  $html .= "<th class='center' style='width:8%'><a class='thsort' href='".$viewUrl."sort=callahan'>"._("Cal.")."</a></th>";
}

if($sort == "total") {
  $html .= "<th class='center' style='width:8%'><b>"._("Tot.")."</b></th>";
}else{
  $html .= "<th class='center' style='width:8%'><a class='thsort' href='".$viewUrl."sort=total'>"._("Tot.")."</a></th>";
}
$html .= "</tr>";

if($teamId){
  if(count($poolIds)){
    $scores = TeamScoreBoard($teamId, $poolIds, $sort, 0);
  }else{
    $scores = TeamScoreBoard($teamId, $poolId, $sort, 0);
  }
}elseif($poolId){
  $scores = PoolScoreBoard($poolId, $sort, 0);
}elseif(count($poolIds)){
  $scores = PoolsScoreBoard($poolIds, $sort, 0);
}elseif($seriesId){
  $scores = SeriesScoreBoard($seriesId, $sort, 0);
}
$i=1;
while($row = mysqli_fetch_assoc($scores)){
  $html .= "<tr>";
  $html .= "<td>".$i++."</td>";
  if($sort == "name") {
    $html .= "<td class='highlight'><a href='?view=playercard&amp;series=$poolId&amp;player=". $row['player_id']."'>";
    $html .= utf8entities($row['firstname']." ".$row['lastname']);
    $html .= "</a></td>";
  }else{
    $html .= "<td><a href='?view=playercard&amp;series=$poolId&amp;player=". $row['player_id']."'>";
    $html .= utf8entities($row['firstname']." ".$row['lastname']);
    $html .= "</a></td>";
  }
  if($sort == "team"){
    $html .= "<td class='highlight'>".utf8entities($row['teamname'])."</td>";
  }else{
    $html .= "<td>".utf8entities($row['teamname'])."</td>";
  }
  if($sort == "games"){
    $html .= "<td class='center highlight'>".intval($row['games'])."</td>";
  }else{
    $html .= "<td class='center'>".intval($row['games'])."</td>";
  }
  if($sort == "pass"){
    $html .= "<td class='center highlight'>".intval($row['fedin'])."</td>";
  }else{
    $html .= "<td class='center'>".intval($row['fedin'])."</td>";
  }
  if($sort == "goal"){
    $html .= "<td class='center highlight'>".intval($row['done'])."</td>";
  }else{
    $html .= "<td class='center'>".intval($row['done'])."</td>";
  }

  if($sort == "callahan"){
    $html .= "<td class='center highlight'>".intval($row['callahan'])."</td>";
  }else{
    $html .= "<td class='center'>".intval($row['callahan'])."</td>";
  }

  if($sort == "total") {
    $html .= "<td class='center highlight'>".intval($row['total'])."</td></tr>";
  }else{
    $html .= "<td class='center'>".intval($row['total'])."</td></tr>";
  }
}

$html .= "</table>";
showPage($title, $html);

?>
