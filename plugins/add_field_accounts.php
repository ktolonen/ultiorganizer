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
title = "Create field responsible users"
description = "Automatically creates field responsibility users and add responsible games per field for them,."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()){die('Insufficient user rights');}
	
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/standings.functions.php';
include_once 'lib/reservation.functions.php';

$html = "";
$title = ("Field users");
$season = "";


if(!empty($_POST['create'])){
	$season = $_POST['season'];
	$maxfields = 0;
	$fields = ReservationFields($season);
	while($field = mysqli_fetch_assoc($fields)){
		if(is_numeric($field['fieldname'])){
			$name = "field".intval($field['fieldname']);
		}else{
			$name = $field['fieldname'];
		}
		$user = DBQueryToValue("SELECT COUNT(*) FROM uo_users WHERE userid='$name'");
		if($user<1){
			DBQuery("INSERT INTO uo_users(name, userid, password, email) VALUES ('$name', '$name', MD5('$name'), '')");
			DBQuery("INSERT INTO uo_userproperties(userid, name, value) VALUES ('$name', 'poolselector', 'currentseason')");
			DBQuery("INSERT INTO uo_userproperties(userid, name, value) VALUES ('$name', 'editseason', '$season')");
		}
		
		$games = ReservationGamesByField($field['fieldname'], $season);
		while($game = mysqli_fetch_assoc($games)){
			$exist = DBQueryToValue("SELECT COUNT(*) FROM uo_userproperties WHERE userid='$name' AND value='gameadmin:".$game['game_id']."'");
			if($user<1){
				DBQuery("INSERT INTO uo_userproperties(userid, name, value) VALUES ('$name', 'userrole', 'gameadmin:".$game['game_id']."')");
			}
		}
	}
}

//season selection
$html .= "<form method='post' id='tables' action='?view=plugins/add_field_accounts'>\n";

$html .= "<p>".("Create field specific user accounts on select event").": <select class='dropdown' name='season'>\n";

$seasons = Seasons();
		
while($row = mysqli_fetch_assoc($seasons)){
	$html .= "<option class='dropdown' value='".utf8entities($row['season_id'])."'>". utf8entities($row['name']) ."</option>";
}

$html .= "</select></p>\n";
$html .= "<p><input class='button' type='submit' name='create' value='".("Create")."'/></p>";

$html .= "</form>";

showPage($title, $html);
?>
