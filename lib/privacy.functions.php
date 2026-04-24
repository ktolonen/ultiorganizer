<?php
require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/common.functions.php';
require_once __DIR__ . '/player.functions.php';
require_once __DIR__ . '/user.functions.php';
require_once __DIR__ . '/image.functions.php';
require_once __DIR__ . '/url.functions.php';
require_once __DIR__ . '/logging.functions.php';

function PrivacyPlayerMatches($search)
{
	PrivacyRequireSuperAdmin();

	$search = trim((string)$search);
	if ($search === '') {
		return array();
	}

	$query = sprintf(
		"SELECT p.player_id, p.profile_id, p.firstname, p.lastname,
			CONCAT(TRIM(COALESCE(p.firstname, '')), ' ', TRIM(COALESCE(p.lastname, ''))) AS player_name,
			p.accreditation_id, p.team, t.name AS team_name, s.name AS series_name, s.season,
			se.name AS season_name, pp.firstname AS profile_firstname, pp.lastname AS profile_lastname,
			pp.email
		FROM uo_player p
		LEFT JOIN uo_team t ON (t.team_id=p.team)
		LEFT JOIN uo_series s ON (s.series_id=t.series)
		LEFT JOIN uo_season se ON (se.season_id=s.season)
		LEFT JOIN uo_player_profile pp ON (pp.profile_id=p.profile_id)
		WHERE CONCAT(TRIM(COALESCE(p.firstname, '')), ' ', TRIM(COALESCE(p.lastname, ''))) LIKE '%%%s%%'
			OR p.firstname LIKE '%%%s%%'
			OR p.lastname LIKE '%%%s%%'
			OR CONCAT(TRIM(COALESCE(pp.firstname, '')), ' ', TRIM(COALESCE(pp.lastname, ''))) LIKE '%%%s%%'
			OR pp.firstname LIKE '%%%s%%'
			OR pp.lastname LIKE '%%%s%%'
		ORDER BY
			CASE WHEN se.season_id IS NULL THEN 1 ELSE 0 END ASC,
			se.starttime DESC,
			s.season DESC,
			s.name ASC,
			t.name ASC,
			p.player_id DESC",
		DBEscapeString($search),
		DBEscapeString($search),
		DBEscapeString($search),
		DBEscapeString($search),
		DBEscapeString($search),
		DBEscapeString($search)
	);

	$rows = DBQueryToArray($query);
	$matches = array();
	$profileMatches = array();

	foreach ($rows as $row) {
		$profileId = isset($row['profile_id']) ? (int)$row['profile_id'] : 0;
		if ($profileId > 0) {
			if (!isset($profileMatches[$profileId])) {
				$displayName = trim((string)$row['profile_firstname'] . ' ' . (string)$row['profile_lastname']);
				if ($displayName === '') {
					$displayName = trim((string)$row['player_name']);
				}

				$profileMatches[$profileId] = array(
					'player_id' => (int)$row['player_id'],
					'profile_id' => $profileId,
					'player_name' => $displayName,
					'team_name' => (string)$row['team_name'],
					'series_name' => (string)$row['series_name'],
					'season_name' => (string)$row['season_name'],
					'email' => (string)$row['email']
				);
			}
			continue;
		}

		$matches[] = array(
			'player_id' => (int)$row['player_id'],
			'profile_id' => 0,
			'player_name' => (string)$row['player_name'],
			'team_name' => (string)$row['team_name'],
			'series_name' => (string)$row['series_name'],
			'season_name' => (string)$row['season_name'],
			'email' => (string)$row['email']
		);
	}

	foreach ($profileMatches as $match) {
		$matches[] = $match;
	}

	usort($matches, 'PrivacyPlayerMatchSort');
	return $matches;
}

function PrivacyGetPlayerSubject($playerId)
{
	PrivacyRequireSuperAdmin();

	$playerId = (int)$playerId;
	if ($playerId <= 0) {
		return null;
	}

	$selected = PlayerInfo($playerId);
	if (empty($selected)) {
		return null;
	}
	$selectedSeasonName = DBQueryToValue(sprintf(
		"SELECT se.name
		FROM uo_player p
		LEFT JOIN uo_team t ON (t.team_id=p.team)
		LEFT JOIN uo_series s ON (s.series_id=t.series)
		LEFT JOIN uo_season se ON (se.season_id=s.season)
		WHERE p.player_id=%d",
		$playerId
	));
	if (!empty($selectedSeasonName)) {
		$selected['season_name'] = $selectedSeasonName;
	}

	$profileId = !empty($selected['profile_id']) ? (int)$selected['profile_id'] : 0;
	$players = array();
	if ($profileId > 0) {
		$players = DBQueryToArray(sprintf(
			"SELECT p.player_id, p.profile_id, p.firstname, p.lastname, p.team, p.num, p.accreditation_id,
				p.accredited, t.name AS team_name, s.name AS series_name, s.season, se.name AS season_name
			FROM uo_player p
			LEFT JOIN uo_team t ON (t.team_id=p.team)
			LEFT JOIN uo_series s ON (s.series_id=t.series)
			LEFT JOIN uo_season se ON (se.season_id=s.season)
			WHERE p.profile_id=%d
			ORDER BY s.season DESC, s.name ASC, t.name ASC, p.player_id ASC",
			$profileId
		));
	} else {
		$players = DBQueryToArray(sprintf(
			"SELECT p.player_id, p.profile_id, p.firstname, p.lastname, p.team, p.num, p.accreditation_id,
				p.accredited, t.name AS team_name, s.name AS series_name, s.season, se.name AS season_name
			FROM uo_player p
			LEFT JOIN uo_team t ON (t.team_id=p.team)
			LEFT JOIN uo_series s ON (s.series_id=t.series)
			LEFT JOIN uo_season se ON (se.season_id=s.season)
			WHERE p.player_id=%d",
			$playerId
		));
	}

	$playerIds = array();
	foreach ($players as $player) {
		$playerIds[] = (int)$player['player_id'];
	}

	$profile = null;
	if ($profileId > 0) {
		$profile = PlayerProfile($profileId);
	}

	return array(
		'selected' => $selected,
		'profile_id' => $profileId,
		'players' => $players,
		'player_ids' => $playerIds,
		'profile' => $profile
	);
}

function PrivacyGetUserSubject($userId)
{
	PrivacyRequireSuperAdmin();

	$userId = trim((string)$userId);
	if ($userId === '' || $userId === 'anonymous') {
		return null;
	}

	$user = DBQueryToRow(sprintf(
		"SELECT id, userid, name, email, last_login,
			CASE WHEN password IS NULL OR password='' THEN 0 ELSE 1 END AS has_password
		FROM uo_users
		WHERE userid='%s'",
		DBEscapeString($userId)
	), true);

	if (empty($user)) {
		return null;
	}

	return array(
		'user' => $user
	);
}

function PrivacyUserMatches($search)
{
	PrivacyRequireSuperAdmin();

	$search = trim((string)$search);
	if ($search === '') {
		return array();
	}

	$query = sprintf(
		"SELECT id, userid, name, email, last_login
		FROM uo_users
		WHERE userid <> 'anonymous'
			AND (
				userid LIKE '%%%s%%'
				OR name LIKE '%%%s%%'
				OR email LIKE '%%%s%%'
			)
		ORDER BY userid ASC, name ASC",
		DBEscapeString($search),
		DBEscapeString($search),
		DBEscapeString($search)
	);

	return DBQueryToArray($query, true);
}

function PrivacyCollectPlayerReportData($playerId)
{
	PrivacyRequireSuperAdmin();

	$subject = PrivacyGetPlayerSubject($playerId);
	if (empty($subject)) {
		return null;
	}

	$playerIds = $subject['player_ids'];
	$profileId = (int)$subject['profile_id'];
	$playerIdList = PrivacyIntList($playerIds);
	$urls = array();
	$playerStats = array();
	$played = array();
	$goals = array();
	$defenses = array();
	$licenseRows = array();
	$accreditationLog = array();
	$eventLog = array();
	$imageInfo = null;
	$accreditationIds = array();
	$playerLogTargets = array();

	if ($profileId > 0) {
		$playerStats = DBQueryToArray(sprintf(
			"SELECT * FROM uo_player_stats WHERE profile_id=%d ORDER BY season DESC, series DESC, team DESC, player_id DESC",
			$profileId
		), true);
		$urls = DBQueryToArray(sprintf(
			"SELECT * FROM uo_urls WHERE owner='player' AND owner_id='%s' ORDER BY ordering, type, name, url_id",
			DBEscapeString($profileId)
		), true);
		if (!empty($subject['profile']['accreditation_id'])) {
			$accreditationIds[] = trim((string)$subject['profile']['accreditation_id']);
		}
	}

	if ($playerIdList !== '') {
		foreach ($playerIds as $linkedPlayerId) {
			$playerLogTargets[] = 'player:' . (int)$linkedPlayerId;
		}
		if ($profileId > 0) {
			$playerLogTargets[] = 'profile:' . $profileId;
		}

		$played = DBQueryToArray(
			"SELECT * FROM uo_played WHERE player IN ($playerIdList) ORDER BY game DESC, player DESC",
			true
		);
		$goals = DBQueryToArray(
			"SELECT * FROM uo_goal WHERE scorer IN ($playerIdList) OR assist IN ($playerIdList) ORDER BY game DESC, num DESC",
			true
		);
		$defenses = DBQueryToArray(
			"SELECT * FROM uo_defense WHERE author IN ($playerIdList) ORDER BY game DESC, num DESC",
			true
		);
		$accreditationLog = DBQueryToArray(
			"SELECT * FROM uo_accreditationlog WHERE player IN ($playerIdList) ORDER BY time DESC",
			true
		);
		$eventLogWhere = array(
			"(category='player' AND id1 IN ($playerIdList))"
		);
		$playerLogTargetList = PrivacyQuotedList(array_unique($playerLogTargets));
		if ($playerLogTargetList !== '') {
			$eventLogWhere[] = "(source='privacy' AND id1 IN ($playerLogTargetList))";
		}
		$eventLog = DBQueryToArray(
			"SELECT * FROM uo_event_log WHERE " . implode(' OR ', $eventLogWhere) . " ORDER BY time DESC",
			true
		);
	}

	foreach ($subject['players'] as $playerRow) {
		if (!empty($playerRow['accreditation_id'])) {
			$accreditationIds[] = trim((string)$playerRow['accreditation_id']);
		}
	}

	$licenseIdList = PrivacyQuotedList(array_unique(array_filter($accreditationIds)));
	if ($licenseIdList !== '') {
		$licenseRows = DBQueryToArray(
			"SELECT * FROM uo_license WHERE accreditation_id IN ($licenseIdList) ORDER BY lastname ASC, firstname ASC, accreditation_id ASC",
			true
		);
	}

	if (!empty($subject['profile']) && !empty($subject['profile']['image'])) {
		$imageInfo = ImageInfo($subject['profile']['image']);
	}

	$subject['image_info'] = $imageInfo;
	$subject['profile_directory'] = $profileId > 0 ? UPLOAD_DIR . "players/$profileId/" : null;

	return array(
		'subject' => $subject,
		'player_rows' => $subject['players'],
		'profile_row' => $subject['profile'],
		'player_stats_rows' => $playerStats,
		'played_rows' => $played,
		'goal_rows' => $goals,
		'defense_rows' => $defenses,
		'license_rows' => $licenseRows,
		'accreditation_log_rows' => $accreditationLog,
		'event_log_rows' => $eventLog,
		'url_rows' => $urls
	);
}

function PrivacyCollectUserReportData($userId)
{
	PrivacyRequireSuperAdmin();

	$subject = PrivacyGetUserSubject($userId);
	if (empty($subject)) {
		return null;
	}

	$userId = $subject['user']['userid'];
	$eventLogWhere = PrivacyUserEventLogWhere($userId);

	return array(
		'subject' => $subject,
		'user_row' => $subject['user'],
		'userproperties_rows' => DBQueryToArray(sprintf(
			"SELECT * FROM uo_userproperties WHERE userid='%s' ORDER BY name, value, prop_id",
			DBEscapeString($userId)
		), true),
		'extraemail_rows' => DBQueryToArray(sprintf(
			"SELECT * FROM uo_extraemail WHERE userid='%s' ORDER BY email",
			DBEscapeString($userId)
		), true),
		'extraemailrequest_rows' => DBQueryToArray(sprintf(
			"SELECT * FROM uo_extraemailrequest WHERE userid='%s' ORDER BY email",
			DBEscapeString($userId)
		), true),
		'enrolledteam_rows' => DBQueryToArray(sprintf(
			"SELECT * FROM uo_enrolledteam WHERE userid='%s' ORDER BY series DESC, id DESC",
			DBEscapeString($userId)
		), true),
		'registerrequest_rows' => DBQueryToArray(sprintf(
			"SELECT userid, name, email, last_login,
				CASE WHEN password IS NULL OR password='' THEN 0 ELSE 1 END AS has_password,
				CASE WHEN token IS NULL OR token='' THEN 0 ELSE 1 END AS has_token
			FROM uo_registerrequest
			WHERE userid='%s'",
			DBEscapeString($userId)
		), true),
		'event_log_rows' => DBQueryToArray(
			"SELECT * FROM uo_event_log WHERE $eventLogWhere ORDER BY time DESC",
			true
		),
		'accreditation_log_rows' => DBQueryToArray(sprintf(
			"SELECT * FROM uo_accreditationlog WHERE userid='%s' ORDER BY time DESC",
			DBEscapeString($userId)
		), true)
	);
}

function PrivacyRenderPlayerReportText($playerId, $adminUserId)
{
	PrivacyRequireSuperAdmin();

	$data = PrivacyCollectPlayerReportData($playerId);
	if (empty($data)) {
		return null;
	}

	$subject = $data['subject'];
	$identity = PrivacyPlayerIdentityLabel($subject);
	$lines = array();
	$lines[] = 'Ultiorganizer Privacy Report';
	$lines[] = 'Subject type: player';
	$lines[] = 'Selected identity: ' . $identity;
	$lines[] = 'Selected player_id: ' . (int)$subject['selected']['player_id'];
	$lines[] = 'Profile id: ' . ($subject['profile_id'] > 0 ? $subject['profile_id'] : 'none');
	$lines[] = 'Generated at: ' . date('Y-m-d H:i:s');
	$lines[] = 'Generated by: ' . $adminUserId;
	$lines[] = '';

	PrivacyAppendRowsSection($lines, 'Current player rows', $data['player_rows']);
	PrivacyAppendRowsSection($lines, 'Linked profile row', empty($data['profile_row']) ? array() : array($data['profile_row']));

	$imageRows = array();
	if (!empty($subject['profile'])) {
		$imageRows[] = array(
			'image_id' => isset($subject['profile']['image']) ? $subject['profile']['image'] : null,
			'profile_image' => isset($subject['profile']['profile_image']) ? $subject['profile']['profile_image'] : null,
			'profile_directory' => $subject['profile_directory'],
			'directory_exists' => !empty($subject['profile_directory']) && is_dir($subject['profile_directory']) ? 1 : 0
		);
		if (!empty($subject['image_info'])) {
			$imageRows[] = $subject['image_info'];
		}
	}
	PrivacyAppendRowsSection($lines, 'Profile image metadata', $imageRows);
	PrivacyAppendRowsSection($lines, 'Player stats rows', $data['player_stats_rows']);
	PrivacyAppendRowsSection($lines, 'Played rows', $data['played_rows']);
	PrivacyAppendRowsSection($lines, 'Goal rows', $data['goal_rows']);
	PrivacyAppendRowsSection($lines, 'Defense rows', $data['defense_rows']);
	PrivacyAppendRowsSection($lines, 'License rows', $data['license_rows']);
	PrivacyAppendRowsSection($lines, 'Accreditation log rows', PrivacySanitizePlayerPrivacyRows($data['accreditation_log_rows']));
	PrivacyAppendRowsSection($lines, 'Player event log rows', PrivacySanitizePlayerEventLogRows($data['event_log_rows']));
	PrivacyAppendRowsSection($lines, 'Player profile URL rows', $data['url_rows']);

	return implode("\n", $lines) . "\n";
}

function PrivacyRenderUserReportText($userId, $adminUserId)
{
	PrivacyRequireSuperAdmin();

	$data = PrivacyCollectUserReportData($userId);
	if (empty($data)) {
		return null;
	}

	$user = $data['user_row'];
	$lines = array();
	$lines[] = 'Ultiorganizer Privacy Report';
	$lines[] = 'Subject type: registered user';
	$lines[] = 'User id: ' . $user['userid'];
	$lines[] = 'User name: ' . $user['name'];
	$lines[] = 'Generated at: ' . date('Y-m-d H:i:s');
	$lines[] = 'Generated by: ' . $adminUserId;
	$lines[] = '';

	$userRow = $user;
	unset($userRow['id']);
	PrivacyAppendRowsSection($lines, 'User row', array($userRow));
	PrivacyAppendRowsSection($lines, 'User property rows', $data['userproperties_rows']);
	PrivacyAppendRowsSection($lines, 'Extra email rows', $data['extraemail_rows']);
	PrivacyAppendRowsSection($lines, 'Extra email request rows', $data['extraemailrequest_rows']);
	PrivacyAppendRowsSection($lines, 'Enrolled team rows', $data['enrolledteam_rows']);
	PrivacyAppendRowsSection($lines, 'Register request rows', $data['registerrequest_rows']);
	PrivacyAppendRowsSection($lines, 'Event log rows', $data['event_log_rows']);
	PrivacyAppendRowsSection($lines, 'Accreditation log rows', $data['accreditation_log_rows']);

	return implode("\n", $lines) . "\n";
}

function PrivacyLogPlayerReportExport($playerId, $adminUserId)
{
	PrivacyRequireSuperAdmin();

	$subject = PrivacyGetPlayerSubject($playerId);
	if (empty($subject)) {
		return false;
	}

	$target = $subject['profile_id'] > 0
		? 'profile:' . (int)$subject['profile_id']
		: 'player:' . (int)$subject['selected']['player_id'];

	return PrivacyLogOperation($adminUserId, 'player privacy report exported', $target);
}

function PrivacyLogUserReportExport($userId, $adminUserId)
{
	PrivacyRequireSuperAdmin();

	$subject = PrivacyGetUserSubject($userId);
	if (empty($subject)) {
		return false;
	}

	$target = 'account:' . (int)$subject['user']['id'];
	return PrivacyLogOperation($adminUserId, 'registered user privacy report exported', $target);
}

function PrivacyPlayerReportFilename($playerId)
{
	PrivacyRequireSuperAdmin();

	$subject = PrivacyGetPlayerSubject($playerId);
	if (empty($subject)) {
		return 'player-privacy-report.txt';
	}

	$suffix = $subject['profile_id'] > 0 ? 'profile-' . $subject['profile_id'] : 'player-' . (int)$subject['selected']['player_id'];
	return 'player-privacy-report-' . $suffix . '.txt';
}

function PrivacyUserReportFilename($userId)
{
	return 'user-privacy-report-' . PrivacySlug((string)$userId) . '.txt';
}

function PrivacyDownloadTextFile($filename, $content)
{
	PrivacyRequireSuperAdmin();

	header('Pragma: public');
	header('Expires: -1');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Cache-Control: public');
	header('Content-Description: File Transfer');
	header('Content-Type: text/plain; charset=UTF-8');
	header('Content-Disposition: attachment; filename=' . $filename);
	header('Content-Transfer-Encoding: binary');
	header('Content-Length: ' . strlen($content));
	echo $content;
	exit();
}

function PrivacyAnonymizePlayer($playerId, $adminUserId)
{
	PrivacyRequireSuperAdmin();

	$subject = PrivacyGetPlayerSubject($playerId);
	if (empty($subject)) {
		return false;
	}

	$playerIds = $subject['player_ids'];
	if (empty($playerIds)) {
		return false;
	}

	$playerIdList = PrivacyIntList($playerIds);
	$profileId = (int)$subject['profile_id'];
	$logTarget = $profileId > 0 ? 'profile:' . $profileId : 'player:' . (int)$subject['selected']['player_id'];
	$accreditationIds = array();

	if ($profileId > 0 && !empty($subject['profile']) && !empty($subject['profile']['accreditation_id'])) {
		$accreditationIds[] = trim((string)$subject['profile']['accreditation_id']);
	}
	foreach ($subject['players'] as $playerRow) {
		if (!empty($playerRow['accreditation_id'])) {
			$accreditationIds[] = trim((string)$playerRow['accreditation_id']);
		}
	}
	$licenseIdList = PrivacyQuotedList(array_unique(array_filter($accreditationIds)));

	DBSetExceptionMode(true);
	try {
		DBQuery('START TRANSACTION');

		if ($profileId > 0 && !empty($subject['profile'])) {
			if (!empty($subject['profile']['profile_image'])) {
				PrivacyRemovePlayerProfileImageByProfileId($profileId, $subject['profile']['profile_image']);
			}
			if (!empty($subject['profile']['image'])) {
				RemoveImage($subject['profile']['image']);
			}
			DBQuery(sprintf(
				"DELETE FROM uo_urls WHERE owner='player' AND owner_id='%s'",
				DBEscapeString($profileId)
			));

			DBQuery(sprintf(
				"UPDATE uo_player_profile
				SET email=NULL,
					firstname='-',
					lastname='-',
					num=NULL,
					nickname=NULL,
					birthdate=NULL,
					birthplace=NULL,
					nationality=NULL,
					throwing_hand=NULL,
					height=NULL,
					story=NULL,
					achievements=NULL,
					image=NULL,
					profile_image=NULL,
					weight=NULL,
					position=NULL,
					gender=NULL,
					info=NULL,
					national_id=NULL,
					accreditation_id=NULL,
					public='',
					ffindr_id=NULL
				WHERE profile_id=%d",
				$profileId
			));
		}

		DBQuery(
			"UPDATE uo_player
			SET firstname='-',
				lastname='-',
				num=NULL,
				accreditation_id=NULL,
				accredited=0,
				reg_id=NULL
			WHERE player_id IN ($playerIdList)"
		);

		if ($licenseIdList !== '') {
			DBQuery("DELETE FROM uo_license WHERE accreditation_id IN ($licenseIdList)");
		}

		DBQuery("DELETE FROM uo_accreditationlog WHERE player IN ($playerIdList)");
		DBQuery("DELETE FROM uo_event_log WHERE category='player' AND id1 IN ($playerIdList)");

		DBQuery('COMMIT');
	} catch (Exception $e) {
		DBSetExceptionMode(false);
		DBQuery('ROLLBACK');
		throw $e;
	}
	DBSetExceptionMode(false);

	PrivacyLogOperation($adminUserId, 'player anonymized', $logTarget);

	return true;
}

function PrivacyDeleteUserData($userId, $adminUserId)
{
	PrivacyRequireSuperAdmin();

	$subject = PrivacyGetUserSubject($userId);
	if (empty($subject)) {
		return false;
	}

	$userId = $subject['user']['userid'];
	$eventLogWhere = PrivacyUserEventLogWhere($userId);

	DBSetExceptionMode(true);
	try {
		DBQuery('START TRANSACTION');
		DBQuery("DELETE FROM uo_event_log WHERE $eventLogWhere");
		DBQuery(sprintf("DELETE FROM uo_accreditationlog WHERE userid='%s'", DBEscapeString($userId)));
		DBQuery(sprintf("DELETE FROM uo_registerrequest WHERE userid='%s'", DBEscapeString($userId)));
		DBQuery(sprintf("DELETE FROM uo_userproperties WHERE userid='%s'", DBEscapeString($userId)));
		DBQuery(sprintf("DELETE FROM uo_users WHERE userid='%s'", DBEscapeString($userId)));
		DBQuery('COMMIT');
	} catch (Exception $e) {
		DBSetExceptionMode(false);
		DBQuery('ROLLBACK');
		throw $e;
	}
	DBSetExceptionMode(false);

	PrivacyLogOperation($adminUserId, 'registered user deleted');

	return true;
}

function PrivacyAppendRowsSection(&$lines, $title, $rows)
{
	$lines[] = '=== ' . $title . ' ===';
	$lines[] = 'Row count: ' . count($rows);
	if (empty($rows)) {
		$lines[] = '(none)';
		$lines[] = '';
		return;
	}

	$index = 1;
	foreach ($rows as $row) {
		$lines[] = '-- Row ' . $index . ' --';
		foreach ($row as $key => $value) {
			$lines[] = $key . ': ' . PrivacyScalarToText($value);
		}
		$lines[] = '';
		$index++;
	}
}

function PrivacyScalarToText($value)
{
	if ($value === null) {
		return '(null)';
	}
	if ($value === '') {
		return '(empty)';
	}
	if (is_bool($value)) {
		return $value ? '1' : '0';
	}
	if (is_array($value)) {
		return json_encode($value);
	}
	return str_replace(array("\r\n", "\r", "\n"), array('\n', '\n', '\n'), (string)$value);
}

function PrivacyPlayerIdentityLabel($subject)
{
	$selected = $subject['selected'];
	$name = trim($selected['firstname'] . ' ' . $selected['lastname']);
	if ($name === '') {
		$name = '(unnamed player)';
	}

	$parts = array($name);
	if (!empty($selected['teamname'])) {
		$parts[] = 'team=' . $selected['teamname'];
	}
	if (!empty($selected['seriesname'])) {
		$parts[] = 'division=' . $selected['seriesname'];
	}
	if (!empty($selected['season_name'])) {
		$parts[] = 'event=' . $selected['season_name'];
	}
	return implode(', ', $parts);
}

function PrivacyPlayerMatchSort($a, $b)
{
	$nameCompare = strcmp(
		strtolower((string)$a['player_name']),
		strtolower((string)$b['player_name'])
	);
	if ($nameCompare !== 0) {
		return $nameCompare;
	}

	$seasonCompare = strcmp(
		strtolower((string)$a['season_name']),
		strtolower((string)$b['season_name'])
	);
	if ($seasonCompare !== 0) {
		return $seasonCompare;
	}

	$seriesCompare = strcmp(
		strtolower((string)$a['series_name']),
		strtolower((string)$b['series_name'])
	);
	if ($seriesCompare !== 0) {
		return $seriesCompare;
	}

	$teamCompare = strcmp(
		strtolower((string)$a['team_name']),
		strtolower((string)$b['team_name'])
	);
	if ($teamCompare !== 0) {
		return $teamCompare;
	}

	return (int)$a['player_id'] - (int)$b['player_id'];
}

function PrivacySanitizePlayerEventLogRows($rows)
{
	return PrivacySanitizePlayerPrivacyRows($rows);
}

function PrivacySanitizePlayerPrivacyRows($rows)
{
	$sanitized = array();
	foreach ($rows as $row) {
		if (isset($row['user_id'])) {
			$row['user_id'] = '(hidden)';
		}
		if (isset($row['userid'])) {
			$row['userid'] = '(hidden)';
		}
		$sanitized[] = $row;
	}
	return $sanitized;
}

function PrivacyUserEventLogWhere($userId)
{
	$userId = DBEscapeString(trim((string)$userId));
	return sprintf(
		"(user_id='%s' OR id1='%s' OR id2='%s')",
		$userId,
		$userId,
		$userId
	);
}

function PrivacyLogOperation($adminUserId, $description, $target = '')
{
	PrivacyRequireSuperAdmin();

	$event = array(
		'user_id' => $adminUserId,
		'category' => 'security',
		'type' => 'change',
		'source' => 'privacy',
		'description' => $description
	);

	if ($target !== '') {
		$event['id1'] = $target;
	}

	return LogEvent($event);
}

function PrivacyRequireSuperAdmin()
{
	if (!isSuperAdmin()) {
		Forbidden(isset($_SESSION['uid']) ? $_SESSION['uid'] : 'anonymous');
	}
}

function PrivacyIntList($values)
{
	$ints = array();
	foreach ($values as $value) {
		$value = (int)$value;
		if ($value > 0) {
			$ints[] = $value;
		}
	}
	return implode(',', $ints);
}

function PrivacyQuotedList($values)
{
	$quoted = array();
	foreach ($values as $value) {
		$value = trim((string)$value);
		if ($value !== '') {
			$quoted[] = "'" . DBEscapeString($value) . "'";
		}
	}
	return implode(',', $quoted);
}

function PrivacySlug($value)
{
	$value = preg_replace('/[^a-z0-9._-]+/i', '-', trim((string)$value));
	$value = trim($value, '-');
	return $value === '' ? 'subject' : $value;
}

function PrivacyRemovePlayerProfileImageByProfileId($profileId, $filename)
{
	$profileId = (int)$profileId;
	$filename = trim((string)$filename);
	if ($profileId <= 0 || $filename === '') {
		return;
	}

	$thumb = UPLOAD_DIR . "players/$profileId/thumbs/$filename";
	if (is_file($thumb)) {
		unlink($thumb);
	}

	$image = UPLOAD_DIR . "players/$profileId/$filename";
	if (is_file($image)) {
		unlink($image);
	}

	PrivacyRemoveEmptyDirectory(UPLOAD_DIR . "players/$profileId/thumbs");
	PrivacyRemoveEmptyDirectory(UPLOAD_DIR . "players/$profileId");
}

function PrivacyRemoveEmptyDirectory($path)
{
	$path = trim((string)$path);
	if ($path === '' || !is_dir($path)) {
		return;
	}

	$entries = scandir($path);
	if ($entries === false) {
		return;
	}

	foreach ($entries as $entry) {
		if ($entry !== '.' && $entry !== '..') {
			return;
		}
	}

	rmdir($path);
}
