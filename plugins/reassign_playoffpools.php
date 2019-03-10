<?php
ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=updater
format=any
security=superadmin
customization=all

[DESCRIPTION]
title = "Reassign play-off pools"
description = "Due to BYEs in Swissdraw, it might be necessary to assign the play-off pools differently. This tool helps doing that while keeping scheduled games alive."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()){die('Insufficient user rights');}
	
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/standings.functions.php';

$html = "";
$title = ("Reassign pla-yoff pools");
$seriesId = "";

	
if (!empty($_POST['series'])) {
	
	// get all playoff pools from this series
	$seriesId = $_POST['series'];
	$pools=SeriesPools($seriesId);

	$moveablepools=array();
	
	foreach ($pools as $pool)
	{
		$poolId=$pool['pool_id'];
		$poolinfo=PoolInfo($poolId);
//		if(PoolIsMoveFromPoolsPlayed($poolId) && !PoolIsAllMoved($poolId)) {
		if(!PoolIsAllMoved($poolId)) {
			$moveablepools[]=$pool; 
			$html .= "<h2>".utf8entities($pool['name'])."</h2>\n";				
			
			$html .= "<table border='1' width='600px'><tr>
				<th>"._("From pool")."</th>
				<th>"._("From pos.")."</th>
				<th>"._("Team")."</th>
				<th>"._("To pos.")."</th>
				<th>"._("To pool")."</th>
				<th>"._("Name in Schedule")."</th></tr>\n";
		
			$moves = PoolMovingsToPool($poolId);	
			foreach($moves as $row){
				$html .= "<tr>\n";
				$html .= "<td style='white-space: nowrap'>".utf8entities($row['name'])."</td>\n";
				if(!$playoffpool){
					$frompool = PoolInfo($row['frompool']);
					if($frompool['type']==2){
						$playoffpool=true;
					}
				}
		//		echo "fetching ".$row['frompool']." ".$row['fromplacing']."</p>";
				$team = PoolTeamFromStandings($row['frompool'],$row['fromplacing'],$poolinfo['type']!=2);  // do not count the BYE team if we are moving to a playoff pool
				$html .= "<td class='center'>".intval($row['fromplacing'])."</td>\n";
				if(TeamPoolCountBYEs($team['team_id'],$row['frompool'])>0){
					$html .= "<td class='highlight'><b>".utf8entities($team['name'])."</b></td>\n";
					$BYEs=true;
				}else{
					$html .= "<td class='highlight'>".utf8entities($team['name'])."</td>\n";
				}
				$html .= "<td class='center'>".intval($row['torank'])."</td>\n";
				$html .= "<td style='white-space: nowrap'>".utf8entities(PoolName($poolId))."</td>\n";
				$html .= "<td>".utf8entities($row['pteamname'])."</td>\n";
				$html .= "</tr>\n";
				}
			$html .= "</table>\n";
	
			if ($BYEs) {
				$html .= "<p>teams in <b>bold</b> had a BYE previously</p>\n";
			}
			
			if ($PlayoffOK==-1) {
				$html .= "<p><b>Warning:</b> You are about to move an odd number of teams, which might result in one of the teams having another BYE.</p>\n";
			}
					
			$html .= "<p>"._("Games to move").":</p>\n";
			$mvgames = intval($poolinfo['mvgames']);
			$games = PoolGetGamesToMove($poolId, $mvgames);	
			if(count($games)>0) {die('This tool is not designed to work if games are moved as well'); }
			else {
				$html .= "<p><i>"._("No games to move").".</i></p>\n";
			}
		}
	}


	
	//pool selection
	$html .= "<form method='post' id='tables' action='?view=plugins/reassign_playoffpools'>\n";
	
	$html .= "<p>".("Select first pool to swap").": <select class='dropdown' name='swap1'>\n";	
	foreach($moveablepools as $pool){
		debugvar($pool);
		$html .= "<option class='dropdown' value='".utf8entities($pool['pool_id'])."'>". utf8entities($pool['name']) . "</option>\n";
	}			
	$html .= "</select>\n";
	
	$html .= "<p>".("Select neighboring pool to swap with").": <select class='dropdown' name='swap2'>\n";	
	foreach($moveablepools as $pool){
		debugvar($pool);
		$html .= "<option class='dropdown' value='".utf8entities($pool['pool_id'])."'>". utf8entities($pool['name']) . "</option>\n";
	}			
	$html .= "</select></p>\n";
	
	$html .= "<p><input class='button' type='submit' name='swap' value='".("Swap Assignment of Play-off Pools")."'/>\n";
	$html .= "<input class='button' type='button' name='back'  value='"._("Return")."' onclick=\"window.location.href='?view=plugins/reassign_playoffpools'\"/></p>";
	
	$html .= "</form>";					
			
		
} elseif(!empty($_POST['swap1']) && !empty($_POST['swap2'])){
	$poolId1=$_POST['swap1'];
	$poolId2=$_POST['swap2'];	
	if ($poolId1==$poolId2) {die('select different pools');}
	
	// check if second pool moves follows the moves of the first pool
	$moves1 = PoolMovingsToPool($poolId1);	
	$moves2 = PoolMovingsToPool($poolId2);

	sort($moves1);
	sort($moves2);
//	debugVar($moves1);
//	debugVar($moves2);

	$placingoffset1=$moves1[0]['fromplacing'];
	for($i=0;$i<(count($moves1)-1);$i++) {
		if($moves1[$i]['frompool']!=$moves1[$i+1]['frompool'] || $moves1[$i]['topool']!=$moves1[$i+1]['topool'] ||
		   $moves1[$i]['fromplacing']!=$i+$placingoffset1) {
		   die('move structure of first pool not applicable for swap');
		}
	}
    if ($moves1[count($moves1)-1]['fromplacing']+1 != $moves2[0]['fromplacing'] || 
        $moves1[count($moves1)-1]['frompool']!=$moves2[0]['frompool']) {
    	die('pools not neighboring');
    }
	$placingoffset2=$moves2[0]['fromplacing'];
    for($i=0;$i<(count($moves2)-1);$i++) {
		if($moves2[$i]['frompool']!=$moves2[$i+1]['frompool'] || $moves2[$i]['topool']!=$moves2[$i+1]['topool'] ||
		   $moves2[$i]['fromplacing'] != $i+$placingoffset2) {
			echo $i;
		   die('move structure of second pool not applicable for swap');
		}
	}

	// set up new moves
	$newmove1=$moves1;
	$newmove2=$moves2;
	
	$frompool1=$moves1[0]['frompool'];
	$frompool2=$moves2[0]['frompool'];
	
	$newoffset2=$placingoffset1+count($moves2);
//	echo $newoffset2;
	
	for($i=0;$i<(count($moves1));$i++) {
		$newmove1[$i]['fromplacing']=($newoffset2+$i);				
		$newmove1[$i]['frompool']=$frompool2;
		$newmove1[$i]['pteamname']=rtrim($newmove1[$i]['pteamname'],"0..9").($newoffset2+$i);		
	}
	for($i=0;$i<(count($moves2));$i++) {				
		$newmove2[$i]['fromplacing']=($placingoffset1+$i);				
		$newmove2[$i]['frompool']=$frompool1;
		$newmove2[$i]['pteamname']=rtrim($newmove2[$i]['pteamname'],"0..9").($placingoffset1+$i);		
	}

	debugvar($newmove1);
	debugvar($newmove2);
		
//	// sort them according to primary key of the database: frompool, fromplacing
//	usort($newmove1, create_function('$a,$b','return $a[\'fromplacing\']==$b[\'fromplacing\']?0:($a[\'fromplacing\']<$b[\'fromplacing\']?-1:1);'));
//	usort($newmove2, create_function('$a,$b','return $a[\'fromplacing\']==$b[\'fromplacing\']?0:($a[\'fromplacing\']<$b[\'fromplacing\']?-1:1);'));
//	debugvar($newmove1);
//	debugvar($newmove2);
//	die;
	
	// actually do them
	for($i=0;$i<(count($newmove1));$i++) {	
		$query=sprintf("UPDATE uo_moveteams SET topool='%s', torank='%s', scheduling_id='%s' 
						WHERE frompool='%s' AND fromplacing='%s'",
			mysql_real_escape_string($newmove1[$i]['topool']),
			mysql_real_escape_string($newmove1[$i]['torank']),
			mysql_real_escape_string($newmove1[$i]['scheduling_id']),
			mysql_real_escape_string($newmove1[$i]['frompool']),
			mysql_real_escape_string($newmove1[$i]['fromplacing'])	 );
			
//		echo $query."<br>";
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error() . $query); }
				
		$query=sprintf("UPDATE uo_scheduling_name SET name='%s' 
						WHERE scheduling_id='%s'",
			mysql_real_escape_string($newmove1[$i]['pteamname']),
			mysql_real_escape_string($newmove1[$i]['scheduling_id']));			
//		echo $query."<br>";
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error() . $query); }				
	}
	for($i=0;$i<(count($newmove2));$i++) {				
		$query=sprintf("UPDATE uo_moveteams SET topool='%s', torank='%s', scheduling_id='%s' 
						WHERE frompool='%s' AND fromplacing='%s'",
			mysql_real_escape_string($newmove2[$i]['topool']),
			mysql_real_escape_string($newmove2[$i]['torank']),
			mysql_real_escape_string($newmove2[$i]['scheduling_id']),
			mysql_real_escape_string($newmove2[$i]['frompool']),
			mysql_real_escape_string($newmove2[$i]['fromplacing'])	 );
		//		echo $query."<br>";
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		
		$query=sprintf("UPDATE uo_scheduling_name SET name='%s' 
						WHERE scheduling_id='%s'",
			mysql_real_escape_string($newmove2[$i]['pteamname']),
			mysql_real_escape_string($newmove2[$i]['scheduling_id']));
//		echo $query."<br>";
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
	}
	
	
	$html = "<p>Playoff pool assignments successfully switched!</p>";
		
	
	
}
else{
	
	//series selection
	$html .= "<form method='post' id='tables' action='?view=plugins/reassign_playoffpools'>\n";
	
	$html .= "<p>".("Select division").": <select class='dropdown' name='series'>\n";
	
	$series = Series();
			
	while($row = mysql_fetch_assoc($series)){
		$html .= "<option class='dropdown' value='".utf8entities($row['series_id'])."'>". utf8entities($row['seasonname']) . " " . utf8entities($row['name']) ."</option>";
	}
	
	$html .= "</select></p>\n";
	$html .= "<p><input class='button' type='submit' name='show' value='".("Show play-off pools")."'/></p>";
	
	$html .= "</form>";
}

showPage($title, $html);
?>
