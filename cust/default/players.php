<?php
include_once '../../lib/database.php';
include_once '../../lib/common.functions.php';
include_once '../../lib/user.functions.php';

if (isset($_GET['firstname'])) {
	$firstname = utf8_encode(trim(urldecode($_GET['firstname'])));
} else {
	$firstname = '';
}
if (isset($_GET['lastname'])) {
	$lastname = utf8_encode(trim(urldecode($_GET['lastname'])));
} else {
	$lastname = '';
}

if (isset($_GET['team'])) {
	$teamId = utf8_encode(trim(urldecode($_GET['team'])));
} else {
	$teamId = '';
}
header("Content-type: text/xml; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");
session_name("UO_SESSID");
session_start();
OpenConnection();

if (hasEditPlayersRight($teamId)) {
	
	$query = sprintf("SELECT pp.profile_id, pp.accreditation_id, pp.firstname, pp.lastname, pp.birthdate, pp.gender, pp.email,
			    pp.num, p2.teamname, p2.seasoname
			FROM uo_player_profile pp 
			LEFT JOIN(SELECT p.profile_id, p.firstname, p.lastname,
			    p.num, t.name AS teamname, sea.name AS seasoname FROM uo_player p
			    LEFT JOIN uo_team t ON (p.team=t.team_id)
    			LEFT JOIN uo_series ser ON (ser.series_id=t.series)
			    LEFT JOIN uo_season sea ON (ser.season=sea.season_id)
				ORDER BY p.player_id DESC) AS p2 ON (pp.profile_id=p2.profile_id)
			LEFT JOIN uo_player AS p1 ON (p1.profile_id=pp.profile_id)
			WHERE pp.firstname like '%%%s%%' and pp.lastname like '%%%s%%'
			GROUP BY pp.profile_id ORDER BY pp.lastname, pp.firstname",
						mysql_real_escape_string($firstname), mysql_real_escape_string($lastname));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }

	// for php 5 onwards
	if (version_compare(PHP_VERSION, '5.0.0', '>')) {
		$dom = new DOMDocument("1.0");
		$node = $dom->createElement("MemberSet");
		$parnode = $dom->appendChild($node);

		while ($row = mysql_fetch_assoc($result)) {
		  $node = $dom->createElement("Member");
		  $newNode = $parnode->appendChild($node);
		  
		  $nextNode = $dom->createElement("AccreditationId"); 
		  $nextNode = $newNode->appendChild($nextNode);
		  $nextText = $dom->createTextNode($row['accreditation_id']);
		  $nextText = $nextNode->appendChild($nextText);
		  
		  $nextNode = $dom->createElement("ProfileId"); 
		  $nextNode = $newNode->appendChild($nextNode);
		  $nextText = $dom->createTextNode($row['profile_id']);
		  $nextText = $nextNode->appendChild($nextText);
			
		  $nextNode = $dom->createElement("Firstname"); 
		  $nextNode = $newNode->appendChild($nextNode);
		  $nextText = $dom->createTextNode($row['firstname']);
		  $nextText = $nextNode->appendChild($nextText);

		  $nextNode = $dom->createElement("Lastname"); 
		  $nextNode = $newNode->appendChild($nextNode);
		  $nextText = $dom->createTextNode($row['lastname']);
		  $nextText = $nextNode->appendChild($nextText);

		  $nextNode = $dom->createElement("BirthDate"); 
		  $nextNode = $newNode->appendChild($nextNode);
		  $nextText = $dom->createTextNode(DefBirthdayFormat($row['birthdate']));
		  $nextText = $nextNode->appendChild($nextText);
		  
		  $nextNode = $dom->createElement("Team"); 
		  $nextNode = $newNode->appendChild($nextNode);
		  $nextText = $dom->createTextNode($row['teamname']);
		  $nextText = $nextNode->appendChild($nextText);

		  $nextNode = $dom->createElement("Event"); 
		  $nextNode = $newNode->appendChild($nextNode);
		  $nextText = $dom->createTextNode($row['seasoname']);
		  $nextText = $nextNode->appendChild($nextText);

		  $nextNode = $dom->createElement("Gender"); 
		  $nextNode = $newNode->appendChild($nextNode);
		  $nextText = $dom->createTextNode($row['gender']);
		  $nextText = $nextNode->appendChild($nextText);

		  $nextNode = $dom->createElement("Email"); 
		  $nextNode = $newNode->appendChild($nextNode);
		  $nextText = $dom->createTextNode($row['email']);
		  $nextText = $nextNode->appendChild($nextText);	  
			
		  $nextNode = $dom->createElement("Jersey"); 
		  $nextNode = $newNode->appendChild($nextNode);
		  if($row['num']<0){
		    $nextText = $dom->createTextNode("");
		  }else{
		    $nextText = $dom->createTextNode($row['num']);
		  }
		  $nextText = $nextNode->appendChild($nextText);	
		}
		echo $dom->saveXML();
	}else{
		echo "<?xml version=\"1.0\"?>\n";
		echo "<MemberSet>\n";

		// Iterate through the rows, adding XML nodes for each
		while ($row = mysql_fetch_assoc($result)){
			echo "<Member>\n";
			echo "<AccreditationId>". $row['accreditation_id'] ."</AccreditationId>\n";
			echo "<ProfileId>". $row['profile_id'] ."</ProfileId>\n";
			echo "<Firstname>". $row['firstname'] ."</Firstname>\n";
			echo "<Lastname>". $row['lastname'] ."</Lastname>\n";
			echo "<BirthDate>". DefBirthdayFormat($row['birthdate']) ."</BirthDate>\n";
			echo "<Team>". $row['teamname'] ."</Team>\n";		
			echo "<Event>". $row['seasoname'] ."</Event>\n";
			echo "<Gender>". $row['gender'] ."</Gender>\n";
			echo "<Email>". $row['email'] ."</Email>\n";
			if($row['num']<0){
			  echo "<Jersey></Jersey>\n";
			}else{
			  echo "<Jersey>". $row['num'] ."</Jersey>\n";
			}
			echo "</Member>\n";
		}
		echo "</MemberSet>\n";
	}
}

CloseConnection();
?>