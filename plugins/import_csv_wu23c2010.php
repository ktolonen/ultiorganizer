<?php
ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=import
format=csv
security=superadmin
customization=WFDF

[DESCRIPTION]
title = "Import WU23C2010 data from CSV file"
description = "CSV file format: division, country, name, surname, shirt_no, d_birth, gender, role."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()){die('Insufficient user rights');}
	
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
if (is_file('cust/'.CUSTOMIZATIONS.'/teamplayers.functions.php')) {
	include_once 'cust/'.CUSTOMIZATIONS.'/teamplayers.functions.php';
}

$html = "";
$title = ("Import WU23C2010 data from CSV file");
$seasonId = "";

if (isset($_POST['import'])) {

	$utf8 = !empty($_POST['utf8']);
	$seasonId = $_POST['season'];
	$separator = $_POST['separator'];
	
	

	if(is_uploaded_file($_FILES['file']['tmp_name'])) {
    	$row = 1;
		if (($handle = fopen($_FILES['file']['tmp_name'], "r")) !== FALSE) {
			while (($data = fgetcsv($handle, 0, $separator)) !== FALSE) {
				//$team = $utf8 ? trim($data[0]) : utf8_encode(trim($data[0]));
				$series = $utf8 ? trim($data[0]) : utf8_encode(trim($data[0]));
				$country = $utf8 ? trim($data[1]) : utf8_encode(trim($data[1]));
				$last = $utf8 ? trim($data[2]) : utf8_encode(trim($data[2]));
				$first = $utf8 ? trim($data[3]) : utf8_encode(trim($data[3]));
				$jersey = $utf8 ? trim($data[4]) : utf8_encode(trim($data[4]));
				$birthdate = $utf8 ? trim($data[5]) : utf8_encode(trim($data[5]));
				$gender = $utf8 ? trim($data[6]) : utf8_encode(trim($data[6]));
				$role = $utf8 ? trim($data[7]) : utf8_encode(trim($data[7]));
				
				if($role=="guest"||$role=="guest2"){
					continue;
				}
				if($country=="USA"){$country="United States";}
				if($country=="Italia"){$country="Italy";}
				if($country=="United Kingdom"||$country=="UK"||$country=="England"||$country=="GB"){$country="Great Britain";}
				
				if($series=="coed"){$series="mixed";}
				
				if($jersey==""){$jersey=-1;}
				
				$team = $country;
					
				set_time_limit(300); //takes time to loop and insert data
				$seriesId = -1;
				$teamId = -1;
				$playerId= -1;
				$allseries = SeasonSeries($seasonId);
				foreach($allseries as $ser){
					if($ser['name']==$series){
						$seriesId=$ser['series_id'];
						break;
					}
				}
				if($seriesId==-1){
					$sp = array(
						"series_id"=>"",
						"name"=>$series,
						"type"=>"",
						"ordering"=>"",
						"season"=>$seasonId,
						"valid"=>"1");
					$seriesId = AddSeries($sp);
				}
				
				$teams = SeriesTeams($seriesId);
				foreach($teams as $t){
					if($t['name']==$team){
						$teamId = $t['team_id'];
						break;
					}
				}
				if($teamId==-1){
					$id = AddSeriesEnrolledTeam($seriesId, $_SESSION['uid'], $team, "", $country);
					$teamId = ConfirmEnrolledTeam($seriesId, $id);
				}

				if($role=="player" || $role=="captain"){
					$players = TeamPlayerList($teamId);
					while($player = mysql_fetch_assoc($players)){
						//echo $player['firstname']."==$first && ".$player['lastname']."==$last &&". $player['num']."==$jersey";
						if($player['firstname']==$first && $player['lastname']==$last && intval($player['num'])==intval($jersey)){
							$playerId=$player['player_id'];
							break;
						}
					}
					
					if($playerId==-1){
						$playerId = AddPlayer($teamId,$first,$last,"",$jersey,"");
						$query = sprintf("SELECT p1.accreditation_id, p2.firstname, p2.lastname, pp.birthdate, pp.gender, p2.email,
								p2.num, p2.teamname, p2.seasoname
								FROM uo_player p1
								LEFT JOIN(SELECT p.accreditation_id, p.firstname, p.lastname, p.email,
								p.num, t.name AS teamname, sea.name AS seasoname FROM uo_player p
								LEFT JOIN uo_team t ON (p.team=t.team_id)
								LEFT JOIN uo_series ser ON (ser.series_id=t.series)
								LEFT JOIN uo_season sea ON (ser.season=sea.season_id)
								ORDER BY p.player_id DESC) AS p2 ON (p1.accreditation_id=p2.accreditation_id)
								LEFT JOIN uo_player_profile AS pp ON (p1.accreditation_id=pp.accreditation_id)
								WHERE p1.accreditation_id > 0 AND UPPER(p1.firstname) like '%%%s%%' and UPPER(p1.lastname) like '%%%s%%'
								GROUP BY p1.accreditation_id",
								mysql_real_escape_string(strtoupper($first)), mysql_real_escape_string(strtoupper($last)));
						$players = DBQueryToArray($query);
						if(count($players)==0){
							SetPlayer($playerId, $jersey, $first, $last, $playerId);
							$pp = array(
								"accreditation_id"=>$playerId,
								"num"=>$jersey,
								"firstname"=>$first,
								"lastname"=>$last,
								"nickname"=>"",
								"gender"=>$gender,
								"email"=>"",
								"national_id"=>"",
								"info"=>"",
								"birthdate"=>ToInternalTimeFormat($birthdate),
								"birthplace"=>"",
								"nationality"=>"",
								"throwing_hand"=>"",
								"height"=>"",
								"weight"=>"",
								"position"=>"",
								"story"=>"",
								"achievements"=>"",
								"profile_image"=>"",
								"public"=>""
								);
							SetPlayerProfile($teamId,$playerId, $pp);
							AccreditPlayer($playerId, "dataimporter");
						}elseif(count($players)==1){
							SetPlayer($playerId, $jersey, $first, $last, $players[0]['accreditation_id']);
							AccreditPlayer($playerId, "dataimporter");
						}else{
							//foreach($players as $p){
							echo "<p>Check manual:".$first." ".$last."</p>";
							//}
							//die("too many matching players");
						}
						
					}
				}
				
				if($role=="captain" || $role=="np_captain"){
					$teamprofile = TeamProfile($teamId);
					$captain = $teamprofile['captain'];
					if(!empty($captain)){
						if(strpos($captain,$first ." ".$last)!== false){
							continue;
						}						
						$captain .= ", ";
					}
					
					$captain .= $first ." ".$last;
					
					//team profile
					$tp = array(
						"team_id"=>$teamId,
						"profile_image"=>$teamprofile['profile_image'],
						"captain"=>$captain,
						"coach"=>$teamprofile['coach'],
						"story"=>$teamprofile['story'],
						"achievements"=>$teamprofile['achievements']
						);
					SetTeamProfile($tp);
				}
				if($role=="coach" || $role=="np_coach"){
					$teamprofile = TeamProfile($teamId);
					$coach = $teamprofile['coach'];
					if(!empty($coach)){
						if(strpos($coach,$first ." ".$last) !== false){
							continue;
						}
						$coach .= ", ";
					}
					
					$coach .= $first ." ".$last;
					//echo "<p>$coach</p>";
					//team profile
					$tp = array(
						"team_id"=>$teamId,
						"profile_image"=>$teamprofile['profile_image'],
						"captain"=>$teamprofile['captain'],
						"coach"=>$coach,
						"story"=>$teamprofile['story'],
						"achievements"=>$teamprofile['achievements']
						);
					SetTeamProfile($tp);
				}
			}
			fclose($handle);
			$html .= "<p>". ("Data imported!"). "</p>";
		}
	}else{
		$html .= "<p>". ("There was an error uploading the file, please try again!"). "</p>";
	
	}
}

//season selection
$html .= "<form method='post' enctype='multipart/form-data' action='?view=plugins/import_csv_wu23c2010'>\n";

$html .= "<p>".("Select event").": <select class='dropdown' name='season'>\n";

$seasons = Seasons();
		
while($row = mysql_fetch_assoc($seasons)){
	$html .= "<option class='dropdown' value='". $row['season_id'] . "'>". utf8entities($row['name']) ."</option>";
}

$html .= "</select></p>\n";

$html .= "<p>".("CSV separator").": <input class='input' maxlength='1' size='1' name='separator' value=','/></p>\n";

$html .= "<p>".("Select file to import").":<br/>\n";
$html .= "<input class='input' type='file' size='100' name='file'/><br/>\n";
$html .= "<input class='input' type='checkbox' name='utf8' /> ".("File in UTF-8 format")."</p>";
$html .= "<p><input class='button' type='submit' name='import' value='".("Import")."'/></p>";
$html .= "<div>";
$html .= "<input type='hidden' name='MAX_FILE_SIZE' value='50000000' />\n";
$html .= "</div>\n";
$html .= "</form>";

showPage(0, $title, $html);
?>
