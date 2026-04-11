<?php
include_once '../../lib/database.php';
include_once '../../lib/common.functions.php';
include_once '../../lib/player.functions.php';
include_once '../../lib/user.functions.php';

$firstname = isset($_GET['firstname']) ? normalizeTextInput($_GET['firstname']) : '';
$lastname = isset($_GET['lastname']) ? normalizeTextInput($_GET['lastname']) : '';
$teamId = isset($_GET['team']) ? normalizeTextInput($_GET['team']) : '';
header("Content-type: text/xml; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");
startSecureSession();
OpenConnection();

if (hasEditPlayersRight($teamId)) {
	
	$players = SearchPlayerProfiles($firstname, $lastname, true);

	$dom = new DOMDocument("1.0");
	$node = $dom->createElement("MemberSet");
	$parnode = $dom->appendChild($node);

	foreach ($players as $row) {
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
}

CloseConnection();
?>
