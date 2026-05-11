<?php

include_once __DIR__ . '/localization.php';
include_once __DIR__ . '/../lib/location.functions.php';

OpenConnection();
header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");
$result = GetSearchLocationsArray();

$dom = new DOMDocument("1.0");
$node = $dom->createElement("markers");
$parnode = $dom->appendChild($node);

// Iterate through the rows, adding XML nodes for each
$savedID = null;
$newnode = null;
foreach ($result as $row) {
    if ($row['id'] !== $savedID || $newnode === null) {
        /** @var DOMElement $newnode */
        $newnode = $dom->createElement("marker");
        $parnode->appendChild($newnode);
        $newnode->setAttribute("id", (string) $row['id']);
        $newnode->setAttribute("name", (string) U_($row['name']));
        $newnode->setAttribute("address", (string) ($row['address'] ?? ''));
        $newnode->setAttribute("lat", (string) ($row['lat'] ?? ''));
        $newnode->setAttribute("lng", (string) ($row['lng'] ?? ''));
        $newnode->setAttribute("info", (string) ($row['info'] ?? ''));
        $newnode->setAttribute("indoor", (string) ($row['indoor'] ?? ''));
        $newnode->setAttribute("fields", (string) ($row['fields'] ?? ''));
    }
    $newnode->setAttribute("info_" . $row['locale'], (string) ($row['locale_info'] ?? ''));
    $savedID = $row['id'];
}

echo $dom->saveXML();
CloseConnection();
