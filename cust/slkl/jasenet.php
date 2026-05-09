<?php
if (!isset($include_prefix)) {
	$include_prefix = __DIR__ . '/../../';
}

include_once $include_prefix . 'lib/auth.guard.php';

include_once '../../lib/database.php';
include_once '../../lib/accreditation.functions.php';
include_once '../../lib/common.functions.php';
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

	$licenses = SearchLicenseData($firstname, $lastname);

	$dom = new DOMDocument("1.0");
	$node = $dom->createElement("MemberSet");
	$parnode = $dom->appendChild($node);

	foreach ($licenses as $row) {
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
}
CloseConnection();
