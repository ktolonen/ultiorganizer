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
	
$query = sprintf("SELECT accreditation_id, firstname, lastname, membership, license, birthdate FROM uo_license WHERE firstname like '%%%s%%' and lastname like '%%%s%%'",
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
	  
	  $nextNode = $dom->createElement("memberId"); 
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

	  $nextNode = $dom->createElement("MembershipYear"); 
	  $nextNode = $newNode->appendChild($nextNode);
	  $nextText = $dom->createTextNode($row['membership']);
	  $nextText = $nextNode->appendChild($nextText);

	  $nextNode = $dom->createElement("LicenseYear"); 
	  $nextNode = $newNode->appendChild($nextNode);
	  $nextText = $dom->createTextNode($row['license']);
	  $nextText = $nextNode->appendChild($nextText);
	  
	  $nextNode = $dom->createElement("BirthDate"); 
	  $nextNode = $newNode->appendChild($nextNode);
	  $nextText = $dom->createTextNode(DefBirthdayFormat($row['birthdate']));
	  $nextText = $nextNode->appendChild($nextText);
	  
	}
	echo $dom->saveXML();
//PHP4 with domxml and iconv extensions
}elseif(extension_loaded("domxml") && extension_loaded("iconv")) {
	$dom = domxml_new_doc("1.0");
	$node = $dom->create_element("MemberSet");
	$parnode = $dom->append_child($node);

	while ($row = mysql_fetch_assoc($result)) {
	  $node = $dom->create_element("Member");
	  $newNode = $parnode->append_child($node);
	  
	  $nextNode = $dom->create_element("memberId"); 
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

	  $nextNode = $dom->create_element("MembershipYear"); 
	  $nextNode = $newNode->append_child($nextNode);
	  $nextText = $dom->create_text_node($row['membership']);
	  $nextText = $nextNode->append_child($nextText);

	  $nextNode = $dom->create_element("LicenseYear"); 
	  $nextNode = $newNode->append_child($nextNode);
	  $nextText = $dom->create_text_node($row['license']);
	  $nextText = $nextNode->append_child($nextText);
	  
	  $nextNode = $dom->create_element("BirthDate"); 
	  $nextNode = $newNode->append_child($nextNode);
	  $nextText = $dom->create_text_node(DefBirthdayFormat($row['birthdate']));
	  $nextText = $nextNode->append_child($nextText);
	  
	}
	echo $dom->dump_mem(true);
}else{
	echo "<?xml version=\"1.0\"?>\n";
	echo "<MemberSet>\n";

	// Iterate through the rows, adding XML nodes for each
	while ($row = mysql_fetch_assoc($result)){
		echo "<Member>\n";
		echo "<memberId>". $row['accreditation_id'] ."</memberId>\n";
		echo "<Firstname>". $row['firstname'] ."</Firstname>\n";
		echo "<Lastname>". $row['lastname'] ."</Lastname>\n";
		echo "<MembershipYear>". $row['membership'] ."</MembershipYear>\n";		
		echo "<LicenseYear>". $row['license'] ."</LicenseYear>\n";
		echo "<BirthDate>". DefBirthdayFormat($row['birthdate']) ."</BirthDate>\n";
		echo "</Member>\n";
	}
	echo "</MemberSet>\n";
}
}
CloseConnection();
?>