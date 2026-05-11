<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/user.functions.php';

function SpiritModeDisabledName()
{
    return _("No spirit scoring");
}

function SpiritSeasonInfo($seasoninfo)
{
    if (!$seasoninfo) {
        return [];
    }
    if (!is_array($seasoninfo)) {
        $seasoninfo = SeasonInfo($seasoninfo);
    }
    return is_array($seasoninfo) ? $seasoninfo : [];
}

function ShowSpiritScoresForSeason($seasoninfo)
{
    $seasoninfo = SpiritSeasonInfo($seasoninfo);
    if (empty($seasoninfo['season_id'])) {
        return false;
    }
    return (
        isset($seasoninfo['spiritmode']) &&
        (int) $seasoninfo['spiritmode'] > 0 &&
        (!empty($seasoninfo['showspiritpoints']) || hasSpiritToolsRight($seasoninfo['season_id']))
    );
}

function ShowSpiritComments($seasoninfo)
{
    $seasoninfo = SpiritSeasonInfo($seasoninfo);
    if (empty($seasoninfo['season_id'])) {
        return false;
    }
    return (
        isset($seasoninfo['spiritmode']) &&
        (int) $seasoninfo['spiritmode'] > 0 &&
        (!empty($seasoninfo['showspiritcomments']) || hasSpiritToolsRight($seasoninfo['season_id']))
    );
}

function SpiritOrderedCategories($categories)
{
    $rows = [];
    foreach ($categories as $category) {
        if ((int) $category['index'] > 0) {
            $rows[] = $category;
        }
    }

    usort($rows, function ($a, $b) {
        return (int) $a['index'] <=> (int) $b['index'];
    });

    return $rows;
}

function SpiritDefaultPoints($categories)
{
    $defaults = [];
    foreach (SpiritOrderedCategories($categories) as $category) {
        $min = (int) $category['min'];
        $max = (int) $category['max'];
        $defaults[(int) $category['category_id']] = (int) floor(($min + $max) / 2);
    }
    return $defaults;
}

function SpiritPointsSummary($points, $categories)
{
    if (empty($points)) {
        return '';
    }

    $parts = [];
    $total = SpiritTotal($points, $categories);
    foreach (SpiritOrderedCategories($categories) as $category) {
        $categoryId = (int) $category['category_id'];
        if (!isset($points[$categoryId])) {
            return '';
        }
        $parts[] = (string) $points[$categoryId];
    }

    $summary = implode(' ', $parts);
    if (!is_null($total)) {
        $summary .= ' (' . (int) $total . ')';
    }

    return $summary;
}

function SpiritGameRow($gameId)
{
    $query = sprintf(
        "SELECT
			g.game_id,
			g.hometeam,
			g.visitorteam,
			g.show_spirit,
			se.season_id,
			se.spiritmode,
			se.showspiritpoints,
			se.showspiritcomments,
			se.showspiritpointsonlyoncomplete,
			se.lockteamspiritonsubmit,
			se.event_readonly
		FROM uo_game g
		LEFT JOIN uo_pool p ON (p.pool_id = g.pool)
		LEFT JOIN uo_series s ON (s.series_id = p.series)
		LEFT JOIN uo_season se ON (se.season_id = s.season)
		WHERE g.game_id=%d",
        (int) $gameId,
    );
    return DBQueryToRow($query);
}

function SpiritRequiredCategoryCount($mode)
{
    $mode = (int) $mode;
    if ($mode <= 0) {
        return 0;
    }
    return (int) DBQueryToValue(sprintf(
        "SELECT COUNT(*) FROM uo_spirit_category
		WHERE mode=%d AND `index` > 0",
        $mode,
    ));
}

function TeamSpiritSubmissionComplete($gameId, $teamId, $spiritmode = null)
{
    $gameId = (int) $gameId;
    $teamId = (int) $teamId;
    $mode = is_null($spiritmode) ? 0 : (int) $spiritmode;
    if ($gameId <= 0 || $teamId <= 0) {
        return false;
    }

    if ($mode <= 0) {
        $game = SpiritGameRow($gameId);
        if (!$game) {
            return false;
        }
        $mode = isset($game['spiritmode']) ? (int) $game['spiritmode'] : 0;
    }
    if ($mode <= 0) {
        return false;
    }

    $required = SpiritRequiredCategoryCount($mode);
    if ($required <= 0) {
        return false;
    }

    $count = (int) DBQueryToValue(sprintf(
        "SELECT COUNT(DISTINCT ssc.category_id)
		FROM uo_spirit_score ssc
		LEFT JOIN uo_spirit_category sct ON (sct.category_id = ssc.category_id)
		WHERE ssc.game_id=%d
			AND ssc.team_id=%d
			AND sct.mode=%d
			AND sct.`index` > 0",
        $gameId,
        $teamId,
        $mode,
    ));

    return $count >= $required;
}

function HasFullGameSpiritEditRight($gameId, $game = null)
{
    $gameId = (int) $gameId;
    if ($gameId <= 0 || !function_exists('isLoggedIn') || !isLoggedIn()) {
        return false;
    }

    if (!$game) {
        $game = SpiritGameRow($gameId);
    }
    if (!$game || empty($game['season_id'])) {
        return false;
    }

    $seasonId = $game['season_id'];
    if (hasSpiritEditRight($seasonId)) {
        return true;
    }
    if (isEventReadonly($seasonId) && !canBypassEventReadonly($seasonId)) {
        return false;
    }

    $seriesId = GameSeries($gameId);
    $reservationId = GameReservation($gameId);

    return isset($_SESSION['userproperties']['userrole']['seriesadmin'][$seriesId]) ||
        isset($_SESSION['userproperties']['userrole']['resgameadmin'][$reservationId]) ||
        isset($_SESSION['userproperties']['userrole']['gameadmin'][$gameId]);
}

function HasFullGameSpiritViewRight($gameId, $game = null)
{
    $gameId = (int) $gameId;
    if ($gameId <= 0 || !function_exists('isLoggedIn') || !isLoggedIn()) {
        return false;
    }

    if (!$game) {
        $game = SpiritGameRow($gameId);
    }
    if (!$game || empty($game['season_id'])) {
        return false;
    }

    return hasSpiritToolsRight($game['season_id']) || HasFullGameSpiritEditRight($gameId, $game);
}

function SpiritEntryTeamForUser($gameId, $game = null)
{
    $gameId = (int) $gameId;
    if ($gameId <= 0 || !function_exists('isLoggedIn') || !isLoggedIn()) {
        return -1;
    }

    if (!$game) {
        $game = SpiritGameRow($gameId);
    }
    if (!$game || empty($game['spiritmode'])) {
        return -1;
    }

    if (HasFullGameSpiritViewRight($gameId, $game)) {
        return 0;
    }

    $teams = [];
    if (!empty($game['hometeam']) && hasEditPlayersRight((int) $game['hometeam'])) {
        $teams[] = (int) $game['hometeam'];
    }
    if (!empty($game['visitorteam']) && hasEditPlayersRight((int) $game['visitorteam'])) {
        $teams[] = (int) $game['visitorteam'];
    }

    if (count($teams) === 0) {
        return -1;
    }
    if (count($teams) === 1) {
        return $teams[0];
    }
    return 0;
}

function SpiritEntryUrl($gameId, $baseView = '?view=user/addspirit')
{
    $teamId = SpiritEntryTeamForUser($gameId);
    if ($teamId < 0) {
        return '';
    }

    $url = $baseView . '&game=' . (int) $gameId;
    if ($teamId > 0) {
        $url .= '&team=' . $teamId;
    }
    return $url;
}

function SpiritTeamIdByToken($token)
{
    $token = trim((string) $token);
    if ($token === '' || !ctype_alnum($token)) {
        return 0;
    }

    $query = sprintf(
        "SELECT team_id FROM uo_team WHERE sotg_token='%s' LIMIT 1",
        DBEscapeString($token),
    );
    return (int) DBQueryToValue($query);
}

function SpiritTokenGameRows($teamId)
{
    $query = sprintf(
        "SELECT
			g.game_id,
			g.time,
			g.hasstarted,
			g.isongoing,
			g.hometeam,
			g.visitorteam,
			g.homescore,
			g.visitorscore,
			g.show_spirit,
			th.name AS hometeamname,
			tv.name AS visitorteamname,
			p.name AS poolname,
			s.name AS seriesname,
			se.season_id,
			se.name AS seasonname,
			se.spiritmode,
			se.showspiritpoints,
			se.showspiritcomments,
			se.showspiritpointsonlyoncomplete,
			se.lockteamspiritonsubmit,
			se.event_readonly
		FROM uo_game g
		LEFT JOIN uo_team th ON (th.team_id = g.hometeam)
		LEFT JOIN uo_team tv ON (tv.team_id = g.visitorteam)
		LEFT JOIN uo_pool p ON (p.pool_id = g.pool)
		LEFT JOIN uo_series s ON (s.series_id = p.series)
		LEFT JOIN uo_season se ON (se.season_id = s.season)
		WHERE (g.hometeam=%d OR g.visitorteam=%d)
			AND (
				COALESCE(se.spiritmode, 0) > 0
				OR EXISTS(
					SELECT 1
					FROM uo_spirit_score ssc
					WHERE ssc.game_id = g.game_id
						AND (ssc.team_id = g.hometeam OR ssc.team_id = g.visitorteam)
				)
				OR EXISTS(
					SELECT 1
					FROM uo_comment uc
					WHERE CAST(uc.id AS UNSIGNED) = g.game_id
						AND uc.type IN (5, 6)
				)
			)
		ORDER BY g.time ASC, g.game_id ASC",
        (int) $teamId,
        (int) $teamId,
    );
    return DBQueryToArray($query);
}

function SpiritTokenGame($gameId, $teamId)
{
    $gameId = (int) $gameId;
    $teamId = (int) $teamId;
    if ($gameId <= 0 || $teamId <= 0) {
        return [];
    }

    static $games = [];
    $cacheKey = $gameId . ':' . $teamId;
    if (array_key_exists($cacheKey, $games)) {
        return $games[$cacheKey];
    }

    $query = sprintf(
        "SELECT
			g.game_id,
			g.time,
			g.hasstarted,
			g.isongoing,
			g.hometeam,
			g.visitorteam,
			g.homescore,
			g.visitorscore,
			g.show_spirit,
			th.name AS hometeamname,
			tv.name AS visitorteamname,
			p.name AS poolname,
			s.name AS seriesname,
			se.season_id,
			se.name AS seasonname,
			se.spiritmode,
			se.showspiritpoints,
			se.showspiritcomments,
			se.showspiritpointsonlyoncomplete,
			se.lockteamspiritonsubmit,
			se.event_readonly
		FROM uo_game g
		LEFT JOIN uo_team th ON (th.team_id = g.hometeam)
		LEFT JOIN uo_team tv ON (tv.team_id = g.visitorteam)
		LEFT JOIN uo_pool p ON (p.pool_id = g.pool)
		LEFT JOIN uo_series s ON (s.series_id = p.series)
		LEFT JOIN uo_season se ON (se.season_id = s.season)
		WHERE g.game_id=%d
			AND (g.hometeam=%d OR g.visitorteam=%d)
			AND (
				COALESCE(se.spiritmode, 0) > 0
				OR EXISTS(
					SELECT 1
					FROM uo_spirit_score ssc
					WHERE ssc.game_id = g.game_id
						AND (ssc.team_id = g.hometeam OR ssc.team_id = g.visitorteam)
				)
				OR EXISTS(
					SELECT 1
					FROM uo_comment uc
					WHERE CAST(uc.id AS UNSIGNED) = g.game_id
						AND uc.type IN (5, 6)
				)
			)",
        $gameId,
        $teamId,
        $teamId,
    );

    $game = DBQueryToRow($query);
    $games[$cacheKey] = $game ? $game : [];
    return $games[$cacheKey];
}

function SpiritTokenRatedTeamId($game, $tokenTeamId)
{
    $tokenTeamId = (int) $tokenTeamId;
    $homeTeam = isset($game['hometeam']) ? (int) $game['hometeam'] : 0;
    $visitorTeam = isset($game['visitorteam']) ? (int) $game['visitorteam'] : 0;

    if ($tokenTeamId === $homeTeam) {
        return $visitorTeam;
    }
    if ($tokenTeamId === $visitorTeam) {
        return $homeTeam;
    }
    return 0;
}

function SpiritTokenHasOwnSubmission($gameId, $tokenTeamId, $game = null)
{
    if (!$game) {
        $game = SpiritTokenGame($gameId, $tokenTeamId);
    }
    if (!$game) {
        return false;
    }

    $ratedTeamId = SpiritTokenRatedTeamId($game, $tokenTeamId);
    if ($ratedTeamId <= 0) {
        return false;
    }

    return TeamSpiritSubmissionComplete($gameId, $ratedTeamId, (int) $game['spiritmode']);
}

function SpiritTokenHasReceivedSubmission($gameId, $tokenTeamId, $game = null)
{
    if (!$game) {
        $game = SpiritTokenGame($gameId, $tokenTeamId);
    }
    if (!$game) {
        return false;
    }

    return TeamSpiritSubmissionComplete($gameId, (int) $tokenTeamId, (int) $game['spiritmode']);
}

function SpiritTokenCanViewReceivedPoints($gameId, $tokenTeamId, $game = null)
{
    if (!$game) {
        $game = SpiritTokenGame($gameId, $tokenTeamId);
    }
    if (!$game) {
        return false;
    }

    return SpiritTokenHasOwnSubmission($gameId, $tokenTeamId, $game) &&
        SpiritTokenHasReceivedSubmission($gameId, $tokenTeamId, $game);
}

function SpiritTokenCanSubmit($gameId, $tokenTeamId, $game = null)
{
    $tokenTeamId = (int) $tokenTeamId;
    if ($tokenTeamId <= 0) {
        return false;
    }

    if (!$game) {
        $game = SpiritTokenGame($gameId, $tokenTeamId);
    }
    if (!$game) {
        return false;
    }

    if (empty($game['spiritmode'])) {
        return false;
    }
    if (!empty($game['event_readonly'])) {
        return false;
    }

    $ratedTeamId = SpiritTokenRatedTeamId($game, $tokenTeamId);
    if ($ratedTeamId <= 0) {
        return false;
    }

    if ((int) $game['hasstarted'] <= 0) {
        return false;
    }

    if (!empty($game['lockteamspiritonsubmit']) &&
        TeamSpiritSubmissionComplete($gameId, $ratedTeamId, (int) $game['spiritmode'])) {
        return false;
    }

    return true;
}

function SpiritValidateSubmittedPoints($submittedPoints, $categories)
{
    $validated = [];
    $required = 0;
    foreach ($categories as $category) {
        if ((int) $category['index'] <= 0) {
            continue;
        }
        $required++;

        $categoryId = (int) $category['category_id'];
        if (!isset($submittedPoints[$categoryId]) && !isset($submittedPoints[(string) $categoryId])) {
            return false;
        }

        $rawValue = isset($submittedPoints[$categoryId]) ? $submittedPoints[$categoryId] : $submittedPoints[(string) $categoryId];
        $rawValue = trim((string) $rawValue);
        if ($rawValue === '' || !preg_match('/^-?\d+$/', $rawValue)) {
            return false;
        }

        $value = (int) $rawValue;
        if ($value < (int) $category['min'] || $value > (int) $category['max']) {
            return false;
        }

        $validated[$categoryId] = $value;
    }

    if ($required === 0) {
        return false;
    }

    return $validated;
}

function SpiritTokenSaveSubmission($gameId, $tokenTeamId, $points, $categories)
{
    $tokenTeamId = (int) $tokenTeamId;
    $game = SpiritTokenGame($gameId, $tokenTeamId);
    if (!$game || !SpiritTokenCanSubmit($gameId, $tokenTeamId, $game)) {
        return false;
    }

    $ratedTeamId = SpiritTokenRatedTeamId($game, $tokenTeamId);
    if ($ratedTeamId <= 0) {
        return false;
    }

    $validatedPoints = SpiritValidateSubmittedPoints($points, $categories);
    if ($validatedPoints === false) {
        return false;
    }

    SpiritScoreReplaceByGameTeam($gameId, $ratedTeamId, $validatedPoints);
    RefreshGameSpiritData($gameId);
    return true;
}

function SpiritTokenSaveSubmissionWithComment($gameId, $tokenTeamId, $points, $categories, $comment, $delete = false, $game = null)
{
    $tokenTeamId = (int) $tokenTeamId;
    if (!$game) {
        $game = SpiritTokenGame($gameId, $tokenTeamId);
    }
    if (!$game || !SpiritTokenCanSubmit($gameId, $tokenTeamId, $game)) {
        return false;
    }

    $ratedTeamId = SpiritTokenRatedTeamId($game, $tokenTeamId);
    if ($ratedTeamId <= 0) {
        return false;
    }

    $validatedPoints = SpiritValidateSubmittedPoints($points, $categories);
    if ($validatedPoints === false) {
        return false;
    }

    SpiritScoreReplaceByGameTeam($gameId, $ratedTeamId, $validatedPoints);

    $type = SpiritCommentTypeForTeam($game, $ratedTeamId);
    if ($type > 0) {
        ApplyCommentChange($type, $gameId, CommentRequestedChange($type, $gameId, $comment, $delete));
    }

    RefreshGameSpiritData($gameId);
    return true;
}

function SpiritTokenSaveComment($gameId, $tokenTeamId, $comment, $delete = false, $game = null)
{
    $tokenTeamId = (int) $tokenTeamId;
    if (!$game) {
        $game = SpiritTokenGame($gameId, $tokenTeamId);
    }
    if (!$game || !SpiritTokenCanSubmit($gameId, $tokenTeamId, $game)) {
        return false;
    }

    $ratedTeamId = SpiritTokenRatedTeamId($game, $tokenTeamId);
    if ($ratedTeamId <= 0) {
        return false;
    }

    $type = SpiritCommentTypeForTeam($game, $ratedTeamId);
    if ($type <= 0) {
        return false;
    }

    return ApplyCommentChange($type, $gameId, CommentRequestedChange($type, $gameId, $comment, $delete));
}

function SpiritkeeperGetToken()
{
    if (isset($_GET['token']) && ctype_alnum((string) $_GET['token'])) {
        return (string) $_GET['token'];
    }
    return '';
}

function SpiritkeeperGameTimeLabel($game)
{
    if (empty($game['time'])) {
        return _("Time TBD");
    }
    return ShortDate($game['time']) . ' ' . DefHourFormat($game['time']);
}

function SpiritkeeperGameScoreLabel($game)
{
    $homeScore = isset($game['homescore']) ? $game['homescore'] : null;
    $visitorScore = isset($game['visitorscore']) ? $game['visitorscore'] : null;

    if ($homeScore === null || $visitorScore === null || $homeScore === '' || $visitorScore === '') {
        return '? - ?';
    }

    return (int) $homeScore . ' - ' . (int) $visitorScore;
}

function SpiritkeeperEditGameUrl($gameId, $teamId = 0)
{
    $url = '?view=editgame&game=' . (int) $gameId;
    if ((int) $teamId > 0) {
        $url .= '&team=' . (int) $teamId;
    }
    return $url;
}

function SpiritkeeperAccessibleTeams()
{
    if (!function_exists('isLoggedIn') || !isLoggedIn() || empty($_SESSION['uid'])) {
        return [];
    }

    $seasonIds = [];
    foreach (getEditSeasons($_SESSION['uid']) as $seasonId => $propId) {
        $seasonIds[$seasonId] = true;
    }
    if (!empty($_SESSION['userproperties']['userrole']['seasonadmin'])) {
        foreach ($_SESSION['userproperties']['userrole']['seasonadmin'] as $seasonId => $propId) {
            $seasonIds[$seasonId] = true;
        }
    }
    if (!empty($_SESSION['userproperties']['userrole']['spiritadmin'])) {
        foreach ($_SESSION['userproperties']['userrole']['spiritadmin'] as $seasonId => $propId) {
            $seasonIds[$seasonId] = true;
        }
    }
    if (!empty($_SESSION['userproperties']['userrole']['teamadmin'])) {
        foreach ($_SESSION['userproperties']['userrole']['teamadmin'] as $teamId => $propId) {
            $seasonId = getTeamSeason($teamId);
            if (!empty($seasonId)) {
                $seasonIds[$seasonId] = true;
            }
        }
    }

    $teamsById = [];
    foreach (array_keys($seasonIds) as $seasonId) {
        $seasonInfo = SeasonInfo($seasonId);
        if (empty($seasonInfo['season_id']) || empty($seasonInfo['spiritmode'])) {
            continue;
        }

        if (hasSpiritToolsRight($seasonId)) {
            $seasonTeams = SeasonTeams($seasonId);
        } else {
            $seasonTeams = [];
            foreach (TeamResponsibilities($_SESSION['uid'], $seasonId) as $teamId) {
                $teamInfo = TeamInfo($teamId);
                if (!$teamInfo || empty($teamInfo['name'])) {
                    continue;
                }
                $teamInfo['team_id'] = (int) $teamId;
                $seasonTeams[] = $teamInfo;
            }
        }

        foreach ($seasonTeams as $team) {
            $teamId = isset($team['team_id']) ? (int) $team['team_id'] : 0;
            if ($teamId <= 0) {
                continue;
            }
            $team['season_id'] = $seasonId;
            $team['seasonname'] = $seasonInfo['name'];
            $teamsById[$teamId] = $team;
        }
    }

    $teams = array_values($teamsById);
    usort($teams, function ($a, $b) {
        $aSeason = isset($a['seasonname']) ? (string) $a['seasonname'] : '';
        $bSeason = isset($b['seasonname']) ? (string) $b['seasonname'] : '';
        $seasonCmp = strcasecmp($aSeason, $bSeason);
        if ($seasonCmp !== 0) {
            return $seasonCmp;
        }

        $aSeries = isset($a['seriesname']) ? (string) $a['seriesname'] : '';
        $bSeries = isset($b['seriesname']) ? (string) $b['seriesname'] : '';
        $seriesCmp = strcasecmp($aSeries, $bSeries);
        if ($seriesCmp !== 0) {
            return $seriesCmp;
        }

        $aName = isset($a['name']) ? (string) $a['name'] : '';
        $bName = isset($b['name']) ? (string) $b['name'] : '';
        return strcasecmp($aName, $bName);
    });

    return $teams;
}

function SpiritkeeperCurrentSeasons()
{
    $seasons = [];
    $currentSeasonRows = CurrentSeasons();
    if (!$currentSeasonRows) {
        return $seasons;
    }

    foreach ($currentSeasonRows as $row) {
        $seasonId = isset($row['season_id']) ? (string) $row['season_id'] : '';
        if ($seasonId === '') {
            continue;
        }
        $seasons[$seasonId] = [
            'season_id' => $seasonId,
            'name' => isset($row['name']) ? $row['name'] : $seasonId,
        ];
    }
    return $seasons;
}

function SpiritkeeperCurrentAccessibleTeams()
{
    $currentSeasons = SpiritkeeperCurrentSeasons();
    if (empty($currentSeasons)) {
        return [];
    }

    $teams = [];
    foreach (SpiritkeeperAccessibleTeams() as $team) {
        $seasonId = isset($team['season_id']) ? (string) $team['season_id'] : '';
        if ($seasonId !== '' && isset($currentSeasons[$seasonId])) {
            $teams[] = $team;
        }
    }
    return $teams;
}

function SpiritkeeperSeasonAccessibleTeams($seasonId)
{
    $seasonId = (string) $seasonId;
    if ($seasonId === '') {
        return [];
    }

    $teams = [];
    foreach (SpiritkeeperAccessibleTeams() as $team) {
        if (isset($team['season_id']) && (string) $team['season_id'] === $seasonId) {
            $teams[(int) $team['team_id']] = $team;
        }
    }
    return $teams;
}

function SpiritkeeperSeasonTeamGroups($seasonId)
{
    $seasonId = (string) $seasonId;
    $accessibleTeams = SpiritkeeperSeasonAccessibleTeams($seasonId);
    if (empty($accessibleTeams)) {
        return [];
    }

    $groups = [];
    $teamsBySeries = [];

    foreach (SeasonTeams($seasonId) as $team) {
        $teamId = isset($team['team_id']) ? (int) $team['team_id'] : 0;
        if ($teamId <= 0 || !isset($accessibleTeams[$teamId])) {
            continue;
        }

        $teamInfo = $accessibleTeams[$teamId];
        $seriesLabel = !empty($team['seriesname']) ? $team['seriesname'] : _("No division");
        if (!isset($teamsBySeries[$seriesLabel])) {
            $teamsBySeries[$seriesLabel] = [];
        }
        $teamsBySeries[$seriesLabel][] = $teamInfo;
    }

    foreach ($teamsBySeries as $seriesLabel => $seriesTeams) {
        $groups[] = [
            'seriesname' => $seriesLabel,
            'teams' => $seriesTeams,
        ];
    }

    return $groups;
}

function SpiritkeeperTeamGamesUrl($teamId, $seasonId = '', $basePath = '')
{
    $basePath = (string) $basePath;
    $url = ($basePath === '') ? '?view=teamgames' : $basePath . '?view=teamgames';
    $seasonId = (string) $seasonId;
    if ($seasonId !== '') {
        $url .= '&season=' . urlencode($seasonId);
    }
    $teamId = (int) $teamId;
    if ($teamId > 0) {
        $url .= '&team=' . $teamId;
    }
    return $url;
}

function SpiritkeeperHomeUrl($seasonId = '', $basePath = './spiritkeeper/')
{
    $basePath = (string) $basePath;
    $url = ($basePath === '') ? '?view=home' : $basePath . '?view=home';
    $seasonId = (string) $seasonId;
    if ($seasonId !== '') {
        $url .= '&season=' . urlencode($seasonId);
        return $url;
    }

    $teams = SpiritkeeperCurrentAccessibleTeams();
    if (count($teams) === 1 && !empty($teams[0]['team_id'])) {
        $url .= '&season=' . urlencode($teams[0]['season_id']);
        $url .= '&team=' . (int) $teams[0]['team_id'];
    }
    return $url;
}

function SpiritSubmissionLocked($gameId, $teamId)
{
    $game = SpiritGameRow($gameId);
    if (!$game || empty($game['lockteamspiritonsubmit'])) {
        return false;
    }
    return TeamSpiritSubmissionComplete($gameId, $teamId, (int) $game['spiritmode']);
}

function SpiritTeamIdForCommentType($gameId, $type)
{
    $game = SpiritGameRow($gameId);
    if (!$game) {
        return 0;
    }
    if ((int) $type === (int) COMMENT_TYPE_SPIRIT_HOME) {
        return (int) $game['hometeam'];
    }
    if ((int) $type === (int) COMMENT_TYPE_SPIRIT_VISITOR) {
        return (int) $game['visitorteam'];
    }
    return 0;
}

function CanEditSpiritSubmission($gameId, $teamId)
{
    $gameId = (int) $gameId;
    $teamId = (int) $teamId;
    if ($gameId <= 0 || $teamId <= 0) {
        return false;
    }
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        return false;
    }
    $game = SpiritGameRow($gameId);
    if (!$game) {
        return false;
    }
    if (HasFullGameSpiritEditRight($gameId, $game)) {
        return true;
    }
    if (!function_exists('hasEditPlayersRight')) {
        return false;
    }

    $homeTeam = isset($game['hometeam']) ? (int) $game['hometeam'] : 0;
    $visitorTeam = isset($game['visitorteam']) ? (int) $game['visitorteam'] : 0;
    $responsibleTeamId = 0;
    if ($teamId === $homeTeam) {
        $responsibleTeamId = $visitorTeam;
    } elseif ($teamId === $visitorTeam) {
        $responsibleTeamId = $homeTeam;
    }
    if ($responsibleTeamId <= 0 || !hasEditPlayersRight($responsibleTeamId)) {
        return false;
    }

    if (!empty($game['lockteamspiritonsubmit']) && TeamSpiritSubmissionComplete($gameId, $teamId, (int) $game['spiritmode'])) {
        return false;
    }

    return true;
}

function GameSpiritVisibilityValue($gameId, $game = null)
{
    if (!$game) {
        $game = SpiritGameRow($gameId);
    }
    if (!$game) {
        return 0;
    }
    if (empty($game['spiritmode']) || empty($game['showspiritpoints'])) {
        return 0;
    }
    if (!empty($game['showspiritpointsonlyoncomplete'])) {
        return GameSpiritComplete($gameId, (int) $game['spiritmode']) ? 1 : 0;
    }
    return 1;
}

function RefreshGameSpiritVisibility($gameId)
{
    $gameId = (int) $gameId;
    if ($gameId <= 0) {
        return false;
    }
    $game = SpiritGameRow($gameId);
    if (!$game) {
        return false;
    }
    $showSpirit = GameSpiritVisibilityValue($gameId, $game);
    DBQuery(sprintf(
        "UPDATE uo_game SET show_spirit=%d WHERE game_id=%d",
        $showSpirit,
        $gameId,
    ));
    return true;
}

function RefreshSeasonSpiritVisibility($seasonId)
{
    $query = sprintf(
        "SELECT g.game_id
		FROM uo_game g
		LEFT JOIN uo_pool p ON (p.pool_id = g.pool)
		LEFT JOIN uo_series s ON (s.series_id = p.series)
		WHERE s.season='%s'",
        DBEscapeString($seasonId),
    );
    $games = DBQueryToArray($query);
    foreach ($games as $game) {
        RefreshGameSpiritVisibility((int) $game['game_id']);
    }
    return true;
}

function RefreshSeasonSpiritData($seasonId)
{
    $seasonInfo = SpiritSeasonInfo($seasonId);
    RefreshSeasonSpiritVisibility($seasonId);
    if (!empty($seasonInfo['season_id'])) {
        if (!empty($seasonInfo['spiritmode'])) {
            SpiritRebuildTeamStatsForSeason($seasonInfo['season_id'], (int) $seasonInfo['spiritmode']);
        }
    }
    return true;
}

function RefreshGameSpiritData($gameId)
{
    if (!RefreshGameSpiritVisibility($gameId)) {
        return false;
    }
    $seasonId = GameSeason($gameId);
    if (!empty($seasonId)) {
        RefreshSeasonSpiritData($seasonId);
    }
    return true;
}

function CanViewSpiritScoresForGame($gameId, $seasoninfo = null)
{
    $seasoninfo = SpiritSeasonInfo($seasoninfo ?: GameSeason($gameId));
    if (!$seasoninfo || empty($seasoninfo['spiritmode'])) {
        return false;
    }
    if (hasSpiritToolsRight($seasoninfo['season_id'])) {
        return true;
    }
    if (empty($seasoninfo['showspiritpoints'])) {
        return false;
    }
    $showSpirit = DBQueryToValue(sprintf(
        "SELECT show_spirit FROM uo_game WHERE game_id=%d",
        (int) $gameId,
    ));
    return !empty($showSpirit);
}

function CanViewSpiritCommentsForGame($gameId, $seasoninfo = null)
{
    $seasoninfo = SpiritSeasonInfo($seasoninfo ?: GameSeason($gameId));
    if (!$seasoninfo || empty($seasoninfo['spiritmode'])) {
        return false;
    }
    if (hasSpiritToolsRight($seasoninfo['season_id'])) {
        return true;
    }
    return !empty($seasoninfo['showspiritcomments']) && CanViewSpiritScoresForGame($gameId, $seasoninfo);
}

function SpiritCategories($mode_id)
{
    $cats = SpiritCategoryRows($mode_id);
    $categories = [];
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
        (int) $modeId,
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
        (int) $modeId,
    );
    return DBQueryToArray($query);
}

function SpiritScoreRowsByGameTeam($gameId, $teamId)
{
    $query = sprintf(
        "SELECT * FROM uo_spirit_score WHERE game_id=%d AND team_id=%d",
        (int) $gameId,
        (int) $teamId,
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
			ORDER BY s.series_id ASC, givenfor ASC, g.time ASC",
        DBEscapeString($season),
    );
    return DBQueryToArray($query);
}

function SpiritTimeoutSummaryBySeason($season)
{
    $query = sprintf(
        "SELECT
			COUNT(*) AS total,
			COUNT(DISTINCT st.game) AS games,
			COALESCE(SUM(CASE WHEN st.ishome = 1 THEN 1 ELSE 0 END), 0) AS home_total,
			COALESCE(SUM(CASE WHEN st.ishome = 0 THEN 1 ELSE 0 END), 0) AS away_total
		FROM uo_spirit_timeout st
		LEFT JOIN uo_game g ON (g.game_id = st.game)
		LEFT JOIN uo_pool p ON (p.pool_id = g.pool)
		LEFT JOIN uo_series s ON (s.series_id = p.series)
		WHERE s.season='%s'",
        DBEscapeString($season),
    );
    return DBQueryToRow($query);
}

function SpiritSotgUrlsBySeason($season)
{
    return DBQueryToArray(sprintf(
        "SELECT t.team_id, s.name AS series, t.name AS team, t.sotg_token AS token FROM uo_team AS t
		JOIN uo_series AS s on t.series=s.series_id
		WHERE s.season='%s'
		ORDER BY s.name, t.name",
        DBEscapeString($season),
    ));
}

function SpiritDeleteSotgToken($season, $teamId)
{
    if (!hasSpiritToolsRight($season)) {
        die('Insufficient rights');
    }

    $teamId = (int) $teamId;
    if ($teamId <= 0) {
        return 0;
    }

    DBQuery(sprintf(
        "UPDATE uo_team AS t
		JOIN uo_series AS s on t.series=s.series_id
		SET t.sotg_token=NULL
		WHERE s.season='%s' AND t.team_id=%d AND t.sotg_token IS NOT NULL",
        DBEscapeString($season),
        $teamId,
    ));
    return (int) DBQueryToValue("SELECT ROW_COUNT()", true);
}

function SpiritGenerateSotgTokens($season, $filter = "onlymissing")
{
    if (!hasSpiritToolsRight($season)) {
        die('Insufficient rights');
    }

    if ($filter !== "onlymissing") {
        return -1;
    }

    $teams = DBQueryToArray(sprintf(
        "SELECT t.team_id FROM uo_team AS t
		JOIN uo_series AS s on t.series=s.series_id
		WHERE s.season='%s' AND t.sotg_token IS NULL",
        DBEscapeString($season),
    ));

    $generated = 0;
    foreach ($teams as $team) {
        $token = bin2hex(random_bytes(16));
        DBQuery(sprintf(
            "UPDATE uo_team
			SET sotg_token='%s'
			WHERE team_id=%d AND sotg_token IS NULL",
            DBEscapeString($token),
            (int) $team['team_id'],
        ));
        $generated += (int) DBQueryToValue("SELECT ROW_COUNT()", true);
    }

    return $generated;
}

function SpiritTimeoutGameRowsBySeason($season)
{
    $query = sprintf(
        "SELECT
			g.game_id,
			s.series_id,
			s.name AS division,
			p.name AS pool,
			g.time,
			g.homescore,
			g.visitorscore,
			th.name AS home,
			tv.name AS visitor,
			COALESCE(SUM(CASE WHEN st.ishome = 1 THEN 1 ELSE 0 END), 0) AS home_total,
			COALESCE(SUM(CASE WHEN st.ishome = 0 THEN 1 ELSE 0 END), 0) AS away_total,
			COUNT(*) AS total
		FROM uo_spirit_timeout st
		LEFT JOIN uo_game g ON (g.game_id = st.game)
		LEFT JOIN uo_pool p ON (p.pool_id = g.pool)
		LEFT JOIN uo_series s ON (s.series_id = p.series)
		LEFT JOIN uo_team th ON (th.team_id = g.hometeam)
		LEFT JOIN uo_team tv ON (tv.team_id = g.visitorteam)
		WHERE s.season='%s'
		GROUP BY g.game_id, s.series_id, s.name, p.name, g.time, g.homescore, g.visitorscore, th.name, tv.name
		ORDER BY s.name ASC, p.ordering ASC, g.time ASC, g.game_id ASC",
        DBEscapeString($season),
    );
    return DBQueryToArray($query);
}

function SpiritToCsv($season, $separator)
{
    $seasoninfo = SeasonInfo($season);
    $showSpiritPoints = ShowSpiritScoresForSeason($seasoninfo);
    if (!$showSpiritPoints) {
        die(_("Spirit scores are not visible."));
    }
    $showSpiritComments = ShowSpiritComments($seasoninfo);
    $rows = SpiritToolRowsBySeason($season);
    $result = [];

    foreach ($rows as $row) {
        $exportRow = [
            "Division" => $row['division'],
            "Day" => isset($row['day']) ? $row['day'] : "",
            "Field" => isset($row['field']) ? $row['field'] : "",
            "Time" => !empty($row['time']) ? substr($row['time'], 11, 5) : "",
            "Pool" => $row['pool'],
            "TeamEvaluated" => $row['givenfor'],
            "ByTeam" => $row['givenby'],
        ];
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

function SpiritMissingGames($query)
{
    $games = DBQueryToArray($query);
    $rows = [];
    $modeCategories = [];
    foreach ($games as $game) {
        $mode = (int) $game['spiritmode'];
        if ($mode <= 0) {
            continue;
        }
        if (!isset($modeCategories[$mode])) {
            $modeCategories[$mode] = SpiritCategories($mode);
        }
        if (
            TeamSpiritSubmissionComplete($game['game_id'], $game['hometeam'], $mode) &&
            TeamSpiritSubmissionComplete($game['game_id'], $game['visitorteam'], $mode)
        ) {
            continue;
        }

        $row = [
            'game_id' => (int) $game['game_id'],
            'home' => $game['home'],
            'visitor' => $game['visitor'],
            'homescore' => $game['homescore'],
            'homesotg' => SpiritTotal(GameGetSpiritPoints($game['game_id'], $game['hometeam']), $modeCategories[$mode]),
            'visitorscore' => $game['visitorscore'],
            'visitorsotg' => SpiritTotal(GameGetSpiritPoints($game['game_id'], $game['visitorteam']), $modeCategories[$mode]),
            'time' => $game['time'],
        ];
        if (isset($game['poolname'])) {
            $row['poolname'] = $game['poolname'];
        }
        $rows[] = $row;
    }
    return $rows;
}

function SpiritMissingGamesByPool($poolId)
{
    $query = sprintf(
        "SELECT
			g.game_id,
			g.hometeam,
			g.visitorteam,
			th.name AS home,
			tv.name AS visitor,
			g.homescore,
			g.visitorscore,
			g.time AS time,
			se.spiritmode
		FROM uo_game AS g
		JOIN uo_team AS th ON (g.hometeam=th.team_id)
		JOIN uo_team AS tv ON (g.visitorteam=tv.team_id)
		LEFT JOIN uo_pool p ON (p.pool_id = g.pool)
		LEFT JOIN uo_series s ON (s.series_id = p.series)
		LEFT JOIN uo_season se ON (se.season_id = s.season)
		WHERE g.pool=%d
			AND g.isongoing=0
			AND (COALESCE(g.homescore,0)+COALESCE(g.visitorscore,0))>0
		ORDER BY g.time ASC",
        (int) $poolId,
    );
    return SpiritMissingGames($query);
}

function SpiritMissingGamesBySeries($seriesId)
{
    $query = sprintf(
        "SELECT
			g.game_id,
			g.hometeam,
			g.visitorteam,
			th.name AS home,
			tv.name AS visitor,
			g.homescore,
			g.visitorscore,
			g.time AS time,
			p.name AS poolname,
			se.spiritmode
		FROM uo_game AS g
		JOIN uo_team AS th ON (g.hometeam=th.team_id)
		JOIN uo_team AS tv ON (g.visitorteam=tv.team_id)
		JOIN uo_pool AS p ON g.pool=p.pool_id
		LEFT JOIN uo_series s ON (s.series_id = p.series)
		LEFT JOIN uo_season se ON (se.season_id = s.season)
		WHERE p.series=%d
			AND g.isongoing=0
			AND (COALESCE(g.homescore,0)+COALESCE(g.visitorscore,0))>0
		ORDER BY g.time ASC",
        (int) $seriesId,
    );
    return SpiritMissingGames($query);
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
        (int) $teamId,
    );
    return DBQueryToRow($query);
}

function GameGetSpiritPoints($gameId, $teamId)
{
    $scores = SpiritScoreRowsByGameTeam($gameId, $teamId);
    $points = [];
    foreach ($scores as $score) {
        $points[$score['category_id']] = $score['value'];
    }
    return $points;
}

function TeamSpiritTotal($teamId, $includeIncomplete = false)
{
    $teamId = (int) $teamId;
    $scoreSubquery = "
		SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total, COUNT(*) AS score_count
		FROM uo_spirit_score ssc
		LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
		GROUP BY ssc.game_id, ssc.team_id
	";
    if ($includeIncomplete) {
        $query = sprintf(
            "SELECT SUM(IF(g.hometeam=%d, hspirit.total, vspirit.total)) AS total
			FROM uo_game g
			LEFT JOIN (
				%s
			) AS hspirit ON (g.game_id = hspirit.game_id AND g.hometeam = hspirit.team_id)
			LEFT JOIN (
				%s
			) AS vspirit ON (g.game_id = vspirit.game_id AND g.visitorteam = vspirit.team_id)
			WHERE
				(g.hometeam=%d AND hspirit.score_count IS NOT NULL)
				OR
				(g.visitorteam=%d AND vspirit.score_count IS NOT NULL)",
            $teamId,
            $scoreSubquery,
            $scoreSubquery,
            $teamId,
            $teamId,
        );
    } else {
        $query = sprintf(
            "SELECT SUM(IF(g.hometeam=%d, hspirit.total, vspirit.total)) AS total
			FROM uo_game g
			LEFT JOIN (
				%s
			) AS hspirit ON (g.game_id = hspirit.game_id AND g.hometeam = hspirit.team_id)
			LEFT JOIN (
				%s
			) AS vspirit ON (g.game_id = vspirit.game_id AND g.visitorteam = vspirit.team_id)
			WHERE
				(g.hometeam=%d OR g.visitorteam=%d)
				AND g.show_spirit=1
				AND IF(g.hometeam=%d, hspirit.score_count, vspirit.score_count) IS NOT NULL",
            $teamId,
            $scoreSubquery,
            $scoreSubquery,
            $teamId,
            $teamId,
            $teamId,
        );
    }

    return DBQueryToRow($query);
}

function TeamSpiritStats($teamId)
{
    $teamId = (int) $teamId;
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
        $teamId,
    );
    return DBQueryToRow($query);
}

function TeamSpiritStats2($teamId, $includeIncomplete = false)
{
    $teamId = (int) $teamId;
    $scoreSubquery = "
		SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS total, COUNT(*) AS score_count
		FROM uo_spirit_score ssc
		LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
		GROUP BY ssc.game_id, ssc.team_id
	";
    if ($includeIncomplete) {
        $query = sprintf(
            "SELECT COUNT(*) AS games
			FROM uo_game g
			LEFT JOIN (
				%s
			) AS tscore ON (g.game_id = tscore.game_id AND tscore.team_id = %d)
			WHERE (g.hometeam=%d OR g.visitorteam=%d) AND tscore.score_count IS NOT NULL",
            $scoreSubquery,
            $teamId,
            $teamId,
            $teamId,
        );
    } else {
        $query = sprintf(
            "SELECT COUNT(*) AS games
			FROM uo_game g
			LEFT JOIN (
				%s
			) AS hspirit ON (g.game_id = hspirit.game_id AND g.hometeam = hspirit.team_id)
			LEFT JOIN (
				%s
			) AS vspirit ON (g.game_id = vspirit.game_id AND g.visitorteam = vspirit.team_id)
			WHERE (g.hometeam=%d OR g.visitorteam=%d)
				AND g.show_spirit=1
				AND IF(g.hometeam=%d, hspirit.score_count, vspirit.score_count) IS NOT NULL",
            $scoreSubquery,
            $scoreSubquery,
            $teamId,
            $teamId,
            $teamId,
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
			AND g.isongoing=0
			AND g.show_spirit=1",
        (int) $teamId,
        (int) $poolId,
    );
    return DBQueryToRow($query);
}

function SpiritScoreReplaceByGameTeam($gameId, $teamId, $points)
{
    $query = sprintf(
        "DELETE FROM uo_spirit_score WHERE game_id=%d AND team_id=%d",
        (int) $gameId,
        (int) $teamId,
    );
    DBQuery($query);

    foreach ($points as $cat => $value) {
        if (!is_null($value)) {
            $query = sprintf(
                "INSERT INTO uo_spirit_score (`game_id`, `team_id`, `category_id`, `value`)
				VALUES (%d, %d, %d, %d)",
                (int) $gameId,
                (int) $teamId,
                (int) $cat,
                (int) $value,
            );
            DBQuery($query);
        }
    }
}

function CanDeleteSpiritSubmission($gameId, $teamId)
{
    $gameId = (int) $gameId;
    $teamId = (int) $teamId;
    if ($gameId <= 0 || $teamId <= 0) {
        return false;
    }
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        return false;
    }
    $game = SpiritGameRow($gameId);
    if (!$game) {
        return false;
    }
    if ($teamId !== (int) $game['hometeam'] && $teamId !== (int) $game['visitorteam']) {
        return false;
    }
    return HasFullGameSpiritEditRight($gameId, $game);
}

function GameSetSpiritPoints($gameId, $teamId, $home, $points, $categories)
{
    if (CanEditSpiritSubmission($gameId, $teamId)) {
        SpiritScoreReplaceByGameTeam($gameId, $teamId, $points);
        RefreshGameSpiritData($gameId);
        return true;
    } else {
        return false;
    }
}

function GameDeleteSpiritPoints($gameId, $teamId)
{
    if (!CanDeleteSpiritSubmission($gameId, $teamId)) {
        return false;
    }
    SpiritScoreReplaceByGameTeam($gameId, $teamId, []);
    RefreshGameSpiritData($gameId);
    return true;
}

function GameSpiritComplete($gameId, $spiritmode = null)
{
    $gameId = (int) $gameId;
    $mode = is_null($spiritmode) ? 0 : (int) $spiritmode;

    $game = SpiritGameRow($gameId);
    if (!$game) {
        return false;
    }

    if ($mode <= 0) {
        $mode = isset($game['spiritmode']) ? (int) $game['spiritmode'] : 0;
    }
    if ($mode <= 0) {
        return false;
    }

    $homeTeam = isset($game['hometeam']) ? (int) $game['hometeam'] : 0;
    $visitorTeam = isset($game['visitorteam']) ? (int) $game['visitorteam'] : 0;
    if ($homeTeam <= 0 || $visitorTeam <= 0) {
        return false;
    }

    return (
        TeamSpiritSubmissionComplete($gameId, $homeTeam, $mode) &&
        TeamSpiritSubmissionComplete($gameId, $visitorTeam, $mode)
    );
}

function SpiritTeamPointRows($seasonId, $teamId, $received = true)
{
    $teamId = (int) $teamId;
    $seasonInfo = SpiritSeasonInfo($seasonId);
    $isAdmin = !empty($seasonInfo['season_id']) && hasSpiritToolsRight($seasonInfo['season_id']);
    $query = sprintf(
        "SELECT
			g.game_id,
			g.time,
			g.show_spirit,
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
        $teamId,
    );
    $games = DBQueryToArray($query);
    $rows = [];
    $modeCategories = [];

    foreach ($games as $game) {
        $mode = isset($game['spiritmode']) ? (int) $game['spiritmode'] : 0;
        if ($mode <= 0) {
            continue;
        }

        if (!isset($modeCategories[$mode])) {
            $modeCategories[$mode] = SpiritCategories($mode);
        }
        $categories = $modeCategories[$mode];

        $homeTeam = (int) $game['hometeam'];
        $visitorTeam = (int) $game['visitorteam'];
        $isHome = ($teamId === $homeTeam);
        $opponentId = $isHome ? $visitorTeam : $homeTeam;
        $opponentName = $isHome ? $game['visitorname'] : $game['homename'];

        $ratedTeamId = $received ? $teamId : $opponentId;
        $points = GameGetSpiritPoints($game['game_id'], $ratedTeamId);
        $total = SpiritTotal($points, $categories);
        $complete = GameSpiritComplete($game['game_id'], $mode);
        $isVisible = $isAdmin || (!empty($seasonInfo['showspiritpoints']) && !empty($game['show_spirit']));
        $showComments = $isAdmin || ($isVisible && !empty($seasonInfo['showspiritcomments']));
        $commentType = ($ratedTeamId === $homeTeam) ? COMMENT_TYPE_SPIRIT_HOME : COMMENT_TYPE_SPIRIT_VISITOR;

        $row = [
            'game_id' => (int) $game['game_id'],
            'time' => $game['time'],
            'spiritmode' => $mode,
            'givenby' => $opponentName,
            'givento' => $opponentName,
            'total' => $total,
            'comments' => $showComments ? CommentRaw($commentType, $game['game_id']) : null,
            'is_complete' => $complete ? 1 : 0,
            'is_visible' => $isVisible ? 1 : 0,
        ];

        foreach ($categories as $category) {
            $index = isset($category['index']) ? (int) $category['index'] : 0;
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
    $factors = [];
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
		WHERE pool.series=%d AND gp.timetable=1 AND g1.isongoing=0 AND g1.hasstarted>0 AND g1.show_spirit=1
		ORDER BY st.team_id, st.category_id",
        (int) $seriesId,
    );
    return DBQueryToArray($query);
}

function SeriesSpiritBoard($seriesId)
{
    $factor = SpiritCategoryFactors();
    $scores = SpiritSeriesScoreRows($seriesId);
    $last_team = null;
    $last_category = null;
    $averages = [];
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
                $teamline = ['teamname' => $row['name']];
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

/**
 * Legacy compatibility wrapper for older `live/` API consumers.
 *
 * Historically this helper returned a numerically indexed row array with
 * `team_id`, `teamname`, `total`, `games`, and `catN` fields. The newer
 * `SeriesSpiritBoard()` returns an associative map keyed by team id instead.
 *
 * @param int $seriesId
 * @param string $sorting One of `team`, `games`, `total`, or `catN`
 * @param bool $includeIncomplete Retained for signature compatibility; current
 *        implementation uses the same complete-game filtering as
 *        `SeriesSpiritBoard()`
 * @return array
 */
function SeriesSpiritBoardAlt2($seriesId, $sorting = "total", $includeIncomplete = false)
{
    $seriesId = (int) $seriesId;
    $sorting = (string) $sorting;

    $mode = (int) DBQueryToValue(sprintf(
        "SELECT se.spiritmode
		FROM uo_series sr
		LEFT JOIN uo_season se ON (se.season_id = sr.season)
		WHERE sr.series_id=%d",
        $seriesId,
    ));

    $categoriesById = [];
    if ($mode > 0) {
        foreach (SpiritCategoryRows($mode) as $category) {
            if ((int) $category['index'] > 0) {
                $categoriesById[(int) $category['category_id']] = (int) $category['index'];
            }
        }
    }

    $rows = [];
    foreach (SeriesSpiritBoard($seriesId) as $teamId => $teamRow) {
        $row = [
            'team_id' => (int) $teamId,
            'teamname' => $teamRow['teamname'],
            'total' => isset($teamRow['total']) ? round((float) $teamRow['total'], 2) : 0,
            'games' => isset($teamRow['games']) ? $teamRow['games'] : 0,
        ];

        foreach ($teamRow as $key => $value) {
            if (!is_int($key) && !ctype_digit((string) $key)) {
                continue;
            }

            $categoryId = (int) $key;
            if (!isset($categoriesById[$categoryId])) {
                continue;
            }

            $row['cat' . $categoriesById[$categoryId]] = round((float) $value, 2);
        }

        $rows[] = $row;
    }

    usort($rows, function ($a, $b) use ($sorting) {
        if ($sorting === "team") {
            return strcasecmp((string) $a['teamname'], (string) $b['teamname']);
        }

        $allowedNumericSorts = ['games', 'total', 'cat1', 'cat2', 'cat3', 'cat4', 'cat5'];
        $sortKey = in_array($sorting, $allowedNumericSorts, true) ? $sorting : 'total';
        $av = isset($a[$sortKey]) ? (float) $a[$sortKey] : 0.0;
        $bv = isset($b[$sortKey]) ? (float) $b[$sortKey] : 0.0;

        if ($av === $bv) {
            return strcasecmp((string) $a['teamname'], (string) $b['teamname']);
        }

        return ($av < $bv) ? 1 : -1;
    });

    return $rows;
}

function SeriesSpiritBoardTotalAverages($seriesId, $includeIncomplete = false)
{
    $seriesId = (int) $seriesId;
    $mode = DBQueryToValue(sprintf(
        "SELECT se.spiritmode
		FROM uo_series sr
		LEFT JOIN uo_season se ON (se.season_id = sr.season)
		WHERE sr.series_id=%d",
        $seriesId,
    ));
    $mode = (int) $mode;
    if ($mode <= 0) {
        return [];
    }

    $requiredCategories = SpiritRequiredCategoryCount($mode);
    if ($requiredCategories <= 0) {
        return [];
    }

    $completeGameFilter = "";
    if (!$includeIncomplete) {
        $completeGameFilter = "AND g.show_spirit=1";
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
        $completeGameFilter,
    );
    $rows = DBQueryToArray($query);

    $ret = ['total' => 0.0];
    foreach ($rows as $row) {
        $categoryId = (int) $row['category_id'];
        $index = (int) $row['catindex'];
        $avg = is_null($row['catavg']) ? null : (float) $row['catavg'];
        $ret[$categoryId] = $avg;
        $ret['cat' . $index] = $avg;
        if (!is_null($avg)) {
            $ret['total'] += ((float) $row['factor'] * $avg);
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
        "SELECT g.game_id, g.time, g.hometeam, g.visitorteam,
			ht.name AS homename, vt.name AS visitorname, sn.name AS gamename, se.spiritmode
		FROM uo_game g
		LEFT JOIN uo_game_pool gp ON (g.game_id=gp.game)
		LEFT JOIN uo_pool pool ON (gp.pool=pool.pool_id)
		LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
		LEFT JOIN uo_season se ON (se.season_id = ser.season)
		LEFT JOIN uo_team ht ON (g.hometeam=ht.team_id)
		LEFT JOIN uo_team vt ON (g.visitorteam=vt.team_id)
		LEFT JOIN uo_scheduling_name sn ON (g.name=sn.scheduling_id)
		WHERE ser.series_id=%d AND gp.timetable=1 AND g.isongoing=0 AND g.hasstarted>0
		ORDER BY g.time, g.game_id",
        (int) $seriesId,
    );
    $games = DBQueryToArray($query);
    $rows = [];
    foreach ($games as $game) {
        $mode = (int) $game['spiritmode'];
        if ($mode <= 0) {
            continue;
        }
        if (!TeamSpiritSubmissionComplete($game['game_id'], $game['hometeam'], $mode)) {
            $rows[] = [
                'team_id' => (int) $game['hometeam'],
                'teamname' => $game['homename'],
                'giver_team_id' => (int) $game['visitorteam'],
                'giver_teamname' => $game['visitorname'],
                'opponent_name' => $game['visitorname'],
                'home_name' => $game['homename'],
                'visitor_name' => $game['visitorname'],
                'game_id' => (int) $game['game_id'],
                'gamename' => $game['gamename'],
                'time' => $game['time'],
            ];
        }
        if (!TeamSpiritSubmissionComplete($game['game_id'], $game['visitorteam'], $mode)) {
            $rows[] = [
                'team_id' => (int) $game['visitorteam'],
                'teamname' => $game['visitorname'],
                'giver_team_id' => (int) $game['hometeam'],
                'giver_teamname' => $game['homename'],
                'opponent_name' => $game['homename'],
                'home_name' => $game['homename'],
                'visitor_name' => $game['visitorname'],
                'game_id' => (int) $game['game_id'],
                'gamename' => $game['gamename'],
                'time' => $game['time'],
            ];
        }
    }
    usort($rows, function ($a, $b) {
        $byName = strcmp((string) $a['teamname'], (string) $b['teamname']);
        if ($byName !== 0) {
            return $byName;
        }
        $byTime = strcmp((string) $a['time'], (string) $b['time']);
        if ($byTime !== 0) {
            return $byTime;
        }
        return ((int) $a['game_id']) <=> ((int) $b['game_id']);
    });
    return $rows;
}

function TeamSpiritCategoryStats($teamId, $seasonId, $spiritmode)
{
    $query = sprintf(
        "SELECT ts.category_id, ts.average, ts.games
		FROM uo_team_spirit_stats ts
		LEFT JOIN uo_spirit_category sct ON (sct.category_id = ts.category_id)
		WHERE ts.team_id=%d AND ts.season='%s' AND sct.mode=%d
		ORDER BY sct.`index`",
        (int) $teamId,
        DBEscapeString($seasonId),
        (int) $spiritmode,
    );
    return DBQueryToArray($query);
}

function TeamSpiritCategoryHistoryAveragesByName($teamname, $seriestype, $spiritmode)
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
        (int) $spiritmode,
    );
    return DBQueryToArray($query);
}

function TeamSpiritAveragesByName($teamname, $seriestype)
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
        DBEscapeString($seriestype),
    );
    return DBQueryToArray($query);
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
			AND g.show_spirit=1
		GROUP BY ssc.team_id, ssc.category_id, ser.series_id
		ON DUPLICATE KEY UPDATE
			season=VALUES(season),
			series=VALUES(series),
			games=VALUES(games),
			average=VALUES(average)",
        $seasonSafe,
        $seasonSafe,
        (int) $spiritmode,
    );

    DBQuery($query);
}

function CalcTeamSpiritStats($season)
{
    if (isSeasonAdmin($season)) {
        $season_info = SeasonInfo($season);
        if (empty($season_info['spiritmode']) || (int) $season_info['spiritmode'] <= 0) {
            return;
        }
        SpiritRebuildTeamStatsForSeason($season_info['season_id'], (int) $season_info['spiritmode']);
    } else {
        die('Insufficient rights to archive season');
    }
}
