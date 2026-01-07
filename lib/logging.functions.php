<?php

function EventCategories()
{
	return array("security", "user", "enrolment", "club", "team", "player", "season", "series", "pool", "game", "media");
}

function LogEvent($event)
{
	if (empty($event['id1']))
		$event['id1'] = "";

	if (empty($event['id2']))
		$event['id2'] = "";

	if (empty($event['source']))
		$event['source'] = "";

	if (empty($event['description']))
		$event['description'] = "";

	if (strlen($event['description']) > 50)
		$event['description'] = substr($event['description'], 0, 50);

	if (strlen($event['id1']) > 20)
		$event['id1'] = substr($event['id1'], 0, 20);

	if (strlen($event['id2']) > 20)
		$event['id2'] = substr($event['id2'], 0, 20);

	if (empty($event['user_id'])) {
		if (!empty($_SESSION['uid']))
			$event['user_id'] = $_SESSION['uid'];
		else
			$event['user_id'] = "unknown";
	}

	$event['ip'] = "";
	if (!empty($_SERVER['REMOTE_ADDR']))
		$event['ip'] = $_SERVER['REMOTE_ADDR'];

	$query = sprintf(
		"INSERT INTO uo_event_log (user_id, ip, category, type, source,
			id1, id2, description)
				VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
		DBEscapeString($event['user_id']),
		DBEscapeString($event['ip']),
		DBEscapeString($event['category']),
		DBEscapeString($event['type']),
		DBEscapeString($event['source']),
		DBEscapeString($event['id1']),
		DBEscapeString($event['id2']),
		DBEscapeString($event['description'])
	);
	return DBQueryInsert($query);
}

function EventList($categoryfilter, $userfilter, $limit = null, $offset = null)
{
	if (isSuperAdmin()) {
		if (count($categoryfilter) == 0) {
			return false;
		}
		$query = "SELECT * FROM uo_event_log WHERE ";

		$i = 0;
		foreach ($categoryfilter as $cat) {
			if ($i == 0) {
				$query .= "(";
			}
			if ($i > 0) {
				$query .= " OR ";
			}

			$query .= sprintf("category='%s'", DBEscapeString($cat));
			$i++;
			if ($i == count($categoryfilter)) {
				$query .= ")";
			}
		}

		if (!empty($userfilter)) {
			$query .= sprintf("AND user_id='%s'", DBEscapeString($userfilter));
		}
		$query .= " ORDER BY time DESC";
		if ($limit !== null) {
			$query .= sprintf(" LIMIT %d", intval($limit));
			if ($offset !== null) {
				$query .= sprintf(" OFFSET %d", intval($offset));
			}
		}
		$result = DBQuery($query);

		return $result;
	}
}

function EventCount($categoryfilter, $userfilter)
{
	if (isSuperAdmin()) {
		if (count($categoryfilter) == 0) {
			return 0;
		}
		$query = "SELECT COUNT(*) AS total FROM uo_event_log WHERE ";

		$i = 0;
		foreach ($categoryfilter as $cat) {
			if ($i == 0) {
				$query .= "(";
			}
			if ($i > 0) {
				$query .= " OR ";
			}

			$query .= sprintf("category='%s'", DBEscapeString($cat));
			$i++;
			if ($i == count($categoryfilter)) {
				$query .= ")";
			}
		}

		if (!empty($userfilter)) {
			$query .= sprintf("AND user_id='%s'", DBEscapeString($userfilter));
		}
		$result = DBQuery($query);
		if (!$result) {
			return 0;
		}
		$row = mysqli_fetch_assoc($result);
		return intval($row['total']);
	}
	return 0;
}

function ClearEventList($ids)
{
	if (isSuperAdmin()) {
		$query = sprintf("DELETE FROM uo_event_log WHERE event_id IN (%s)", DBEscapeString($ids));

		$result = DBQuery($query);

		return $result;
	}
}

function Log1($category, $type, $id1 = "", $id2 = "", $description = "", $source = "")
{
	$event['category'] = $category;
	$event['type'] = $type;
	$event['id1'] = $id1;
	$event['id2'] = $id2;
	$event['description'] = $description;
	$event['source'] = $source;
	return LogEvent($event);
}

function Log2($category, $type, $description = "", $source = "")
{
	$event['category'] = $category;
	$event['type'] = $type;
	$event['description'] = $description;
	$event['source'] = $source;
	return LogEvent($event);
}

function LogPlayerProfileUpdate($playerId, $source = "")
{
	$event['category'] = "player";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $playerId;
	$event['description'] = "profile updated";
	return LogEvent($event);
}

function LogTeamProfileUpdate($teamId, $source = "")
{
	$event['category'] = "team";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $teamId;
	$event['description'] = "profile updated";
	return LogEvent($event);
}

function LogUserAuthentication($userId, $result, $source = "")
{
	$event['user_id'] = $userId;
	$event['category'] = "security";
	$event['type'] = "authenticate";
	$event['source'] = $source;
	$event['description'] = $result;
	return LogEvent($event);
}

function LogGameResult($gameId, $result, $source = "")
{
	$event['category'] = "game";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $gameId;
	$event['description'] = $result;
	return LogEvent($event);
}

function LogDefenseResult($gameId, $result, $source = "")
{
	$event['category'] = "defense";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $gameId;
	$event['description'] = $result;
	return LogEvent($event);
}

function LogGameUpdate($gameId, $details, $source = "")
{
	$event['category'] = "game";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $gameId;
	$event['description'] = $details;
	return LogEvent($event);
}

function LogDefenseUpdate($gameId, $details, $source = "")
{
	$event['category'] = "defense";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $gameId;
	$event['description'] = $details;
	return LogEvent($event);
}

function GetLastGameUpdateEntry($gameId, $source)
{
	$query = sprintf(
		"SELECT * FROM uo_event_log WHERE id1=%d AND source='%s' ORDER BY TIME DESC",
		(int) $gameId,
		DBEscapeString($source)
	);
	$result = DBQueryToRow($query);

	return $result;
}

function LogPoolUpdate($poolId, $details, $source = "")
{
	$event['category'] = "pool";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $poolId;
	$event['description'] = $details;
	return LogEvent($event);
}

function LogDbUpgrade($version, $end = false, $source = "")
{
	$event['category'] = "database";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $version;
	$event['description'] = $end ? "finished" : "started";
	return LogEvent($event);
}

/**
 * Log page load into database for usage statistics.
 *
 * @param string $page
 *          - loaded page
 */
function LogPageLoad($page)
{
	// Guard against logging raw or clearly invalid input
	if (empty($page) || !preg_match('/^[a-z0-9_\\/\\-]+$/i', $page)) {
		return;
	}

	$query = sprintf(
		"SELECT loads FROM uo_pageload_counter WHERE page='%s'",
		DBEscapeString($page)
	);
	$loads = DBQueryToValue($query);

	if ($loads < 0) {
		$query = sprintf(
			"INSERT INTO uo_pageload_counter (page, loads) VALUES ('%s',%d)",
			DBEscapeString($page),
			1
		);
		DBQuery($query);
	} else {
		$loads++;
		$query = sprintf(
			"UPDATE uo_pageload_counter SET loads=%d WHERE page='%s'",
			$loads,
			DBEscapeString($page)
		);
		DBQuery($query);
	}
}

/**
 * Log visitors visit into database for usage statistics.
 * 
 * @param string $ip - ip address
 */
function LogVisitor($ip)
{

	$query = sprintf(
		"SELECT visits FROM uo_visitor_counter WHERE ip='%s'",
		DBEscapeString($ip)
	);
	$visits = DBQueryToValue($query);

	if ($visits < 0) {
		$query = sprintf(
			"INSERT INTO uo_visitor_counter (ip, visits) VALUES ('%s',%d)",
			DBEscapeString($ip),
			1
		);
		DBQuery($query);
	} else {
		$visits++;
		$query = sprintf(
			"UPDATE uo_visitor_counter SET visits=%d WHERE ip='%s'",
			$visits,
			DBEscapeString($ip)
		);
		DBQuery($query);
	}
}

/**
 * Get visitor count.
 */
function LogGetVisitorCount()
{
	$query = sprintf("SELECT SUM(visits) AS visits, COUNT(ip) AS visitors FROM uo_visitor_counter");
	return DBQueryToRow($query);
}

/**
 * Get page loads.
 */
function LogGetPageLoads()
{
	$query = sprintf("SELECT page, loads FROM uo_pageload_counter ORDER BY loads DESC");
	return DBQueryToArray($query);
}

/**
 * Clear visitor counter table.
 */
function LogResetVisitorCounter()
{
	if (!isSuperAdmin()) {
		return false;
	}
	$result = DBQuery("DELETE FROM uo_visitor_counter");
	if ($result) {
		$timestamp = date('Y-m-d H:i:s');
		SetServerConfValue('VisitorCounterResetAt', $timestamp);
		global $serverConf;
		$serverConf['VisitorCounterResetAt'] = $timestamp;
	}
	return $result;
}

/**
 * Clear page load counter table.
 */
function LogResetPageLoadCounter()
{
	if (!isSuperAdmin()) {
		return false;
	}
	$result = DBQuery("DELETE FROM uo_pageload_counter");
	if ($result) {
		$timestamp = date('Y-m-d H:i:s');
		SetServerConfValue('PageLoadCounterResetAt', $timestamp);
		global $serverConf;
		$serverConf['PageLoadCounterResetAt'] = $timestamp;
	}
	return $result;
}
