<?php
require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/image.functions.php';
require_once __DIR__ . '/url.functions.php';
require_once __DIR__ . '/common.functions.php';

/**
 * Set player details.
 *
 * @param int $playerId
 * @param int $number
 * @param string $fname
 * @param string $lname
 * @param string $accrId
 */
function SetPlayer($playerId, $number, $fname, $lname, $accrId, $profileId)
{
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayersRight($playerInfo['team'])) {
    $defaultNumber = 0;
    if ($number < 0) {
      $number = "null";
    } else {
      $defaultNumber = (int)$number;
      $number = (int)$number;
    }
    //echo "<p>".$profileId."</p>";
    $query = sprintf(
      "UPDATE uo_player SET num=%s, firstname='%s', lastname='%s', accreditation_id='%s',
    		profile_id='%s'
			WHERE player_id=%d",
      $number,
      DBEscapeString($fname),
      DBEscapeString($lname),
      DBEscapeString($accrId),
      DBEscapeString($profileId),
      (int)$playerId
    );
    $result = DBQuery($query);
    if ($result && $defaultNumber > 0 && !empty($profileId)) {
      SetPlayerProfileDefaultNumberIfEmpty($profileId, $defaultNumber);
    }
    return $result;
  } else {
    die("Insufficient rights to edit player");
  }
}


/**
 * Update player's jersey number
 * 
 * @param int $playerId
 * @param int $number
 * 
 */
function SetPlayerNumber($playerId,$number) {
  
  $playerInfo = PlayerInfo($playerId);
  if (!$playerInfo) {
    die("Invalid player");
  }

  if (hasEditPlayersRight($playerInfo['team'])) {
    $normalized = is_string($number) ? trim($number) : $number;
    $numberSql = "NULL";

    if ($normalized !== "" && $normalized !== null) {
      $intNumber = filter_var(
        $normalized,
        FILTER_VALIDATE_INT,
        array("options" => array("min_range" => 0, "max_range" => 99))
      );
      if ($intNumber !== false) {
        $numberSql = (string)$intNumber;
      }
    }

    $query = sprintf("UPDATE uo_player SET num=%s WHERE player_id=%d", $numberSql, (int) $playerId);
    return DBQuery($query);
  } else { die("Insufficient rights to edit player"); }
}


/**
 * Set profile default jersey number if it is currently empty.
 *
 * @param int $profileId
 * @param int $number
 */
function SetPlayerProfileDefaultNumberIfEmpty($profileId, $number)
{
  if (empty($profileId) || (int)$number <= 0) {
    return false;
  }

  $query = sprintf(
    "UPDATE uo_player_profile SET num=%d
		WHERE profile_id=%d AND (num IS NULL OR num <= 0)",
    (int)$number,
    (int)$profileId
  );

  return DBQuery($query);
}

/**
 * Find an existing player profile for given identity data.
 * Matching is strict and considered a hit only in these cases:
 * 1) Exact same non-empty accreditation_id
 * 2) Exact same valid e-mail (case-insensitive) AND same firstname + lastname
 * 3) Exact same firstname + lastname + birthdate (date part)
 *
 * E-mail alone is not enough because it can be shared (for example parent's
 * address). If both accreditation_id and e-mail+name point to different
 * profiles, return 0 (ambiguous conflict). For multiple hits, choose the most
 * referred profile (uo_player_stats refs + uo_player refs, then lower profile_id).
 *
 * @param array $profile
 * @return int profile_id or 0 when no safe match exists
 */
function FindExistingPlayerProfileId($profile)
{
  if (!is_array($profile)) {
    return 0;
  }

  $firstname = isset($profile['firstname']) ? trim((string)$profile['firstname']) : "";
  $lastname = isset($profile['lastname']) ? trim((string)$profile['lastname']) : "";
  $accreditationId = isset($profile['accreditation_id']) ? trim((string)$profile['accreditation_id']) : "";
  $email = isset($profile['email']) ? trim((string)$profile['email']) : "";
  $birthdate = isset($profile['birthdate']) ? trim((string)$profile['birthdate']) : "";
  $normalizedBirthdate = "";
  if (!empty($birthdate) && !isEmptyDate($birthdate)) {
    $timestamp = strtotime($birthdate);
    if ($timestamp !== false) {
      $normalizedBirthdate = date('Y-m-d', $timestamp);
    }
  }

  $matchCount = function ($condition) {
    $query = "SELECT COUNT(*) FROM uo_player_profile pr WHERE " . $condition;
    return (int)DBQueryToValue($query);
  };

  $bestMatchId = function ($condition) {
    $query = "
      SELECT pr.profile_id
      FROM uo_player_profile pr
      LEFT JOIN (
        SELECT profile_id, COUNT(*) AS cnt
        FROM uo_player_stats
        GROUP BY profile_id
      ) ps ON ps.profile_id = pr.profile_id
      LEFT JOIN (
        SELECT profile_id, COUNT(*) AS cnt
        FROM uo_player
        GROUP BY profile_id
      ) p ON p.profile_id = pr.profile_id
      WHERE " . $condition . "
      ORDER BY (COALESCE(ps.cnt, 0) + COALESCE(p.cnt, 0)) DESC, COALESCE(ps.cnt, 0) DESC, pr.profile_id ASC
      LIMIT 1";
    return (int)DBQueryToValue($query);
  };

  $accreditationMatchId = 0;
  if (!empty($accreditationId) && $accreditationId !== "0") {
    $condition = sprintf("pr.accreditation_id='%s'", DBEscapeString($accreditationId));
    if ($matchCount($condition) > 0) {
      $accreditationMatchId = $bestMatchId($condition);
    }
  }

  $hasName = !empty($firstname) && !empty($lastname);
  $nameCondition = "";
  if ($hasName) {
    $nameCondition = sprintf(
      "LOWER(TRIM(pr.firstname))='%s' AND LOWER(TRIM(pr.lastname))='%s'",
      DBEscapeString(strtolower($firstname)),
      DBEscapeString(strtolower($lastname))
    );
  }

  $emailNameMatchId = 0;
  if ($hasName && !empty($email) && validEmail($email)) {
    $emailNameCondition = sprintf(
      "LOWER(pr.email)='%s' AND %s",
      DBEscapeString(strtolower($email)),
      $nameCondition
    );

    // Prefer stricter match when birthdate is available.
    if (!empty($normalizedBirthdate)) {
      $emailNameBirthdateCondition = $emailNameCondition . sprintf(
        " AND DATE(pr.birthdate)='%s'",
        DBEscapeString($normalizedBirthdate)
      );
      if ($matchCount($emailNameBirthdateCondition) > 0) {
        $emailNameMatchId = $bestMatchId($emailNameBirthdateCondition);
      }
    }

    if ($emailNameMatchId === 0 && $matchCount($emailNameCondition) > 0) {
      $emailNameMatchId = $bestMatchId($emailNameCondition);
    }
  }

  if ($accreditationMatchId > 0 && $emailNameMatchId > 0 && $accreditationMatchId != $emailNameMatchId) {
    return 0;
  }
  if ($accreditationMatchId > 0) {
    return $accreditationMatchId;
  }
  if ($emailNameMatchId > 0) {
    return $emailNameMatchId;
  }

  if (!$hasName || empty($normalizedBirthdate)) {
    return 0;
  }

  $nameBirthdateCondition = sprintf(
    "%s AND DATE(pr.birthdate)='%s'",
    $nameCondition,
    DBEscapeString($normalizedBirthdate)
  );

  if ($matchCount($nameBirthdateCondition) > 0) {
    return $bestMatchId($nameBirthdateCondition);
  }

  return 0;
}

/**
 * Create profile for player.
 *
 * @param unknown_type $playerId
 */
function CreatePlayerProfile($playerId)
{
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayersRight($playerInfo['team'])) {
    $existingProfileId = FindExistingPlayerProfileId(array(
      "firstname" => $playerInfo['firstname'],
      "lastname" => $playerInfo['lastname'],
      "accreditation_id" => $playerInfo['accreditation_id'],
      "email" => $playerInfo['email'],
      "birthdate" => $playerInfo['birthdate']
    ));

    if ($existingProfileId > 0) {
      $query = sprintf(
        "UPDATE uo_player SET profile_id=%d
			WHERE player_id=%d",
        (int)$existingProfileId,
        (int)$playerId
      );
      DBQuery($query);
      return;
    }

    $query = sprintf(
      "INSERT INTO uo_player_profile (firstname,lastname,accreditation_id,num) VALUES
				('%s','%s','%s','%s')",
      DBEscapeString($playerInfo['firstname']),
      DBEscapeString($playerInfo['lastname']),
      DBEscapeString($playerInfo['accreditation_id']),
      DBEscapeString($playerInfo['num'])
    );
    $profileId = DBQueryInsert($query);

    $query = sprintf(
      "UPDATE uo_player SET profile_id=%d
			WHERE player_id=%d",
      (int)$profileId,
      (int)$playerId
    );
    $result = DBQuery($query);
  } else {
    die("Insufficient rights to edit player");
  }
}

/**
 * Gets players.
 *
 * @param unknown_type $filter
 * @param unknown_type $ordering
 */
function Players($filter = null, $ordering = null)
{
  if (!isset($ordering)) {
    $ordering = array("season.starttime" => "ASC", "series.ordering" => "ASC", "pool.ordering" => "ASC");
  }
  $tables = array("uo_player" => "player", "uo_team" => "team", "uo_pool" => "pool", "uo_series" => "series", "uo_season" => "season");
  $orderby = CreateOrdering($tables, $ordering);
  $where = CreateFilter($tables, $filter);
  $query = "SELECT player_id, CONCAT(player.firstname, ' ', player.lastname) as name, num,
		player.firstname, player.lastname, player.accredited, player.accreditation_id, player.profile_id
		FROM uo_player player
		LEFT JOIN uo_team team ON (player.team=team.team_id)
		LEFT JOIN uo_pool pool ON (team.pool=pool.pool_id)
		LEFT JOIN uo_series series ON (team.series=series.series_id)
		LEFT JOIN uo_season season ON (series.season=season.season_id)
		$where $orderby";
  return DBQuery(trim($query));
}

/**
 * Player info.
 * 
 * @param int $playerId
 */
function PlayerInfo($playerId)
{
  $query = sprintf(
    "SELECT p.player_id, p.profile_id, CONCAT(p.firstname, ' ', p.lastname) as name, p.firstname,
		p.lastname, p.num, p.accreditation_id, p.team, t.name AS teamname, p.accredited, 
		p.team, t.series, ser.type, ser.name AS seriesname, pp.profile_image, pp.email, pp.gender,
		pp.birthdate
		FROM uo_player p 
		LEFT JOIN uo_team t ON (p.team=t.team_id) 
		LEFT JOIN uo_series ser ON (ser.series_id=t.series)
		LEFT JOIN uo_player_profile pp ON (p.profile_id=pp.profile_id)
		WHERE player_id='%s'",
    DBEscapeString($playerId)
  );

  return DBQueryToRow($query);
}

/**
 * Gets latest playerId for given profile id.
 * @param int $profileId
 */
function PlayerLatestId($profileId)
{
  if (!empty($profileId)) {
    $query = sprintf(
      "SELECT MAX(p.player_id) FROM uo_player p
			LEFT JOIN uo_team t ON (p.team=t.team_id) 
			LEFT JOIN uo_series ser ON (ser.series_id=t.series)
			WHERE p.profile_id=%d",
      (int)$profileId
    );

    return DBQueryToValue($query);
  }
  return -1;
}

function PlayerListAll($lastname = "")
{
  $query = "SELECT MAX(player_id) AS player_id, firstname, lastname, num, accreditation_id, profile_id, team, uo_team.name AS teamname
		FROM uo_player p 
		LEFT JOIN uo_team ON p.team=team_id
		WHERE accredited=1";
  if (!empty($lastname) && $lastname != "ALL") {
    $query .= " AND UPPER(lastname) LIKE '" . DBEscapeString($lastname) . "%'";
  }

  $query .= " GROUP BY profile_id, firstname, lastname ORDER BY lastname, firstname";

	return DBQuery($query);
}

function SeasonPlayersMissingNumbers($season)
{
	return DBQueryToArray(sprintf(
		"SELECT p.num, p.firstname, p.lastname, t.name AS team, t.team_id AS team_id, s.name AS division
		FROM uo_player p
		JOIN uo_team t ON p.team = t.team_id
		JOIN uo_series s ON t.series = s.series_id
		WHERE s.season = '%s' AND p.num IS NULL
		ORDER BY division, team, p.num",
		DBEscapeString($season)
	));
}

function SeasonPlayersDuplicateNumbers($season)
{
	return DBQueryToArray(sprintf(
		"SELECT p.num, p.firstname, p.lastname, t.name AS team, t.team_id AS team_id, s.name AS division
		FROM uo_player p
		JOIN (
		SELECT num, team, COUNT(*) AS duplicates
		FROM uo_player
		GROUP BY num, team
		HAVING COUNT(*) > 1
		) dups ON p.num = dups.num
		AND p.team = dups.team
		JOIN uo_team t ON p.team = t.team_id
		JOIN uo_series s ON t.series = s.series_id
		WHERE s.season = '%s'
		ORDER BY division, team, p.num",
		DBEscapeString($season)
	));
}

function PlayerListAllArray($lastname = "")
{
  return DBFetchAllAssoc(PlayerListAll($lastname));
}

/**
 * Returns player name. 
 * 
 * @param int $playerId
 */
function PlayerName($playerId)
{
  $query = sprintf(
    "SELECT firstname, lastname 
		FROM uo_player p 
		WHERE player_id='%s'",
    DBEscapeString($playerId)
  );

  $row = DBQueryToRow($query);
  if (!$row) {
    return '';
  }
  return $row['firstname'] . " " . $row['lastname'];
}

/**
 * Get Player's profile.
 * 
 * @param int $profileId
 */
function PlayerProfile($profileId)
{
  $query = sprintf(
    " SELECT pp.*
		FROM uo_player_profile pp 
		WHERE pp.profile_id=%d",
    (int)$profileId
  );

  return DBQueryToRow($query);
}

/**
 * Get player info by accreditation id.
 * 
 * @param int $accrId
 * @param int $series
 */
function PlayerInfoByAccrId($accrId, $series)
{
  $query = sprintf(
    "SELECT player_id, firstname, lastname, num, accreditation_id, profile_id, team, team.name AS teamname, accredited
		FROM uo_player p 
		LEFT JOIN uo_team team ON (p.team=team.team_id)
		LEFT JOIN uo_series ser ON (team.series=ser.series_id)
		WHERE p.accreditation_id='%s' AND ser.series_id=%d",
    DBEscapeString($accrId),
    (int)$series
  );

  return DBQueryToRow($query);
}

/**
 * Get player jersey number in game.
 * 
 * @param int $playerId
 * @param int $gameId
 */
function PlayerNumber($playerId, $gameId)
{
  $query = sprintf(
    "SELECT p.num as defnum, pel.num as game 
		FROM uo_player AS p 
		LEFT JOIN (SELECT player, num FROM uo_played  WHERE game=%d)
			AS pel ON (p.player_id=pel.player) 
		WHERE p.player_id=%d",
    (int)$gameId,
    (int)$playerId
  );

  $result = DBQuery($query);

  if (!mysqli_num_rows($result))
    return -1;

  $row = mysqli_fetch_assoc($result);

  if (is_numeric($row['game'])) {
    return intval($row['game']);
  } else if (is_numeric($row['defnum']) && $row['defnum'] >= 0) {
    return intval($row['defnum']);
  } else {
    return -1;
  }
}

/**
 * Get games where given player has made points on given event.
 * 
 * @param int $playerId
 * @param string $seasonId
 */

function PlayerSeasonGames($playerId, $seasonId)
{
  $query = sprintf(
    "SELECT game_id,hometeam,visitorteam 
		FROM uo_game p 
		WHERE p.pool IN
			(SELECT pool.pool_id 
			FROM uo_pool pool 
				LEFT JOIN uo_series ser ON (pool.series=ser.series_id) 
			WHERE ser.season='%s') 
		AND p.game_id IN (SELECT uo_goal.game FROM uo_goal 
			WHERE scorer=%d OR assist=%d)
		AND p.isongoing=0
		ORDER BY p.time, p.game_id",
    DBEscapeString($seasonId),
    (int)$playerId,
    (int)$playerId
  );

  return DBQueryToArray($query);
}

/**
 * Total number of played games on given season by given player.
 * 
 * @param int $playerId
 * @param string $seasonId
 */
function PlayerSeasonPlayedGames($playerId, $seasonId)
{
  $query = sprintf(
    "
		SELECT COUNT(*) AS games 
		FROM uo_played 
		WHERE game IN (SELECT gp.game FROM uo_game_pool gp 
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game) 
			LEFT JOIN uo_game AS ug ON (pp.game=ug.game_id) 
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1 AND ug.isongoing=0) 
		AND player='%s'", // FIXME ug.hasstarted>0??
    DBEscapeString($seasonId),
    DBEscapeString($playerId),
    DBEscapeString($playerId)
  );

  return DBQueryToValue($query);
}

/**
 * Total number of played games on given season by given player on given team.
 * 
 * @param int $playerId
 * @param int $teamId
 * @param string $seasonId
 */
function PlayerSeasonTeamPlayedGames($playerId, $teamId, $seasonId)
{
  $query = sprintf(
    "SELECT COUNT(*) AS games
		FROM uo_played p
		WHERE p.game IN (SELECT gp.game FROM uo_game_pool gp 
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game)
			LEFT JOIN uo_game AS g ON (g.game_id=gp.game)
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)	
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1
			AND g.isongoing=0
			AND (g.hometeam='%s' OR g.visitorteam='%s')) 
		AND p.player='%s'",
    DBEscapeString($seasonId),
    DBEscapeString($playerId),
    DBEscapeString($teamId),
    DBEscapeString($teamId),
    DBEscapeString($playerId)
  );

  return DBQueryToValue($query);
}

/**
 * Total number of passes on given season by given player.
 * 
 * @param int $playerId
 * @param string $seasonId
 * @return int Total Passes.
 */
function PlayerSeasonPasses($playerId, $seasonId)
{
  $query = sprintf(
    "SELECT COUNT(*) AS passes
		FROM uo_goal 
		WHERE game IN (SELECT gp.game FROM uo_game_pool gp
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game)
			LEFT JOIN uo_game AS ug ON (pp.game=ug.game_id) 			
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1 AND ug.isongoing=0) 
		AND assist='%s'", // FIXME ug.hasstarted>0??
    DBEscapeString($seasonId),
    DBEscapeString($playerId),
    DBEscapeString($playerId)
  );

  return DBQueryToValue($query);
}

/**
 * Total number of goals on given season by given player.
 * 
 * @param int $playerId
 * @param string $seasonId
 * @return int Total Goals.
 */
function PlayerSeasonGoals($playerId, $seasonId)
{
  $query = sprintf(
    "SELECT COUNT(*) AS goals
		FROM uo_goal 
		WHERE game IN (SELECT gp.game FROM uo_game_pool gp
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game)
			LEFT JOIN uo_game AS ug ON (pp.game=ug.game_id) 
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)			
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1 AND ug.isongoing=0) 
		AND scorer='%s'", // FIXME ug.hasstarted>0??
    DBEscapeString($seasonId),
    DBEscapeString($playerId),
    DBEscapeString($playerId)
  );

  return DBQueryToValue($query);
}

/**
 * Total number of defenses on given season by given player.
 * 
 * @param int $playerId
 * @param string $seasonId
 */
function PlayerSeasonDefenses($playerId, $seasonId)
{
  $query = sprintf(
    "SELECT COUNT(*) AS defenses
		FROM uo_defense
		WHERE game IN (SELECT gp.game FROM uo_game_pool gp
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game)
			LEFT JOIN uo_game AS ug ON (pp.game=ug.game_id) 
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)			
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1 AND ug.isongoing=0) 
		AND author='%s'", // FIXME ug.hasstarted>0??
    DBEscapeString($seasonId),
    DBEscapeString($playerId),
    DBEscapeString($playerId)
  );

  return DBQueryToValue($query);
}


/**
 * Total number of callahan goals on given season by given player.
 * 
 * @param int $playerId
 * @param string $seasonId
 */
function PlayerSeasonCallahanGoals($playerId, $seasonId)
{
  $query = sprintf(
    "SELECT COUNT(*) AS goals
		FROM uo_goal 
		WHERE game IN (SELECT gp.game FROM uo_game_pool gp
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game)
			LEFT JOIN uo_game AS ug ON (pp.game=ug.game_id) 
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)			
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1 AND ug.isongoing=0) 
		AND scorer='%s' AND iscallahan=1",
    DBEscapeString($seasonId),
    DBEscapeString($playerId),
    DBEscapeString($playerId)
  );

  return DBQueryToValue($query);
}

/**
 * Total number of wins on given season by given player.
 * 
 * @param int $playerId
 * @param string $seasonId
 */
function PlayerSeasonWins($playerId, $teamId, $seasonId)
{
  $query = sprintf(
    "SELECT COUNT(*) AS wins
		FROM uo_played p
		WHERE p.game IN (SELECT gp.game FROM uo_game_pool gp 
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game)
			LEFT JOIN uo_game AS g ON (g.game_id=gp.game)
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)	
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1
			AND g.isongoing=0
			AND ((g.homescore>g.visitorscore AND g.hometeam='%s')
			OR (g.homescore<g.visitorscore AND g.visitorteam='%s'))) 
		AND p.player='%s'",
    DBEscapeString($seasonId),
    DBEscapeString($playerId),
    DBEscapeString($teamId),
    DBEscapeString($teamId),
    DBEscapeString($playerId)
  );

  return DBQueryToValue($query);
}

/**
 * Player's game events in given game.
 * 
 * @param int $playerId
 * @param int $gameId
 */
function PlayerGameEvents($playerId, $gameId)
{
  $query = sprintf(
    " SELECT time,homescore,visitorscore,assist,scorer,iscallahan 
		FROM uo_goal 
		WHERE game=%d AND (scorer=%d OR assist=%d) 
		ORDER BY time",
    (int)$gameId,
    (int)$playerId,
    (int)$playerId
  );

  return DBQueryToArray($query);
}

/**
 * Add or update player profile.
 * 
 * @param int $teamId
 * @param int $playerId
 * @param int $profile
 */
function SetPlayerProfile($teamId, $playerId, $profile)
{
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId) && $playerInfo['team'] == $teamId) {
    $profileId = (int)$playerInfo['profile_id'];

    $query = sprintf(
      "SELECT pp.profile_id
				FROM uo_player_profile pp 
				WHERE pp.profile_id=%d",
      $profileId
    );

    $exist = DBQueryRowCount($query);

    //SetPlayer($playerId, $profile['num'], $profile['firstname'], $profile['lastname'], $profile['accreditation_id']);
    if (empty($profile['num']) || $profile['num'] < 0) {
      $number = "null";
    } else {
      $number = (int)$profile['num'];
    }

    //update player data according profile data
    $query = sprintf(
      "UPDATE uo_player SET num=%s, firstname='%s', lastname='%s', accreditation_id='%s'
			WHERE player_id=%d",
      $number,
      DBEscapeString($profile['firstname']),
      DBEscapeString($profile['lastname']),
      DBEscapeString($profile['accreditation_id']),
      (int)$playerId
    );

    DBQuery($query);

    //add
    if (!$exist) {
      $existingProfileId = FindExistingPlayerProfileId($profile);
      if ($existingProfileId > 0) {
        $profileId = (int)$existingProfileId;
        $query = sprintf(
          "UPDATE uo_player SET profile_id=%d WHERE player_id=%d",
          $profileId,
          (int)$playerId
        );
        DBQuery($query);
        $exist = 1;
      }
    }

    if (!$exist) {
      $query = sprintf(
        "INSERT INTO uo_player_profile (accreditation_id, firstname,
			lastname, num, email, nickname, gender, info, national_id, birthdate, birthplace, nationality, 
			throwing_hand, height, weight, position, story, achievements, public) VALUES 
			('%s', '%s','%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
			'%s', '%s', '%s', '%s', '%s', '%s')",
        DBEscapeString($profile['accreditation_id']),
        DBEscapeString($profile['firstname']),
        DBEscapeString($profile['lastname']),
        (int)($profile['num']),
        DBEscapeString($profile['email']),
        DBEscapeString($profile['nickname']),
        DBEscapeString($profile['gender']),
        DBEscapeString($profile['info']),
        DBEscapeString($profile['national_id']),
        DBEscapeString($profile['birthdate']),
        DBEscapeString($profile['birthplace']),
        DBEscapeString($profile['nationality']),
        DBEscapeString($profile['throwing_hand']),
        DBEscapeString($profile['height']),
        DBEscapeString($profile['weight']),
        DBEscapeString($profile['position']),
        DBEscapeString($profile['story']),
        DBEscapeString($profile['achievements']),
        DBEscapeString($profile['public'])
      );

      $profileId = DBQueryInsert($query);
      $query = sprintf(
        "UPDATE uo_player SET profile_id=%d WHERE player_id=%d",
        $profileId,
        (int)$playerId
      );

      DBQuery($query);
    } else {
      $query = sprintf(
        "UPDATE uo_player_profile SET accreditation_id='%s', email='%s', firstname='%s', lastname='%s', num='%s',
			nickname='%s', gender='%s', info='%s', national_id='%s', birthdate='%s', birthplace='%s', nationality='%s', throwing_hand='%s', 
			height='%s', weight='%s', position='%s', story='%s', achievements='%s', public='%s' WHERE profile_id='%s'",
        DBEscapeString($profile['accreditation_id']),
        DBEscapeString($profile['email']),
        DBEscapeString($profile['firstname']),
        DBEscapeString($profile['lastname']),
        (int)($profile['num']),
        DBEscapeString($profile['nickname']),
        DBEscapeString($profile['gender']),
        DBEscapeString($profile['info']),
        DBEscapeString($profile['national_id']),
        DBEscapeString($profile['birthdate']),
        DBEscapeString($profile['birthplace']),
        DBEscapeString($profile['nationality']),
        DBEscapeString($profile['throwing_hand']),
        DBEscapeString($profile['height']),
        DBEscapeString($profile['weight']),
        DBEscapeString($profile['position']),
        DBEscapeString($profile['story']),
        DBEscapeString($profile['achievements']),
        DBEscapeString($profile['public']),
        DBEscapeString($profileId)
      );

      DBQuery($query);
    }

    LogPlayerProfileUpdate($playerId);
  } else {
    die('Insufficient rights to edit player profile');
  }
}

// Update names and number on player profile
function UpdatePlayerProfile($profileId, $first, $last, $num) {
  $profileId = (int)$profileId;
  $playerId = (int)PlayerLatestId($profileId);
  if ($profileId <= 0 || $playerId <= 0) {
    die('Invalid player profile');
  }
  if (!hasEditPlayerProfileRight($playerId)) {
    die('Insufficient rights to edit player profile');
  }

  $normalized = is_string($num) ? trim($num) : $num;
  $numSql = "NULL";
  if ($normalized !== "" && $normalized !== null) {
    $intNum = filter_var(
      $normalized,
      FILTER_VALIDATE_INT,
      array("options" => array("min_range" => 0, "max_range" => 99))
    );
    if ($intNum !== false) {
      $numSql = (string)$intNum;
    }
  }

  $query = sprintf("UPDATE uo_player_profile 
    SET firstname = '%s', lastname = '%s', num = %s 
    WHERE profile_id = %d",
    DBEscapeString($first),
    DBEscapeString($last),
    $numSql,
    $profileId
  );
  DBQuery($query);
}

/**
 * Add image on player profile.
 * 
 * @param int $playerId
 */
function UploadPlayerImage($playerId)
{
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId)) {
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
    $imgname = time() . $playerInfo['profile_id'] . ".jpg";
    $basedir = "" . UPLOAD_DIR . "players/" . $playerInfo['profile_id'] . "/";
    if (!is_dir($basedir)) {
      recur_mkdirs($basedir, 0775);
      recur_mkdirs($basedir . "thumbs/", 0775);
    }

    ConvertToJpeg($file_tmp_name, $basedir . $imgname);
    CreateThumb($basedir . $imgname, $basedir . "thumbs/" . $imgname, 120, 160);

    //currently removes old image, in future there might be a gallery of images
    RemovePlayerProfileImage($playerId);
    SetPlayerProfileImage($playerId, $imgname);

    return "";
  } else {
    die('Insufficient rights to upload image');
  }
}

/**
 * Set profile image for player.
 * 
 * @param int $playerId
 * @param string $filename
 */
function SetPlayerProfileImage($playerId, $filename)
{
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId)) {

    $query = sprintf(
      "UPDATE uo_player_profile SET profile_image='%s' WHERE profile_id='%s'",
      DBEscapeString($filename),
      DBEscapeString($playerInfo['profile_id'])
    );

    DBQuery($query);
  } else {
    die('Insufficient rights to edit player profile');
  }
}

function RemovePlayerProfileImage($playerId)
{
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId)) {

    $profile = PlayerProfile($playerInfo['profile_id']);

    if (!empty($profile['profile_image'])) {

      //thumbnail
      $file = "" . UPLOAD_DIR . "players/" . $playerInfo['profile_id'] . "/thumbs/" . $profile['profile_image'];
      if (is_file($file)) {
        unlink($file); //  remove old images if present
      }

      //image
      $file = "" . UPLOAD_DIR . "players/" . $playerInfo['profile_id'] . "/" . $profile['profile_image'];

      if (is_file($file)) {
        unlink($file); //  remove old images if present
      }

      $query = sprintf(
        "UPDATE uo_player_profile SET profile_image=NULL WHERE profile_id='%s'",
        DBEscapeString($playerInfo['profile_id'])
      );

      DBQuery($query);
    }
  } else {
    die('Insufficient rights to edit player profile');
  }
}

/**
 * Add url into player profile.
 * 
 * @param int $playerId
 * @param string $type
 * @param string $url
 * @param string $name
 */
function AddPlayerProfileUrl($playerId, $type, $url, $name)
{
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId)) {
    $url = SafeUrl($url);
    $query = sprintf(
      "INSERT INTO uo_urls (owner,owner_id,type,name,url)
				VALUES('player',%d,'%s','%s','%s')",
      (int)$playerInfo['profile_id'],
      DBEscapeString($type),
      DBEscapeString($name),
      DBEscapeString($url)
    );
    return DBQuery($query);
  } else {
    die('Insufficient rights to add url');
  }
}

/**
 * Remove URL form plater profile.
 *
 * @param int $playerId
 * @param int $urlId
 */
function RemovePlayerProfileUrl($playerId, $urlId)
{
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId)) {
    $query = sprintf(
      "DELETE FROM uo_urls WHERE url_id=%d",
      (int)$urlId
    );
    return DBQuery($query);
  } else {
    die('Insufficient rights to remove url');
  }
}

/**
 * Returns all event player in CVS format.
 * 
 * @param string $season
 * @param string $separator
 */
function PlayersToCsv($season, $separator)
{

  $query = sprintf(
    "
		SELECT p.firstname AS FirstName, p.lastname AS LastName, p.num AS Jersey, j.name AS TeamName, 
		j.abbreviation AS TeamAbbreviation, club.name AS Club, divi.name AS Division, c.name AS Country,
		pel.games AS Games, 
		COALESCE(s.fedin,0) AS Assists, COALESCE(t.done,0) AS Goals, 
		COALESCE(t1.callahan,0) AS Callahans, (COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS Total
		FROM uo_player AS p 
		LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done FROM uo_goal AS m 
			LEFT JOIN uo_game_pool AS ps ON (m.game=ps.game)
			LEFT JOIN uo_pool pool ON(ps.pool=pool.pool_id)
			LEFT JOIN uo_series ser ON(ser.series_id=pool.series)
			LEFT JOIN uo_game AS g1 ON (ps.game=g1.game_id)
			WHERE ser.season='%s' AND ps.timetable=1 AND scorer IS NOT NULL AND g1.isongoing=0 GROUP BY scorer) AS t ON (p.player_id=t.scorer)
		LEFT JOIN (SELECT m1.scorer AS scorer1, COUNT(*) AS callahan FROM uo_goal AS m1 
			LEFT JOIN uo_game_pool AS ps1 ON (m1.game=ps1.game)
			LEFT JOIN uo_pool pool ON(ps1.pool=pool.pool_id)
			LEFT JOIN uo_series ser ON(ser.series_id=pool.series)
			LEFT JOIN uo_game AS g2 ON (ps1.game=g2.game_id)
			WHERE ser.season='%s' AND ps1.timetable=1 AND m1.scorer IS NOT NULL AND g2.isongoing=0  AND iscallahan=1 GROUP BY m1.scorer) AS t1 ON (p.player_id=t1.scorer1)
		LEFT JOIN  (SELECT m2.assist AS assist, COUNT(*) AS fedin 
			FROM uo_goal AS m2 LEFT JOIN uo_game_pool AS ps2 ON (m2.game=ps2.game) 
			LEFT JOIN uo_game AS g3 ON (ps2.game=g3.game_id)
			LEFT JOIN uo_pool pool ON(ps2.pool=pool.pool_id)
			LEFT JOIN uo_series ser ON(ser.series_id=pool.series)
			WHERE ser.season='%s' AND ps2.timetable=1 AND g3.isongoing=0 GROUP BY assist) AS s ON (p.player_id=s.assist) 
		LEFT JOIN uo_team AS j ON(p.team=j.team_id) 
		LEFT JOIN uo_series AS divi ON(divi.series_id=j.series)
		LEFT JOIN uo_country AS c ON(c.country_id=j.country)
		LEFT JOIN uo_club AS club ON(club.club_id=j.club)
		LEFT JOIN (SELECT up.player, COUNT(*) AS games 
			FROM uo_played up
			LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
			LEFT JOIN uo_pool pool ON(g4.pool=pool.pool_id)
			LEFT JOIN uo_series ser ON(ser.series_id=pool.series)
			WHERE ser.season='%s' AND g4.isongoing=0 
			GROUP BY player) AS pel ON (p.player_id=pel.player)
		WHERE divi.season='%s'
		ORDER BY j.name, p.lastname, p.firstname", // FIXME g4.hasstarted>0??
    DBEscapeString($season),
    DBEscapeString($season),
    DBEscapeString($season),
    DBEscapeString($season),
    DBEscapeString($season),
    DBEscapeString($season)
  );

  // Gets the data from the database
  $result = DBQuery($query);
  return ResultsetToCsv($result, $separator);
}
