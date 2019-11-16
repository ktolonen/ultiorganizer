<?php
include_once $include_prefix.'lib/player.functions.php';
include_once $include_prefix.'lib/common.functions.php';

function SeasonUnaccredited($season) {
	$query = sprintf("SELECT p.player_id, p.firstname, p.lastname, pt.name as teamname, 
		pt.team_id as team, ht.name as hometeamname, gt.name as visitorteamname, pp.time, 
		played.acknowledged, pp.game_id, pp.hometeam, pp.visitorteam
	FROM uo_played played 
		LEFT JOIN uo_player p ON (played.player=p.player_id)
		LEFT JOIN uo_game pp ON (played.game=pp.game_id)
		LEFT JOIN uo_team ht ON (pp.hometeam=ht.team_id)
		LEFT JOIN uo_team gt ON (pp.visitorteam=gt.team_id)
		LEFT JOIN uo_team pt ON (p.team=pt.team_id)
		LEFT JOIN uo_reservation res ON (pp.reservation=res.id)
		LEFT JOIN uo_location loc ON (res.location=loc.id)
		LEFT JOIN uo_pool pool ON (pp.pool=pool.pool_id)
		LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
	WHERE played.accredited=0 AND ser.season='%s'", 
	DBEscapeString($season));
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	return $result;
}

function AccreditPlayer($playerId, $source) {
	$playerInfo = PlayerInfo($playerId);
	if (hasAccredidationRight($playerInfo['team'])) {
		$query = sprintf("UPDATE uo_player SET accredited=1 WHERE player_id=%d",
			(int)$playerId);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		AccreditationLogEntry($playerId, $playerInfo['team'], $source, 1);
		checkUserAdmin($playerInfo);
		return $result;
	} else { die('Insufficient rights to accredit player'); }
}

function ExternalLicenseValidityList() {
	return DBQueryToArray("SELECT DISTINCT external_validity FROM uo_license WHERE external_validity IS NOT NULL AND external_validity > 0");
}

function ExternalLicenseTypes() {
	return DBQueryToArray("SELECT DISTINCT external_type FROM uo_license WHERE external_type IS NOT NULL AND external_type > 0");
}

function LicenseData($accreditation_id) {
	return DBQueryToRow("SELECT membership, license, external_id, external_type, external_validity, ultimate 
		FROM uo_license WHERE accreditation_id='".DBEscapeString($accreditation_id)."'");
}

function checkUserAdmin($playerInfo) {
	// Check for existing user for player
	$query = sprintf("SELECT userid FROM uo_userproperties WHERE name='userrole' AND value='playeradmin:%s'",
		DBEscapeString($playerInfo['accreditation_id']));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($userid = mysql_fetch_row($result)) {
		//Player already administered
		return;
	} else {
		//Check for matching emails
		if (validEmail($playerInfo['email'])) {
			$query = sprintf("SELECT userid FROM uo_users  WHERE LOWER(email)='%s'",
				DBEscapeString(strtolower($playerInfo['email'])));
			$result = mysql_query($query);
			if (!$result) { die('Invalid query: ' . mysql_error()); }
			if ($userId = mysql_fetch_row($result)) {
				$id = $userId[0];
				$query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', 'playeradmin:%s')", 
					DBEscapeString($id), 
					DBEscapeString($playerInfo['profile_id']) );
				$result = mysql_query($query);
				if (!$result) { die('Invalid query: ' . mysql_error()); }
				return true;				
			}
		} else {
			return false;
		}
	}

}

function DeAccreditPlayer($playerId, $source) {
	$playerInfo = PlayerInfo($playerId);
	if (hasAccredidationRight($playerInfo['team']) || hasEditPlayersRight($playerInfo['team'])) {
		$query = sprintf("UPDATE uo_player SET accredited=0 WHERE player_id=%d",
			(int)$playerId);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		AccreditationLogEntry($playerId, $playerInfo['team'], $source, 0);	
		return $result;
	} else { die('Insufficient rights to accredit player'); }
}

function AccreditationLogEntry($player, $team, $source, $value, $game = NULL) {
	if (!isset($game)) {
		$gameVal = "NULL";
	} else {
		$gameVal = (int)$game;
	}
	$query = sprintf("INSERT INTO uo_accreditationlog (player, team, userid, source, value, time, game) VALUES (%d, %d, '%s', '%s', %d, now(), %s)",
		(int)$player, 
		(int)$team, 
		DBEscapeString($_SESSION['uid']), 
		DBEscapeString($source), 
		(int)$value,
		$gameVal);
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
}

function isAccredited($playerId) {
	$playerInfo = PlayerInfo($playerId);
	if (isset($playerInfo['accredited'])) return $playerInfo['accredited']; 
	else return 0; 
}

function AcknowledgeUnaccredited($playerId, $gameId, $source) {
	$playerInfo = PlayerInfo($playerId);
	if (hasAccredidationRight($playerInfo['team'])) {
		$query = sprintf("UPDATE uo_played SET acknowledged=1 WHERE player=%d AND game=%d",
			(int)$playerId,
			(int)$gameId);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		AccreditationLogEntry($playerId, $playerInfo['team'], $source, 1, $gameId);	
		return $result;
	} else { die('Insufficient rights to accredit player'); }
}

function UnAcknowledgeUnaccredited($playerId, $gameId, $source) {
	$playerInfo = PlayerInfo($playerId);
	if (hasAccredidationRight($playerInfo['team'])) {
		$query = sprintf("UPDATE uo_played SET acknowledged=0 WHERE player=%d AND game=%d",
			(int)$playerId,
			(int)$gameId);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		AccreditationLogEntry($playerId, $playerInfo['team'], $source, 0, $gameId);	
		return $result;
	} else { die('Insufficient rights to accredit player'); }
}

function AccreditPlayerByAccrId($accrId, $seriesId, $source) {
	if ($playerInfo = PlayerInfoByAccrId($accrId, $seriesId)) {
		if (!$playerInfo['accredited']) {
			AccreditPlayer($playerInfo['player_id'], $source);
		}
	}
}

function DeAccreditPlayerByAccrId($accrId, $seriesId, $source) {
	if ($playerInfo = PlayerInfoByAccrId($accrId, $seriesId)) {
		if ($playerInfo['accredited']) {
			DeAccreditPlayer($playerInfo['player_id'], $source);
		}
	}
}

function SeasonAccreditationLog($season) {
	$query = sprintf("SELECT p.player_id, p.firstname, p.lastname, pt.name as teamname, 
			pt.team_id as team, ht.name as hometeamname, gt.name as visitorteamname, pp.time as gametime, 
			log.value, pp.game_id, user.name as uname, user.email, log.source, log.time, log.game,
			pp.hometeam, pp.visitorteam
		FROM uo_accreditationlog log 
			LEFT JOIN uo_player p ON (log.player=p.player_id)
			LEFT JOIN uo_game pp ON (log.game=pp.game_id)
			LEFT JOIN uo_team ht ON (pp.hometeam=ht.team_id)
			LEFT JOIN uo_team gt ON (pp.visitorteam=gt.team_id)
			LEFT JOIN uo_team pt ON (p.team=pt.team_id)
			LEFT JOIN uo_reservation res ON (pp.reservation=res.id)
			LEFT JOIN uo_location loc ON (res.location=loc.id)
			LEFT JOIN uo_series ser ON (pt.series=ser.series_id)
			LEFT JOIN uo_users user ON (log.userid=user.userid)
		WHERE ser.season='%s'
		ORDER BY log.time DESC", 
	DBEscapeString($season));
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	return $result;
	
}

?>