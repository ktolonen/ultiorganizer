<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';

$LAYOUT_ID = SERIESTATUS;

$title = _("Statistics")." ";
$viewUrl="?view=seriesstatus";
$sort="winavg";
$html = "";

if(iget("series")){
  $seriesinfo = SeriesInfo(iget("series"));
  $viewUrl .= "&amp;series=".$seriesinfo['series_id'];
  $seasoninfo = SeasonInfo($seriesinfo['season']);
  $title.= U_($seriesinfo['name']);
}

if(iget("sort")){
  $sort = iget("sort");
}

$teamstats = array();
$allteams = array();
$teams = SeriesTeams($seriesinfo['series_id']);

foreach ($teams as $team) {
  $stats = TeamStats($team['team_id']);
  $spiritstats = TeamSpiritStats($team['team_id']);
  $points = TeamPoints($team['team_id']);

  $teamstats['name']=$team['name'];
  $teamstats['team_id']=$team['team_id'];
  $teamstats['seed']=$team['rank'];
  $teamstats['flagfile']=$team['flagfile'];
  $teamstats['pool']=$team['poolname'];
  $teamstats['wins']=$stats['wins'];
  $teamstats['games']=$stats['games'];
  $teamstats['for']=$points['scores'];
  $teamstats['against']=$points['against'];
  $teamstats['losses']=$teamstats['games']-$teamstats['wins'];
  $teamstats['diff']=$teamstats['for']-$teamstats['against'];
  $teamstats['spirit']=$points['spirit'];
  $teamstats['spiritavg']=number_format(SafeDivide(intval($points['spirit']), intval($spiritstats['games'])),1);
  $teamstats['winavg']=number_format(SafeDivide(intval($stats['wins']), intval($stats['games']))*100,1);

  $allteams[] = $teamstats;
}

$html .= "<h2>"._("Division statistics:")." ".utf8entities($seriesinfo['name'])."</h2>";
$style = "";

$html .= "<table border='1' style='width:100%'>\n";
$html .= "<tr>";

if($sort == "name" || $sort == "pool" || $sort == "against" || $sort == "seed") {
  usort($allteams, create_function('$a,$b','return $a[\''.$sort.'\']==$b[\''.$sort.'\']?0:($a[\''.$sort.'\']<$b[\''.$sort.'\']?-1:1);'));
}else{
  usort($allteams, create_function('$a,$b','return $a[\''.$sort.'\']==$b[\''.$sort.'\']?0:($a[\''.$sort.'\']>$b[\''.$sort.'\']?-1:1);'));
}

if($sort == "name") {
  $html .= "<th style='width:180px'>"._("Team")."</th>";
}else{
  $html .= "<th style='width:180px'><a class='thsort' href='".$viewUrl."&amp;Sort=name'>"._("Team")."</a></th>";
}

/*
 if($sort == "pool") {
 $html .= "<th style='width:200px'>"._("Pool")."</th>";
 }else{
 $html .= "<th style='width:200px'><a href='".$viewUrl."&amp;Sort=pool'>"._("Pool")."</a></th>";
 }
 */

if($sort == "seed") {
  $html .= "<th class='center'>"._("Seeding")."</th>";
}else{
  $html .= "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=seed'>"._("Seeding")."</a></th>";
}

if($sort == "games") {
  $html .= "<th class='center'>"._("Games")."</th>";
}else{
  $html .= "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=games'>"._("Games")."</a></th>";
}

if($sort == "wins") {
  $html .= "<th class='center'>"._("Wins")."</th>";
}else{
  $html .= "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=wins'>"._("Wins")."</a></th>";
}

if($sort == "losses") {
  $html .= "<th class='center'>"._("Losses")."</th>";
}else{
  $html .= "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=losses'>"._("Losses")."</a></th>";
}

if($sort == "for") {
  $html .= "<th class='center'>"._("Goals for")."</th>";
}else{
  $html .= "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=for'>"._("Goals for")."</a></th>";
}

if($sort == "against") {
  $html .= "<th class='center'>"._("Goals against")."</th>";
}else{
  $html .= "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=against'>"._("Goals against")."</a></th>";
}

if($sort == "diff") {
  $html .= "<th class='center'>"._("Goals diff")."</th>";
}else{
  $html .= "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=diff'>"._("Goals diff")."</a></th>";
}

if($sort == "winavg") {
  $html .= "<th class='center'>"._("Win-%")."</th>";
}else{
  $html .= "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=winavg'>"._("Win-%")."</a></th>";
}
if($seasoninfo['spiritpoints'] && ($seasoninfo['showspiritpoints'] || isSeasonAdmin($seriesinfo['season']))){
  if($sort == "spirit") {
    $html .= "<th class='center'>"._("Spirit points")."</th>";
  }else{
    $html .= "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=spirit'>"._("Spirit points")."</a></th>";
  }

  if($sort == "spiritavg") {
    $html .= "<th class='center'>"._("Spirit / Game")."</th>";
  }else{
    $html .= "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=spiritavg'>"._("Spirit / Game")."</a></th>";
  }
}

$html .= "</tr>\n";

foreach($allteams as $stats){
  $html .= "<tr>";
  $flag="";
  if(intval($seasoninfo['isinternational'])){
    $flag = "<img height='10' src='images/flags/tiny/".$stats['flagfile']."' alt=''/> ";
  }
  if($sort == "name") {
    $html .= "<td class='highlight'>$flag<a href='?view=teamcard&amp;team=".$stats['team_id']."'>".utf8entities(U_($stats['name']))."</a></td>";
  }else{
    $html .= "<td>$flag<a href='?view=teamcard&amp;team=".$stats['team_id']."'>".utf8entities(U_($stats['name']))."</a></td>";
  }
  /*
   if($sort == "pool") {
   $html .= "<td class='highlight'>",utf8entities(U_($stats['pool'])),"</td>";
   }else{
   $html .= "<td>",utf8entities(U_($stats['pool'])),"</td>";
   }
   */
  if($sort == "seed") {
    $html .= "<td class='center highlight'>".intval($stats['seed']).".</td>";
  }else{
    $html .= "<td class='center'>".intval($stats['seed']).".</td>";
  }

  if($sort == "games") {
    $html .= "<td class='center highlight'>".intval($stats['games'])."</td>";
  }else{
    $html .= "<td class='center'>".intval($stats['games'])."</td>";
  }
  if($sort == "wins") {
    $html .="<td class='center highlight'>".intval($stats['wins'])."</td>";
  }else{
    $html .="<td class='center'>".intval($stats['wins'])."</td>";
  }
  if($sort == "losses") {
    $html .= "<td class='center highlight'>".intval($stats['losses'])."</td>";
  }else{
    $html .= "<td class='center'>".intval($stats['losses'])."</td>";
  }
  if($sort == "for") {
    $html .= "<td class='center highlight'>".intval($stats['for'])."</td>";
  }else{
    $html .= "<td class='center'>".intval($stats['for'])."</td>";
  }
  if($sort == "against") {
    $html .= "<td class='center highlight'>".intval($stats['against'])."</td>";
  }else{
    $html .= "<td class='center'>".intval($stats['against'])."</td>";
  }
  if($sort == "diff") {
    $html .= "<td class='center highlight'>".intval($stats['diff'])."</td>";
  }else{
    $html .= "<td class='center'>".intval($stats['diff'])."</td>";
  }

  if($sort == "winavg") {
    $html .= "<td class='center highlight'>".$stats['winavg']."%</td>";
  }else{
    $html .= "<td class='center'>".$stats['winavg']."%</td>";
  }

  if($seasoninfo['spiritpoints'] && ($seasoninfo['showspiritpoints'] || isSeasonAdmin($seriesinfo['season']))){
    if($sort == "spirit") {
      $html .= "<td class='center highlight'>".$stats['spirit']."</td>";
    }else{
      $html .= "<td class='center'>".$stats['spirit']."</td>";
    }
    if($sort == "spiritavg") {
      $html .= "<td class='center highlight'>".$stats['spiritavg']."</td>";
    }else{
      $html .= "<td class='center'>".$stats['spiritavg']."</td>";
    }
  }

  $html .= "</tr>\n";
}
$html .= "</table>\n";
$html .= "<a href='?view=poolstatus&amp;series=".$seriesinfo['series_id']."'>"._("Show all pools")."</a>";
$html .= "<h2>"._("Scoreboard leaders")."</h2>\n";
$html .= "<table cellspacing='0' border='0' width='100%'>\n";
$html .= "<tr><th style='width:200px'>"._("Player")."</th><th style='width:200px'>"._("Team")."</th><th class='center'>"._("Games")."</th>
<th class='center'>"._("Assists")."</th><th class='center'>"._("Goals")."</th><th class='center'>"._("Tot.")."</th></tr>\n";

$scores = SeriesScoreBoard($seriesinfo['series_id'],"total", 10);
while($row = mysql_fetch_assoc($scores)){
  $html .= "<tr><td>". utf8entities($row['firstname']." ".$row['lastname'])."</td>";
  $html .= "<td>".utf8entities($row['teamname'])."</td>";
  $html .= "<td class='center'>".intval($row['games'])."</td>";
  $html .= "<td class='center'>".intval($row['fedin'])."</td>";
  $html .= "<td class='center'>".intval($row['done'])."</td>";
  $html .= "<td class='center'>".intval($row['total'])."</td></tr>\n";
}

$html .= "</table>";
$html .= "<a href='?view=scorestatus&amp;series=".$seriesinfo['series_id']."'>"._("Scoreboard")."</a>";


if(ShowDefenseStats()) {
  $html .= "<h2>"._("Defenseboard leaders")."</h2>\n";
  $html .= "<table cellspacing='0' border='0' width='100%'>\n";
  $html .= "<tr><th style='width:200px'>"._("Player")."</th><th style='width:200px'>"._("Team")."</th><th class='center'>"._("Games")."</th>
	<th class='center'>"._("Total defenses")."</th></tr>\n";


  $defenses = SeriesDefenseBoard($seriesinfo['series_id'],"deftotal", 10);
  while($row = mysql_fetch_assoc($defenses)) {
    $html .= "<tr><td>". utf8entities($row['firstname']." ".$row['lastname'])."</td>";
    $html .= "<td>".utf8entities($row['teamname'])."</td>";
    $html .= "<td class='center'>".intval($row['games'])."</td>";
    $html .= "<td class='center'>".intval($row['deftotal'])."</td></tr>\n";
  }

  $html .= "</table>";
  $html .= "<a href='?view=defensestatus&amp;series=".$seriesinfo['series_id']."'>"._("Defenseboard")."</a>";

if ($seasoninfo['showspiritpoints']){
  $html .= "<h2>"._("Spirit points average per category")."</h2>\n";
  $html .= "<table cellspacing='0' border='0' width='100%'>\n";
  $html .= "<tr><th style='width:200px'>"._("Team")."</th><th style='width:80px'>"._("Rules knowlege")."</th><th class='center'>"._("Fouls and contact")."</th>
	<th class='center'>"._("Fair mindedness")."</th><th class='center'>"._("Positive attitude")."</th><th class='center'>"._("Their spirit vs ours")."</th></tr>\n";

 $spiritAvg = SeriesSpiritBoard($seriesinfo['series_id'], 0);
 while($row = mysql_fetch_assoc($spiritAvg)) {
    $html .= "<td>".utf8entities($row['teamname'])."</td>";
    $cat1Res = number_format(SafeDivide(intval($row['cat1']), intval($row['games'])),2);
    $cat2Res = number_format(SafeDivide(intval($row['cat2']), intval($row['games'])),2);
    $cat3Res = number_format(SafeDivide(intval($row['cat3']), intval($row['games'])),2);
    $cat4Res = number_format(SafeDivide(intval($row['cat4']), intval($row['games'])),2);
    $cat5Res = number_format(SafeDivide(intval($row['cat5']), intval($row['games'])),2);
    $html .= "<td class='center'>".$cat1Res."</td>";
    $html .= "<td class='center'>".$cat2Res."</td>";
    $html .= "<td class='center'>".$cat3Res."</td>";
    $html .= "<td class='center'>".$cat4Res."</td>";
    $html .= "<td class='center'>".$cat5Res."</td></tr>\n";
  }
}
  $html .= "</table>";
}

showPage($title, $html);

?>
