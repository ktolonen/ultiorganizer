<?php

function ClubName($clubId)
{
	$query = sprintf(
		"SELECT name FROM uo_club WHERE club_id='%s'",
		DBEscapeString($clubId)
	);
	return DBQueryToValue($query);
}

function ClubInfo($clubId)
{
	$query = sprintf(
		"SELECT club.name, club.club_id, club.country, c.name as countryname, 
		club.city, club.contacts, club.story, club.achievements, club.profile_image, club.valid,
		club.founded
		FROM uo_club club 
		LEFT JOIN uo_country c ON(club.country=c.country_id)
		WHERE club.club_id = '%s'",
		DBEscapeString($clubId)
	);

	return  DBQueryToRow($query);
}

function ClubList($onlyvalid = false, $namefilter = "")
{

	$query = "SELECT club.club_id, club.name, club.valid, club.country, c.flagfile 
		FROM uo_club club
		LEFT JOIN uo_country c ON (club.country=c.country_id)";

	if ($onlyvalid || (!empty($namefilter) && $namefilter != "ALL")) {
		$query .= " WHERE ";
	}

	if ($onlyvalid) {
		$query .= "club.valid=1";
	}

	if ($onlyvalid && (!empty($namefilter) && $namefilter != "ALL")) {
		$query .= " AND ";
	}

	if (!empty($namefilter) && $namefilter != "ALL") {
		if ($namefilter == "#") {
			$query .= "UPPER(club.name) REGEXP '^[0-9]'";
		} else {
			$query .= "UPPER(club.name) LIKE '" . DBEscapeString($namefilter) . "%'";
		}
	}

	$query .= " ORDER BY club.valid DESC, club.name ASC";

	return  DBQueryToArray($query);
}


function SetClubName($clubId, $name)
{
	if (isSuperAdmin()) {
		$query = sprintf(
			"
			UPDATE uo_club SET name='%s' WHERE club_id='%s'",
			DBEscapeString($name),
			DBEscapeString($clubId)
		);

		return DBQuery($query);
	} else {
		die('Insufficient rights to edit team');
	}
}

function ClubTeams($clubId, $season = "")
{
	$query = sprintf(
		"SELECT team.team_id, team.name, ser.name AS seriesname, ser.series_id FROM uo_club club
		LEFT JOIN uo_team team ON(team.club = club.club_id)
		LEFT JOIN uo_series ser ON(team.series = ser.series_id)
		WHERE team.club='%s' AND ser.season='%s' ORDER BY ser.ordering, team.name",
		DBEscapeString($clubId),
		DBEscapeString($season)
	);

	return DBQueryToArray($query);
}

function ClubTeamsHistory($clubId)
{
	$curseason = CurrentSeason();
	$query = sprintf(
		"SELECT ser.season, team.team_id, team.name, ser.name AS seriesname, ser.series_id FROM uo_club club
			LEFT JOIN uo_team team ON(team.club = club.club_id)
			LEFT JOIN uo_series ser ON(team.series = ser.series_id)
			LEFT JOIN uo_season s ON(s.season_id = ser.season)
			WHERE team.club='%s' AND ser.season!='%s' ORDER BY ser.type, s.starttime DESC, team.name",
		DBEscapeString($clubId),
		DBEscapeString($curseason)
	);

	return DBQueryToArray($query);
}

function ClubNumOfTeams($clubId)
{
	$query = sprintf(
		"SELECT count(team.team_id) FROM uo_club club
		LEFT JOIN uo_team team ON(team.club = club.club_id)
		WHERE club.club_id='%s'",
		DBEscapeString($clubId)
	);
	return DBQueryToValue($query);
}

function ClubId($name)
{
	$query = sprintf(
		"SELECT club_id FROM uo_club WHERE lower(name) LIKE lower('%s')",
		DBEscapeString($name)
	);
	return DBQueryToValue($query);
}

function RemoveClub($clubId)
{
	if (CanDeleteClub($clubId) && isSuperAdmin()) {
		Log2("club", "delete", ClubName($clubId));
		$query = sprintf(
			"DELETE FROM uo_club WHERE club_id='%s'",
			DBEscapeString($clubId)
		);
		return DBQuery($query);
	} else {
		die('Insufficient rights to remove player');
	}
}

function AddClub($seriesId, $name)
{
	if (hasEditTeamsRight($seriesId)) {
		$query = sprintf(
			"INSERT INTO uo_club (name) VALUES ('%s')",
			DBEscapeString($name)
		);
		$clubId = DBQueryInsert($query);
		Log1("club", "add", $clubId);
		return $clubId;
	} else {
		die('Insufficient rights to add club');
	}
}

function CanDeleteClub($clubId)
{
	$query = sprintf(
		"SELECT count(*) FROM uo_team WHERE club='%s'",
		DBEscapeString($clubId)
	);
	$count = DBQueryToValue($query);
	return ($count == 0);
}

function SetClubProfile($teamId, $profile)
{
	$teaminfo = TeamInfo($teamId);
	if (isSuperAdmin() || (hasEditPlayersRight($teamId) && $teaminfo['club'] == $profile['club_id'])) {

		$query = sprintf(
			"UPDATE uo_club SET name='%s', contacts='%s', 
				country='%s', city='%s', founded='%s', story='%s',
				achievements='%s', valid=%d WHERE club_id='%s'",
			DBEscapeString($profile['name']),
			DBEscapeString($profile['contacts']),
			DBEscapeString($profile['country']),
			DBEscapeString($profile['city']),
			DBEscapeString($profile['founded']),
			DBEscapeString($profile['story']),
			DBEscapeString($profile['achievements']),
			(int)$profile['valid'],
			DBEscapeString($profile['club_id'])
		);

		return DBQuery($query);
	} else {
		die('Insufficient rights to edit club profile');
	}
}

function UploadClubImage($teamId, $clubId)
{
	$teaminfo = TeamInfo($teamId);
	if (isSuperAdmin() || (hasEditPlayersRight($teamId) && $teaminfo['club'] == $clubId)) {
		$max_file_size = 5 * 1024 * 1024; //5 MB

		if ($_FILES['picture']['size'] > $max_file_size) {
			return "<p class='warning'>" . _("File is too large") . "</p>";
		}

		$imgType = $_FILES['picture']['type'];
		$type = explode("/", $imgType);
		$type1 = $type[0];
		$type2 = $type[1];
		if ($type1 != "image") {
			return "<p class='warning'>" . _("File is not supported image format") . "</p>";
		}

		if (!extension_loaded("gd")) {
			return "<p class='warning'>" . _("Missing gd extension for image handling.") . "</p>";
		}

		$file_tmp_name = $_FILES['picture']['tmp_name'];
		$imgname = time() . $clubId . ".jpg";
		$basedir = UPLOAD_DIR . "clubs/$clubId/";
		if (!is_dir($basedir)) {
			recur_mkdirs($basedir, 0775);
			recur_mkdirs($basedir . "thumbs/", 0775);
		}

		ConvertToJpeg($file_tmp_name, $basedir . $imgname);
		CreateThumb($basedir . $imgname, $basedir . "thumbs/" . $imgname, 160, 120);

		//currently removes old image, in future there might be a gallery of images
		RemoveClubProfileImage($teamId, $clubId);
		SetClubProfileImage($teamId, $clubId, $imgname);

		return "";
	} else {
		die('Insufficient rights to upload image');
	}
}


function SetClubProfileImage($teamId, $clubId, $filename)
{
	$teaminfo = TeamInfo($teamId);
	if (isSuperAdmin() || (hasEditPlayersRight($teamId) && $teaminfo['club'] == $clubId)) {

		$query = sprintf(
			"UPDATE uo_club SET profile_image='%s' WHERE club_id='%s'",
			DBEscapeString($filename),
			DBEscapeString($clubId)
		);

		DBQuery($query);
	} else {
		die('Insufficient rights to edit club profile');
	}
}

function RemoveClubProfileImage($teamId, $clubId)
{
	$teaminfo = TeamInfo($teamId);
	if (isSuperAdmin() || (hasEditPlayersRight($teamId) && $teaminfo['club'] == $clubId)) {

		$profile = ClubInfo($clubId);

		if (!empty($profile['profile_image'])) {

			//thumbnail
			$file = "" . UPLOAD_DIR . "clubs/$clubId/thumbs/" . $profile['profile_image'];
			if (is_file($file)) {
				unlink($file); //  remove old images if present
			}

			//image
			$file = "" . UPLOAD_DIR . "clubs/$clubId/" . $profile['profile_image'];

			if (is_file($file)) {
				unlink($file); //  remove old images if present
			}

			$query = sprintf(
				"UPDATE uo_club SET profile_image=NULL WHERE club_id='%s'",
				DBEscapeString($clubId)
			);

			DBQuery($query);
		}
	} else {
		die('Insufficient rights to edit player profile');
	}
}

function SetClubValidity($clubId, $valid)
{
	if (isSuperAdmin()) {
		$query = sprintf(
			"UPDATE uo_club SET valid=%d WHERE club_id='%s'",
			(int)($valid),
			DBEscapeString($clubId)
		);

		return DBQuery($query);
	} else {
		die('Insufficient rights to set club validity');
	}
}

function AddClubProfileUrl($teamId, $clubId, $type, $url, $name)
{
	$teaminfo = TeamInfo($teamId);
	if (isSuperAdmin() || (hasEditPlayersRight($teamId) && $teaminfo['club'] == $clubId)) {
		$url = SafeUrl($url);
		$query = sprintf(
			"INSERT INTO uo_urls (owner,owner_id,type,name,url)
				VALUES('club',%d,'%s','%s','%s')",
			(int)$clubId,
			DBEscapeString($type),
			DBEscapeString($name),
			DBEscapeString($url)
		);
		return DBQuery($query);
	} else {
		die('Insufficient rights to add url');
	}
}

function RemoveClubProfileUrl($teamId, $clubId, $urlId)
{
	$teaminfo = TeamInfo($teamId);
	if (isSuperAdmin() || (hasEditPlayersRight($teamId) && $teaminfo['club'] == $clubId)) {
		$query = sprintf(
			"DELETE FROM uo_urls WHERE url_id=%d",
			(int)$urlId
		);
		return DBQuery($query);
	} else {
		die('Insufficient rights to remove url');
	}
}
