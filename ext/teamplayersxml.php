<?php

include_once 'localization.php';
include_once '../lib/team.functions.php';

header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");
	
$result = GetTeamPlayers();
	
// for php 5 onwards
if (version_compare(PHP_VERSION, '5.0.0', '>')) {
	$dom = new DOMDocument("1.0");
	$node = $dom->createElement("PlayerSet");
	$parnode = $dom->appendChild($node);

	// Iterate through the rows, adding XML nodes for each
	while ($row = mysqli_fetch_assoc($result)){
	  $node = $dom->createElement("Player");
	  $newNode = $parnode->appendChild($node);
	  
	  $nextNode = $dom->createElement("playerId"); 
	  $nextNode = $newNode->appendChild($nextNode);
	  $nextText = $dom->createTextNode($row['player_id']);
	  $nextText = $nextNode->appendChild($nextText);
	  
	  $nextNode = $dom->createElement("accrId"); 
	  $nextNode = $newNode->appendChild($nextNode);
	  $nextText = $dom->createTextNode($row['accreditation_id']);
	  $nextText = $nextNode->appendChild($nextText);
	  
	  $nextNode = $dom->createElement("Firstname"); 
	  $nextNode = $newNode->appendChild($nextNode);
	  $nextText = $dom->createTextNode($row['firstname']);
	  $nextText = $nextNode->appendChild($nextText);
	  
	  $nextNode = $dom->createElement("Lastname"); 
	  $nextNode = $newNode->appendChild($nextNode);
	  $nextText = $dom->createTextNode($row['lastname']);
	  $nextText = $nextNode->appendChild($nextText);
	  
	  $nextNode = $dom->createElement("Number"); 
	  $nextNode = $newNode->appendChild($nextNode);
	  $nextText = $dom->createTextNode($row['num']);
	  $nextText = $nextNode->appendChild($nextText);
	  
	  $nextNode = $dom->createElement("Accredited"); 
	  $nextNode = $newNode->appendChild($nextNode);
	  $nextText = $dom->createTextNode($row['accredited']);
	  $nextText = $nextNode->appendChild($nextText);
	  
	}

	echo $dom->saveXML();
//PHP4 with domxml and iconv extensions
}elseif(extension_loaded("domxml") && extension_loaded("iconv")) {
	$dom = domxml_new_doc("1.0");
	$node = $dom->create_element("PlayerSet");
	$parnode = $dom->append_child($node);

	// Iterate through the rows, adding XML nodes for each
	while ($row = mysqli_fetch_assoc($result)){
	  $node = $dom->create_element("Player");
	  $newNode = $parnode->append_child($node);
	  
	  $nextNode = $dom->create_element("playerId"); 
	  $nextNode = $newNode->append_child($nextNode);
	  $nextText = $dom->create_text_node($row['player_id']);
	  $nextText = $nextNode->append_child($nextText);
	  
	  $nextNode = $dom->create_element("accrId"); 
	  $nextNode = $newNode->append_child($nextNode);
	  $nextText = $dom->create_text_node($row['accreditation_id']);
	  $nextText = $nextNode->append_child($nextText);
	  
	  $nextNode = $dom->create_element("Firstname"); 
	  $nextNode = $newNode->append_child($nextNode);
	  $nextText = $dom->create_text_node($row['firstname']);
	  $nextText = $nextNode->append_child($nextText);
	  
	  $nextNode = $dom->create_element("Lastname"); 
	  $nextNode = $newNode->append_child($nextNode);
	  $nextText = $dom->create_text_node($row['lastname']);
	  $nextText = $nextNode->append_child($nextText);
	  
	  $nextNode = $dom->create_element("Number"); 
	  $nextNode = $newNode->append_child($nextNode);
	  $nextText = $dom->create_text_node($row['num']);
	  $nextText = $nextNode->append_child($nextText);
	  
	  $nextNode = $dom->create_element("Accredited"); 
	  $nextNode = $newNode->append_child($nextNode);
	  $nextText = $dom->create_text_node($row['accredited']);
	  $nextText = $nextNode->append_child($nextText);
	}
	
	echo $dom->dump_mem(true);
//hardcoded xml for those who can't install extensions	
}else{
	echo "<?xml version=\"1.0\"?>\n";
	echo "<PlayerSet>\n";

	// Iterate through the rows, adding XML nodes for each
	while ($row = mysqli_fetch_assoc($result)){
		echo "<Player>\n";
		echo "<playerId>". $row['player_id'] ."</playerId>\n";
		echo "<accrId>". $row['accreditation_id'] ."</accrId>\n";
		echo "<Firstname>". $row['firstname'] ."</Firstname>\n";
		echo "<Lastname>". $row['lastname'] ."</Lastname>\n";
		echo "<Number>". $row['num'] ."</Number>\n";
		echo "<Accredited>". $row['accredited'] ."</Accredited>\n";
		echo "</Player>\n";
	}
	echo "</PlayerSet>\n";
}
CloseConnection();
?>
