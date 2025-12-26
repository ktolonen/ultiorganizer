<?php

include_once 'localization.php';
include_once '../lib/location.functions.php';

OpenConnection();
header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");
$result = GetSearchLocations();

$dom = new DOMDocument("1.0");
$node = $dom->createElement("markers");
$parnode = $dom->appendChild($node);

// Iterate through the rows, adding XML nodes for each
$savedID = null;
while ($row = @mysqli_fetch_assoc($result)) {
	if ($row['id'] !== $savedID) {
		$node = $dom->createElement("marker");
		$newnode = $parnode->appendChild($node);
		$newnode->setAttribute("id", $row['id']);
		$newnode->setAttribute("name", U_($row['name']));
		$newnode->setAttribute("address", $row['address']);
		$newnode->setAttribute("lat", $row['lat']);
		$newnode->setAttribute("lng", $row['lng']);
		$newnode->setAttribute("info", $row['info']);
		$newnode->setAttribute("indoor", $row['indoor']);
		$newnode->setAttribute("fields", $row['fields']);
	}
	$newnode->setAttribute("info_" . $row['locale'], $row['locale_info']);
	$savedID = $row['id'];
}

echo $dom->saveXML();
CloseConnection();
