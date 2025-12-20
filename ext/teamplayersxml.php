<?php

include_once 'localization.php';
include_once '../lib/team.functions.php';

header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");

$result = GetTeamPlayers();

$dom = new DOMDocument("1.0");
$node = $dom->createElement("PlayerSet");
$parnode = $dom->appendChild($node);

// Iterate through the rows, adding XML nodes for each
foreach ($result as $row) {
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
CloseConnection();
