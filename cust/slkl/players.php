<?php
if (!isset($include_prefix)) {
	$include_prefix = __DIR__ . '/../../';
}

include_once $include_prefix . 'lib/auth.guard.php';

include_once '../../lib/database.php';
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

	$query = sprintf(
		"SELECT pp.profile_id, pp.accreditation_id, pp.firstname, pp.lastname, pp.birthdate, pp.gender, pp.email,
			    pp.num, p2.teamname, p2.seasoname
			FROM uo_license l 
			LEFT JOIN uo_player_profile AS pp ON (l.accreditation_id=pp.accreditation_id)
			LEFT JOIN(SELECT p.profile_id, p.firstname, p.lastname,
			    p.num, t.name AS teamname, sea.name AS seasoname FROM uo_player p
			    LEFT JOIN uo_team t ON (p.team=t.team_id)
    			LEFT JOIN uo_series ser ON (ser.series_id=t.series)
			    LEFT JOIN uo_season sea ON (ser.season=sea.season_id)
				ORDER BY p.player_id DESC) AS p2 ON (pp.profile_id=p2.profile_id)
			LEFT JOIN uo_player AS p1 ON (p1.profile_id=pp.profile_id)
			WHERE l.firstname like '%%%s%%' and l.lastname like '%%%s%%'
			GROUP BY pp.profile_id ORDER BY pp.lastname, pp.firstname",
		DBEscapeString($firstname),
		DBEscapeString($lastname)
	);
	$result = DBQuery($query);

	$dom = new DOMDocument("1.0");
	$node = $dom->createElement("MemberSet");
	$parnode = $dom->appendChild($node);

	while ($row = mysqli_fetch_assoc($result)) {
		$node = $dom->createElement("Member");
		$newNode = $parnode->appendChild($node);

		$nextNode = $dom->createElement("AccreditationId");
		$nextNode = $newNode->appendChild($nextNode);
		$nextText = $dom->createTextNode((string)$row['accreditation_id']);
		$nextText = $nextNode->appendChild($nextText);

		$nextNode = $dom->createElement("ProfileId");
		$nextNode = $newNode->appendChild($nextNode);
		$nextText = $dom->createTextNode((string)$row['profile_id']);
		$nextText = $nextNode->appendChild($nextText);

		$nextNode = $dom->createElement("Firstname");
		$nextNode = $newNode->appendChild($nextNode);
		$nextText = $dom->createTextNode((string)$row['firstname']);
		$nextText = $nextNode->appendChild($nextText);

		$nextNode = $dom->createElement("Lastname");
		$nextNode = $newNode->appendChild($nextNode);
		$nextText = $dom->createTextNode((string)$row['lastname']);
		$nextText = $nextNode->appendChild($nextText);

		$nextNode = $dom->createElement("BirthDate");
		$nextNode = $newNode->appendChild($nextNode);
		$nextText = $dom->createTextNode((string)DefBirthdayFormat($row['birthdate']));
		$nextText = $nextNode->appendChild($nextText);

		$nextNode = $dom->createElement("Team");
		$nextNode = $newNode->appendChild($nextNode);
		$nextText = $dom->createTextNode((string)$row['teamname']);
		$nextText = $nextNode->appendChild($nextText);

		$nextNode = $dom->createElement("Event");
		$nextNode = $newNode->appendChild($nextNode);
		$nextText = $dom->createTextNode((string)$row['seasoname']);
		$nextText = $nextNode->appendChild($nextText);

		$nextNode = $dom->createElement("Gender");
		$nextNode = $newNode->appendChild($nextNode);
		$nextText = $dom->createTextNode((string)$row['gender']);
		$nextText = $nextNode->appendChild($nextText);

		$nextNode = $dom->createElement("Email");
		$nextNode = $newNode->appendChild($nextNode);
		$nextText = $dom->createTextNode((string)$row['email']);
		$nextText = $nextNode->appendChild($nextText);

		$nextNode = $dom->createElement("Jersey");
		$nextNode = $newNode->appendChild($nextNode);
		if ($row['num'] < 0) {
			$nextText = $dom->createTextNode("");
		} else {
			$nextText = $dom->createTextNode((string)$row['num']);
		}
		$nextText = $nextNode->appendChild($nextText);
	}
	echo $dom->saveXML();
}

CloseConnection();
