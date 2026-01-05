<?php

/**
 * Returns rounds for a season and division.
 *
 * @param string $seasonId uo_season.season_id
 * @param int $seriesId uo_series.series_id
 * @return array of rounds
 */
function SeasonPointsRounds($seasonId, $seriesId)
{
  $query = sprintf(
    "SELECT round_id, round_no, name
		FROM uo_season_round
		WHERE season='%s' AND series=%d
		ORDER BY round_no",
    DBEscapeString($seasonId),
    (int)$seriesId
  );
  return DBQueryToArray($query);
}

/**
 * Returns info for a single round.
 *
 * @param int $roundId uo_season_round.round_id
 * @return array|null
 */
function SeasonPointsRoundInfo($roundId)
{
  $query = sprintf(
    "SELECT round_id, season, series, round_no, name
		FROM uo_season_round
		WHERE round_id=%d",
    (int)$roundId
  );
  return DBQueryToRow($query, true);
}

/**
 * Adds a round for a season/division.
 *
 * @param string $seasonId uo_season.season_id
 * @param int $seriesId uo_series.series_id
 * @param int $roundNo sequential round number
 * @param string $name round name
 * @return boolean TRUE on success
 */
function AddSeasonPointsRound($seasonId, $seriesId, $roundNo, $name)
{
  if (!isSeasonAdmin($seasonId)) {
    die('Insufficient rights to add season points round');
  }
  $query = sprintf(
    "INSERT INTO uo_season_round (season, series, round_no, name)
		VALUES ('%s', %d, %d, '%s')",
    DBEscapeString($seasonId),
    (int)$seriesId,
    (int)$roundNo,
    DBEscapeString($name)
  );
  return DBQuery($query);
}

/**
 * Deletes a round and its points.
 *
 * @param int $roundId uo_season_round.round_id
 * @return boolean TRUE on success
 */
function DeleteSeasonPointsRound($roundId)
{
  $round = SeasonPointsRoundInfo($roundId);
  if (!$round) {
    return false;
  }
  if (!isSeasonAdmin($round['season'])) {
    die('Insufficient rights to delete season points round');
  }
  $query = sprintf(
    "DELETE FROM uo_season_round WHERE round_id=%d",
    (int)$roundId
  );
  return DBQuery($query);
}

/**
 * Returns points for a round indexed by team_id.
 *
 * @param int $roundId uo_season_round.round_id
 * @return array team_id => points
 */
function SeasonPointsRoundPoints($roundId)
{
  $query = sprintf(
    "SELECT team_id, points
		FROM uo_season_points
		WHERE round_id=%d",
    (int)$roundId
  );
  $rows = DBQueryToArray($query);
  $points = array();
  foreach ($rows as $row) {
    $points[$row['team_id']] = (int)$row['points'];
  }
  return $points;
}

/**
 * Saves points for a round.
 *
 * @param int $roundId uo_season_round.round_id
 * @param array $pointsByTeam team_id => points
 * @return boolean TRUE on success
 */
function SaveSeasonPointsRoundPoints($roundId, $pointsByTeam)
{
  $round = SeasonPointsRoundInfo($roundId);
  if (!$round) {
    return false;
  }
  if (!isSeasonAdmin($round['season'])) {
    die('Insufficient rights to edit season points');
  }

  foreach ($pointsByTeam as $teamId => $points) {
    $query = sprintf(
      "INSERT INTO uo_season_points (round_id, team_id, points)
			VALUES (%d, %d, %d)
			ON DUPLICATE KEY UPDATE points=VALUES(points)",
      (int)$roundId,
      (int)$teamId,
      (int)$points
    );
    if (!DBQuery($query)) {
      return false;
    }
  }
  return true;
}

/**
 * Returns total points for a season/division.
 *
 * @param string $seasonId uo_season.season_id
 * @param int $seriesId uo_series.series_id
 * @return array team_id => total points
 */
function SeasonPointsSeriesTotals($seasonId, $seriesId)
{
  $query = sprintf(
    "SELECT tp.team_id, SUM(tp.points) AS total
		FROM uo_season_points tp
		LEFT JOIN uo_season_round tr ON (tp.round_id = tr.round_id)
		WHERE tr.season='%s' AND tr.series=%d
		GROUP BY tp.team_id",
    DBEscapeString($seasonId),
    (int)$seriesId
  );
  $rows = DBQueryToArray($query);
  $totals = array();
  foreach ($rows as $row) {
    $totals[$row['team_id']] = (int)$row['total'];
  }
  return $totals;
}
