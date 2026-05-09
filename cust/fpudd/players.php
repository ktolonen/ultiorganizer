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
    $appendTextElement = function ($parentNode, $name, $value) use ($dom) {
        $element = $dom->createElement($name);
        $element = $parentNode->appendChild($element);
        $text = $dom->createTextNode((string) ($value ?? ''));
        $element->appendChild($text);
    };

    foreach ($players as $row) {
        $node = $dom->createElement("Member");
        $newNode = $parnode->appendChild($node);

        $appendTextElement($newNode, "AccreditationId", $row['accreditation_id']);
        $appendTextElement($newNode, "ProfileId", $row['profile_id']);
        $appendTextElement($newNode, "Firstname", $row['firstname']);
        $appendTextElement($newNode, "Lastname", $row['lastname']);
        $appendTextElement($newNode, "BirthDate", DefBirthdayFormat($row['birthdate']));
        $appendTextElement($newNode, "Team", $row['teamname']);
        $appendTextElement($newNode, "Event", $row['seasoname']);
        $appendTextElement($newNode, "Gender", $row['gender']);
        $appendTextElement($newNode, "Email", $row['email']);
        $appendTextElement($newNode, "Jersey", ($row['num'] < 0) ? '' : $row['num']);
    }
    echo $dom->saveXML();
}

CloseConnection();
