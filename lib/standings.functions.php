<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/pool.functions.php';
require_once __DIR__ . '/seasonpoints.functions.php';
require_once __DIR__ . '/series.functions.php';

function ResolvePoolStandings($poolId)
{
    $poolinfo = PoolInfo($poolId);
    if ($poolinfo['type'] == 1) {
        ResolveSeriesPoolStandings($poolId);
    } elseif ($poolinfo['type'] == 2) {
        ResolvePlayoffPoolStandings($poolId);
    } elseif ($poolinfo['type'] == 3) {
        ResolveSwissdrawPoolStandings($poolId);
    } elseif ($poolinfo['type'] == 4) {
        ResolveCrossMatchPoolStandings($poolId);
    }
}

function ResolvePlayoffPoolStandings($poolId)
{

    //query pool teams
    $query = sprintf(
        "
		SELECT j.team_id, js.activerank 
		FROM uo_team AS j INNER JOIN uo_team_pool AS js ON (j.team_id = js.team) 
		WHERE js.pool=%d 
		ORDER BY js.rank ASC",
        (int) $poolId,
    );

    $teams = DBQueryToArray($query);
    $steams = PoolSchedulingTeams($poolId);

    if (count($teams) <= 1 || count($teams) < count($steams)) {
        return;
    }

    for ($i = 0; $i < (count($teams) - 1); $i = $i + 2) {
        //loop team in pairs, but also be aware if there is odd number of teams
        $teamId1 = $teams[$i]['team_id'];
        $teamId2 = $teams[$i + 1]['team_id'];
        $query = sprintf(
            "SELECT 
				COUNT((hometeam=%d AND (homescore>visitorscore)) OR (visitorteam=%d AND (homescore<visitorscore)) OR NULL) AS team1wins, 
				COUNT((hometeam=%d AND (homescore>visitorscore)) OR (visitorteam=%d AND (homescore<visitorscore)) OR NULL) AS team2wins 
				FROM uo_game 
				WHERE (homescore != visitorscore) AND ((hometeam=%d AND visitorteam=%d) OR (hometeam=%d AND visitorteam=%d)) 
					AND isongoing=0
					AND game_id IN (SELECT game FROM uo_game_pool WHERE pool=%d)",
            (int) $teamId1,
            (int) $teamId1,
            (int) $teamId2,
            (int) $teamId2,
            (int) $teamId1,
            (int) $teamId2,
            (int) $teamId2,
            (int) $teamId1,
            (int) $poolId,
        );
        $games = DBQueryToRow($query);

        if ($games['team1wins'] > $games['team2wins']) {
            DBQuery("UPDATE uo_team_pool SET activerank=" . ($i + 1) . " WHERE pool=" . intval($poolId) . " AND team=$teamId1");
            DBQuery("UPDATE uo_team_pool SET activerank=" . ($i + 2) . " WHERE pool=" . intval($poolId) . " AND team=$teamId2");
        } elseif ($games['team1wins'] < $games['team2wins']) {
            DBQuery("UPDATE uo_team_pool SET activerank=" . ($i + 1) . " WHERE pool=" . intval($poolId) . " AND team=$teamId2");
            DBQuery("UPDATE uo_team_pool SET activerank=" . ($i + 2) . " WHERE pool=" . intval($poolId) . " AND team=$teamId1");
        } else {
            //keep current positions
        }
        //check if teams can be moved to next round
        $gamesleft1 = TeamPoolGamesLeft($teamId1, $poolId);
        $gamesleft2 = TeamPoolGamesLeft($teamId2, $poolId);
        if (count($gamesleft1) + count($gamesleft2) == 0) {
            TeamMove($teamId1, $poolId, true);
            TeamMove($teamId2, $poolId, true);
        }
    }
    // if odd number of teams
    if (count($teams) % 2 == 1) {
        $byeTeamId = $teams[count($teams) - 1]['team_id'];
        // set activerank to the last position in pool
        DBQuery("UPDATE uo_team_pool SET activerank=" . (count($teams)) . " WHERE pool=" . intval($poolId) . " AND team=$byeTeamId");
        // and attempt to move
        TeamMove($byeTeamId, $poolId, true);
    }

    //check if there are special ranking rules and apply them
    CheckSpecialRanking($poolId);
}

function CheckSpecialRanking($poolId)
{
    //check if there are special ranking rules for this pool and apply them
    $query = sprintf(
        "		
			SELECT team,pool,activerank as oldrank,torank as newrank
			FROM uo_specialranking r 
			LEFT JOIN uo_team_pool tp ON (tp.pool = r.frompool AND tp.activerank = r.fromplacing)
			WHERE tp.pool='%s'",
        (int) $poolId,
    );
    $specialranking = DBQueryToArray($query);
    foreach ($specialranking as $row) {
        //		print_r($row);
        DBQuery("UPDATE uo_team_pool SET activerank=" . $row['newrank'] . " WHERE pool=" . intval($row['pool']) . " AND team=" . $row['team']);
    }
}

function ResolveCrossMatchPoolStandings($poolId)
{

    //query pool teams
    $query = sprintf(
        "
		SELECT j.team_id, js.activerank 
		FROM uo_team AS j INNER JOIN uo_team_pool AS js ON (j.team_id = js.team) 
		WHERE js.pool=%d 
		ORDER BY js.activerank ASC, js.rank ASC",
        (int) $poolId,
    );

    $teams = DBQueryToArray($query);

    if (count($teams) <= 1) {
        return;
    }

    for ($i = 0; $i < (count($teams) - 1); $i = $i + 2) {
        //loop team in pairs, but also be aware if there is odd number of teams
        $teamId1 = $teams[$i]['team_id'];
        $teamId2 = $teams[$i + 1]['team_id'];
        $query = sprintf(
            "SELECT 
				COUNT((hometeam=%d AND (homescore>visitorscore)) OR (visitorteam=%d AND (homescore<visitorscore)) OR NULL) AS team1wins, 
				COUNT((hometeam=%d AND (homescore>visitorscore)) OR (visitorteam=%d AND (homescore<visitorscore)) OR NULL) AS team2wins 
				FROM uo_game 
				WHERE (homescore != visitorscore) AND ((hometeam=%d AND visitorteam=%d) OR (hometeam=%d AND visitorteam=%d)) 
					AND isongoing=0
					AND game_id IN (SELECT game FROM uo_game_pool WHERE pool=%d)",
            (int) $teamId1,
            (int) $teamId1,
            (int) $teamId2,
            (int) $teamId2,
            (int) $teamId1,
            (int) $teamId2,
            (int) $teamId2,
            (int) $teamId1,
            (int) $poolId,
        );
        $games = DBQueryToRow($query);

        if ($games['team1wins'] > $games['team2wins']) {
            DBQuery("UPDATE uo_team_pool SET activerank=" . ($i + 1) . " WHERE pool=" . intval($poolId) . " AND team=$teamId1");
            DBQuery("UPDATE uo_team_pool SET activerank=" . ($i + 2) . " WHERE pool=" . intval($poolId) . " AND team=$teamId2");
        } elseif ($games['team1wins'] < $games['team2wins']) {
            DBQuery("UPDATE uo_team_pool SET activerank=" . ($i + 1) . " WHERE pool=" . intval($poolId) . " AND team=$teamId2");
            DBQuery("UPDATE uo_team_pool SET activerank=" . ($i + 2) . " WHERE pool=" . intval($poolId) . " AND team=$teamId1");
        } else {
            //keep current positions
        }
        //check if teams can be moved to next round
        $gamesleft1 = TeamPoolGamesLeft($teamId1, $poolId);
        $gamesleft2 = TeamPoolGamesLeft($teamId2, $poolId);

        if (count($gamesleft1) + count($gamesleft2) == 0) {
            TeamMove($teamId1, $poolId);
            TeamMove($teamId2, $poolId);
        }
    }
}

function CompareTeamsSwissdraw($a, $b)
{ // distinguish between first round and the rest
    if ($a['games'] == 1 && $b['games'] == 1) {
        // sort according to
        // 1. victory points
        // 2. margin
        // 3. total points scored
        // 4. spirit score
        if ($a['vp'] != $b['vp']) {
            return ($a['vp'] > $b['vp']) ? -1 : 1;
        } else {
            if ($a['margin'] != $b['margin']) {
                return ($a['margin'] > $b['margin']) ? -1 : 1;
            } else {
                if ($a['score'] != $b['score']) {
                    return ($a['score'] > $b['score']) ? -1 : 1;
                } else {
                    if ($a['spirit'] != $b['spirit']) {
                        return ($a['spirit'] > $b['spirit']) ? -1 : 1;
                    } else {
                        return 0;
                    }
                }
            }
        }
    } else {
        // sort according to
        // 0. number of games
        // 1. victory points
        // 2. opponent's victory points
        // 3. total points scored
        // 4. spirit score

        if ($a['games'] != $b['games']) {
            return ($a['games'] > $b['games']) ? -1 : 1;
        } else {
            if ($a['vp'] != $b['vp']) {
                return ($a['vp'] > $b['vp']) ? -1 : 1;
            } else {
                if ($a['oppvp'] != $b['oppvp']) {
                    return ($a['oppvp'] > $b['oppvp']) ? -1 : 1;
                } else {
                    if ($a['score'] != $b['score']) {
                        return ($a['score'] > $b['score']) ? -1 : 1;
                    } else {
                        if ($a['spirit'] != $b['spirit']) {
                            return ($a['spirit'] > $b['spirit']) ? -1 : 1;
                        } else {
                            return 0;
                        }
                    }
                }
            }
        }
    }
}

function SolveStandingsAccordingSwissdraw($points)
{
    //sort according victorypoints
    usort($points, "CompareTeamsSwissdraw");

    //update active rank
    $stand = 1;
    $points[0]['arank'] = 1;

    for ($i = 1; $i < count($points); $i++) {
        if (CompareTeamsSwissdraw($points[$i - 1], $points[$i]) != 0) {
            $stand = $i + 1;
        }
        $points[$i]['arank'] = $stand;
    }
    return $points;
}


function ResolveSwissdrawPoolStandings($poolId)
{
    //query pool teams
    $query = sprintf(
        "
		SELECT j.team_id, js.activerank 
		FROM uo_team AS j INNER JOIN uo_team_pool AS js ON (j.team_id = js.team) 
		WHERE js.pool='%s' 
		ORDER BY js.activerank ASC, js.rank ASC",
        DBEscapeString($poolId),
    );

    $standings = DBQueryToArray($query);

    $points = [];
    $i = 0;

    if (count($standings) <= 1) {
        return;
    }

    foreach ($standings as $row) {
        // retrieve nr of games, victory points, average opponent's victory points
        $stats1 = TeamVictoryPointsByPool($poolId, $row['team_id']);

        $points[$i]['team'] = $row['team_id'];
        $points[$i]['games'] = $stats1['games'];
        $points[$i]['vp'] = $stats1['victorypoints'];
        $points[$i]['oppvp'] = $stats1['oppvp'];
        $points[$i]['margin'] = $stats1['margin'];
        $points[$i]['score'] = $stats1['score'];
        $points[$i]['spirit'] = $stats1['spirit'];
        $i++;
    }

    //	echo "before sorting acc to games:"
    //	PrintStandingsSwissdraw($points);

    //initial sort according games
    usort($points, function ($a, $b) {
        return $a['games'] == $b['games'] ? 0 : ($a['games'] > $b['games'] ? -1 : 1);
    });

    //	echo "before sorting acc to points:";
    //	PrintStandingsSwissdraw($points);

    $points = SolveStandingsAccordingSwissdraw($points);
    //	echo "after sorting acc to points:";
    //	PrintStandingsSwissdraw($points);


    //update results
    for ($i = 0; $i < count($standings) && !empty($points[$i]['team']); $i++) {
        //echo "<p>win t".$points[$i]['team']." v".$points[$i]['wins']." s".$points[$i]['arank']."</p>";
        $query = sprintf(
            "UPDATE uo_team_pool 
				SET activerank='%s' WHERE pool='%s' AND team='%s'",
            DBEscapeString($points[$i]['arank']),
            DBEscapeString($poolId),
            DBEscapeString($points[$i]['team']),
        );

        DBQuery($query);
    }
}


function ResolveSeriesPoolStandings($poolId)
{
    $poolId = intval($poolId);

    //query pool teams
    $query = sprintf(
        "
	SELECT j.team_id, js.activerank 
	FROM uo_team AS j INNER JOIN uo_team_pool AS js ON (j.team_id = js.team) 
	WHERE js.pool='%s' 
	ORDER BY js.activerank ASC, js.rank ASC",
        DBEscapeString($poolId),
    );

    $standings = DBQueryToArray($query);

    $points = [];
    $i = 0;

    if (count($standings) <= 1) {
        return;
    }

    foreach ($standings as $row) {
        $points[$i]['team'] = $row['team_id'];
        $points[$i]['arank'] = 1;
        $i++;
    }
    $points = getMatchesWins($points, $poolId);

    //initial sort according games
    usort($points, function ($a, $b) {
        return $a['games'] == $b['games'] ? 0 : ($a['games'] > $b['games'] ? -1 : 1);
    });

    //sort according to score (wins*winscore+draws*drawscore)
    $points = SolveStandings($points, 'cmp_score');
    $offset = 1;

    //if team sharing same standing
    $samerank = FindSameRank($points, $offset);

    //check in order
    //1st condition: check matches played against teams sharing same standing
    //2nd condition: check goal difference from matches played against teams sharing same standing
    //3rd condition: all matches goal difference
    //4th condition: made  goals in matches played against teams sharing same standing
    //5th condition: made goals in all matches
    //whenever one of these condtions solve one or more team standings start checking on begin for teams still sharing same standings
    while (count($samerank)) {
        $solved = false;
        $offset = $samerank[0]['arank'];

        //PrintStandings($samerank);
        //1st condition: check matches played against teams sharing same standing
        $samerank = SolveStandings(getMatchesWins($samerank, $poolId, true), 'cmp_score');

        //PrintStandings($samerank);
        //continue to 2nd condition if all teams are still sharing the same standing
        if (IsSameRank($samerank)) {
            //2nd condition: check goal difference from matches played against teams sharing same standing
            //       $samerank = SolveStandingsSharedMatchesGoalsDiff($samerank, $poolId);
            $samerank = SolveStandings(getMatchesGoals($samerank, $poolId, true), 'cmp_goalsdiff');
        } else {
            $solved = true;
        }

        //PrintStandings($samerank);
        //continue to 3rd condition if standings not solved
        if (!$solved && IsSameRank($samerank)) {
            //3rd condition: all matches goal difference
            //       $samerank = SolveStandingsAllMatchesGoalsDiff($samerank, $poolId);
            $samerank = SolveStandings(getMatchesGoals($samerank, $poolId, false), 'cmp_goalsdiff');
        } else {
            $solved = true;
        }

        //PrintStandings($samerank);
        //continue to 4th condition if standings not solved
        if (!$solved && IsSameRank($samerank)) {
            //4th condition: made  goals in matches played against teams sharing same standing
            //       $samerank = SolveStandingsSharedMatchesGoalsMade($samerank, $poolId);
            $samerank = SolveStandings(getMatchesGoals($samerank, $poolId, true), 'cmp_goalsmade');
        } else {
            $solved = true;
        }

        //PrintStandings($samerank);
        //continue to 5th condition if standings not solved
        if (!$solved && IsSameRank($samerank)) {
            //5th condition: made goals in all matches
            //       $samerank = SolveStandingsAllMatchesGoalsMade($samerank, $poolId);
            $samerank = SolveStandings(getMatchesGoals($samerank, $poolId, false), 'cmp_goalsmade');
        } else {
            $solved = true;
        }

        if (!$solved && !IsSameRank($samerank)) {
            $solved = true;
        }

        //PrintStandings($samerank);
        if ($solved) {
            //update standings and check remaining standings in same pool
            $points = UpdateStandings($points, $samerank);
        } else {
            //cannot solve standings with current conditions. Leave teams to shared stands and check remaining standings in same pool
            //echo "<p>count: ".$offset." ".count($samerank)."</p>";
            $offset += count($samerank);
        }

        $samerank = FindSameRank($points, $offset);
    }

    //update results
    for ($i = 0; $i < count($standings) && !empty($points[$i]['team']); $i++) {
        //echo "<p>win t".$points[$i]['team']." v".$points[$i]['wins']." s".$points[$i]['arank']."</p>";
        $query = sprintf(
            "UPDATE uo_team_pool
			SET activerank='%s' WHERE pool='%s' AND team='%s'",
            DBEscapeString($points[$i]['arank']),
            DBEscapeString($poolId),
            DBEscapeString($points[$i]['team']),
        );

        DBQuery($query);
    }

    //test if pool is played
    $games = DBQueryRowCount("SELECT game.game_id
		FROM uo_game game
		INNER JOIN uo_game_pool gp ON (gp.game=game.game_id AND gp.timetable=1)
		WHERE gp.pool=$poolId");
    $played = DBQueryRowCount("SELECT game.game_id
		FROM uo_game game
		INNER JOIN uo_game_pool gp ON (gp.game=game.game_id AND gp.timetable=1)
		WHERE gp.pool=$poolId AND (game.hasstarted>0) AND game.isongoing=0");
    if ($games == $played) {

        //test that standings are not shared
        $query = sprintf(
            "SELECT activerank, COUNT(activerank) AS num
			FROM uo_team_pool WHERE pool=%d 
			GROUP BY activerank HAVING ( COUNT(activerank) > 1 )",
            (int) $poolId,
        );

        $duplicates = DBQueryRowCount($query);
        if (!$duplicates) {
            $topools = PoolMovingsFromPool($poolId);

            foreach ($topools as $pool) {
                $poolinfo = PoolInfo($pool['topool']);
                if ($poolinfo['mvgames'] == 1) {
                    PoolMakeMove($pool['frompool'], $pool['fromplacing'], false);
                    //set pool visible
                    $query = sprintf("UPDATE uo_pool SET visible='1' WHERE pool_id=%d", (int) $pool['topool']);
                    DBQuery($query);
                }
            }
        }
    }
}

function Score($point)
{
    return $point['wins'] * 2 + ($point['games'] - $point['wins'] - $point['losses']) * 1;
}

function cmp_score($pointa, $pointb)
{
    return (Score($pointa) > Score($pointb)) ? -1 : ((Score($pointa) < Score($pointb)) ? 1 : 0);
}

function cmp_goalsdiff($pointa, $pointb)
{
    return ($pointa['goalsdiff'] > $pointb['goalsdiff']) ? -1 : (($pointa['goalsdiff'] < $pointb['goalsdiff']) ? 1 : 0);
}

function cmp_goalsmade($pointa, $pointb)
{
    return ($pointa['goalsmade'] > $pointb['goalsmade']) ? -1 : (($pointa['goalsmade'] < $pointb['goalsmade']) ? 1 : 0);
}

function SolveStandings($points, $cmpf)
{
    if (count($points) == 0) {
        return $points;
    }
    //sort according wins
    usort($points, $cmpf);

    //update active rank
    $offset = 1;

    for ($i = 1; $i < count($points); $i++) {
        if ($cmpf($points[$i], $points[$i - 1]) != 0) {
            $points[$i]['arank'] = $points[$i - 1]['arank'] + $offset;
            $offset = 1;
        } else {
            $points[$i]['arank'] = $points[$i - 1]['arank'];
            $offset++;
        }
    }

    return $points;
}

function FindSameRank($points, $offset)
{
    usort($points, function ($a, $b) {
        return $a['arank'] == $b['arank'] ? 0 : ($a['arank'] < $b['arank'] ? -1 : 1);
    });
    $samerank = [];
    $total = 0;

    for ($i = $offset; $i < count($points) && !empty($points[$i]['team']); $i++) {
        if ($points[$i]['arank'] == $points[$i - 1]['arank']) {
            //if first found, then previous team was with same rank
            if (!$total) {
                $samerank[$total]['team'] = $points[$i - 1]['team'];
                $samerank[$total]['wins'] = 0;
                $samerank[$total]['arank'] = $points[$i - 1]['arank'];
                $total++;
            }
            $samerank[$total]['team'] = $points[$i]['team'];
            $samerank[$total]['wins'] = 0;
            $samerank[$total]['arank'] = $points[$i]['arank'];
            $total++;
        } elseif ($total) {
            break;
        }
    }
    return $samerank;
}

function IsSameRank($points)
{
    for ($i = 1; $i < count($points); $i++) {
        if ($points[$i]['arank'] != $points[$i - 1]['arank']) {
            return false;
        }
    }
    return true;
}

function PrintStandings($points)
{
    for ($i = 0; $i < count($points); $i++) {
        echo "<p>t" . $points[$i]['team'] . " w" . $points[$i]['wins'] . " #" . $points[$i]['arank'] . "</p>";
    }
}

function PrintStandingsSwissdraw($points)
{
    for ($i = 0; $i < count($points); $i++) {
        echo "<p>" . $points[$i]['team'] . " g" . $points[$i]['games'] . " vp" . $points[$i]['vp'] . " oppvp" . $points[$i]['oppvp'] . " sc" . $points[$i]['score'] . " #" . $points[$i]['arank'] . "</p>";
    }
}


function UpdateStandings($to, $from)
{
    foreach ($from as $newrank) {
        for ($i = 0; $i < count($to); $i++) {
            if ($newrank['team'] == $to[$i]['team']) {
                $to[$i]['arank'] = $newrank['arank'];
                break;
            }
        }
    }
    //for ($i=0; $i < count($to); $i++)
    //	{
    //	echo "<p>update t".$to[$i]['team']." v".$to[$i]['wins']." s".$to[$i]['arank']."</p>";
    //	}

    return $to;
}

function getMatchesWins($points, $poolId, $shared = false)
{
    $sameteams = DBEscapeString($points[0]['team']);
    for ($i = 1; $i < count($points); $i++) {
        $sameteams .= "," . DBEscapeString($points[$i]['team']);
    }
    for ($i = 0; $i < count($points); $i++) {
        $team = DBEscapeString($points[$i]['team']);
        $query = sprintf(
            "
		SELECT COUNT(*) AS games,
    		COUNT((hometeam='%s' AND (homescore>visitorscore)) OR (visitorteam='%s' AND (homescore<visitorscore)) OR NULL) AS wins,
    		COUNT((hometeam='%s' AND (homescore<visitorscore)) OR (visitorteam='%s' AND (homescore>visitorscore)) OR NULL) AS losses
		FROM uo_game
		WHERE (hasStarted) AND (hometeam='%s' OR visitorteam='%s') AND isongoing=0
			AND game_id IN (SELECT game FROM uo_game_pool WHERE pool='%s')",
            $team,
            $team,
            $team,
            $team,
            $team,
            $team,
            DBEscapeString($poolId),
        );
        if ($shared) {
            $query .= sprintf(" AND hometeam IN (%s) AND visitorteam IN (%s)", $sameteams, $sameteams);
        }

        $stats1 = DBQueryToRow($query);

        $points[$i]['games'] = $stats1['games'];
        $points[$i]['wins'] = $stats1['wins'];
        $points[$i]['losses'] = $stats1['losses'];
    }
    return $points;
}

function getMatchesGoals($points, $poolId, $shared = false)
{
    $sameteams = DBEscapeString($points[0]['team']);
    for ($i = 1; $i < count($points); $i++) {
        $sameteams .= "," . DBEscapeString($points[$i]['team']);
    }
    //reset counters
    for ($i = 0; $i < count($points); $i++) {
        $points[$i]['goalsmade'] = 0;
        $points[$i]['goalsagainst'] = 0;
        $points[$i]['goalsdiff'] = 0;
    }

    // 	foreach ($points as $point) {
    for ($i = 0; $i < count($points); $i++) {
        $team = DBEscapeString($points[$i]['team']);

        $query = sprintf(
            "
			SELECT hometeam,visitorteam,homescore,visitorscore
			  FROM uo_game
			  WHERE (hometeam='%s' OR visitorteam='%s') AND hasstarted AND isongoing=0
			  AND game_id IN (SELECT game FROM uo_game_pool WHERE pool='%s')",
            $team,
            $team,
            DBEscapeString($poolId),
        );
        if ($shared) {
            $query .= sprintf(" AND hometeam IN (%s) AND visitorteam IN (%s)", $sameteams, $sameteams);
        }

        $result = DBQueryToArray($query);
        foreach ($result as $stats) {
            if ($stats['hometeam'] == $points[$i]['team']) {
                $points[$i]['goalsmade'] += $stats['homescore'];
                $points[$i]['goalsagainst'] += $stats['visitorscore'];
            } elseif ($stats['visitorteam'] == $points[$i]['team']) {
                $points[$i]['goalsmade'] += $stats['visitorscore'];
                $points[$i]['goalsagainst'] += $stats['homescore'];
            }
        }
        $points[$i]['goalsdiff'] = $points[$i]['goalsmade'] - $points[$i]['goalsagainst'];
    }
    return $points;
}

function TeamPoolStanding($teamId, $poolId)
{
    $query = sprintf(
        "SELECT u.activerank FROM uo_team_pool u WHERE pool='%d' AND team='%d'",
        (int) $poolId,
        (int) $teamId,
    );
    return DBQueryToValue($query);
}

function TeamSeriesStanding($teamId)
{

    $team_info = TeamInfo($teamId);
    $ppools = SeriesPlacementPoolIds($team_info['series']);
    $standing = 1;

    $found = false;

    //loop all placement pools
    foreach ($ppools as $ppool) {
        $teams = PoolTeams($ppool['pool_id']);
        $movedPlacings = PoolMovedPlacings($ppool['pool_id']);
        $i = 0;
        //loop all teams
        foreach ($teams as $team) {
            $i++;
            //if not moved and team searched exit loop
            if (!isset($movedPlacings[$i]) && $team['team_id'] == $teamId) {
                $found = true;
                break;
            } elseif (!isset($movedPlacings[$i])) {
                $standing++;
            }
        }
        if ($found) {
            break;
        }
    }

    //if not found then return best guess
    if (!$found) {
        $standing = TeamPoolStanding($teamId, $team_info['pool']);
    }

    return intval($standing);
}

function ManualFinalStandings($seriesId)
{
    $query = sprintf(
        "SELECT fs.season, fs.series, fs.team_id, fs.standing, fs.disqualified, fs.updated_at,
            t.name, t.abbreviation, t.club, cl.name AS clubname, t.valid,
            t.country, c.name AS countryname, t.rank, c.flagfile, tp.poolname
        FROM uo_team_final_standing fs
        LEFT JOIN uo_team t ON (t.team_id=fs.team_id)
        LEFT JOIN (
            SELECT tp.team, GROUP_CONCAT(DISTINCT p.name ORDER BY p.ordering ASC, p.name SEPARATOR ', ') AS poolname
            FROM uo_team_pool tp
            LEFT JOIN uo_pool p ON (tp.pool=p.pool_id)
            GROUP BY tp.team
        ) AS tp ON (t.team_id=tp.team)
        LEFT JOIN uo_club cl ON (cl.club_id=t.club)
        LEFT JOIN uo_country c ON (c.country_id=t.country)
        WHERE fs.series=%d AND t.series=%d
        ORDER BY fs.disqualified ASC, fs.standing IS NULL, fs.standing, t.name, t.team_id",
        (int) $seriesId,
        (int) $seriesId,
    );
    return DBQueryToArray($query);
}

function HasCompleteManualFinalStandings($seriesId)
{
    $teamCount = count(SeriesTeams($seriesId));
    if ($teamCount === 0) {
        return false;
    }

    $standingCount = (int) DBQueryToValue(sprintf(
        "SELECT COUNT(*)
        FROM uo_team_final_standing fs
        LEFT JOIN uo_team t ON (t.team_id=fs.team_id)
        WHERE fs.series=%d AND t.series=%d",
        (int) $seriesId,
        (int) $seriesId,
    ));
    return $standingCount === $teamCount;
}

function ClearFinalStandingsOrder($seasonId, $seriesId)
{
    if (!isSeasonAdmin($seasonId)) {
        die('Insufficient rights to edit final standings');
    }
    if (SeriesSeasonId($seriesId) !== $seasonId) {
        return false;
    }

    $hasArchived = SeriesHasArchivedStats($seriesId);

    DBQuery('START TRANSACTION');
    try {
        DBQuery(sprintf(
            "DELETE FROM uo_team_final_standing WHERE season='%s' AND series=%d",
            DBEscapeString($seasonId),
            (int) $seriesId,
        ));
        if ($hasArchived) {
            foreach (SeriesTeams($seriesId) as $team) {
                DBQuery(sprintf(
                    "UPDATE uo_team_stats SET standing=%d WHERE team_id=%d",
                    TeamSeriesStanding((int) $team['team_id']),
                    (int) $team['team_id'],
                ));
            }
        }
        DBQuery('COMMIT');
    } catch (Throwable $e) {
        DBQuery('ROLLBACK');
        throw $e;
    }

    return true;
}

function SeriesHasArchivedStats($seriesId)
{
    return (bool) DBQueryToValue(sprintf(
        "SELECT 1 FROM uo_team_stats WHERE series=%d LIMIT 1",
        (int) $seriesId,
    ));
}

function SeriesUnplayedGamesCount($seriesId)
{
    return (int) DBQueryToValue(sprintf(
        "SELECT COUNT(*)
        FROM uo_game g
        LEFT JOIN uo_game_pool gp ON (gp.game=g.game_id)
        LEFT JOIN uo_pool p ON (p.pool_id=gp.pool)
        WHERE p.series=%d
            AND gp.timetable=1
            AND g.valid=1
            AND (g.hasstarted=0 OR g.isongoing=1 OR g.homescore IS NULL OR g.visitorscore IS NULL)",
        (int) $seriesId,
    ));
}

function FinalStandingsSeasonStatus($seasonId)
{
    $status = [
        'published' => 0,
        'unpublished' => 0,
    ];

    foreach (SeasonSeries($seasonId) as $series) {
        if (HasCompleteManualFinalStandings((int) $series['series_id'])) {
            $status['published']++;
        } else {
            $status['unpublished']++;
        }
    }

    return $status;
}

/**
 * Final standings for a division: confirmed manual placements when they
 * cover every team, otherwise the automatic live standings.
 */
function SeriesFinalStandings($seriesId)
{
    if (HasCompleteManualFinalStandings($seriesId)) {
        return ManualFinalStandings($seriesId);
    }
    return SeriesRanking($seriesId);
}

function SeriesFinalStandingsConfirmed($seriesId)
{
    return HasCompleteManualFinalStandings($seriesId);
}

function SeriesFinalStandingsMap($seriesId)
{
    $standings = [];
    foreach (SeriesFinalStandings($seriesId) as $index => $row) {
        if (isset($row['team_id'])) {
            if (isset($row['disqualified']) && (int) $row['disqualified'] === 1) {
                $standings[(int) $row['team_id']] = 0;
            } elseif (isset($row['standing']) && (int) $row['standing'] > 0) {
                $standings[(int) $row['team_id']] = (int) $row['standing'];
            } else {
                $standings[(int) $row['team_id']] = $index + 1;
            }
        }
    }
    return $standings;
}

function FinalStandingLabel($standing, $disqualified = false)
{
    if ($disqualified) {
        return _("Disqualified");
    }
    $standing = (int) $standing;
    if ($standing === 1) {
        return _("Gold");
    }
    if ($standing === 2) {
        return _("Silver");
    }
    if ($standing === 3) {
        return _("Bronze");
    }
    if ($standing > 3) {
        return ordinal($standing);
    }
    return _("Undecided");
}

function FinalStandingsAdminOrder($seasonId, $seriesId)
{
    $teams = SeriesTeams($seriesId);
    $teamsById = [];
    foreach ($teams as $team) {
        $teamsById[(int) $team['team_id']] = $team;
    }

    $source = 'teams';
    $manual = ManualFinalStandings($seriesId);
    $manualByStanding = [];
    foreach ($manual as $team) {
        $manualByStanding[(int) $team['standing']] = (int) $team['team_id'];
    }

    if (count($manualByStanding) > 0) {
        $source = 'manual';
    } else {
        $pointsOrder = FinalStandingsSeasonPointsOrder($seasonId, $seriesId, $teams);
        if (count($pointsOrder) > 0) {
            foreach ($pointsOrder as $standing => $teamId) {
                $manualByStanding[$standing + 1] = $teamId;
            }
            $source = 'seasonpoints';
        } else {
            $liveOrder = FinalStandingsLiveOrder($seriesId);
            if (count($liveOrder) > 0) {
                foreach ($liveOrder as $standing => $teamId) {
                    $manualByStanding[$standing + 1] = $teamId;
                }
                $source = 'live';
            }
        }
    }

    $ordered = [];
    $seen = [];
    if (count($manualByStanding) === 0) {
        return [
            'teams' => $teams,
            'source' => $source,
        ];
    }

    $teamCount = count($teams);
    for ($standing = 1; $standing <= $teamCount; $standing++) {
        $teamId = isset($manualByStanding[$standing]) ? (int) $manualByStanding[$standing] : 0;
        if ($teamId > 0 && isset($teamsById[$teamId]) && !isset($seen[$teamId])) {
            $ordered[] = $teamsById[$teamId];
            $seen[$teamId] = true;
        } else {
            $ordered[] = null;
        }
    }

    return [
        'teams' => $ordered,
        'source' => $source,
    ];
}

function SaveFinalStandingsOrder($seasonId, $seriesId, $teamIds)
{
    if (!isSeasonAdmin($seasonId)) {
        die('Insufficient rights to edit final standings');
    }
    if (SeriesSeasonId($seriesId) !== $seasonId) {
        return false;
    }

    $expectedIds = [];
    foreach (SeriesTeams($seriesId) as $team) {
        $expectedIds[] = (int) $team['team_id'];
    }
    $expectedSet = array_flip($expectedIds);
    $cleanIds = [];
    foreach ($teamIds as $teamId) {
        $teamId = (int) $teamId;
        if ($teamId <= 0) {
            $cleanIds[] = 0;
            continue;
        }
        if (!isset($expectedSet[$teamId])) {
            return false;
        }
        $cleanIds[] = $teamId;
    }

    if (count($cleanIds) !== count($expectedIds)) {
        return false;
    }

    $selectedIds = array_values(array_filter($cleanIds));
    if (count($selectedIds) !== count(array_unique($selectedIds))) {
        return false;
    }

    // Preserve placements the drag-reorder list cannot express: disqualifications
    // and already shared placements.
    $disqualified = [];
    $standingGroups = [];
    foreach (DBQueryToArray(sprintf(
        "SELECT team_id, standing, disqualified FROM uo_team_final_standing WHERE series=%d",
        (int) $seriesId,
    )) as $row) {
        $teamId = (int) $row['team_id'];
        if ((int) $row['disqualified'] === 1) {
            $disqualified[$teamId] = true;
            continue;
        }
        $standing = (int) $row['standing'];
        if ($standing > 0) {
            if (!isset($standingGroups[$standing])) {
                $standingGroups[$standing] = [];
            }
            $standingGroups[$standing][] = $teamId;
        }
    }

    $positionsByTeam = [];
    foreach ($cleanIds as $position => $teamId) {
        if ($teamId > 0) {
            $positionsByTeam[$teamId] = $position;
        }
    }

    $preservedSharedGroups = [];
    $preservedSharedGroupByTeam = [];
    foreach ($standingGroups as $groupTeamIds) {
        if (count($groupTeamIds) <= 1) {
            continue;
        }

        $positions = [];
        foreach ($groupTeamIds as $teamId) {
            if (!isset($positionsByTeam[$teamId])) {
                continue 2;
            }
            $positions[] = $positionsByTeam[$teamId];
        }
        sort($positions);
        $firstPosition = $positions[0];
        $lastPosition = $positions[count($positions) - 1];
        if ($lastPosition - $firstPosition + 1 !== count($positions)) {
            continue;
        }

        $groupId = count($preservedSharedGroups);
        $preservedSharedGroups[$groupId] = [];
        for ($position = $firstPosition; $position <= $lastPosition; $position++) {
            $teamId = (int) $cleanIds[$position];
            $preservedSharedGroups[$groupId][] = $teamId;
            $preservedSharedGroupByTeam[$teamId] = $groupId;
        }
    }

    $hasArchived = SeriesHasArchivedStats($seriesId);

    DBQuery('START TRANSACTION');
    try {
        DBQuery(sprintf(
            "DELETE FROM uo_team_final_standing WHERE series=%d",
            (int) $seriesId,
        ));
        $standing = 0;
        $processedSharedGroups = [];
        foreach ($cleanIds as $teamId) {
            if ($teamId <= 0) {
                continue;
            }
            if (isset($preservedSharedGroupByTeam[$teamId])) {
                $groupId = $preservedSharedGroupByTeam[$teamId];
                if (isset($processedSharedGroups[$groupId])) {
                    continue;
                }
                $standing++;
                foreach ($preservedSharedGroups[$groupId] as $sharedTeamId) {
                    DBQuery(sprintf(
                        "INSERT INTO uo_team_final_standing (season, series, team_id, standing, disqualified)
                        VALUES ('%s', %d, %d, %d, 0)",
                        DBEscapeString($seasonId),
                        (int) $seriesId,
                        (int) $sharedTeamId,
                        (int) $standing,
                    ));
                    if ($hasArchived) {
                        DBQuery(sprintf(
                            "UPDATE uo_team_stats SET standing=%d WHERE team_id=%d",
                            (int) $standing,
                            (int) $sharedTeamId,
                        ));
                    }
                }
                $standing += count($preservedSharedGroups[$groupId]) - 1;
                $processedSharedGroups[$groupId] = true;
                continue;
            }
            if (isset($disqualified[$teamId])) {
                DBQuery(sprintf(
                    "INSERT INTO uo_team_final_standing (season, series, team_id, standing, disqualified)
                    VALUES ('%s', %d, %d, NULL, 1)",
                    DBEscapeString($seasonId),
                    (int) $seriesId,
                    (int) $teamId,
                ));
                if ($hasArchived) {
                    DBQuery(sprintf(
                        "UPDATE uo_team_stats SET standing=0 WHERE team_id=%d",
                        (int) $teamId,
                    ));
                }
                continue;
            }
            $standing++;
            DBQuery(sprintf(
                "INSERT INTO uo_team_final_standing (season, series, team_id, standing, disqualified)
                VALUES ('%s', %d, %d, %d, 0)",
                DBEscapeString($seasonId),
                (int) $seriesId,
                (int) $teamId,
                (int) $standing,
            ));
            if ($hasArchived) {
                DBQuery(sprintf(
                    "UPDATE uo_team_stats SET standing=%d WHERE team_id=%d",
                    (int) $standing,
                    (int) $teamId,
                ));
            }
        }
        DBQuery('COMMIT');
    } catch (Throwable $e) {
        DBQuery('ROLLBACK');
        throw $e;
    }

    return true;
}

function SaveFinalStandingsAssignments($seasonId, $seriesId, $assignments)
{
    if (!isSeasonAdmin($seasonId)) {
        die('Insufficient rights to edit final standings');
    }
    if (SeriesSeasonId($seriesId) !== $seasonId) {
        return false;
    }

    $teams = SeriesTeams($seriesId);
    $teamCount = count($teams);
    if ($teamCount === 0) {
        return false;
    }

    // Placements are all-or-nothing: every team must be assigned a placement
    // or a disqualification, otherwise the division stays on live standings.
    $clean = [];
    foreach ($teams as $team) {
        $teamId = (int) $team['team_id'];
        $value = isset($assignments[$teamId]) ? $assignments[$teamId] : 0;
        if ($value === 'dq') {
            $clean[$teamId] = [
                'standing' => null,
                'disqualified' => 1,
            ];
            continue;
        }
        $standing = (int) $value;
        if ($standing < 1 || $standing > $teamCount) {
            return false;
        }
        $clean[$teamId] = [
            'standing' => $standing,
            'disqualified' => 0,
        ];
    }

    $hasArchived = SeriesHasArchivedStats($seriesId);

    DBQuery('START TRANSACTION');
    try {
        DBQuery(sprintf(
            "DELETE FROM uo_team_final_standing WHERE series=%d",
            (int) $seriesId,
        ));
        foreach ($clean as $teamId => $assignment) {
            $standingValue = is_null($assignment['standing']) ? 'NULL' : (string) ((int) $assignment['standing']);
            DBQuery(sprintf(
                "INSERT INTO uo_team_final_standing (season, series, team_id, standing, disqualified)
                VALUES ('%s', %d, %d, %s, %d)",
                DBEscapeString($seasonId),
                (int) $seriesId,
                (int) $teamId,
                $standingValue,
                (int) $assignment['disqualified'],
            ));
            if ($hasArchived) {
                $statsStanding = (int) $assignment['disqualified'] === 1 ? 0 : (int) $assignment['standing'];
                DBQuery(sprintf(
                    "UPDATE uo_team_stats SET standing=%d WHERE team_id=%d",
                    (int) $statsStanding,
                    (int) $teamId,
                ));
            }
        }
        DBQuery('COMMIT');
    } catch (Throwable $e) {
        DBQuery('ROLLBACK');
        throw $e;
    }

    return true;
}

function SaveFinalStandingsOrderByTeamIds($teamIds)
{
    $firstTeamId = 0;
    foreach ($teamIds as $teamId) {
        if ((int) $teamId > 0) {
            $firstTeamId = (int) $teamId;
            break;
        }
    }
    if ($firstTeamId === 0) {
        return false;
    }

    $team = TeamInfo($firstTeamId);
    if (!$team) {
        return false;
    }
    return SaveFinalStandingsOrder($team['season'], (int) $team['series'], $teamIds);
}

function FinalStandingsSeasonPointsOrder($seasonId, $seriesId, $teams)
{
    $rounds = SeasonPointsRounds($seasonId, $seriesId);
    if (!count($rounds)) {
        return [];
    }

    $totals = SeasonPointsSeriesTotals($seasonId, $seriesId);
    $lastRoundId = $rounds[count($rounds) - 1]['round_id'];
    $lastRoundPoints = SeasonPointsRoundPoints($lastRoundId);
    usort($teams, function ($a, $b) use ($totals, $lastRoundPoints) {
        $left = isset($totals[$a['team_id']]) ? (int) $totals[$a['team_id']] : 0;
        $right = isset($totals[$b['team_id']]) ? (int) $totals[$b['team_id']] : 0;
        if ($left !== $right) {
            return $right <=> $left;
        }

        $leftLast = isset($lastRoundPoints[$a['team_id']]) ? (int) $lastRoundPoints[$a['team_id']] : 0;
        $rightLast = isset($lastRoundPoints[$b['team_id']]) ? (int) $lastRoundPoints[$b['team_id']] : 0;
        if ($leftLast !== $rightLast) {
            return $rightLast <=> $leftLast;
        }
        return strcasecmp($a['name'], $b['name']);
    });

    $orderedIds = [];
    foreach ($teams as $team) {
        $orderedIds[] = (int) $team['team_id'];
    }
    return $orderedIds;
}

function FinalStandingsLiveOrder($seriesId)
{
    $orderedIds = [];
    foreach (SeriesRanking($seriesId) as $team) {
        if (isset($team['team_id'])) {
            $orderedIds[] = (int) $team['team_id'];
        }
    }
    return $orderedIds;
}
