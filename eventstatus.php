<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';

$title = _("Final standings")." ";
$html = "";

$season = iget("season");
$seasoninfo = SeasonInfo($season);
$title.= U_($seasoninfo['name']);

$html .= "<h1>"._("Final standings")."</h1>";

$htmlseries = array();
$maxplacements=0;

$series = SeasonSeries($seasoninfo['season_id'], true);
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
          $html = "";
          if(intval($seasoninfo['isinternational'])){
            $html .= "<img height='10' src='images/flags/tiny/".$team['flagfile']."' alt=''/> ";
          }
          $html .= "<a href='?view=teamcard&amp;team=".$team['team_id']."'>".utf8entities($team['name'])."</a>";
          $htmlteams[] = $html;
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

showPage($title, $html);
?>
