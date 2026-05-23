<?php

include_once __DIR__ . '/localization.php';
include_once __DIR__ . '/../lib/season.functions.php';
include_once __DIR__ . '/../lib/team.functions.php';

$teamId = 0;
if (isset($_GET['search'])) {
    $teamId = (int) $_GET['search'];
} elseif (isset($_GET['query'])) {
    $teamId = (int) $_GET['query'];
} elseif (isset($_GET['q'])) {
    $teamId = (int) $_GET['q'];
}
if ($teamId > 0) {
    $teamInfo = TeamInfo($teamId);
    RequireSeasonPublicExternal($teamInfo['season'] ?? "");
}

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
