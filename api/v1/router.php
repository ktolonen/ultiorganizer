<?php
define('API_RATE_LIMIT', 120);
define('API_RATE_WINDOW', 60);

function api_get_path_parts()
{
  if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
    if ($path !== '') {
      return explode('/', $path);
    }
  }

  $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
  if ($scriptDir !== '' && strpos($requestPath, $scriptDir) === 0) {
    $requestPath = substr($requestPath, strlen($scriptDir));
  }
  $requestPath = trim($requestPath, '/');
  if ($requestPath === '' || $requestPath === 'index.php') {
    return array();
  }
  return explode('/', $requestPath);
}

function api_send_json($statusCode, $payload)
{
  if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
  }
  http_response_code($statusCode);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

function api_error($statusCode, $code, $message, $details = null)
{
  $payload = array(
    'status' => 'error',
    'error' => array(
      'code' => $code,
      'message' => $message
    )
  );
  if ($details !== null) {
    $payload['error']['details'] = $details;
  }
  api_send_json($statusCode, $payload);
}

function api_not_found()
{
  api_error(404, 'not_found', 'Endpoint not found.');
}

function api_get_headers()
{
  $headers = array();
  foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
      $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
      $headers[$name] = $value;
    }
  }
  return $headers;
}

function api_get_token()
{
  $headers = api_get_headers();
  if (!empty($headers['Authorization'])) {
    $auth = trim($headers['Authorization']);
    if (stripos($auth, 'Bearer ') === 0) {
      return trim(substr($auth, 7));
    }
    if (stripos($auth, 'Token ') === 0) {
      return trim(substr($auth, 6));
    }
  }

  if (!empty($headers['X-Api-Token'])) {
    return trim($headers['X-Api-Token']);
  }
  if (!empty($headers['X-Api-Key'])) {
    return trim($headers['X-Api-Key']);
  }

  $queryToken = iget('token');
  if (!empty($queryToken)) {
    return trim($queryToken);
  }

  return '';
}

function api_get_client_ip()
{
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    return trim($parts[0]);
  }
  return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function api_require_method($method)
{
  if (strtoupper($_SERVER['REQUEST_METHOD']) !== strtoupper($method)) {
    api_error(405, 'method_not_allowed', 'Only ' . strtoupper($method) . ' is supported.');
  }
}

function api_apply_rate_headers($rate)
{
  if (headers_sent()) {
    return;
  }
  header('X-RateLimit-Limit: ' . API_RATE_LIMIT);
  header('X-RateLimit-Remaining: ' . $rate['remaining']);
  header('X-RateLimit-Reset: ' . $rate['reset']);
}

function api_enforce_rate_limit($tokenHash, $clientIp)
{
  $rateKey = $tokenHash . '|' . $clientIp;
  $rate = ApiRateLimitCheck($rateKey, API_RATE_LIMIT, API_RATE_WINDOW);
  api_apply_rate_headers($rate);
  if (!$rate['allowed']) {
    api_error(429, 'rate_limited', 'Rate limit exceeded.');
  }
}

function api_require_token()
{
  $token = api_get_token();
  if ($token === '') {
    api_error(401, 'missing_token', 'API token required.');
  }
  $tokenRow = ApiTokenLookup($token);
  if (!$tokenRow) {
    api_error(401, 'invalid_token', 'Invalid API token.');
  }
  ApiTokenTouch($tokenRow['token_id']);
  return $tokenRow;
}

function api_require_current_season($seasonId)
{
  if (empty($seasonId)) {
    api_error(400, 'season_required', 'Season is required.');
  }
  $seasonInfo = SeasonInfo($seasonId);
  if (!$seasonInfo) {
    api_error(404, 'season_not_found', 'Season not found.');
  }
  if (empty($seasonInfo['iscurrent'])) {
    api_error(400, 'historical_season', 'Historical seasons are not available.');
  }
  return $seasonInfo;
}

function api_enforce_token_scope($tokenRow, $seasonId)
{
  if (!$tokenRow) {
    return;
  }
  $scopeType = $tokenRow['scope_type'];
  if ($scopeType === 'installation') {
    return;
  }
  if ($scopeType === 'season') {
    if ((string)$tokenRow['scope_id'] !== (string)$seasonId) {
      api_error(403, 'season_scope_mismatch', 'Token does not grant access to this season.');
    }
    return;
  }
  if ($scopeType === 'user') {
    return;
  }
  api_error(403, 'invalid_scope', 'Token scope is not supported.');
}

function api_resolve_season_id($requestedSeason, $tokenRow)
{
  $seasonId = $requestedSeason;
  if ($seasonId === '' && $tokenRow && $tokenRow['scope_type'] === 'season') {
    $seasonId = $tokenRow['scope_id'];
  }
  if ($seasonId === '') {
    $seasonId = CurrentSeason();
  }
  api_require_current_season($seasonId);
  api_enforce_token_scope($tokenRow, $seasonId);
  return $seasonId;
}

function api_fetch_all($result)
{
  $rows = array();
  if (!$result) {
    return $rows;
  }
  while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
  }
  return $rows;
}

function api_normalize_team($team)
{
  return array(
    'team_id' => (int)$team['team_id'],
    'name' => $team['name'],
    'abbreviation' => $team['abbreviation'],
    'rank' => isset($team['rank']) ? (int)$team['rank'] : null,
    'club' => array(
      'club_id' => isset($team['club']) ? (int)$team['club'] : null,
      'name' => $team['clubname'] ?? null
    ),
    'country' => array(
      'country_id' => isset($team['country']) ? (int)$team['country'] : null,
      'name' => $team['countryname'] ?? null,
      'flagfile' => $team['flagfile'] ?? null
    )
  );
}

function api_normalize_game($row)
{
  return array(
    'game_id' => (int)$row['game_id'],
    'time' => $row['time'],
    'timezone' => $row['timezone'],
    'hasstarted' => (int)$row['hasstarted'],
    'isongoing' => (int)$row['isongoing'],
    'scoresheet_count' => isset($row['scoresheet']) ? (int)$row['scoresheet'] : 0,
    'scores' => array(
      'home' => is_null($row['homescore']) ? null : (int)$row['homescore'],
      'visitor' => is_null($row['visitorscore']) ? null : (int)$row['visitorscore']
    ),
    'teams' => array(
      'home' => array(
        'team_id' => isset($row['hometeam']) ? (int)$row['hometeam'] : null,
        'name' => $row['hometeamname'],
        'abbreviation' => $row['homeshortname'] ?? null,
        'country' => array(
          'country_id' => isset($row['homecountryid']) ? (int)$row['homecountryid'] : null,
          'name' => $row['homecountry'] ?? null,
          'flagfile' => $row['homeflag'] ?? null
        )
      ),
      'visitor' => array(
        'team_id' => isset($row['visitorteam']) ? (int)$row['visitorteam'] : null,
        'name' => $row['visitorteamname'],
        'abbreviation' => $row['visitorshortname'] ?? null,
        'country' => array(
          'country_id' => isset($row['visitorcountryid']) ? (int)$row['visitorcountryid'] : null,
          'name' => $row['visitorcountry'] ?? null,
          'flagfile' => $row['visitorflag'] ?? null
        )
      ),
      'placeholder' => array(
        'home' => $row['phometeamname'] ?? null,
        'visitor' => $row['pvisitorteamname'] ?? null
      )
    ),
    'pool' => array(
      'pool_id' => isset($row['pool']) ? (int)$row['pool'] : null,
      'name' => $row['poolname'] ?? null,
      'color' => $row['color'] ?? null,
      'timeslot' => isset($row['timeslot']) ? (int)$row['timeslot'] : null
    ),
    'division' => array(
      'division_id' => isset($row['series_id']) ? (int)$row['series_id'] : null,
      'name' => $row['seriesname'] ?? null,
      'season' => $row['season'] ?? null,
      'type' => isset($row['type']) ? (int)$row['type'] : null
    ),
    'reservation' => array(
      'reservation_id' => isset($row['reservation_id']) ? (int)$row['reservation_id'] : null,
      'group' => $row['reservationgroup'] ?? null,
      'field' => $row['fieldname'] ?? null,
      'starttime' => $row['starttime'] ?? null,
      'endtime' => $row['endtime'] ?? null
    ),
    'location' => array(
      'location_id' => isset($row['place_id']) ? (int)$row['place_id'] : null,
      'name' => $row['placename'] ?? null,
      'address' => $row['address'] ?? null
    ),
    'scheduling' => array(
      'name' => $row['gamename'] ?? null
    )
  );
}

function api_handle_teams($tokenRow)
{
  api_require_method('GET');
  $seasonId = iget('season');
  if ($seasonId === '') {
    api_error(400, 'season_required', 'Season is required.');
  }
  $seasonInfo = SeasonInfo($seasonId);
  if (!$seasonInfo) {
    api_error(404, 'season_not_found', 'Season not found.');
  }
  api_enforce_token_scope($tokenRow, $seasonId);
  $order = strtolower(iget('order'));
  $bySeeding = ($order === 'seeding');

  $series = SeasonSeries($seasonId, true);
  $divisions = array();
  foreach ($series as $row) {
    $teams = SeriesTeams($row['series_id'], $bySeeding);
    $normalized = array();
    foreach ($teams as $team) {
      $normalized[] = api_normalize_team($team);
    }
    $divisions[] = array(
      'division_id' => (int)$row['series_id'],
      'name' => $row['name'],
      'type' => isset($row['type']) ? (int)$row['type'] : null,
      'teams' => $normalized
    );
  }

  api_send_json(200, array(
    'status' => 'ok',
    'data' => array(
      'season' => array(
        'season_id' => $seasonInfo['season_id'],
        'name' => $seasonInfo['name']
      ),
      'divisions' => $divisions
    )
  ));
}

function api_handle_seasons()
{
  api_require_method('GET');
  $rows = SeasonsAllInfo();
  $seasons = array();
  foreach ($rows as $row) {
    $seasons[] = array(
      'season_id' => $row['season_id'],
      'name' => $row['name'],
      'starttime' => $row['starttime'],
      'endtime' => $row['endtime'],
      'iscurrent' => (int)$row['iscurrent'],
      'type' => $row['type'],
      'istournament' => (int)$row['istournament'],
      'isinternational' => (int)$row['isinternational'],
      'isnationalteams' => (int)$row['isnationalteams']
    );
  }

  api_send_json(200, array(
    'status' => 'ok',
    'data' => array(
      'seasons' => $seasons
    )
  ));
}

function api_handle_divisions($tokenRow)
{
  api_require_method('GET');
  $seasonId = iget('season');
  if ($seasonId === '') {
    api_error(400, 'season_required', 'Season is required.');
  }
  $seasonInfo = SeasonInfo($seasonId);
  if (!$seasonInfo) {
    api_error(404, 'season_not_found', 'Season not found.');
  }
  api_enforce_token_scope($tokenRow, $seasonId);

  $series = SeasonSeries($seasonId, true);
  $pools = SeasonPools($seasonId, false, true);
  $poolsBySeries = array();
  foreach ($pools as $pool) {
    $seriesId = (int)$pool['series_id'];
    if (!isset($poolsBySeries[$seriesId])) {
      $poolsBySeries[$seriesId] = array();
    }
    $poolsBySeries[$seriesId][] = array(
      'pool_id' => (int)$pool['pool_id'],
      'name' => $pool['poolname']
    );
  }

  $divisions = array();
  foreach ($series as $row) {
    $seriesId = (int)$row['series_id'];
    $divisions[] = array(
      'division_id' => $seriesId,
      'name' => $row['name'],
      'type' => isset($row['type']) ? (int)$row['type'] : null,
      'pools' => isset($poolsBySeries[$seriesId]) ? $poolsBySeries[$seriesId] : array()
    );
  }

  api_send_json(200, array(
    'status' => 'ok',
    'data' => array(
      'season' => array(
        'season_id' => $seasonInfo['season_id'],
        'name' => $seasonInfo['name']
      ),
      'divisions' => $divisions
    )
  ));
}

function api_parse_pools_param($value)
{
  $ids = array_filter(array_map('intval', explode(',', $value)), function ($val) {
    return $val > 0;
  });
  return array_values($ids);
}

function api_games_context($tokenRow)
{
  $seasonId = '';
  $id = 0;
  $gamefilter = 'season';

  if (iget('division')) {
    $id = (int)iget('division');
    $gamefilter = 'series';
    $seasonId = SeriesSeasonId($id);
  } elseif (iget('series')) {
    $id = (int)iget('series');
    $gamefilter = 'series';
    $seasonId = SeriesSeasonId($id);
  } elseif (iget('pool')) {
    $id = (int)iget('pool');
    $gamefilter = 'pool';
    $poolInfo = PoolInfo($id);
    $seasonId = $poolInfo ? $poolInfo['season'] : '';
  } elseif (iget('pools')) {
    $poolIds = api_parse_pools_param(iget('pools'));
    if (!empty($poolIds)) {
      $gamefilter = 'poolgroup';
      $id = implode(',', $poolIds);
      foreach ($poolIds as $poolId) {
        $poolInfo = PoolInfo($poolId);
        if (!$poolInfo) {
          api_error(404, 'pool_not_found', 'Pool not found.');
        }
        if ($seasonId === '') {
          $seasonId = $poolInfo['season'];
        } elseif ($seasonId !== $poolInfo['season']) {
          api_error(400, 'pool_season_mismatch', 'Pools must belong to the same season.');
        }
      }
    } else {
      $gamefilter = 'season';
      $seasonId = CurrentSeason();
      $id = $seasonId;
    }
  } elseif (iget('team')) {
    $id = (int)iget('team');
    $gamefilter = 'team';
    $seasonId = TeamSeason($id);
  } elseif (iget('season')) {
    $id = iget('season');
    $gamefilter = 'season';
    $seasonId = $id;
  } else {
    $seasonId = CurrentSeason();
    $gamefilter = 'season';
    $id = $seasonId;
  }

  $seasonId = api_resolve_season_id($seasonId, $tokenRow);

  return array($id, $gamefilter, $seasonId);
}

function api_games_filter()
{
  $filter = iget('filter');
  if ($filter === '') {
    $filter = 'tournaments';
  }

  $timefilter = 'all';
  $order = 'tournaments';

  switch ($filter) {
    case 'today':
      $timefilter = 'today';
      $order = 'series';
      break;
    case 'tomorrow':
      $timefilter = 'tomorrow';
      $order = 'series';
      break;
    case 'yesterday':
      $timefilter = 'yesterday';
      $order = 'series';
      break;
    case 'next':
      $timefilter = 'all';
      $order = 'tournaments';
      break;
    case 'tournaments':
      $timefilter = 'all';
      $order = 'tournaments';
      break;
    case 'series':
      $timefilter = 'all';
      $order = 'series';
      break;
    case 'places':
      $timefilter = 'all';
      $order = 'places';
      break;
    case 'timeslot':
      $timefilter = 'all';
      $order = 'time';
      break;
    case 'all':
      $timefilter = 'all';
      $order = 'series';
      break;
    default:
      $timefilter = 'all';
      $order = 'tournaments';
      break;
  }

  return array($filter, $timefilter, $order);
}

function api_handle_games($tokenRow)
{
  api_require_method('GET');
  list($id, $gamefilter, $seasonId) = api_games_context($tokenRow);
  list($filter, $timefilter, $order) = api_games_filter();

  $group = iget('group');
  if ($group === '') {
    $group = 'all';
  }

  $games = TimetableGames($id, $gamefilter, $timefilter, $order, $group);
  $rows = array();
  while ($row = mysqli_fetch_assoc($games)) {
    $rows[] = api_normalize_game($row);
  }

  $groupings = array();
  if ($group === 'all') {
    $groups = TimetableGrouping($id, $gamefilter, $timefilter);
    foreach ($groups as $groupRow) {
      $groupings[] = $groupRow['reservationgroup'];
    }
  }

  api_send_json(200, array(
    'status' => 'ok',
    'data' => array(
      'season_id' => $seasonId,
      'filter' => array(
        'gamefilter' => $gamefilter,
        'timefilter' => $timefilter,
        'order' => $order,
        'group' => $group
      ),
      'groupings' => $groupings,
      'games' => $rows
    )
  ));
}

function api_gameplay_statistics($gameId, $gameResult)
{
  if (intval($gameResult['isongoing'])) {
    return null;
  }

  $allgoals = GameAllGoals($gameId);
  if (!$allgoals || mysqli_num_rows($allgoals) === 0) {
    return null;
  }

  $turnovers = GameTurnovers($gameId);
  $goal = mysqli_fetch_assoc($allgoals);
  $turnover = mysqli_fetch_assoc($turnovers);

  $ishome = GameIsFirstOffenceHome($gameId);
  if ($ishome == 1) {
    $homeStarts = true;
  } elseif ($ishome == 0) {
    $homeStarts = false;
  } else {
    if ($turnover) {
      if (intval($turnover['time']) < intval($goal['time'])) {
        $homeStarts = intval($turnover['ishome']) > 0;
      } else {
        $homeStarts = intval($goal['ishomegoal']) > 0;
      }
    } else {
      $homeStarts = intval($goal['ishomegoal']) > 0;
    }
  }

  $homeOffence = $homeStarts;
  $homeOffencePoints = 0;
  $visitorOffencePoints = 0;
  $homeBreaks = 0;
  $visitorBreaks = 0;
  $homeTime = 0;
  $visitorTime = 0;
  $homeGoals = 0;
  $visitorGoals = 0;
  $clockTime = 0;
  $homeTurnovers = 0;
  $visitorTurnovers = 0;

  mysqli_data_seek($allgoals, 0);
  while ($goal = mysqli_fetch_assoc($allgoals)) {
    if (($clockTime <= intval($gameResult['halftime'])) && (intval($goal['time']) >= intval($gameResult['halftime']))) {
      $clockTime = intval($gameResult['halftime']);
      $homeOffence = !$homeStarts;
    }

    if ($homeOffence) {
      $homeOffencePoints++;
    } else {
      $visitorOffencePoints++;
    }

    if (mysqli_num_rows($turnovers)) {
      $turnovers = GameTurnovers($gameId);
    }
    while ($turnover = mysqli_fetch_assoc($turnovers)) {
      if ((intval($turnover['time']) > $clockTime) && (intval($turnover['time']) < intval($goal['time']))) {
        if (intval($turnover['ishome'])) {
          $homeTurnovers++;
        } else {
          $visitorTurnovers++;
        }
      }
    }

    if (intval($goal['ishomegoal']) && !$homeOffence) {
      $homeBreaks++;
    } elseif (!intval($goal['ishomegoal']) && $homeOffence) {
      $visitorBreaks++;
    }

    $duration = intval($goal['time']) - $clockTime;
    $clockTime = intval($goal['time']);

    if ($homeOffence) {
      $homeTime += $duration;
    } else {
      $visitorTime += $duration;
    }

    if (intval($goal['ishomegoal'])) {
      $homeGoals++;
      $homeOffence = false;
    } else {
      $visitorGoals++;
      $homeOffence = true;
    }
  }

  $timeouts = GameTimeouts($gameId);
  $homeTimeouts = 0;
  $visitorTimeouts = 0;
  while ($timeout = mysqli_fetch_assoc($timeouts)) {
    if (intval($timeout['ishome'])) {
      $homeTimeouts++;
    } else {
      $visitorTimeouts++;
    }
  }

  $totalTime = $homeTime + $visitorTime;
  $homePct = SafeDivide($homeTime, $totalTime) * 100;
  $visitorPct = SafeDivide($visitorTime, $totalTime) * 100;

  $homeOffenceGoals = abs($homeGoals - $homeBreaks);
  $visitorOffenceGoals = abs($visitorGoals - $visitorBreaks);

  return array(
    'goals' => array('home' => $homeGoals, 'visitor' => $visitorGoals),
    'offence_points' => array('home' => $homeOffencePoints, 'visitor' => $visitorOffencePoints),
    'breaks' => array('home' => $homeBreaks, 'visitor' => $visitorBreaks),
    'turnovers' => array('home' => $homeTurnovers, 'visitor' => $visitorTurnovers),
    'timeouts' => array('home' => $homeTimeouts, 'visitor' => $visitorTimeouts),
    'time_on_offence' => array(
      'home_seconds' => $homeTime,
      'visitor_seconds' => $visitorTime,
      'home_pct' => round($homePct, 1),
      'visitor_pct' => round($visitorPct, 1)
    ),
    'time_on_offence_per_goal' => array(
      'home_seconds' => SafeDivide($homeTime, $homeGoals),
      'visitor_seconds' => SafeDivide($visitorTime, $visitorGoals)
    ),
    'goals_from_offence' => array(
      'home' => $homeOffenceGoals,
      'visitor' => $visitorOffenceGoals
    ),
    'goals_from_defence' => array(
      'home' => $homeBreaks,
      'visitor' => $visitorBreaks
    )
  );
}

function api_handle_gameplay($tokenRow)
{
  api_require_method('GET');
  $gameId = (int)iget('game');
  if ($gameId <= 0) {
    api_error(400, 'game_required', 'Game id is required.');
  }

  $gameResult = GameResult($gameId);
  if (!$gameResult) {
    api_error(404, 'game_not_found', 'Game not found.');
  }

  $seasonId = GameSeason($gameId);
  $seasonInfo = api_require_current_season($seasonId);
  api_enforce_token_scope($tokenRow, $seasonId);

  $gameInfo = GameInfo($gameId);
  $poolInfo = PoolInfo($gameResult['pool']);

  $homeCaptain = GameCaptain($gameId, $gameResult['hometeam']);
  $visitorCaptain = GameCaptain($gameId, $gameResult['visitorteam']);

  $homeBoard = api_fetch_all(GameTeamScoreBorad($gameId, $gameResult['hometeam']));
  $visitorBoard = api_fetch_all(GameTeamScoreBorad($gameId, $gameResult['visitorteam']));
  $homePlayers = GamePlayers($gameId, $gameResult['hometeam']);
  $visitorPlayers = GamePlayers($gameId, $gameResult['visitorteam']);

  $goals = api_fetch_all(GameGoals($gameId));
  $events = GameEvents($gameId);
  $mediaEvents = GameMediaEvents($gameId);
  $media = GetMediaUrlList('game', $gameId);

  $statistics = null;
  if (GameHasStarted($gameResult)) {
    $statistics = api_gameplay_statistics($gameId, $gameResult);
  }

  $spirit = null;
  if (!intval($gameResult['isongoing']) && !empty($seasonInfo['spiritmode'])) {
    $categories = SpiritCategories($seasonInfo['spiritmode']);
    $homePoints = GameGetSpiritPoints($gameId, $gameResult['hometeam']);
    $visitorPoints = GameGetSpiritPoints($gameId, $gameResult['visitorteam']);
    $spiritCategories = array();
    foreach ($categories as $cat) {
      if ($cat['index'] == 0) {
        continue;
      }
      $id = $cat['category_id'];
      $spiritCategories[] = array(
        'category_id' => (int)$id,
        'index' => (int)$cat['index'],
        'name' => $cat['text'],
        'factor' => isset($cat['factor']) ? (float)$cat['factor'] : null,
        'home' => isset($homePoints[$id]) ? (int)$homePoints[$id] : null,
        'visitor' => isset($visitorPoints[$id]) ? (int)$visitorPoints[$id] : null
      );
    }
    $spirit = array(
      'mode' => (int)$seasonInfo['spiritmode'],
      'home_total' => isset($gameResult['homesotg']) ? (float)$gameResult['homesotg'] : null,
      'visitor_total' => isset($gameResult['visitorsotg']) ? (float)$gameResult['visitorsotg'] : null,
      'categories' => $spiritCategories
    );
  }

  $homeScoreboard = array();
  foreach ($homeBoard as $row) {
    $homeScoreboard[] = array(
      'player_id' => (int)$row['player_id'],
      'num' => $row['num'],
      'firstname' => $row['firstname'],
      'lastname' => $row['lastname'],
      'assists' => (int)$row['fedin'],
      'goals' => (int)$row['done'],
      'total' => (int)$row['total'],
      'captain' => ((int)$row['player_id'] === (int)$homeCaptain)
    );
  }

  $visitorScoreboard = array();
  foreach ($visitorBoard as $row) {
    $visitorScoreboard[] = array(
      'player_id' => (int)$row['player_id'],
      'num' => $row['num'],
      'firstname' => $row['firstname'],
      'lastname' => $row['lastname'],
      'assists' => (int)$row['fedin'],
      'goals' => (int)$row['done'],
      'total' => (int)$row['total'],
      'captain' => ((int)$row['player_id'] === (int)$visitorCaptain)
    );
  }

  $goalRows = array();
  foreach ($goals as $goal) {
    $goalRows[] = array(
      'num' => isset($goal['num']) ? (int)$goal['num'] : null,
      'time' => isset($goal['time']) ? (int)$goal['time'] : null,
      'ishomegoal' => isset($goal['ishomegoal']) ? (int)$goal['ishomegoal'] : null,
      'homescore' => isset($goal['homescore']) ? (int)$goal['homescore'] : null,
      'visitorscore' => isset($goal['visitorscore']) ? (int)$goal['visitorscore'] : null,
      'iscallahan' => isset($goal['iscallahan']) ? (int)$goal['iscallahan'] : null,
      'scorer' => array(
        'player_id' => isset($goal['scorer']) ? (int)$goal['scorer'] : null,
        'firstname' => $goal['scorerfirstname'] ?? null,
        'lastname' => $goal['scorerlastname'] ?? null
      ),
      'assist' => array(
        'player_id' => isset($goal['assist']) ? (int)$goal['assist'] : null,
        'firstname' => $goal['assistfirstname'] ?? null,
        'lastname' => $goal['assistlastname'] ?? null
      )
    );
  }

  $eventRows = array();
  foreach ($events as $event) {
    $eventRows[] = array(
      'time' => (int)$event['time'],
      'ishome' => (int)$event['ishome'],
      'type' => $event['type']
    );
  }

  $mediaEventRows = array();
  foreach ($mediaEvents as $event) {
    $mediaEventRows[] = array(
      'time' => isset($event['time']) ? (int)$event['time'] : null,
      'ishome' => isset($event['ishome']) ? (int)$event['ishome'] : null,
      'type' => $event['eventtype'] ?? null,
      'url' => $event['url'] ?? null,
      'name' => $event['name'] ?? null,
      'owner' => $event['owner'] ?? null,
      'owner_id' => isset($event['owner_id']) ? (int)$event['owner_id'] : null
    );
  }

  $mediaRows = array();
  foreach ($media as $entry) {
    $mediaRows[] = array(
      'url_id' => isset($entry['url_id']) ? (int)$entry['url_id'] : null,
      'type' => $entry['type'] ?? null,
      'url' => $entry['url'] ?? null,
      'name' => $entry['name'] ?? null,
      'mediaowner' => $entry['mediaowner'] ?? null,
      'publisher' => $entry['publisher'] ?? null,
      'time' => isset($entry['time']) ? (int)$entry['time'] : null
    );
  }

  api_send_json(200, array(
    'status' => 'ok',
    'data' => array(
      'game' => array(
        'game_id' => (int)$gameResult['game_id'],
        'time' => $gameResult['time'],
        'timezone' => $seasonInfo['timezone'],
        'hasstarted' => (int)$gameResult['hasstarted'],
        'isongoing' => (int)$gameResult['isongoing'],
        'halftime' => isset($gameResult['halftime']) ? (int)$gameResult['halftime'] : null,
        'official' => $gameResult['official'] ?? null,
        'homescore' => is_null($gameResult['homescore']) ? null : (int)$gameResult['homescore'],
        'visitorscore' => is_null($gameResult['visitorscore']) ? null : (int)$gameResult['visitorscore'],
        'gamename' => $gameResult['gamename'] ?? null,
        'pool' => array(
          'pool_id' => isset($poolInfo['pool_id']) ? (int)$poolInfo['pool_id'] : null,
          'name' => $poolInfo['name'] ?? null,
          'series_id' => isset($poolInfo['series']) ? (int)$poolInfo['series'] : null
        ),
        'location' => array(
          'placename' => $gameInfo['placename'] ?? null,
          'fieldname' => $gameInfo['fieldname'] ?? null
        )
      ),
      'teams' => array(
        'home' => array(
          'team_id' => isset($gameResult['hometeam']) ? (int)$gameResult['hometeam'] : null,
          'name' => $gameResult['hometeamname'] ?? null
        ),
        'visitor' => array(
          'team_id' => isset($gameResult['visitorteam']) ? (int)$gameResult['visitorteam'] : null,
          'name' => $gameResult['visitorteamname'] ?? null
        )
      ),
      'players' => array(
        'home' => $homePlayers,
        'visitor' => $visitorPlayers
      ),
      'scoreboard' => array(
        'home' => $homeScoreboard,
        'visitor' => $visitorScoreboard
      ),
      'goals' => $goalRows,
      'events' => $eventRows,
      'media_events' => $mediaEventRows,
      'media' => $mediaRows,
      'statistics' => $statistics,
      'spirit' => $spirit
    )
  ));
}

function api_send_openapi()
{
  $specPath = dirname(__DIR__) . '/openapi.json';
  if (!is_readable($specPath)) {
    api_error(500, 'openapi_missing', 'OpenAPI spec not found.');
  }
  $contents = file_get_contents($specPath);
  if ($contents === false) {
    api_error(500, 'openapi_unreadable', 'Unable to read OpenAPI spec.');
  }
  if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
  }
  http_response_code(200);
  echo $contents;
  exit;
}

function api_v1_route($parts)
{
  $resource = isset($parts[0]) ? $parts[0] : '';
  if ($resource === '' || $resource === 'openapi') {
    api_send_openapi();
  }

  $tokenRow = api_require_token();
  $clientIp = api_get_client_ip();
  api_enforce_rate_limit($tokenRow['token_hash'], $clientIp);

  switch ($resource) {
    case 'seasons':
      api_handle_seasons();
      break;
    case 'divisions':
      api_handle_divisions($tokenRow);
      break;
    case 'teams':
      api_handle_teams($tokenRow);
      break;
    case 'games':
      api_handle_games($tokenRow);
      break;
    case 'gameplay':
      api_handle_gameplay($tokenRow);
      break;
    default:
      api_not_found();
      break;
  }
}
