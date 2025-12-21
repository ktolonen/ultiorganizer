<?php
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/standings.functions.php';
include_once $include_prefix . 'lib/player.functions.php';
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/debug.functions.php';

function IsSeasonStatsCalculated($season)
{
	$query = sprintf(
		"SELECT count(*) FROM uo_season_stats WHERE season='%s'",
		DBEscapeString($season)
	);
	return DBQueryToValue($query);
}

function IsStatsDataAvailable()
{
	return DBQueryToValue("SELECT count(*) FROM uo_season_stats");
}

function DeleteSeasonStats($season)
{
	if (isSeasonAdmin($season)) {
		$season_safe = DBEscapeString($season);
		DBQuery(sprintf("DELETE FROM uo_season_stats WHERE season='%s'", $season_safe));
		DBQuery(sprintf("DELETE FROM uo_series_stats WHERE season='%s'", $season_safe));
		DBQuery(sprintf("DELETE FROM uo_team_stats WHERE season='%s'", $season_safe));
		DBQuery(sprintf("DELETE FROM uo_player_stats WHERE season='%s'", $season_safe));
	} else {
		die('Insufficient rights to archive season');
	}
}

function SeriesStatistics($season)
{
	$query = sprintf(
		"SELECT ss.*, ser.name AS seriesname FROM uo_series_stats ss 
		LEFT JOIN uo_series ser ON(ser.series_id=ss.series_id)
		WHERE ss.season='%s'
		ORDER BY ss.season, ss.series_id",
		DBEscapeString($season)
	);
	return DBQueryToArray($query);
}

function SeriesStatisticsByType($seriestype, $seasontype)
{
	$query = sprintf(
		"SELECT ss.*, ser.name AS seriesname FROM uo_series_stats ss 
		LEFT JOIN uo_series ser ON(ser.series_id=ss.series_id)
		LEFT JOIN uo_season se ON(ss.season=se.season_id)
		WHERE ser.type='%s' AND se.type='%s'
		ORDER BY ss.season, ss.series_id",
		DBEscapeString($seriestype),
		DBEscapeString($seasontype)
	);
	return DBQueryToArray($query);
}

function ALLSeriesStatistics()
{
	$query = sprintf("SELECT ss.*, ser.name AS seriesname, 
		ser.type AS seriestype, s.name AS seasonname, s.type AS seasontype 
		FROM uo_series_stats ss 
		LEFT JOIN uo_series ser ON(ser.series_id=ss.series_id)
		LEFT JOIN uo_season s ON(ser.season=s.season_id)
		ORDER BY ser.type, s.type, ss.series_id");
	return DBQueryToArray($query);
}

function SeasonStatistics($season)
{
	$query = sprintf(
		"SELECT ss.*, s.name AS seasonname, s.type AS seasontype 
		FROM uo_season_stats ss 
		LEFT JOIN uo_season s ON(s.season_id=ss.season)
		WHERE ss.season='%s'
		ORDER BY ss.season",
		DBEscapeString($season)
	);
	return DBQueryToRow($query);
}

function AllSeasonStatistics()
{
	$query = sprintf("SELECT ss.*, s.name AS seasonname, s.type AS seasontype 
		FROM uo_season_stats ss 
		LEFT JOIN uo_season s ON(s.season_id=ss.season)
		ORDER BY s.type, s.name");
	return DBQueryToArray($query);
}

function SeasonTeamStatistics($season)
{
	$query = sprintf(
		"SELECT ts.*, ser.name AS seriesname, t.name AS teamname,
		s.name AS seasonname, s.type AS seasontype, ser.type AS seriestype 
		FROM uo_team_stats ts 
		LEFT JOIN uo_series ser ON(ser.series_id=ts.series)
		LEFT JOIN uo_season s ON(s.season_id=ts.season)
		LEFT JOIN uo_team t ON(t.team_id=ts.team_id)
		WHERE ts.season='%s'
		ORDER BY ts.series,ts.standing",
		DBEscapeString($season)
	);
	return DBQueryToArray($query);
}

function TeamStatistics($team)
{
	$query = sprintf(
		"SELECT ts.*, ser.name AS seriesname, t.name AS teamname,
		s.name AS seasonname, s.type AS seasontype, ser.type AS seriestype
		FROM uo_team_stats ts 
		LEFT JOIN uo_series ser ON(ser.series_id=ts.series)
		LEFT JOIN uo_season s ON(s.season_id=ts.season)
		LEFT JOIN uo_team t ON(t.team_id=ts.team_id)
		WHERE ts.team_id='%s'
		ORDER BY ts.series,ts.standing",
		DBEscapeString($team)
	);
	return DBQueryToArray($query);
}

function TeamStandings($season, $seriestype)
{
	$query = sprintf(
		"SELECT ts.*, ser.name AS seriesname, t.name AS teamname,
		s.name AS seasonname, s.type AS seasontype, ser.type AS seriestype,
		t.country, c.flagfile
		FROM uo_team_stats ts 
		LEFT JOIN uo_series ser ON(ser.series_id=ts.series)
		LEFT JOIN uo_season s ON(s.season_id=ts.season)
		LEFT JOIN uo_team t ON(t.team_id=ts.team_id)
		LEFT JOIN uo_country c ON(t.country=c.country_id)
		WHERE ts.season='%s' AND ser.type='%s'
		ORDER BY ts.series,ts.standing",
		DBEscapeString($season),
		DBEscapeString($seriestype)
	);
	return DBQueryToArray($query);
}

function TeamStatisticsByName($teamname, $seriestype)
{
	$query = sprintf(
		"SELECT ts.*, ser.name AS seriesname, t.name AS teamname,
		s.name AS seasonname, s.type AS seasontype, ser.type AS seriestype
		FROM uo_team_stats ts 
		LEFT JOIN uo_series ser ON(ser.series_id=ts.series)
		LEFT JOIN uo_season s ON(s.season_id=ts.season)
		LEFT JOIN uo_team t ON(t.team_id=ts.team_id)
		WHERE t.name='%s' AND ser.type='%s'
		ORDER BY s.starttime DESC, ts.series,ts.standing",
		DBEscapeString($teamname),
		DBEscapeString($seriestype)
	);
	return DBQueryToArray($query);
}

function PlayerStatistics($profile_id)
{
	$query = sprintf(
		"SELECT ps.*, ser.name AS seriesname, t.name AS teamname,
		s.name AS seasonname, s.type AS seasontype, ser.type AS seriestype
		FROM uo_player_stats ps 
		LEFT JOIN uo_series ser ON(ser.series_id=ps.series)
		LEFT JOIN uo_season s ON(s.season_id=ps.season)
		LEFT JOIN uo_team t ON(t.team_id=ps.team)
		WHERE ps.profile_id='%s'
		ORDER BY s.starttime DESC, ps.season,ps.series",
		DBEscapeString($profile_id)
	);
	return DBQueryToArray($query);
}

function AlltimeScoreboard($season, $seriestype)
{
	$query = sprintf(
		"SELECT ps.*, ser.name AS seriesname, t.name AS teamname,
		(COALESCE(ps.goals,0) + COALESCE(ps.passes,0)) AS total,
		p.firstname, p.lastname,
		s.name AS seasonname, s.type AS seasontype, ser.type AS seriestype
		FROM uo_player_stats ps 
		LEFT JOIN uo_series ser ON(ser.series_id=ps.series)
		LEFT JOIN uo_season s ON(s.season_id=ps.season)
		LEFT JOIN uo_team t ON(t.team_id=ps.team)
		LEFT JOIN uo_player p ON(p.player_id=ps.player_id)
		WHERE ps.season='%s' AND ser.type='%s'
		ORDER BY total DESC, ps.games ASC, lastname ASC LIMIT 5",
		DBEscapeString($season),
		DBEscapeString($seriestype)
	);
	return DBQueryToArray($query);
}

function ScoreboardAllTime($limit, $seasontype = "", $seriestype = "", $club = "", $sorting = "")
{

	//SELECT SUM(ps.goals) as goalstotal, SUM(passes) as passestotal, SUM(ps.games) as gamestotal, MAX(ser.series_id) as last_series, MAX(t.team_id) as last_team, SUM(COALESCE(ps.goals,0) + COALESCE(ps.passes,0)) AS total FROM uo_player_stats ps LEFT JOIN uo_series ser ON(ser.series_id=ps.series) LEFT JOIN uo_season s ON(s.season_id=ps.season) LEFT JOIN uo_team t ON(t.team_id=ps.team) LEFT JOIN uo_player p ON(p.player_id=ps.player_id) LEFT JOIN uo_player_profile pp ON(pp.profile_id=ps.profile_id) GROUP BY ps.profile_id ORDER BY total DESC, SUM(ps.games) ASC LIMIT 100
	$query = "SELECT ps.profile_id,
			SUM(ps.goals) as goalstotal, SUM(passes) as passestotal,
			SUM(ps.games) as gamestotal, MAX(ser.series_id) as last_series,
			 MAX(t.team_id) as last_team,
			SUM(COALESCE(ps.goals,0) + COALESCE(ps.passes,0)) AS total,
			MAX(s.season_id)
			FROM uo_player_stats ps 
			LEFT JOIN uo_series ser ON(ser.series_id=ps.series)
			LEFT JOIN uo_season s ON(s.season_id=ps.season)
			LEFT JOIN uo_team t ON(t.team_id=ps.team)
			LEFT JOIN uo_player p ON(p.player_id=ps.player_id)
			LEFT JOIN uo_player_profile pp ON(pp.profile_id=ps.profile_id) ";

	if (!empty($seasontype) && !empty($seriestype)) {
		$query .= sprintf(
			"WHERE s.type='%s' AND ser.type='%s' ",
			DBEscapeString($seasontype),
			DBEscapeString($seriestype)
		);
	} elseif (!empty($seasontype)) {
		$query .= sprintf(
			"WHERE s.type='%s' ",
			DBEscapeString($seasontype)
		);
	} elseif (!empty($seriestype)) {
		$query .= sprintf(
			"WHERE ser.type='%s' ",
			DBEscapeString($seriestype)
		);
	} elseif (!empty($club)) {
		$query .= sprintf(
			"WHERE t.team_id IN %s ",
			$club
		);
		debugMsg($query);
	}

	$query .= sprintf("GROUP BY ps.profile_id ");

	switch ($sorting) {
		case "total":
			$query .= "ORDER BY total DESC, gamestotal ASC, ps.profile_id ASC ";
			break;

		case "goal":
			$query .= "ORDER BY goalstotal DESC, gamestotal ASC, ps.profile_id ASC ";
			break;

		case "pass":
			$query .= "ORDER BY passestotal DESC, gamestotal ASC, ps.profile_id ASC ";
			break;

		case "games":
			$query .= "ORDER BY gamestotal DESC, gamestotal ASC, ps.profile_id ASC ";
			break;

		default:
			$query .= "ORDER BY total DESC, gamestotal ASC, ps.profile_id ASC ";
			break;
	}
	$query .= sprintf(" LIMIT %d", (int)$limit);

	return DBQueryToArray($query);
}


function SetTeamSeasonStanding($teamId, $standing)
{
	$teaminfo = TeamInfo($teamId);
	if (isSeasonAdmin($teaminfo['season'])) {
		$query = sprintf(
			"UPDATE uo_team_stats SET
						standing='%d' 
						WHERE team_id='%d'",
			(int)($standing),
			(int)($teamId)
		);

		DBQuery($query);
	} else {
		die('Insufficient rights to archive season');
	}
}


function CalcSeasonStats($season)
{
	if (isSeasonAdmin($season)) {
		$season_info = SeasonInfo($season);
		$teams = SeasonTeams($season);
		$teams_total = count($teams);
		$allgames = SeasonAllGames($season);
		$games_total = count($allgames);
		$goals_total = 0;
		$defenses_total = 0;
		$home_wins = 0;
		$home_draws = 0;
		$home_losses = 0;

		$players = SeasonAllPlayers($season);
		$played_players = 0;
		foreach ($players as $player) {
			$playedgames = PlayerSeasonPlayedGames($player['player_id'], $season_info['season_id']);
			if ($playedgames) {
				$played_players++;
			}
		}

		foreach ($allgames as $game_info) {
			$goals_total += $game_info['homescore'] + $game_info['visitorscore'];
			if ($game_info['homescore'] > $game_info['visitorscore']) {
				$home_wins++;
			} elseif ($game_info['homescore'] == $game_info['visitorscore']) {
				$home_draws++;
			} elseif ($game_info['homescore'] < $game_info['visitorscore']) {
				$home_losses++;
			}

			if (ShowDefenseStats()) {
				$defenses_total += $game_info['homedefenses'] + $game_info['visitordefenses'];
			}
		}
		//save season stats
		$query = sprintf(
			"INSERT IGNORE INTO uo_season_stats (season) VALUES ('%s')",
			DBEscapeString($season)
		);

		DBQuery($query);
		$defense_str = " ";
		if (ShowDefenseStats()) {
			$defense_str = ",defenses_total=$defenses_total ";
		}
		// FIXME update draws, losses
		$query = "UPDATE uo_season_stats SET
				teams=$teams_total, 
				games=$games_total, 
				goals_total=$goals_total, 
				home_wins=$home_wins, 
				players=$played_players" . $defense_str .
			"WHERE season='" . $season_info['season_id'] . "'";
		DBQuery($query);
	} else {
		die('Insufficient rights to archive season');
	}
}

function CalcSeriesStats($season)
{
	if (isSeasonAdmin($season)) {
		$season_info = SeasonInfo($season);
		$series_info = SeasonSeries($season);

		foreach ($series_info as $series) {

			$teams = SeriesTeams($series['series_id']);
			$teams_total = count($teams);
			$allgames = SeriesAllGames($series['series_id']);
			$games_total = count($allgames);
			$goals_total = 0;
			$home_wins = 0;
			$defenses_total = 0;

			$players = SeriesAllPlayers($series['series_id']);
			$played_players = 0;
			foreach ($players as $player) {
				$playedgames = PlayerSeasonPlayedGames($player['player_id'], $season_info['season_id']);
				if ($playedgames) {
					$played_players++;
				}
			}

			foreach ($allgames as $game) {
				$game_info = GameResult($game['game']);
				$goals_total += $game_info['homescore'] + $game_info['visitorscore'];
				if ($game_info['homescore'] > $game_info['visitorscore']) {
					$home_wins++;
				}
				if (ShowDefenseStats()) {
					$defenses_total += $game_info['homedefenses'] + $game_info['visitordefenses'];
				}
			}
			//save season stats
			$query = sprintf(
				"INSERT IGNORE INTO uo_series_stats (series_id) VALUES ('%s')",
				DBEscapeString($series['series_id'])
			);

			DBQuery($query);
			$defense_str = " ";
			if (ShowDefenseStats()) {
				$defense_str = ",defenses_total=$defenses_total ";
			}
			$query = "UPDATE uo_series_stats SET
					season='" . $season_info['season_id'] . "',
					teams=$teams_total, 
					games=$games_total, 
					goals_total=$goals_total, 
					home_wins=$home_wins, 
					players=$played_players" . $defense_str .
				"WHERE series_id=" . $series['series_id'];
			DBQuery($query);
		}
	} else {
		die('Insufficient rights to archive season');
	}
}

function CalcPlayerStats($season)
{
	if (isSeasonAdmin($season)) {
		$season_info = SeasonInfo($season);
		$players = SeasonAllPlayers($season);

		foreach ($players as $player) {
			$player_info = PlayerInfo($player['player_id']);
			$allgames = PlayerSeasonPlayedGames($player['player_id'], $season_info['season_id']);

			if ($allgames) {
				$games = $allgames;
				$goals = PlayerSeasonGoals($player['player_id'], $season_info['season_id']);
				$passes = PlayerSeasonPasses($player['player_id'], $season_info['season_id']);
				$wins = PlayerSeasonWins($player['player_id'], $player_info['team'], $season_info['season_id']);
				if (ShowDefenseStats()) {
					$defenses = PlayerSeasonDefenses($player['player_id'], $season_info['season_id']);
				}
				$callahans = PlayerSeasonCallahanGoals($player['player_id'], $season_info['season_id']);
				$breaks = 0;
				$offence_turns = 0;
				$defence_turns = 0;
				$offence_time = 0;
				$defence_time = 0;

				//save player stats
				$query = "INSERT IGNORE INTO uo_player_stats (player_id) VALUES (" . $player['player_id'] . ")";

				DBQuery($query);
				$defense_str = " ";
				if (ShowDefenseStats()) {
					$defense_str = ",defenses=$defenses ";
				}
				$query = "UPDATE uo_player_stats SET
						profile_id=" . intval($player_info['profile_id']) . ", 
						team=" . $player_info['team'] . ", 
						season='" . $season_info['season_id'] . "', 
						series=" . $player_info['series'] . ", 
						games=$games, 
						wins=$wins,
						goals=$goals, 
						passes=$passes, 
						callahans=$callahans, 
						breaks=$breaks, 
						offence_turns=$offence_turns,
						defence_turns=$defence_turns,
						offence_time=$offence_time,
						defence_time=$defence_time" . $defense_str .
					"WHERE player_id=" . $player['player_id'];
				DBQuery($query);
			}
		}
	} else {
		die('Insufficient rights to archive season');
	}
}

function CalcTeamStats($season)
{
	if (isSeasonAdmin($season)) {
		$season_info = SeasonInfo($season);
		$series_info = SeasonSeries($season);

		foreach ($series_info as $series) {
			$teams = SeriesTeams($series['series_id']);

			foreach ($teams as $team) {
				$team_info = TeamFullInfo($team['team_id']);
				$goals_made = 0;
				$goals_against = 0;
				$wins = 0;
				$losses = 0;
				$defenses_total = 0;
				$standing = TeamSeriesStanding($team['team_id']);
				$allgames = TeamGames($team['team_id']);

				while ($game = mysqli_fetch_assoc($allgames)) {
					if (!is_null($game['homescore']) && !is_null($game['visitorscore'])) {

						if ($team['team_id'] == $game['hometeam']) {
							$goals_made += intval($game['homescore']);
							$goals_against += intval($game['visitorscore']);

							if (intval($game['homescore']) > intval($game['visitorscore'])) {
								$wins++;
							} else {
								$losses++;
							}
							if (ShowDefenseStats()) {
								$defenses_total += $game['homedefenses'];
							}
						} else {
							$goals_made += intval($game['visitorscore']);
							$goals_against += intval($game['homescore']);
							if (intval($game['homescore']) < intval($game['visitorscore'])) {
								$wins++;
							} elseif (intval($game['homescore']) > intval($game['visitorscore'])) {
								$losses++;
							}
							if (ShowDefenseStats()) {
								$defenses_total += $game['visitordefenses'];
							}
						}
					}
				}

				//save team stats
				$query = "INSERT IGNORE INTO uo_team_stats (team_id) VALUES (" . $team['team_id'] . ")";

				DBQuery($query);
				$defense_str = " ";
				if (ShowDefenseStats()) {
					$defense_str = ",defenses_total=$defenses_total ";
				}
				$query = "UPDATE uo_team_stats SET
						season='" . $season_info['season_id'] . "', 
						series=" . $team_info['series'] . ", 
						goals_made=$goals_made, 
						goals_against=$goals_against, 
						standing=$standing, 
						wins=$wins, 
						losses=$losses" . $defense_str .
					"WHERE team_id=" . $team['team_id'];
				DBQuery($query);
			}
		}
	} else {
		die('Insufficient rights to archive season');
	}
}
