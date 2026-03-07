<?php

include_once $include_prefix . 'lib/user.functions.php';

function SpiritMode($mode_id)
{
	return SpiritCategoryModeRow($mode_id);
}

function SpiritModes()
{
	return SpiritCategoryModeRows();
}

function ShowSpiritScoresForSeason($seasoninfo)
{
	if (!$seasoninfo || !isset($seasoninfo['season_id'])) {
		return false;
	}
	return (
		isset($seasoninfo['spiritmode']) &&
		(int)$seasoninfo['spiritmode'] > 0 &&
		(!empty($seasoninfo['showspiritpoints']) || isSeasonAdmin($seasoninfo['season_id']))
	);
}

function SpiritCategories($mode_id)
{
	$cats = SpiritCategoryRows($mode_id);
	$categories = array();
	foreach ($cats as $cat) {
		$categories[$cat['category_id']] = $cat;
	}
	return $categories;
}

function SpiritTotal($points, $categories)
{
	$allset = true;
	$total = 0;
	foreach ($categories as $cat) {
		if ($cat['index'] > 0) {
			if (isset($points[$cat['category_id']])) {
				$total += $points[$cat['category_id']] * $cat['factor'];
			} else {
				$allset = false;
			}
		}
	}
	if ($allset) {
		return $total;
	}
	return null;
}

function SpiritCategoryModeRow($modeId)
{
	$query = sprintf(
		"SELECT mode, text AS name FROM `uo_spirit_category`
		WHERE `mode` = %d AND `index` = 0",
		(int)$modeId
	);
	return DBQueryToRow($query);
}

function SpiritCategoryModeRows()
{
	$query = "SELECT mode, text AS name FROM `uo_spirit_category` WHERE `index` = 0";
	return DBQueryToArray($query);
}

function SpiritCategoryRows($modeId)
{
	$query = sprintf(
		"SELECT * FROM `uo_spirit_category`
		WHERE `mode`=%d
		ORDER BY `group` ASC, `index` ASC",
		(int)$modeId
	);
	return DBQueryToArray($query);
}

function SpiritScoreRowsByGameTeam($gameId, $teamId)
{
	$query = sprintf(
		"SELECT * FROM uo_spirit_score WHERE game_id=%d AND team_id=%d",
		(int)$gameId,
		(int)$teamId
	);
	return DBQueryToArray($query);
}

function SpiritToolRowsBySeason($season)
{
	$query = sprintf(
		"SELECT
			g.game_id,
			ssc.team_id,
			s.series_id,
			s.name AS division,
			p.name AS pool,
			g.time,
			IF(ssc.team_id = g.hometeam, th.name, tv.name) AS givenfor,
			IF(ssc.team_id = g.hometeam, tv.name, th.name) AS givenby,
			MAX(CASE WHEN sct.`index` = 1 THEN ssc.value END) AS cat1,
			MAX(CASE WHEN sct.`index` = 2 THEN ssc.value END) AS cat2,
			MAX(CASE WHEN sct.`index` = 3 THEN ssc.value END) AS cat3,
			MAX(CASE WHEN sct.`index` = 4 THEN ssc.value END) AS cat4,
			MAX(CASE WHEN sct.`index` = 5 THEN ssc.value END) AS cat5,
			COALESCE(MAX(CASE WHEN sct.`index` = 1 THEN ssc.value END), 0) +
			COALESCE(MAX(CASE WHEN sct.`index` = 2 THEN ssc.value END), 0) +
			COALESCE(MAX(CASE WHEN sct.`index` = 3 THEN ssc.value END), 0) +
			COALESCE(MAX(CASE WHEN sct.`index` = 4 THEN ssc.value END), 0) +
			COALESCE(MAX(CASE WHEN sct.`index` = 5 THEN ssc.value END), 0) AS total,
			MAX(uc.comment) AS comments
		FROM uo_spirit_score ssc
		LEFT JOIN uo_spirit_category sct ON (sct.category_id = ssc.category_id)
		LEFT JOIN uo_game g ON (g.game_id = ssc.game_id)
		LEFT JOIN uo_pool p ON (p.pool_id = g.pool)
		LEFT JOIN uo_series s ON (s.series_id = p.series)
		LEFT JOIN uo_team th ON (th.team_id = g.hometeam)
		LEFT JOIN uo_team tv ON (tv.team_id = g.visitorteam)
		LEFT JOIN uo_comment uc ON (
			CAST(uc.id AS UNSIGNED) = g.game_id
			AND (
				(ssc.team_id = g.hometeam AND uc.type = 5) OR
				(ssc.team_id = g.visitorteam AND uc.type = 6)
			)
		)
		WHERE s.season='%s'
			AND g.isongoing=0
			AND (COALESCE(g.homescore,0)+COALESCE(g.visitorscore,0))>0
			AND sct.`index` BETWEEN 1 AND 5
		GROUP BY g.game_id, ssc.team_id, s.series_id, s.name, p.name, g.time, givenfor, givenby
		HAVING total > 0
		ORDER BY s.series_id ASC, givenfor ASC, g.time ASC",
		DBEscapeString($season)
	);
	return DBQueryToArray($query);
}

function SpiritToCsv($season, $separator)
{
	$seasoninfo = SeasonInfo($season);
	$showSpiritPoints = ShowSpiritScoresForSeason($seasoninfo);
	if (!$showSpiritPoints) {
		die('Spirit points are not set visible');
	}
	$showSpiritComments = $showSpiritPoints && ShowSpiritComments();
	$rows = SpiritToolRowsBySeason($season);
	$result = array();

		foreach ($rows as $row) {
			$exportRow = array(
				"Division" => $row['division'],
				"Day" => isset($row['day']) ? $row['day'] : "",
				"Field" => isset($row['field']) ? $row['field'] : "",
				"Time" => !empty($row['time']) ? substr($row['time'], 11, 5) : "",
				"Pool" => $row['pool'],
				"TeamEvaluated" => $row['givenfor'],
				"ByTeam" => $row['givenby']
		);
			$exportRow["Rules"] = $row['cat1'];
			$exportRow["Fouls"] = $row['cat2'];
			$exportRow["Fair"] = $row['cat3'];
			$exportRow["Positive"] = $row['cat4'];
			$exportRow["Com"] = $row['cat5'];
			$exportRow["Total"] = $row['total'];
		if ($showSpiritComments) {
			$exportRow["Comments"] = $row['comments'];
		}
		$result[] = $exportRow;
	}

	return ArrayToCsv($result, $separator);
}

function SpiritMissingGamesByPool($poolId)
{
	$query = sprintf(
		"SELECT
			g.game_id,
			th.name AS home,
			tv.name AS visitor,
			g.homescore,
			COALESCE(hspirit.total, 0) AS homesotg,
			g.visitorscore,
			COALESCE(vspirit.total, 0) AS visitorsotg,
			g.time AS time
		FROM uo_game AS g
		JOIN uo_team AS th ON (g.hometeam=th.team_id)
		JOIN uo_team AS tv ON (g.visitorteam=tv.team_id)
		LEFT JOIN (
			SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
			FROM uo_spirit_score ssc
			LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
			GROUP BY ssc.game_id, ssc.team_id
		) AS hspirit ON (g.game_id = hspirit.game_id AND g.hometeam = hspirit.team_id)
		LEFT JOIN (
			SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
			FROM uo_spirit_score ssc
			LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
			GROUP BY ssc.game_id, ssc.team_id
		) AS vspirit ON (g.game_id = vspirit.game_id AND g.visitorteam = vspirit.team_id)
		WHERE g.pool=%d
			AND g.isongoing=0
			AND (COALESCE(g.homescore,0)+COALESCE(g.visitorscore,0))>0
			AND (COALESCE(hspirit.total,0)=0 OR COALESCE(vspirit.total,0)=0)
		ORDER BY g.time ASC",
		(int)$poolId
	);
	return DBQueryToArray($query);
}

function SpiritMissingGamesBySeries($seriesId)
{
	$query = sprintf(
		"SELECT
			g.game_id,
			th.name AS home,
			tv.name AS visitor,
			g.homescore,
			COALESCE(hspirit.total, 0) AS homesotg,
			g.visitorscore,
			COALESCE(vspirit.total, 0) AS visitorsotg,
			g.time AS time,
			p.name AS poolname
		FROM uo_game AS g
		JOIN uo_team AS th ON (g.hometeam=th.team_id)
		JOIN uo_team AS tv ON (g.visitorteam=tv.team_id)
		JOIN uo_pool AS p ON g.pool=p.pool_id
		LEFT JOIN (
			SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
			FROM uo_spirit_score ssc
			LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
			GROUP BY ssc.game_id, ssc.team_id
		) AS hspirit ON (g.game_id = hspirit.game_id AND g.hometeam = hspirit.team_id)
		LEFT JOIN (
			SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
			FROM uo_spirit_score ssc
			LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
			GROUP BY ssc.game_id, ssc.team_id
		) AS vspirit ON (g.game_id = vspirit.game_id AND g.visitorteam = vspirit.team_id)
		WHERE p.series=%d
			AND g.isongoing=0
			AND (COALESCE(g.homescore,0)+COALESCE(g.visitorscore,0))>0
			AND (COALESCE(hspirit.total,0)=0 OR COALESCE(vspirit.total,0)=0)
		ORDER BY g.time ASC",
		(int)$seriesId
	);
	return DBQueryToArray($query);
}

function CountSpiritStats($teamId)
{
	$query = sprintf(
		"SELECT COUNT(*) AS games
		FROM (
			SELECT ssc.game_id
			FROM uo_spirit_score ssc
			WHERE ssc.team_id=%d
			GROUP BY ssc.game_id
			HAVING SUM(COALESCE(ssc.value,0)) > 0
		) AS scored_games",
		(int)$teamId
	);
	return DBQueryToRow($query);
}

function GameGetSpiritPoints($gameId, $teamId)
{
	$scores = SpiritScoreRowsByGameTeam($gameId, $teamId);
	$points = array();
	foreach ($scores as $score) {
		$points[$score['category_id']] = $score['value'];
	}
	return $points;
}

function TeamSpiritTotal($teamId, $includeIncomplete = false)
{
	$teamId = (int)$teamId;
	if ($includeIncomplete) {
		$query = sprintf(
			"SELECT SUM(IF(g.hometeam=%d, hspirit.total, vspirit.total)) AS total
			FROM uo_game g
			LEFT JOIN (
				SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
				FROM uo_spirit_score ssc
				LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
				GROUP BY ssc.game_id, ssc.team_id
			) AS hspirit ON (g.game_id = hspirit.game_id AND g.hometeam = hspirit.team_id)
			LEFT JOIN (
				SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
				FROM uo_spirit_score ssc
				LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
				GROUP BY ssc.game_id, ssc.team_id
			) AS vspirit ON (g.game_id = vspirit.game_id AND g.visitorteam = vspirit.team_id)
			WHERE
				(g.hometeam=%d AND COALESCE(hspirit.total,0)>0)
				OR
				(g.visitorteam=%d AND COALESCE(vspirit.total,0)>0)",
			$teamId,
			$teamId,
			$teamId
		);
	} else {
		$query = sprintf(
			"SELECT SUM(IF(g.hometeam=%d, hspirit.total, vspirit.total)) AS total
			FROM uo_game g
			LEFT JOIN (
				SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
				FROM uo_spirit_score ssc
				LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
				GROUP BY ssc.game_id, ssc.team_id
			) AS hspirit ON (g.game_id = hspirit.game_id AND g.hometeam = hspirit.team_id)
			LEFT JOIN (
				SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
				FROM uo_spirit_score ssc
				LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
				GROUP BY ssc.game_id, ssc.team_id
			) AS vspirit ON (g.game_id = vspirit.game_id AND g.visitorteam = vspirit.team_id)
			WHERE
				(g.hometeam=%d OR g.visitorteam=%d)
				AND COALESCE(hspirit.total,0)>0
				AND COALESCE(vspirit.total,0)>0",
			$teamId,
			$teamId,
			$teamId
		);
	}

	return DBQueryToRow($query);
}

function TeamSpiritStats($teamId)
{
	$teamId = (int)$teamId;
	$query = sprintf(
		"SELECT COUNT(*) AS games
		FROM uo_game g
		LEFT JOIN uo_game_pool gp ON (g.game_id = gp.game)
		LEFT JOIN (
			SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
			FROM uo_spirit_score ssc
			LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
			GROUP BY ssc.game_id, ssc.team_id
		) AS tscore ON (g.game_id = tscore.game_id AND tscore.team_id = %d)
		WHERE (g.homescore != g.visitorscore)
			AND ((g.hometeam=%d AND COALESCE(tscore.total,0)>0) OR (g.visitorteam=%d AND COALESCE(tscore.total,0)>0))
			AND g.isongoing=0
			AND gp.timetable=1",
		$teamId,
		$teamId,
		$teamId
	);
	return DBQueryToRow($query);
}

function TeamSpiritStats2($teamId, $includeIncomplete = false)
{
	$teamId = (int)$teamId;
	if ($includeIncomplete) {
		$query = sprintf(
			"SELECT COUNT(*) AS games
			FROM uo_game g
			LEFT JOIN (
				SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
				FROM uo_spirit_score ssc
				LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
				GROUP BY ssc.game_id, ssc.team_id
			) AS tscore ON (g.game_id = tscore.game_id AND tscore.team_id = %d)
			WHERE (g.hometeam=%d OR g.visitorteam=%d) AND COALESCE(tscore.total,0)>0",
			$teamId,
			$teamId,
			$teamId
		);
	} else {
		$query = sprintf(
			"SELECT COUNT(*) AS games
			FROM uo_game g
			LEFT JOIN (
				SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
				FROM uo_spirit_score ssc
				LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
				GROUP BY ssc.game_id, ssc.team_id
			) AS hspirit ON (g.game_id = hspirit.game_id AND g.hometeam = hspirit.team_id)
			LEFT JOIN (
				SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
				FROM uo_spirit_score ssc
				LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
				GROUP BY ssc.game_id, ssc.team_id
			) AS vspirit ON (g.game_id = vspirit.game_id AND g.visitorteam = vspirit.team_id)
			WHERE (g.hometeam=%d OR g.visitorteam=%d)
				AND COALESCE(hspirit.total,0)>0
				AND COALESCE(vspirit.total,0)>0",
			$teamId,
			$teamId
		);
	}
	return DBQueryToRow($query);
}

function TeamSpiritTotalByPool($poolId, $teamId)
{
	$query = sprintf(
		"SELECT COALESCE(SUM(ts.total), 0) AS spirit
		FROM uo_game_pool gp
		LEFT JOIN uo_game g ON (g.game_id = gp.game)
		LEFT JOIN (
			SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total
			FROM uo_spirit_score ssc
			LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
			GROUP BY ssc.game_id, ssc.team_id
		) AS ts ON (ts.game_id = gp.game AND ts.team_id = %d)
		WHERE gp.pool=%d
			AND g.hasstarted>0
			AND g.isongoing=0",
		(int)$teamId,
		(int)$poolId
	);
	return DBQueryToRow($query);
}

function SpiritScoreReplaceByGameTeam($gameId, $teamId, $points)
{
	$query = sprintf(
		"DELETE FROM uo_spirit_score WHERE game_id=%d AND team_id=%d",
		(int)$gameId,
		(int)$teamId
	);
	DBQuery($query);

	foreach ($points as $cat => $value) {
		if (!is_null($value)) {
			$query = sprintf(
				"INSERT INTO uo_spirit_score (`game_id`, `team_id`, `category_id`, `value`)
				VALUES (%d, %d, %d, %d)",
				(int)$gameId,
				(int)$teamId,
				(int)$cat,
				(int)$value
			);
			DBQuery($query);
		}
	}
}

function GameSetSpiritPoints($gameId, $teamId, $home, $points, $categories)
{
	if (hasEditGameEventsRight($gameId)) {
		SpiritScoreReplaceByGameTeam($gameId, $teamId, $points);
	} else {
		die('Insufficient rights to edit game');
	}
}

function GameSpiritComplete($gameId, $spiritmode = null)
{
	$gameId = (int)$gameId;
	$mode = is_null($spiritmode) ? 0 : (int)$spiritmode;

	$query = sprintf(
		"SELECT g.hometeam, g.visitorteam, se.spiritmode
		FROM uo_game g
		LEFT JOIN uo_pool p ON (p.pool_id = g.pool)
		LEFT JOIN uo_series s ON (s.series_id = p.series)
		LEFT JOIN uo_season se ON (se.season_id = s.season)
		WHERE g.game_id=%d",
		$gameId
	);
	$game = DBQueryToRow($query);
	if (!$game) {
		return false;
	}

	if ($mode <= 0) {
		$mode = isset($game['spiritmode']) ? (int)$game['spiritmode'] : 0;
	}
	if ($mode <= 0) {
		return false;
	}

	$required = DBQueryToValue(sprintf(
		"SELECT COUNT(*) FROM uo_spirit_category
		WHERE mode=%d AND `index` > 0",
		$mode
	));
	$required = (int)$required;
	if ($required <= 0) {
		return false;
	}

	$rows = DBQueryToArray(sprintf(
		"SELECT ssc.team_id, COUNT(*) AS cnt
		FROM uo_spirit_score ssc
		LEFT JOIN uo_spirit_category sct ON (sct.category_id = ssc.category_id)
		WHERE ssc.game_id=%d AND sct.mode=%d AND sct.`index` > 0
		GROUP BY ssc.team_id",
		$gameId,
		$mode
	));

	$counts = array();
	foreach ($rows as $row) {
		$counts[(int)$row['team_id']] = (int)$row['cnt'];
	}

	$homeTeam = isset($game['hometeam']) ? (int)$game['hometeam'] : 0;
	$visitorTeam = isset($game['visitorteam']) ? (int)$game['visitorteam'] : 0;
	if ($homeTeam <= 0 || $visitorTeam <= 0) {
		return false;
	}

	return (
		isset($counts[$homeTeam]) && $counts[$homeTeam] >= $required &&
		isset($counts[$visitorTeam]) && $counts[$visitorTeam] >= $required
	);
}

function SpiritTeamPointRows($seasonId, $teamId, $received = true)
{
	$teamId = (int)$teamId;
	$query = sprintf(
		"SELECT
			g.game_id,
			g.time,
			g.hometeam,
			g.visitorteam,
			th.name AS homename,
			tv.name AS visitorname,
			se.spiritmode
		FROM uo_game g
		LEFT JOIN uo_pool p ON (p.pool_id = g.pool)
		LEFT JOIN uo_series s ON (s.series_id = p.series)
		LEFT JOIN uo_season se ON (se.season_id = s.season)
		LEFT JOIN uo_team th ON (th.team_id = g.hometeam)
		LEFT JOIN uo_team tv ON (tv.team_id = g.visitorteam)
		WHERE s.season='%s'
			AND (g.hometeam=%d OR g.visitorteam=%d)
			AND g.isongoing=0
			AND (COALESCE(g.homescore,0)+COALESCE(g.visitorscore,0))>0
		ORDER BY g.time ASC",
		DBEscapeString($seasonId),
		$teamId,
		$teamId
	);
	$games = DBQueryToArray($query);
	$rows = array();
	$modeCategories = array();

	foreach ($games as $game) {
		$mode = isset($game['spiritmode']) ? (int)$game['spiritmode'] : 0;
		if ($mode <= 0) {
			continue;
		}

		if (!isset($modeCategories[$mode])) {
			$modeCategories[$mode] = SpiritCategories($mode);
		}
		$categories = $modeCategories[$mode];

		$homeTeam = (int)$game['hometeam'];
		$visitorTeam = (int)$game['visitorteam'];
		$isHome = ($teamId === $homeTeam);
		$opponentId = $isHome ? $visitorTeam : $homeTeam;
		$opponentName = $isHome ? $game['visitorname'] : $game['homename'];

		$ratedTeamId = $received ? $teamId : $opponentId;
		$points = GameGetSpiritPoints($game['game_id'], $ratedTeamId);
		$total = SpiritTotal($points, $categories);
		$complete = GameSpiritComplete($game['game_id'], $mode);
		$commentType = ($ratedTeamId === $homeTeam) ? COMMENT_TYPE_SPIRIT_HOME : COMMENT_TYPE_SPIRIT_VISITOR;

		$row = array(
			'game_id' => (int)$game['game_id'],
			'time' => $game['time'],
			'spiritmode' => $mode,
			'givenby' => $opponentName,
			'givento' => $opponentName,
			'total' => $total,
			'comments' => CommentRaw($commentType, $game['game_id']),
			'is_complete' => $complete ? 1 : 0
		);

		foreach ($categories as $category) {
			$index = isset($category['index']) ? (int)$category['index'] : 0;
			if ($index <= 0) {
				continue;
			}
			$value = null;
			if (isset($points[$category['category_id']])) {
				$value = $points[$category['category_id']];
			}
			$row['cat' . $index] = $value;
		}

		$rows[] = $row;
	}

	return $rows;
}

function TeamSpiritPointsReceived($seasonId, $teamId)
{
	return SpiritTeamPointRows($seasonId, $teamId, true);
}

function TeamSpiritPointsGiven($seasonId, $teamId)
{
	return SpiritTeamPointRows($seasonId, $teamId, false);
}

function SpiritCategoryFactors()
{
	$rows = DBQueryToArray("SELECT category_id, factor FROM uo_spirit_category");
	$factors = array();
	foreach ($rows as $row) {
		$factors[$row['category_id']] = $row['factor'];
	}
	return $factors;
}

function SpiritSeriesScoreRows($seriesId)
{
	$query = sprintf(
		"SELECT st.team_id, te.name, st.category_id, st.value, pool.series
		FROM uo_team AS te
		LEFT JOIN uo_spirit_score AS st ON (te.team_id=st.team_id)
		LEFT JOIN uo_game_pool AS gp ON (st.game_id=gp.game)
		LEFT JOIN uo_pool pool ON(gp.pool=pool.pool_id)
		LEFT JOIN uo_game AS g1 ON (gp.game=g1.game_id)
		WHERE pool.series=%d AND gp.timetable=1 AND g1.isongoing=0 AND g1.hasstarted>0
		ORDER BY st.team_id, st.category_id",
		(int)$seriesId
	);
	return DBQueryToArray($query);
}

function SeriesSpiritBoard($seriesId)
{
	$factor = SpiritCategoryFactors();
	$scores = SpiritSeriesScoreRows($seriesId);
	$last_team = null;
	$last_category = null;
	$averages = array();
	$total = 0;
	$sum = 0;
	$games = 0;
	foreach ($scores as $row) {
		if ($last_team != $row['team_id'] || $last_category != $row['category_id']) {
			if (!is_null($last_category)) {
				$teamline[$last_category] = SafeDivide($sum, $games);
				$factorValue = isset($factor[$last_category]) ? $factor[$last_category] : 0;
				$total += SafeDivide($factorValue * $sum, $games);
			}
			if ($last_team != $row['team_id']) {
				if (!is_null($last_team)) {
					$teamline['total'] = $total;
					$teamline['games'] = $games;
					$averages[$last_team] = $teamline;
					$total = 0;
				}
				$teamline = array('teamname' => $row['name']);
			}
			$sum = 0;
			$games = 0;
			$last_team = $row['team_id'];
			$last_category = $row['category_id'];
		}
		$sum += $row['value'];
		++$games;
	}
	if (!is_null($last_team)) {
		$teamline[$last_category] = SafeDivide($sum, $games);
		$factorValue = isset($factor[$last_category]) ? $factor[$last_category] : 0;
		$total += SafeDivide($factorValue * $sum, $games);
		$teamline['total'] = $total;
		$teamline['games'] = $games;
		$averages[$last_team] = $teamline;
	}
	return $averages;
}

function SeriesSpiritBoardTotalAverages($seriesId, $includeIncomplete = false)
{
	$seriesId = (int)$seriesId;
	$mode = DBQueryToValue(sprintf(
		"SELECT se.spiritmode
		FROM uo_series sr
		LEFT JOIN uo_season se ON (se.season_id = sr.season)
		WHERE sr.series_id=%d",
		$seriesId
	));
	$mode = (int)$mode;
	if ($mode <= 0) {
		return array();
	}

	$requiredCategories = (int)DBQueryToValue(sprintf(
		"SELECT COUNT(*) FROM uo_spirit_category
		WHERE mode=%d AND `index` > 0",
		$mode
	));
	if ($requiredCategories <= 0) {
		return array();
	}

	$completeGameFilter = "";
	if (!$includeIncomplete) {
		$completeGameFilter = sprintf(
			"AND g.game_id IN (
				SELECT ssc2.game_id
				FROM uo_spirit_score ssc2
				LEFT JOIN uo_spirit_category sct2 ON (sct2.category_id = ssc2.category_id)
				WHERE sct2.mode=%d AND sct2.`index` > 0
				GROUP BY ssc2.game_id
				HAVING COUNT(*) >= %d
					AND COUNT(DISTINCT ssc2.team_id) = 2
			)",
			$mode,
			$requiredCategories * 2
		);
	}

	$query = sprintf(
		"SELECT sct.category_id, sct.`index` AS catindex, sct.factor,
			AVG(ssc.value) AS catavg
		FROM uo_spirit_score ssc
		LEFT JOIN uo_spirit_category sct ON (sct.category_id = ssc.category_id)
		LEFT JOIN uo_game g ON (g.game_id = ssc.game_id)
		LEFT JOIN uo_game_pool gp ON (gp.game = g.game_id)
		LEFT JOIN uo_pool p ON (p.pool_id = gp.pool)
		WHERE p.series=%d
			AND sct.mode=%d
			AND sct.`index` > 0
			AND gp.timetable=1
			AND g.isongoing=0
			AND g.hasstarted>0
			%s
		GROUP BY sct.category_id, sct.`index`, sct.factor
		ORDER BY sct.`index`",
		$seriesId,
		$mode,
		$completeGameFilter
	);
	$rows = DBQueryToArray($query);

	$ret = array('total' => 0.0);
	foreach ($rows as $row) {
		$categoryId = (int)$row['category_id'];
		$index = (int)$row['catindex'];
		$avg = is_null($row['catavg']) ? null : (float)$row['catavg'];
		$ret[$categoryId] = $avg;
		$ret['cat' . $index] = $avg;
		if (!is_null($avg)) {
			$ret['total'] += ((float)$row['factor'] * $avg);
		}
	}

	if (count($rows) === 0) {
		$ret['total'] = null;
	}

	return $ret;
}

function SpiritSeriesMissingPointRows($seriesId)
{
	$query = sprintf(
		"SELECT missing.team_id, missing.teamname, missing.giver_team_id, missing.giver_teamname,
		missing.opponent_name, missing.home_name, missing.visitor_name, missing.game_id, missing.gamename, missing.time
		FROM (
			SELECT g.game_id, g.time, g.hometeam AS team_id, ht.name AS teamname,
				g.visitorteam AS giver_team_id, vt.name AS giver_teamname,
				vt.name AS opponent_name, ht.name AS home_name, vt.name AS visitor_name, sn.name AS gamename
			FROM uo_game g
			LEFT JOIN uo_game_pool gp ON (g.game_id=gp.game)
			LEFT JOIN uo_pool pool ON (gp.pool=pool.pool_id)
			LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
			LEFT JOIN uo_team ht ON (g.hometeam=ht.team_id)
			LEFT JOIN uo_team vt ON (g.visitorteam=vt.team_id)
			LEFT JOIN uo_scheduling_name sn ON (g.name=sn.scheduling_id)
			LEFT JOIN (SELECT DISTINCT game_id, team_id FROM uo_spirit_score) ssc
				ON (ssc.game_id=g.game_id AND ssc.team_id=g.hometeam)
			WHERE ser.series_id=%d AND gp.timetable=1 AND g.isongoing=0 AND g.hasstarted>0 AND ssc.game_id IS NULL
			UNION ALL
			SELECT g.game_id, g.time, g.visitorteam AS team_id, vt.name AS teamname,
				g.hometeam AS giver_team_id, ht.name AS giver_teamname,
				ht.name AS opponent_name, ht.name AS home_name, vt.name AS visitor_name, sn.name AS gamename
			FROM uo_game g
			LEFT JOIN uo_game_pool gp ON (g.game_id=gp.game)
			LEFT JOIN uo_pool pool ON (gp.pool=pool.pool_id)
			LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
			LEFT JOIN uo_team ht ON (g.hometeam=ht.team_id)
			LEFT JOIN uo_team vt ON (g.visitorteam=vt.team_id)
			LEFT JOIN uo_scheduling_name sn ON (g.name=sn.scheduling_id)
			LEFT JOIN (SELECT DISTINCT game_id, team_id FROM uo_spirit_score) ssc
				ON (ssc.game_id=g.game_id AND ssc.team_id=g.visitorteam)
			WHERE ser.series_id=%d AND gp.timetable=1 AND g.isongoing=0 AND g.hasstarted>0 AND ssc.game_id IS NULL
		) AS missing
		ORDER BY missing.teamname, missing.time, missing.game_id",
		(int)$seriesId,
		(int)$seriesId
	);
	return DBQueryToArray($query);
}

function SeriesMissingSpiritPoints($seriesId)
{
	return SpiritSeriesMissingPointRows($seriesId);
}

function SpiritTeamCategoryStatsRows($teamId, $seasonId, $spiritmode)
{
	$query = sprintf(
		"SELECT ts.category_id, ts.average, ts.games
		FROM uo_team_spirit_stats ts
		LEFT JOIN uo_spirit_category sct ON (sct.category_id = ts.category_id)
		WHERE ts.team_id=%d AND ts.season='%s' AND sct.mode=%d
		ORDER BY sct.`index`",
		(int)$teamId,
		DBEscapeString($seasonId),
		(int)$spiritmode
	);
	return DBQueryToArray($query);
}

function TeamSpiritCategoryStats($teamId, $seasonId, $spiritmode)
{
	return SpiritTeamCategoryStatsRows($teamId, $seasonId, $spiritmode);
}

function SpiritTeamCategoryHistoryRowsByName($teamname, $seriestype, $spiritmode)
{
	$query = sprintf(
		"SELECT ts.category_id,
			SUM(ts.average * ts.games) / NULLIF(SUM(ts.games), 0) AS average,
			SUM(ts.games) AS games
		FROM uo_team_spirit_stats ts
		LEFT JOIN uo_spirit_category sct ON (sct.category_id = ts.category_id)
		LEFT JOIN uo_team t ON (t.team_id = ts.team_id)
		LEFT JOIN uo_series ser ON (ser.series_id = ts.series)
		WHERE t.name='%s' AND ser.type='%s' AND sct.mode=%d
		GROUP BY ts.category_id
		ORDER BY sct.`index`",
		DBEscapeString($teamname),
		DBEscapeString($seriestype),
		(int)$spiritmode
	);
	return DBQueryToArray($query);
}

function TeamSpiritCategoryHistoryAveragesByName($teamname, $seriestype, $spiritmode)
{
	return SpiritTeamCategoryHistoryRowsByName($teamname, $seriestype, $spiritmode);
}

function SpiritTeamAverageRowsByName($teamname, $seriestype)
{
	$query = sprintf(
		"SELECT ts.season, ts.series, SUM(ts.average * sct.factor) AS spirit_total
		FROM uo_team_spirit_stats ts
		LEFT JOIN uo_spirit_category sct ON (sct.category_id = ts.category_id)
		LEFT JOIN uo_team t ON (t.team_id = ts.team_id)
		LEFT JOIN uo_series ser ON (ser.series_id = ts.series)
		WHERE t.name='%s' AND ser.type='%s'
		GROUP BY ts.season, ts.series",
		DBEscapeString($teamname),
		DBEscapeString($seriestype)
	);
	return DBQueryToArray($query);
}

function TeamSpiritAveragesByName($teamname, $seriestype)
{
	return SpiritTeamAverageRowsByName($teamname, $seriestype);
}

function SpiritRebuildTeamStatsForSeason($seasonId, $spiritmode)
{
	$seasonSafe = DBEscapeString($seasonId);
	DBQuery(sprintf("DELETE FROM uo_team_spirit_stats WHERE season='%s'", $seasonSafe));

	$query = sprintf(
		"INSERT INTO uo_team_spirit_stats (team_id, season, series, category_id, games, average)
		SELECT ssc.team_id, '%s', ser.series_id, ssc.category_id,
			COUNT(*) AS games, AVG(ssc.value) AS average
		FROM uo_spirit_score ssc
		LEFT JOIN uo_spirit_category sct ON (sct.category_id = ssc.category_id)
		LEFT JOIN uo_game g ON (g.game_id = ssc.game_id)
		LEFT JOIN uo_game_pool gp ON (gp.game = g.game_id)
		LEFT JOIN uo_pool p ON (p.pool_id = gp.pool)
		LEFT JOIN uo_series ser ON (ser.series_id = p.series)
		WHERE ser.season='%s'
			AND sct.mode=%d
			AND gp.timetable=1
			AND g.isongoing=0
			AND g.hasstarted>0
		GROUP BY ssc.team_id, ssc.category_id, ser.series_id
		ON DUPLICATE KEY UPDATE
			season=VALUES(season),
			series=VALUES(series),
			games=VALUES(games),
			average=VALUES(average)",
		$seasonSafe,
		$seasonSafe,
		(int)$spiritmode
	);

	DBQuery($query);
}

function CalcTeamSpiritStats($season)
{
	if (isSeasonAdmin($season)) {
		$season_info = SeasonInfo($season);
		if (empty($season_info['spiritmode']) || (int)$season_info['spiritmode'] <= 0) {
			return;
		}
		SpiritRebuildTeamStatsForSeason($season_info['season_id'], (int)$season_info['spiritmode']);
	} else {
		die('Insufficient rights to archive season');
	}
}
