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
title = "Reschedule"
description = "Hard coded re-scheduling script to move all not played games to next day."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()){die('Insufficient user rights');}
	
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/standings.functions.php';

$html = "";
$title = ("Reschedule");
$seasonId = "";

if(!empty($_POST['season'])){
	$seasonId = $_POST['season'];
}

if (isset($_POST['update'])) {

	//DBQuery("UPDATE uo_reservation SET starttime='2010-07-07 08:30:00', endtime='2010-07-07 22:30:00' WHERE reservationgroup='Day 4'");
	
	//re-schedule Day 4 games:
	$games = DBQueryToArray("SELECT g.*, r.* FROM uo_game g 
		LEFT JOIN uo_reservation r ON(g.reservation=r.id)
		WHERE r.reservationgroup='Day 4'
		ORDER BY r.fieldname+0,g.time");
	foreach($games as $game){
		if(Hours($game['time'])==8){
			DBQuery("UPDATE uo_game SET time='2010-07-07 12:15:00', timeslot=105 WHERE game_id=".$game['game_id']."");
		}elseif(Hours($game['time'])==10){
			DBQuery("UPDATE uo_game SET time='2010-07-07 14:00:00', timeslot=105 WHERE game_id=".$game['game_id']."");		
		}elseif(Hours($game['time'])==13){
			DBQuery("UPDATE uo_game SET time='2010-07-07 15:45:00', timeslot=105 WHERE game_id=".$game['game_id']."");
		}elseif(Hours($game['time'])==15){
			DBQuery("UPDATE uo_game SET time='2010-07-07 17:30:00', timeslot=105 WHERE game_id=".$game['game_id']."");
		}
	}
	
	//re-schedule Day 3 games
	$games = DBQueryToArray("SELECT g.*, r.* FROM uo_game g 
		LEFT JOIN uo_reservation r ON(g.reservation=r.id)
		WHERE r.reservationgroup='Day 3' AND (TIME_FORMAT(time,'%H')='13' OR TIME_FORMAT(time,'%H')='15')
		ORDER BY r.fieldname+0,time");
	
	foreach($games as $game){

		if(Hours($game['time'])==13){
			$timestring="2010-07-07 08:30:00";
			$timeslot = 120;
		}elseif(Hours($game['time'])==15){
			$timestring="2010-07-07 10:30:00";
			$timeslot = 105;
		}else{
		die;
		}
		
		$newresid = DBQueryToValue("SELECT r.id FROM uo_reservation r
						WHERE r.reservationgroup='Day 4' AND r.fieldname='".$game['fieldname']."'");
		
		DBQuery("UPDATE uo_game SET time='$timestring', timeslot=$timeslot,
		reservation=$newresid WHERE game_id=".$game['game_id']."");
		
	}
}

//season selection
$html .= "<form method='post' id='tables' action='?view=plugins/reschedule'>\n";


$html .= "<p>".("Select event").": <select class='dropdown' name='season'>\n";

$seasons = Seasons();
		
while($row = mysqli_fetch_assoc($seasons)){
	$html .= "<option class='dropdown' value='".utf8entities($row['season_id'])."'>". utf8entities($row['name']) ."</option>";
}

$html .= "</select></p>\n";
$html .= "<p><input class='button' type='submit' name='update' value='".("Update")."'/></p>";

$html .= "</form>";

showPage($title, $html);
?>
