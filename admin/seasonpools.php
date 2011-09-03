<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = SEASONPOOLS;

$season = $_GET["Season"];
$html = "";
$title = utf8entities(SeasonName($season)).": "._("Pools");

//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
<!--
function setId(id){
	var input = document.getElementById("hiddenDeleteId");
	input.value = id;
	}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

//process itself on submit
if(!empty($_POST['remove_x'])) {
  $id = $_POST['hiddenDeleteId'];
  if(CanDeletePool($id)){
    DeletePool($id);
  }
}
	
$html .= "<form method='post' action='?view=admin/seasonpools&amp;Season=$season'>";

$series = SeasonSeries($season);

foreach($series as $row){
	$html .= "<h2>".utf8entities(U_($row['name']))."</h2>\n";
	$pools = SeriesPools($row['series_id']);
	
	if(count($pools)){
		$html .= "<table style='white-space: nowrap' border='0' width='100%' cellpadding='4px'>\n";
		$html .= "<tr><th>"._("Name")."</th>
			<th>"._("Order")."</th>
			<th>"._("Visible")."</th>
			<th>"._("Continuing pool")."</th>
			<th>"._("Placement pool")."</th>
			<th>"._("Played")."</th>
			<th>"._("Color")."</th>
			<th>"._("Operations")."</th>
			<th>&nbsp;</th>
			</tr>\n";
		$movestotal = 0;
		$cangenerateallgames = true;
		
		foreach($pools as $pool){
			$info = PoolInfo($pool['pool_id']);
			$continuationSerie = intval($info['continuingpool'])?_("yes"):_("no");
			$placementpool = intval($info['placementpool'])?_("yes"):_("no");
			$visible = intval($info['visible'])?_("yes"):_("no");
			$played = intval($info['played'])?_("yes"):_("no");
			$allmoved = true;
			$moves = 1;
			if(intval($info['continuingpool'])){
				$allmoved = PoolIsAllMoved($pool['pool_id']);
				$moves = count(PoolMovingsToPool($pool['pool_id']));
				$movestotal += $moves;
			}
			if(intval($info['placementpool']) && !intval($info['follower'])){
				$ppools = SeriesPlacementPoolIds($row['series_id']);
				$placementfrom = 1;
				$placementto = 0;
				foreach ($ppools as $ppool){
					$teams = PoolTeams($ppool['pool_id']);
					if(count($teams)==0){
						$teams = PoolSchedulingTeams($ppool['pool_id']);
					}
					if($pool['pool_id']==$ppool['pool_id']){
					
						for($i=1;$i<=count($teams);$i++){
							$moved = PoolMoveExist($ppool['pool_id'], $i);
							if(!$moved){
								$placementto++;
							}
						}
						break;
					}
					for($i=1;$i<=count($teams);$i++){
						$moved = PoolMoveExist($ppool['pool_id'], $i);
						if(!$moved){
							$placementfrom++;
							$placementto++;
						}
					}					
				}
				if($placementfrom <= $placementto){
					$placementpool .= " [$placementfrom.-$placementto.]";
				}else{
					$placementpool .= " [$placementfrom...]";
				}
			}elseif(intval($info['follower'])){
				$placementpool = _("yes");
			}
			
			$html .= "<tr>";
			
			if(!empty($info['name'])){
				$name = utf8entities($info['name']);
			}else{
				$name = _("No name");
			}
			$frompool = PoolGetMoveFrom($info['pool_id'],1);
			$frompoolinfo = PoolInfo($frompool['frompool']);
			//if($frompoolinfo['type']==2 OR ($frompoolinfo['type']==3 AND $frompoolinfo['continuingpool']==0)){ // Playoff or Swissdraw
			if(rtrim($frompoolinfo['ordering'],"0..9")==rtrim($info['ordering'],"0..9")){ // Playoff or Swissdraw
				$html .= "<td>&nbsp; '- <a href='?view=admin/addseasonpools&amp;Season=$season&amp;Pool=".$info['pool_id']."'>".$name."</a></td>";
			}else{
				$html .= "<td><a href='?view=admin/addseasonpools&amp;Season=$season&amp;Pool=".$info['pool_id']."'>".$name."</a></td>";
			}
			
			$started = IsPoolStarted($pool['pool_id']);
			$teams = count(PoolTeams($pool['pool_id']));
			$html .= "<td class='center'>".$info['ordering']."</td>";
			if(rtrim($frompoolinfo['ordering'],"0..9")==rtrim($info['ordering'],"0..9")){ // Playoff or Swissdraw
			  $html .= "<td class='center'>-</td>";
			}else{
			  $html .= "<td class='center'>$visible</td>";
			}			
			$html .= "<td class='center'>$continuationSerie</td>";
			$html .= "<td class='center'>$placementpool</td>";
			$html .= "<td class='center'>$played</td>";
			$html .= "<td style='background-color:#".$info['color'].";background-color:".RGBtoRGBa($info['color'],0.3).";color:#".textColor($info['color']).";'>"._("Team")."</td>";
			$html .= "<td>";

			if(!intval($info['continuingpool']) && !$started){
				if($teams){
					$html .= "<a href='?view=admin/select_teams&amp;Series=".$row['series_id']."'>"._("Select teams")."</a> | ";
				}else{
					$html .= "<b><a href='?view=admin/select_teams&amp;Series=".$row['series_id']."'>"._("Select teams")."</a></b> | ";
				}
			}elseif($allmoved && $moves>0 || $started){
				$html .= "<a href='?view=admin/serieteams&amp;Season=$season&amp;Series=".$row['series_id']."&amp;Pool=".$info['pool_id']."'>"._("Teams")."</a> | ";
			}else{
				if($moves){
					if(PoolIsMoveFromPoolsPlayed($info['pool_id'])){
						$html .= "<b><a href='?view=admin/serieteams&amp;Season=$season&amp;Series=".$row['series_id']."&amp;Pool=".$info['pool_id']."'>"._("Move teams")."</a></b> | ";
					}else{
						$html .= "<a href='?view=admin/serieteams&amp;Season=$season&amp;Series=".$row['series_id']."&amp;Pool=".$info['pool_id']."'>"._("Move teams")."</a> | ";
					}
				}else{
					$html .= "<b><a href='?view=admin/poolmoves&amp;Season=$season&amp;Series=".$row['series_id']."&amp;Pool=".$info['pool_id']."'>"._("Manage moves")."</a></b> | ";
				}
			}

			//playoff pool
			if($info['type']==2){
				if (CanGenerateGames($info['pool_id'])) {	
					$html .= "<b><a href='?view=admin/poolgames&amp;Season=".$info['season']."&amp;Series=".$info['series']."&amp;Pool=".$info['pool_id']."'>"._("Playoff games")."</a></b>";
				}else{
					$html .= "<a href='?view=admin/poolgames&amp;Season=".$info['season']."&amp;Series=".$info['series']."&amp;Pool=".$info['pool_id']."'>"._("Playoff games")."</a>";
					$cangenerateallgames = false;
				}
			}else{
				if (CanGenerateGames($info['pool_id'])) {	
					$html .= "<b><a href='?view=admin/poolgames&amp;Season=$season&amp;Pool=".$info['pool_id']."'>"._("Game management")."</a></b>";
				}else{
					$html .= "<a href='?view=admin/poolgames&amp;Season=$season&amp;Pool=".$info['pool_id']."'>"._("Game management")."</a>";
					$cangenerateallgames = false;
				}
			}
			$html .= "</td>";
			if (CanDeletePool($info['pool_id'])) {
				$html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId(".$info['pool_id'].");\"/></td>";
			}
			
			$html .= "</tr>\n";	
		}
		
		if($movestotal || $cangenerateallgames){
			$html .= "<tr><td class='right' colspan='7'>";
			if($movestotal){
				$html .= "<a href='?view=admin/seasonmoves&amp;Series=".$row['series_id']."'>"._("Show all moves")."</a> ";
			}
			if($cangenerateallgames){
				$html .= "| <a href='?view=admin/seriesgames&amp;Season=$season&amp;Series=".$row['series_id']."'>"._("Generate all games")."</a>";
			}
			$html .= "</td></tr>";	
		}
		$html .= "</table>\n";
		
	}else{
		$html .= "<p>"._("No pools").".</p>\n";
	}
	$html .= "<p><input class='button' name='add' type='button' value='"._("Add")."' 
		onclick=\"window.location.href='?view=admin/addseasonpools&amp;Season=$season&amp;Series=".$row['series_id']."'\"/></p>";
	$html .= "<hr/>";
}

//stores id to delete
$html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
$html .= "</form>\n";

echo $html;

contentEnd();
pageEnd();
?>