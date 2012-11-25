<?php
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/series.functions.php';
include_once $include_prefix.'lib/pool.functions.php';
include_once $include_prefix.'lib/statistical.functions.php';

$title = _("Teams");
$html = "";

$list = iget("list");
$season = iget("season");

if(empty($season)){
  $season = CurrentSeason();
}

if(empty($list)){
  $list = "allteams";
}

$seasonInfo = SeasonInfo($season);
$series = SeasonSeries($season, true);

$menutabs[_("By division")]= "?view=teams&season=$season&list=allteams";
$menutabs[_("By pool")]= "?view=teams&season=$season&list=bypool";
$menutabs[_("By seeding")]= "?view=teams&season=$season&list=byseeding";
$menutabs[_("By result")]= "?view=teams&season=$season&list=bystandings";
$html .= pageMenu($menutabs,"",false);

$cols = 2;
if (!intval($seasonInfo['isnationalteams'])){
  $cols++;
}
if(intval($seasonInfo['isinternational'])){
  $cols++;
}
if($list=="byseeding"){
  $cols++;
}
$isstatdata = IsStatsDataAvailable();

$html .= "<h1>"._("Teams")."</h1>";

if($list=="allteams" || $list=="byseeding"){

  foreach($series as $row){

    $html .= "<table border='0' cellspacing='0' cellpadding='2' width='100%'>\n";
    $html .= "<tr>";
    $html .= "<th colspan='$cols'>";
    $html .=utf8entities(U_($row['name']))."</th>\n";
    $html .= "</tr>\n";
    if($list=="byseeding"){
      $teams = SeriesTeams($row['series_id'], true);
    }else{
      $teams = SeriesTeams($row['series_id']);
    }
    $i=0;
    foreach ($teams as $team) {
      $i++;
      $html .= "<tr>";
      if($list=="byseeding"){
        if(!empty($team['rank'])){
          $html .= "<td style='width:2px'>".$team['rank'].".</td>";
        }else{
          $html .= "<td style='width:2px'>-</td>";
        }
      }
      if(intval($seasonInfo['isnationalteams'])){
        $html .= "<td style='width:200px'><a href='?view=teamcard&amp;team=".$team['team_id']."'>".utf8entities(U_($team['name']))."</a></td>";
      } else {
        $html .= "<td style='width:150px'><a href='?view=teamcard&amp;team=".$team['team_id']."'>".utf8entities($team['name'])."</a></td>";
        $html .= "<td style='width:150px'><a href='?view=clubcard&amp;club=". $team['club']."'>".utf8entities($team['clubname'])."</a></td>";
      }
      if(intval($seasonInfo['isinternational'])){
        $html .= "<td style='width:150px'>";

        if(!empty($team['flagfile'])){
          $html .= "<img height='10' src='images/flags/tiny/".$team['flagfile']."' alt=''/>&nbsp;";
        }
        if(!empty($team['countryname'])){
          $html .= "<a href='?view=countrycard&amp;country=". $team['country']."'>".utf8entities(_($team['countryname']))."</a>";
        }
        $html .= "</td>";
      }
      	
      $html .= "<td class='right' style='white-space: nowrap;width:15%'>\n";
      if($isstatdata){
        $html .= "<a href='?view=playerlist&amp;team=".$team['team_id']."'>"._("Roster")."</a>";
        $html .= "&nbsp;&nbsp;";
      }
      $html .= "<a href='?view=scorestatus&amp;team=".$team['team_id']."'>"._("Scoreboard")."</a>";
      	
      $html .= "&nbsp;&nbsp;";
      $html .= "<a href='?view=games&amp;team=".$team['team_id']."&amp;singleview=1'>"._("Games")."</a>";
      $html .= "</td>";
      $html .= "</tr>\n";
    }
    $html .= "</table>\n";
  }
}elseif ($list=="bypool") {

  foreach($series as $row){
    $html .= "<h2>".utf8entities(U_($row['name']))."</h2>\n";

    $pools = SeriesPools($row['series_id'], true);
    if(!count($pools)){
      $html .= "<p>"._("Pools not yet created")."</p>";
      continue;
    }
    foreach ($pools as $pool) {
      $html .= "<table border='0' cellspacing='0' cellpadding='2' width='100%'>\n";
      $html .= "<tr>";
      $html .= "<th colspan='".($cols-1)."'>".utf8entities(U_(PoolSeriesName($pool['pool_id'])).", ". U_($pool['name']))."</th><th class='right'>"._("Scoreboard")."</th>\n";
      $html .= "</tr>\n";
      if($pool['type']==2){
        //find out sub pools
        $pools = array();
        $pools[] = $pool['pool_id'];
        $followers = PoolFollowersArray($pool['pool_id']);
        $pools = array_merge($pools,$followers);
        $playoffpools = implode(",",$pools);
      }
      $teams = PoolTeams($pool['pool_id']);

      foreach($teams as $team){
        $html .= "<tr>";
        if(intval($seasonInfo['isnationalteams'])){
          $html .= "<td style='width:150px'><a href='?view=teamcard&amp;team=".$team['team_id']."'>".utf8entities(U_($team['name']))."</a></td>";
        } else {
          $html .= "<td style='width:150px'><a href='?view=teamcard&amp;team=".$team['team_id']."'>".utf8entities($team['name'])."</a></td>";
          $html .= "<td style='width:150px'><a href='?view=clubcard&amp;club=". $team['club']."'>".utf8entities($team['clubname'])."</a></td>";
        }
        if(intval($seasonInfo['isinternational'])){
          $html .= "<td style='width:150px'>";
          if(!empty($team['flagfile'])){
            $html .= "<img height='10' src='images/flags/tiny/".$team['flagfile']."' alt=''/>&nbsp;";
          }
          if(!empty($team['countryname'])){
            $html .= "<a href='?view=countrycard&amp;country=". $team['country']."'>".utf8entities(_($team['countryname']))."</a>";
          }
          $html .= "</td>";
        }

        $html .= "<td class='right' style='white-space: nowrap;width:15%'>\n";
        $html .= "<a href='?view=games&amp;team=".$team['team_id']."&amp;singleview=1'>"._("Games")."</a>";
        $html .= "&nbsp;&nbsp;";

        if($pool['type']==2){
          $html .= "<a href='?view=scorestatus&amp;team=".$team['team_id']."&amp;pools=".$playoffpools."'>"._("Pool")."</a>";
        }else{
          $html .= "<a href='?view=scorestatus&amp;team=".$team['team_id']."&amp;pool=". $pool['pool_id'] ."'>"._("Pool")."</a>";
        }
        $html .= "&nbsp;&nbsp;";

        $html .= "<a href='?view=scorestatus&amp;team=".$team['team_id']."'>"._("Division")."</a></td>";
        $html .= "</tr>\n";
      }
      $html .= "</table>\n";
    }
  }
}elseif ($list=="bystandings") {


  $htmlseries = array();
  $maxplacements=0;

  $series = SeasonSeries($seasonInfo['season_id'], true);
  foreach($series as $ser){
    $ppools = SeriesPlacementPoolIds($ser['series_id']);
    $htmlteams = array();
    foreach ($ppools as $ppool){
      $teams = PoolTeams($ppool['pool_id']);
      $steams = PoolSchedulingTeams($ppool['pool_id']);
      if(count($teams) < count($steams)){
        $totalteams = count($steams);
      }else{
        $totalteams = count($teams);
      }

      for($i=1;$i<=$totalteams;$i++){
        $moved = PoolMoveExist($ppool['pool_id'], $i);
        if(!$moved){
          $team = PoolTeamFromStandings($ppool['pool_id'], $i);
          $gamesleft = TeamPoolGamesLeft($team['team_id'], $ppool['pool_id']);
          if($ppool['played'] || ($ppool['type']==2 && mysql_num_rows($gamesleft)==0)){
            $team = PoolTeamFromStandings($ppool['pool_id'], $i);
            $htmltmp = "";
            if(intval($seasonInfo['isinternational'])){
              $htmltmp .= "<img height='10' src='images/flags/tiny/".$team['flagfile']."' alt=''/> ";
            }
            $htmltmp .= "<a href='?view=teamcard&amp;team=".$team['team_id']."'>".utf8entities($team['name'])."</a>";
            $htmlteams[] = $htmltmp;
          }else{
            $htmlteams[]= "&nbsp;";
          }
        }
      }
    }
    $htmlseries[] = $htmlteams;
  }

  $html .= "<table cellpadding='2' style='width:100%;'>\n";
  $html .= "<tr>";
  $html .= "<th style='width:20%;'>". _("Placement"). "</th>";
  foreach($series as $ser){
    $html .= "<th style='width:".(80/count($series))."%;'>". utf8entities(U_($ser['name'])) ."</th>";
    $maxplacements = max(count(SeriesTeams($ser['series_id'])), $maxplacements);
  }
  $html .= "</tr>\n";
  for($i=0;$i<$maxplacements;$i++){

    if($i<3){
      $html .= "<tr style='font-weight:bold;border-bottom-style:dashed;border-bottom-width:1px;border-bottom-color:#E0E0E0;'>";
    }else{
      $html .= "<tr style='border-bottom-style:dashed;border-bottom-width:1px;border-bottom-color:#E0E0E0;'>";
    }
    if($i==0){
      $html .= "<td>"._("Gold")."</td>";
    }elseif($i==1){
      $html .= "<td>"._("Silver")."</td>";
    }elseif($i==2){
      $html .= "<td>"._("Bronze")."</td>";
    }elseif($i>2){
      $html .= "<td>".ordinal($i+1)."</td>";
    }

    for($j=0;$j<count($series);$j++){
      $html .= "<td>";
      if(!empty($htmlseries[$j][$i])){
        $html .= $htmlseries[$j][$i];
      }else{
        $html .= "&nbsp;";
      }
      $html .= "</td>";
    }
    $html .= "</tr>\n";
  }
  $html .= "</table>\n";


}

showPage($title, $html);

?>
