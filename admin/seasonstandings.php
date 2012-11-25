<?php
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = SEASONSTANDINGS;

$season = $_GET["season"];
$series_id = CurrentSeries($season);
$series = SeasonSeries($season);
$html = "";

$_SESSION['hide_played_pools'] = !empty($_SESSION['hide_played_pools']) ? $_SESSION['hide_played_pools'] : 0;
 
if(!empty($_GET["v"])) {
	$visibility = $_GET["v"];
	
	if($visibility=="pool"){
      $_SESSION['hide_played_pools'] = $_SESSION['hide_played_pools'] ? 0 : 1;
	}
}

//process itself on submit
if(!empty($_POST['remove_x'])){
  $pool = $_POST['PoolDeleteId'];
  $team = $_POST['TeamDeleteId'];
  if(CanDeleteTeamFromPool($pool, $team)){
    PoolDeleteTeam($pool, $team);
  }
}

if(!empty($_POST['recalculate'])){
  ResolvePoolStandings($_POST['PoolId']);
}


$title = utf8entities(SeasonName($season)).": "._("Pool standings");

//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
<!--
function setDeleteId(pool, team) 
	{
	var input = document.getElementById("PoolDeleteId");
	input.value = pool;
	var input = document.getElementById("TeamDeleteId");
	input.value = team;
	}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

foreach($series as $row){
  $menutabs[U_($row['name'])]="?view=admin/seasonstandings&season=".$season."&series=".$row['series_id'];
}
$menutabs[_("...")]="?view=admin/seasonseries&season=".$season;
pageMenu($menutabs,"?view=admin/seasonstandings&season=".$season."&series=".$series_id);

$html .= "<p>";
if($_SESSION['hide_played_pools']){
  $html .= "<a href='?view=admin/seasonstandings&amp;season=$season&amp;v=pool'>"._("Show played pools")."</a> ";
}else{
  $html .= "<a href='?view=admin/seasonstandings&amp;season=$season&amp;v=pool'>"._("Hide played pools")."</a> ";
}
$html .= "</p>";


$html .= "<form method='post' action='?view=admin/seasonstandings&amp;season=$season'>";
$seasoninfo = SeasonInfo($season);
$pools = SeriesPools($series_id);
if(!count($pools)){
  $html .= "<p>"._("Add pools first")."</p>\n";
}

$tour = "";
foreach($pools as $spool){

  $poolinfo = PoolInfo($spool['pool_id']);
  
  if($_SESSION['hide_played_pools'] && $poolinfo['played']){
    continue;
  }
  
  $standings = PoolTeams($spool['pool_id'], "rank");
  
  $html .= "<h2>".utf8entities(U_($poolinfo['name']))."</h2>";

  if($poolinfo['type']==3){// Swissdraw
    if(count($standings)){
      	
      $style = "class='admintable'";
      	
      if($poolinfo['played']){
        $style = "class='playedpool admintable'";
      }

      $html .= "<table $style border='0' width='100%'>
				<tr><th>"._("Pos.")."</th>
				<th>"._("Team")."</th>";
      $html .= "<th class='center'>"._("Games")."</th>";
      $html .= "<th class='center'>"._("Victory Points")."</th>";
      $html .= "<th class='center'>"._("Opponent VPs")."</th>";
      $html .= "<th class='center'>"._("Margin")."</th>";
      $html .= "<th class='center'>"._("Goals scored")."</th>";
      if($seasoninfo['spiritpoints']){
        $html .= "<th class='center'>"._("Spirit points")."</th>";
      }
      $html .= "<th></th></tr>";

      foreach($standings as $row)	{
        $vp = TeamVictoryPointsByPool($spool['pool_id'], $row['team_id']);

        $html .= "<tr>";
        $html .= "<td>".intval($row['activerank'])."</td>";
        $html .= "<td>". utf8entities($row['name']) ."</td>";

        $html .= "<td class='center'>".intval($vp['games'])."</td>";
        $html .="<td class='center'>".intval($vp['victorypoints'])."</td>";
        $html .= "<td class='center'>".intval($vp['oppvp'])."</td>";
        $html .= "<td class='center'>".intval($vp['margin'])."</td>";
        $html .= "<td class='center'>".intval($vp['score'])."</td>";
        if($seasoninfo['spiritpoints']){
          $html .= "<td class='center'>". (number_format(SafeDivide(intval($vp['spirit']), intval($vp['games'])),1)) ."</td>";
        }

        $html .= "<td class='center' style='width:60px;'>";
        $html .= "<a href='?view=admin/editstanding&amp;season=$season&amp;pool=".$spool['pool_id']."&amp;team=".$row['team_id']."'><img class='deletebutton' src='images/settings.png' alt='D' title='"._("edit details")."'/></a>";
        
        if(CanDeleteTeamFromPool($spool['pool_id'], $row['team_id']))
        $html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setDeleteId(".$spool['pool_id'] .",". $row['team_id'].");\"/></td>";
        $html .= "</tr>";
      }
      $html .= "</table>";
    }
  }else{
    // regular pool or playoff
    if(count($standings)){
      	
      $style = "class='admintable'";
      	
      if($poolinfo['played']){
        $style = "class='playedpool admintable'";
      }

      $html .= "<table $style>
				<tr><th>"._("Pos.")."</th>
				<th>"._("Team")."</th>";
      $html .= "<th class='center'>"._("Games")."</th>";
      $html .= "<th class='center'>"._("Wins")."</th>";
      $html .= "<th class='center'>"._("Losses")."</th>";
      $html .= "<th class='center'>"._("Goals for")."</th>";
      $html .= "<th class='center'>"._("Goals against")."</th>";
      $html .= "<th class='center'>"._("Goal diff")."</th>";
      if($seasoninfo['spiritpoints']){
        $html .= "<th class='center'>"._("Spirit points")."</th>";
      }
      $html .= "<th></th></tr>";

      foreach($standings as $row) {
        $stats = TeamStatsByPool($spool['pool_id'], $row['team_id']);
        $points = TeamPointsByPool($spool['pool_id'], $row['team_id']);

        $html .= "<tr>";
        $html .= "<td>".intval($row['activerank'])."</td>";
        $html .= "<td>". utf8entities($row['name']) ."</td>";

        $html .= "<td class='center'>".intval($stats['games'])."</td>";
        $html .="<td class='center'>".intval($stats['wins'])."</td>";
        $html .= "<td class='center'>".(intval($stats['games'])-intval($stats['wins']))."</td>";
        $html .= "<td class='center'>".intval($points['scores'])."</td>";
        $html .= "<td class='center'>".intval($points['against'])."</td>";
        $html .= "<td class='center'>".((intval($points['scores'])-intval($points['against'])))."</td>";
        if($seasoninfo['spiritpoints']){
          $html .= "<td class='center'>".(number_format(SafeDivide(intval($points['spirit']), intval($stats['games'])),1))."</td>";
        }

        $html .= "<td class='center' style='width:60px;'>";
        $html .= "<a href='?view=admin/editstanding&amp;season=$season&amp;pool=".$spool['pool_id']."&amp;team=".$row['team_id']."'><img class='deletebutton' src='images/settings.png' alt='D' title='"._("edit details")."'/></a>";
        
        if(CanDeleteTeamFromPool($spool['pool_id'], $row['team_id'])){
          $html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setDeleteId(".$spool['pool_id'] .",". $row['team_id'].");\"/></td>";
        }
        $html .= "</tr>";
      }
      $html .= "</table>";
    }
  }
if(count(PoolTeams($spool['pool_id']))){
   $html .= "<p><input class='button' type='submit' name='recalculate' value='"._("Re-calculate standings")."' onclick='setDeleteId(".$spool['pool_id'].");'/></p>";
}
  if(!$poolinfo['played'] && PoolIsMoveFromPoolsPlayed($spool['pool_id']) && !PoolIsAllMoved($spool['pool_id'])){
    $html .= "<div class='highlight'><b><a href='?view=admin/serieteams&amp;season=$season&amp;series=".$spool['series_id']."&amp;pool=".$spool['pool_id']."'>"._("Move teams")."</a></b></div>";
  }
}

$html .= "<p>";
$html .= "<input type='hidden' id='PoolDeleteId' name='PoolDeleteId'/>";
$html .= "<input type='hidden' id='TeamDeleteId' name='TeamDeleteId'/>";
$html .= "<input type='hidden' id='PoolId' name='PoolId'/>";
$html .= "</p>";
$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();
?>