<?php

if (!isset($include_prefix)) {
    $include_prefix = __DIR__ . '/../../';
}

// conf/config.inc.php must be loaded before the session starts: the session
// instance fingerprint is built from DB_HOST, DB_DATABASE and BASEURL, and a
// fingerprint computed without them does not match the one index.php stored,
// so the caller's login session would be discarded as another installation's.
include_once $include_prefix . 'lib/database.php';

include_once $include_prefix . 'lib/auth.guard.php';

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

$dom = new DOMDocument("1.0");
$node = $dom->createElement("MemberSet");
$parnode = $dom->appendChild($node);

// An empty body is not a parseable XML document, so the datasource would
// report a denied request as a data error. Always emit the document and
// leave it without members instead.
if (hasEditPlayersRight($teamId)) {
    $players = SearchPlayerProfiles($firstname, $lastname);

    foreach ($players as $row) {
        $node = $dom->createElement("Member");
        $newNode = $parnode->appendChild($node);

        $nextNode = $dom->createElement("AccreditationId");
        $nextNode = $newNode->appendChild($nextNode);
        $nextText = $dom->createTextNode((string) $row['accreditation_id']);
        $nextText = $nextNode->appendChild($nextText);

        $nextNode = $dom->createElement("ProfileId");
        $nextNode = $newNode->appendChild($nextNode);
        $nextText = $dom->createTextNode((string) $row['profile_id']);
        $nextText = $nextNode->appendChild($nextText);

        $nextNode = $dom->createElement("Firstname");
        $nextNode = $newNode->appendChild($nextNode);
        $nextText = $dom->createTextNode((string) $row['firstname']);
        $nextText = $nextNode->appendChild($nextText);

        $nextNode = $dom->createElement("Lastname");
        $nextNode = $newNode->appendChild($nextNode);
        $nextText = $dom->createTextNode((string) $row['lastname']);
        $nextText = $nextNode->appendChild($nextText);

        $nextNode = $dom->createElement("BirthDate");
        $nextNode = $newNode->appendChild($nextNode);
        $nextText = $dom->createTextNode((string) DefBirthdayFormat($row['birthdate']));
        $nextText = $nextNode->appendChild($nextText);

        $nextNode = $dom->createElement("Team");
        $nextNode = $newNode->appendChild($nextNode);
        $nextText = $dom->createTextNode((string) $row['teamname']);
        $nextText = $nextNode->appendChild($nextText);

        $nextNode = $dom->createElement("Event");
        $nextNode = $newNode->appendChild($nextNode);
        $nextText = $dom->createTextNode((string) $row['seasoname']);
        $nextText = $nextNode->appendChild($nextText);

        $nextNode = $dom->createElement("Gender");
        $nextNode = $newNode->appendChild($nextNode);
        $nextText = $dom->createTextNode((string) $row['gender']);
        $nextText = $nextNode->appendChild($nextText);


        $nextNode = $dom->createElement("Jersey");
        $nextNode = $newNode->appendChild($nextNode);
        if ($row['num'] < 0) {
            $nextText = $dom->createTextNode("");
        } else {
            $nextText = $dom->createTextNode((string) $row['num']);
        }
        $nextText = $nextNode->appendChild($nextText);
    }
}

echo $dom->saveXML();

CloseConnection();
