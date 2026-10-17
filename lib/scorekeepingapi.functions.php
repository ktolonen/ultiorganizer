<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/cache.functions.php';
require_once __DIR__ . '/persistent-cache.functions.php';
require_once __DIR__ . '/scoresheethistory.functions.php';
require_once __DIR__ . '/game.functions.php';
require_once __DIR__ . '/scorekeeper.functions.php';
require_once __DIR__ . '/standings.functions.php';
require_once __DIR__ . '/pool.functions.php';
require_once __DIR__ . '/season.functions.php';
require_once __DIR__ . '/reservation.functions.php';
require_once __DIR__ . '/team.functions.php';
require_once __DIR__ . '/accreditation.functions.php';

/**
 * Lock a game row for the rest of the open transaction.
 *
 * Take it as the first statement, before any other read of the game: the
 * first read fixes the transaction's view, and locking first avoids the
 * shared-to-exclusive lock upgrade deadlock on the game's foreign keys.
 *
 * @param int $gameId uo_game.game_id
 * @return bool Whether the game exists
 * @throws LogicException Outside DBTransactionBegin()
 */
function GameLockForUpdate($gameId)
{
    if (DBTransactionDepth() === 0) {
        throw new LogicException('GameLockForUpdate() needs an open transaction.');
    }
    return DBQueryToValueUncached(sprintf(
        "SELECT game_id FROM uo_game WHERE game_id=%d FOR UPDATE",
        (int) $gameId,
    )) !== null;
}

/**
 * Concurrency token of everything a complete game upload replaces: the
 * SHA-256 of a canonical JSON of the fixture teams, both rosters, goals,
 * timeouts and spirit stoppages, result state, starting offence, halftime
 * and official.
 *
 * Timer fields, defenses, other events and the comment are left out, so a
 * running clock or a no-op write does not move the token. Bypasses the
 * persistent cache for the rest of the request, as live-entry apps do.
 *
 * @param int $gameId uo_game.game_id
 * @param array|null $snapshot A ScoresheetHistoryBuildSnapshot() the caller just
 *     read, to hash it instead of reading again
 * @return string 64 hex characters
 */
function ScorekeeperUploadToken($gameId, ?array $snapshot = null)
{
    if ($snapshot === null) {
        // A post-write call must read the rows the write just changed, and on a
        // GET the snapshot reads would otherwise come from the cross-request cache.
        DisablePersistentCacheForRequest();
        CacheForgetNamespace("scoresheet_history_snapshot");
        $snapshot = ScoresheetHistoryBuildSnapshot((int) $gameId);
    }
    $game = $snapshot['game'];

    $rosters = $snapshot['played'];
    usort($rosters, fn($a, $b) => (int) $a['player'] <=> (int) $b['player']);
    $rosters = array_map(fn($row) => [
        'player' => (int) $row['player'],
        'team' => (int) $row['team'],
        'num' => (int) $row['num'],
        'captain' => (int) $row['captain'],
        'spirit_captain' => (int) $row['spirit_captain'],
    ], $rosters);

    $goals = $snapshot['goals'];
    usort($goals, fn($a, $b) => (int) $a['num'] <=> (int) $b['num']);
    $goals = array_map(fn($row) => [
        'num' => (int) $row['num'],
        'scorer' => $row['scorer'] === null ? null : (int) $row['scorer'],
        'assist' => $row['assist'] === null ? null : (int) $row['assist'],
        'time' => (int) $row['time'],
        'homescore' => (int) $row['homescore'],
        'visitorscore' => (int) $row['visitorscore'],
        'ishomegoal' => (int) $row['ishomegoal'],
        'iscallahan' => (int) $row['iscallahan'],
    ], $goals);

    $stoppages = function (array $rows) {
        usort($rows, fn($a, $b) => (int) $a['num'] <=> (int) $b['num']);
        $byTeam = ['home' => [], 'away' => []];
        foreach ($rows as $row) {
            $byTeam[(int) $row['ishome'] === 1 ? 'home' : 'away'][] = [
                'num' => (int) $row['num'],
                'time' => (int) $row['time'],
            ];
        }
        return $byTeam;
    };

    $offence = null;
    foreach ($snapshot['events'] as $event) {
        if ($event['type'] === 'offence') {
            $offence = (int) $event['ishome'];
            break;
        }
    }

    $scope = [
        'hometeam' => $game['hometeam'] === null ? null : (int) $game['hometeam'],
        'visitorteam' => $game['visitorteam'] === null ? null : (int) $game['visitorteam'],
        'rosters' => $rosters,
        'goals' => $goals,
        'timeouts' => $stoppages($snapshot['timeouts']),
        'spirit_timeouts' => $stoppages($snapshot['spirit_timeouts']),
        'result' => [
            'homescore' => (int) $game['homescore'],
            'visitorscore' => (int) $game['visitorscore'],
            'hasstarted' => (int) $game['hasstarted'],
            'isongoing' => (int) $game['isongoing'],
        ],
        'starting_offence' => $offence,
        'halftime' => (int) $game['halftime'],
        'official' => (string) ($game['official'] ?? ''),
    ];
    return hash('sha256', json_encode($scope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Seconds a scorekeeping API session waits for a row lock before giving up,
 * short so a stuck writer turns into a retry and then a 503 rather than a
 * hung request.
 */
const SCOREKEEPING_API_LOCK_WAIT_SECONDS = 5;

/** Largest accepted number of goals for one side: uo_goal.homescore is unsigned tinyint. */
const SCOREKEEPING_API_MAX_SIDE_GOALS = 255;

/** Largest jersey number: uo_player.num is unsigned tinyint. */
const SCOREKEEPING_API_MAX_JERSEY_NUMBER = 255;

/** Largest goal time in seconds: uo_goal.time is smallint. */
const SCOREKEEPING_API_MAX_GOAL_TIME = 32767;

/** Largest time of a timeout, spirit stoppage or halftime, in seconds. */
const SCOREKEEPING_API_MAX_EVENT_TIME = 86400;

/**
 * A scorekeeping API failure with the answer the client should get. The lib
 * throws it; the API layer maps it to its JSON error envelope.
 */
class ScorekeepingApiException extends RuntimeException
{
    /**
     * @param int $httpStatus HTTP status code
     * @param string $apiCode Machine-readable error code
     * @param string $message English message for the client
     * @param mixed $details Optional structured detail, such as field paths
     * @param string[] $headers Extra response headers, such as "Retry-After: 1"
     */
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $apiCode,
        string $message,
        public readonly mixed $details = null,
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }
}

/**
 * Prepare a scorekeeping API request: bypass the persistent cache for it and
 * make database helper failures throw instead of ending the request. Call
 * before the first database read.
 *
 * @return void
 */
function ScorekeepingApiBootstrap()
{
    DisablePersistentCacheForRequest();
    DBSetExceptionMode(true);
}

/**
 * Roll back whatever transaction the request left open and drop the
 * request-local caches it filled. For the API's last-resort failure handler.
 *
 * @return void
 */
function ScorekeepingApiAbort()
{
    DBTransactionRollbackAll();
    CacheForgetNamespace("scoresheet_history_snapshot");
    CacheForgetNamespace("scorekeeper_grant");
}

/**
 * The stored receipt of an earlier request with this idempotency key.
 *
 * @param int $apiTokenId uo_api_token.token_id
 * @param int $linkTokenId uo_scorekeeper_token.token_id
 * @param int $gameId uo_game.game_id
 * @param string $key Client idempotency key
 * @return array{method: string, path: string, request_hash: string, status: int, response: array}|null
 */
function ApiReceiptFind($apiTokenId, $linkTokenId, $gameId, $key)
{
    $row = DBQueryToRowUncached(sprintf(
        "SELECT method, path, request_hash, status, response FROM uo_api_receipt
		WHERE api_token=%d AND scorekeeper_token=%d AND game=%d AND idempotency_key='%s'",
        (int) $apiTokenId,
        (int) $linkTokenId,
        (int) $gameId,
        DBEscapeString((string) $key),
    ));
    if (!is_array($row)) {
        return null;
    }
    $response = json_decode((string) $row['response'], true);
    return [
        'method' => (string) $row['method'],
        'path' => (string) $row['path'],
        'request_hash' => (string) $row['request_hash'],
        'status' => (int) $row['status'],
        'response' => is_array($response) ? $response : [],
    ];
}

/**
 * Remember the answer to an idempotent request, in the transaction that made
 * its writes so the two stand or fall together.
 *
 * @param int $apiTokenId uo_api_token.token_id
 * @param int $linkTokenId uo_scorekeeper_token.token_id
 * @param int $gameId uo_game.game_id
 * @param string $key Client idempotency key
 * @param string $method HTTP method
 * @param string $path Request path
 * @param string $requestHash SHA-256 of the canonical request body
 * @param int $status HTTP status of the stored answer
 * @param array $response Body of the stored answer
 * @return void
 */
function ApiReceiptStore($apiTokenId, $linkTokenId, $gameId, $key, $method, $path, $requestHash, $status, array $response)
{
    DBExecute(sprintf(
        "INSERT INTO uo_api_receipt (api_token, scorekeeper_token, game, idempotency_key, method, path, request_hash, status, response)
		VALUES (%d, %d, %d, '%s', '%s', '%s', '%s', %d, '%s')",
        (int) $apiTokenId,
        (int) $linkTokenId,
        (int) $gameId,
        DBEscapeString((string) $key),
        DBEscapeString(substr((string) $method, 0, 10)),
        DBEscapeString(substr((string) $path, 0, 255)),
        DBEscapeString((string) $requestHash),
        (int) $status,
        DBEscapeString((string) json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
    ));
}

/**
 * Check an Idempotency-Key header value: 1 to 64 printable ASCII characters.
 *
 * @param string|null $key Header value, null when absent
 * @return string The key
 * @throws ScorekeepingApiException 400 when missing or malformed
 */
function ScorekeepingApiIdempotencyKey($key)
{
    if ($key === null || $key === '') {
        throw new ScorekeepingApiException(400, 'idempotency_key_required', 'The Idempotency-Key header is required.');
    }
    if (!preg_match('/^[\x20-\x7e]{1,64}$/', $key)) {
        throw new ScorekeepingApiException(400, 'invalid_idempotency_key', 'The Idempotency-Key header must be 1 to 64 printable ASCII characters.');
    }
    return $key;
}

/**
 * Run a scorekeeping API write under the game row lock.
 *
 * One transaction per attempt: lock timeout, game lock first, coverage
 * recheck with fresh grant data, then $work. A failure rolls back at every
 * nesting level and clears the request-local caches the attempt filled. A
 * deadlock or lock wait timeout is retried twice, then answered with 503.
 * Output the helpers print while $work runs is discarded and logged.
 *
 * @param int $gameId uo_game.game_id
 * @param callable $work Runs inside the transaction; its return value is returned
 * @return mixed
 * @throws ScorekeepingApiException 404 unknown game, 403 game not covered, 503 busy
 */
function ScorekeepingApiTransaction($gameId, callable $work)
{
    $gameId = (int) $gameId;
    $attempt = 0;
    while (true) {
        $attempt++;
        $buffering = false;
        try {
            DBExecute('SET SESSION innodb_lock_wait_timeout=' . SCOREKEEPING_API_LOCK_WAIT_SECONDS);
            DBTransactionBegin();
            if (!GameLockForUpdate($gameId)) {
                throw new ScorekeepingApiException(404, 'game_not_found', 'Game not found.');
            }
            // The preflight may have memoized grant and snapshot data from before
            // the lock; everything the rest of the attempt decides on is read now.
            CacheForgetNamespace("scorekeeper_grant");
            CacheForgetNamespace("scoresheet_history_snapshot");
            if (ScorekeeperGrantTokenId($gameId) <= 0) {
                throw new ScorekeepingApiException(403, 'game_not_covered', 'The scorekeeping link does not cover this game now.');
            }
            ob_start();
            $buffering = true;
            $result = $work();
            $printed = (string) ob_get_clean();
            $buffering = false;
            if ($printed !== '') {
                error_log('Scorekeeping API: discarded output of a write helper: ' . substr(strip_tags($printed), 0, 200));
            }
            DBTransactionCommit();
            return $result;
        } catch (Throwable $e) {
            if ($buffering) {
                ob_end_clean();
            }
            ScorekeepingApiAbort();
            $errno = ($e instanceof mysqli_sql_exception || $e instanceof DBOperationException) ? (int) $e->getCode() : 0;
            if ($errno !== 1205 && $errno !== 1213) {
                throw $e;
            }
            if ($attempt > 2) {
                throw new ScorekeepingApiException(
                    503,
                    'busy',
                    'The game is being changed by another request. Try again shortly.',
                    null,
                    ['Retry-After: 1'],
                );
            }
            usleep(random_int(50000, 150000));
        }
    }
}

/**
 * The games a scorekeeping link covers right now, for the app to pick from.
 *
 * Needs the scorekeeping API actor set. A link outside its day yields no
 * games and the date it starts working.
 *
 * @param string $season The link's event
 * @param int $linkTokenId uo_scorekeeper_token.token_id
 * @return array{games: array<int, array<string, mixed>>, available_from: ?string}
 */
function ScorekeeperDiscoverGames($season, $linkTokenId)
{
    $games = [];
    foreach (ScorekeeperGrantedGameIds($season) as $gameId) {
        $game = GameInfo($gameId);
        if (!is_array($game)) {
            continue;
        }
        $state = 'not_started';
        if ((int) $game['hasstarted'] > 0) {
            $state = (int) $game['isongoing'] === 1 ? 'ongoing' : 'final';
        }
        $games[] = [
            'game_id' => (int) $game['game_id'],
            'time' => $game['time'],
            'field' => $game['fieldname'],
            'location' => $game['placename'],
            'pool' => [
                'pool_id' => $game['pool'] === null ? null : (int) $game['pool'],
                'name' => $game['poolname'],
            ],
            'home' => [
                'team_id' => $game['hometeam'] === null ? null : (int) $game['hometeam'],
                'name' => $game['hometeam'] === null ? $game['phometeamname'] : $game['hometeamname'],
            ],
            'away' => [
                'team_id' => $game['visitorteam'] === null ? null : (int) $game['visitorteam'],
                'name' => $game['visitorteam'] === null ? $game['pvisitorteamname'] : $game['visitorteamname'],
            ],
            'result' => [
                'state' => $state,
                'home' => $game['homescore'] === null ? null : (int) $game['homescore'],
                'away' => $game['visitorscore'] === null ? null : (int) $game['visitorscore'],
            ],
        ];
    }
    usort($games, fn($a, $b) => [(string) $a['time'], $a['game_id']] <=> [(string) $b['time'], $b['game_id']]);

    $availableFrom = null;
    if ($games === []) {
        $row = ScorekeeperTokenRow($linkTokenId);
        $start = null;
        if (is_array($row) && !empty($row['game'])) {
            $info = GameInfo((int) $row['game']);
            $start = is_array($info) ? $info['time'] : null;
        } elseif (is_array($row) && !empty($row['reservation'])) {
            $reservation = ReservationInfo((int) $row['reservation']);
            $start = is_array($reservation) ? $reservation['starttime'] : null;
        }
        $availableFrom = empty($start) ? null : substr((string) $start, 0, 10);
    }
    return ['games' => $games, 'available_from' => $availableFrom];
}

/**
 * What the scorekeeping app needs to keep one game: one consistent read of the
 * game, both teams' eligible players and game rosters, the scoresheet content
 * a complete upload replaces, the rules, the state an upload must preserve,
 * and the concurrency token. No game notes, contact data or history.
 *
 * Needs the scorekeeping API actor set. Takes no lock.
 *
 * @param int $gameId uo_game.game_id
 * @return array<string, mixed>
 * @throws ScorekeepingApiException 404 unknown game, 403 game not covered
 */
function ScorekeeperEntryContext($gameId)
{
    $gameId = (int) $gameId;
    DisablePersistentCacheForRequest();
    DBTransactionBegin(true);
    try {
        $game = GameInfo($gameId);
        if (!is_array($game)) {
            throw new ScorekeepingApiException(404, 'game_not_found', 'Game not found.');
        }
        if (ScorekeeperGrantTokenId($gameId) <= 0) {
            throw new ScorekeepingApiException(403, 'game_not_covered', 'The scorekeeping link does not cover this game now.');
        }
        $season = (string) $game['season'];
        $seasonInfo = SeasonInfo($season);
        $hideTime = !empty($seasonInfo['hide_time_on_scoresheet']);
        $requireAccreditation = !empty($seasonInfo['require_accreditation']);
        $spiritEnabled = !empty($seasonInfo['spiritmode']);

        CacheForgetNamespace("scoresheet_history_snapshot");
        $snapshot = ScoresheetHistoryBuildSnapshot($gameId);
        $stored = $snapshot['game'];
        $homeTeam = $stored['hometeam'];
        $awayTeam = $stored['visitorteam'];

        $sides = [];
        foreach (['home' => $homeTeam, 'away' => $awayTeam] as $side => $teamId) {
            $eligible = [];
            $roster = [];
            if ($teamId !== null) {
                foreach (TeamPlayerList($teamId) as $player) {
                    $entry = [
                        'player_id' => (int) $player['player_id'],
                        'name' => trim($player['firstname'] . ' ' . $player['lastname']),
                        'number' => $player['num'] === null ? null : (int) $player['num'],
                    ];
                    if ($requireAccreditation) {
                        $entry['accredited'] = (int) $player['accredited'] === 1;
                    }
                    $eligible[] = $entry;
                }
                foreach ($snapshot['played'] as $row) {
                    if ((int) $row['team'] === (int) $teamId) {
                        $roster[] = [
                            'player_id' => (int) $row['player'],
                            'number' => $row['num'] === null ? null : (int) $row['num'],
                            'captain' => (int) $row['captain'] === 1,
                            'spirit_captain' => (int) $row['spirit_captain'] === 1,
                        ];
                    }
                }
            }
            $sides[$side] = [
                'team_id' => $teamId === null ? null : (int) $teamId,
                'name' => $teamId === null
                    ? ($side === 'home' ? $game['phometeamname'] : $game['pvisitorteamname'])
                    : ($side === 'home' ? $game['hometeamname'] : $game['visitorteamname']),
                'eligible_players' => $eligible,
                'roster' => $roster,
            ];
        }

        $goals = [];
        foreach ($snapshot['goals'] as $row) {
            $goals[] = [
                'num' => (int) $row['num'],
                'side' => (int) $row['ishomegoal'] === 1 ? 'home' : 'away',
                'scorer_id' => $row['scorer'] === null ? null : (int) $row['scorer'],
                'assist_id' => $row['assist'] === null ? null : (int) $row['assist'],
                'callahan' => (int) $row['iscallahan'] === 1,
                'time' => $hideTime ? null : (int) $row['time'],
                'home_score' => (int) $row['homescore'],
                'away_score' => (int) $row['visitorscore'],
            ];
        }

        $stoppages = function (array $rows) {
            usort($rows, fn($a, $b) => (int) $a['num'] <=> (int) $b['num']);
            $bySide = ['home' => [], 'away' => []];
            foreach ($rows as $row) {
                $bySide[(int) $row['ishome'] === 1 ? 'home' : 'away'][] = ['time' => (int) $row['time']];
            }
            return $bySide;
        };

        $offence = null;
        foreach ($snapshot['events'] as $event) {
            if ($event['type'] === 'offence') {
                $offence = (int) $event['ishome'] === 1 ? 'home' : 'away';
                break;
            }
        }

        $result = null;
        if ((int) $stored['hasstarted'] > 0) {
            $result = [
                'home' => (int) $stored['homescore'],
                'away' => (int) $stored['visitorscore'],
                'state' => (int) $stored['isongoing'] === 1 ? 'ongoing' : 'final',
            ];
        }

        $capEvents = [];
        foreach (GameCapEvents($gameId) as $event) {
            $capEvents[] = ['type' => $event['type'], 'time' => (int) $event['time'], 'point_cap' => (int) $event['info']];
        }
        $referenced = [];
        foreach ($snapshot['defenses'] as $row) {
            if ($row['author'] !== null) {
                $referenced[(int) $row['author']] = true;
            }
        }
        $referenced = array_keys($referenced);
        sort($referenced);
        $forfeitLabels = [0 => 'none', 1 => 'home', 2 => 'away', 3 => 'both'];

        $halftime = (int) ($stored['halftime'] ?? 0);
        $spiritTimeouts = $spiritEnabled ? $stoppages($snapshot['spirit_timeouts']) : ['home' => [], 'away' => []];
        $token = ScorekeeperUploadToken($gameId, $snapshot);

        $context = [
            'game' => [
                'game_id' => (int) $game['game_id'],
                'time' => $game['time'],
                'field' => $game['fieldname'],
                'location' => $game['placename'],
                'pool' => [
                    'pool_id' => $game['pool'] === null ? null : (int) $game['pool'],
                    'name' => $game['poolname'],
                ],
                'division' => $game['seriesname'],
                'event' => [
                    'event_id' => $season,
                    'name' => $seasonInfo['name'] ?? null,
                    'timezone' => $seasonInfo['timezone'] ?? null,
                ],
            ],
            'home' => $sides['home'],
            'away' => $sides['away'],
            'result' => $result,
            'goals' => $goals,
            'timeouts' => $stoppages($snapshot['timeouts']),
            'spirit_stoppages' => $spiritTimeouts,
            'metadata' => [
                'starting_offence' => $offence,
                'halftime' => $halftime > 0 ? $halftime : null,
                'official' => ($stored['official'] ?? '') === '' ? null : (string) $stored['official'],
            ],
            'rules' => [
                'hide_time_on_scoresheet' => $hideTime,
                'timeouts_per_team' => GameTimeoutsPerTeam($gameId),
                'spirit_stoppages_enabled' => $spiritEnabled,
                'require_accreditation' => $requireAccreditation,
                'max_game_score' => MAX_GAME_SCORE,
                'max_goals_per_side' => SCOREKEEPING_API_MAX_SIDE_GOALS,
                'max_jersey_number' => SCOREKEEPING_API_MAX_JERSEY_NUMBER,
            ],
            'retained' => [
                'forfeit' => $forfeitLabels[(int) $stored['forfeit']] ?? 'none',
                'cap_events' => $capEvents,
                'defenses' => [
                    'home' => (int) $stored['homedefenses'],
                    'away' => (int) $stored['visitordefenses'],
                    'referenced_player_ids' => $referenced,
                ],
            ],
            'token' => $token,
        ];
        DBTransactionCommit();
        return $context;
    } catch (Throwable $e) {
        DBTransactionRollbackAll();
        throw $e;
    }
}

/**
 * Check the shape of a complete game upload document and return it with
 * normalized types. Every field is required, unknown fields are refused, and
 * nothing is read from the database.
 *
 * @param mixed $raw The decoded JSON body
 * @return array<string, mixed> The document in a fixed key order
 * @throws ScorekeepingApiException 400 with the paths of the offending fields
 */
function ScorekeeperGameDataNormalize($raw)
{
    $errors = [];
    $add = function ($path, $message) use (&$errors) {
        if (count($errors) < 50) {
            $errors[] = ['path' => $path, 'message' => $message];
        }
    };
    $isObject = fn($value) => is_array($value) && ($value === [] || !array_is_list($value));
    // Reports unknown and missing keys; true when the object has exactly the expected keys.
    $keys = function ($value, $path, array $expected) use ($add, $isObject) {
        if (!$isObject($value)) {
            $add($path, 'Must be an object.');
            return false;
        }
        $ok = true;
        foreach (array_keys($value) as $key) {
            if (!in_array($key, $expected, true)) {
                $add($path === '' ? (string) $key : $path . '.' . $key, 'Unknown field.');
                $ok = false;
            }
        }
        foreach ($expected as $key) {
            if (!array_key_exists($key, $value)) {
                $add($path === '' ? $key : $path . '.' . $key, 'Required field is missing.');
                $ok = false;
            }
        }
        return $ok;
    };
    $int = function ($value, $path, $min, $max, $nullable = false) use ($add) {
        if ($value === null && $nullable) {
            return null;
        }
        if (!is_int($value) || $value < $min || $value > $max) {
            $add($path, sprintf('Must be %swhole number from %d to %d.', $nullable ? 'null or a ' : 'a ', $min, $max));
            return null;
        }
        return $value;
    };
    $list = function ($value, $path, $max) use ($add) {
        if (!is_array($value) || !array_is_list($value)) {
            $add($path, 'Must be a list.');
            return [];
        }
        if (count($value) > $max) {
            $add($path, sprintf('Must have at most %d items.', $max));
            return [];
        }
        return $value;
    };

    $doc = [];
    $keys($raw, '', [
        'version', 'home_team_id', 'away_team_id', 'rosters', 'goals', 'timeouts',
        'spirit_stoppages', 'result', 'starting_offence', 'halftime', 'official',
    ]);
    $raw = $isObject($raw) ? $raw : [];

    if (array_key_exists('version', $raw) && $raw['version'] !== 1) {
        $add('version', 'Unsupported version; this server accepts version 1.');
    }
    $doc['version'] = 1;
    $doc['home_team_id'] = array_key_exists('home_team_id', $raw) ? $int($raw['home_team_id'], 'home_team_id', 1, 2147483647) : null;
    $doc['away_team_id'] = array_key_exists('away_team_id', $raw) ? $int($raw['away_team_id'], 'away_team_id', 1, 2147483647) : null;

    $doc['rosters'] = ['home' => [], 'away' => []];
    if (array_key_exists('rosters', $raw) && $keys($raw['rosters'], 'rosters', ['home', 'away'])) {
        foreach (['home', 'away'] as $side) {
            foreach ($list($raw['rosters'][$side], "rosters.$side", 100) as $i => $entry) {
                $path = "rosters.{$side}[$i]";
                if (!$keys($entry, $path, ['player_id', 'number', 'captain', 'spirit_captain'])) {
                    continue;
                }
                foreach (['captain', 'spirit_captain'] as $flag) {
                    if (!is_bool($entry[$flag])) {
                        $add("$path.$flag", 'Must be true or false.');
                    }
                }
                $doc['rosters'][$side][] = [
                    'player_id' => $int($entry['player_id'], "$path.player_id", 1, 2147483647),
                    'number' => $int($entry['number'], "$path.number", 0, SCOREKEEPING_API_MAX_JERSEY_NUMBER),
                    'captain' => $entry['captain'] === true,
                    'spirit_captain' => $entry['spirit_captain'] === true,
                ];
            }
        }
    }

    $doc['goals'] = [];
    $sideGoals = ['home' => 0, 'away' => 0];
    if (array_key_exists('goals', $raw)) {
        foreach ($list($raw['goals'], 'goals', 2 * SCOREKEEPING_API_MAX_SIDE_GOALS) as $i => $entry) {
            $path = "goals[$i]";
            if (!$keys($entry, $path, ['side', 'scorer_id', 'assist_id', 'callahan', 'time'])) {
                continue;
            }
            if ($entry['side'] !== 'home' && $entry['side'] !== 'away') {
                $add("$path.side", 'Must be "home" or "away".');
            } elseif (++$sideGoals[$entry['side']] > SCOREKEEPING_API_MAX_SIDE_GOALS) {
                $add($path, sprintf('A side can have at most %d goals.', SCOREKEEPING_API_MAX_SIDE_GOALS));
            }
            if (!is_bool($entry['callahan'])) {
                $add("$path.callahan", 'Must be true or false.');
            }
            $doc['goals'][] = [
                'side' => $entry['side'],
                'scorer_id' => $int($entry['scorer_id'], "$path.scorer_id", 1, 2147483647, true),
                'assist_id' => $int($entry['assist_id'], "$path.assist_id", 1, 2147483647, true),
                'callahan' => $entry['callahan'] === true,
                'time' => $int($entry['time'], "$path.time", 0, SCOREKEEPING_API_MAX_GOAL_TIME, true),
            ];
        }
    }

    foreach (['timeouts', 'spirit_stoppages'] as $name) {
        $doc[$name] = ['home' => [], 'away' => []];
        if (array_key_exists($name, $raw) && $keys($raw[$name], $name, ['home', 'away'])) {
            foreach (['home', 'away'] as $side) {
                foreach ($list($raw[$name][$side], "$name.$side", 255) as $i => $entry) {
                    $path = "{$name}.{$side}[$i]";
                    if ($keys($entry, $path, ['time'])) {
                        $doc[$name][$side][] = ['time' => $int($entry['time'], "$path.time", 0, SCOREKEEPING_API_MAX_EVENT_TIME)];
                    }
                }
            }
        }
    }

    $doc['result'] = null;
    if (array_key_exists('result', $raw) && $raw['result'] !== null) {
        if ($keys($raw['result'], 'result', ['home', 'away', 'state'])) {
            if ($raw['result']['state'] !== 'ongoing' && $raw['result']['state'] !== 'final') {
                $add('result.state', 'Must be "ongoing" or "final".');
            }
            $doc['result'] = [
                'home' => $int($raw['result']['home'], 'result.home', 0, MAX_GAME_SCORE),
                'away' => $int($raw['result']['away'], 'result.away', 0, MAX_GAME_SCORE),
                'state' => $raw['result']['state'],
            ];
        }
    }

    $doc['starting_offence'] = $raw['starting_offence'] ?? null;
    if (array_key_exists('starting_offence', $raw) && $doc['starting_offence'] !== null && $doc['starting_offence'] !== 'home' && $doc['starting_offence'] !== 'away') {
        $add('starting_offence', 'Must be null, "home" or "away".');
    }
    $doc['halftime'] = array_key_exists('halftime', $raw) ? $int($raw['halftime'], 'halftime', 0, SCOREKEEPING_API_MAX_EVENT_TIME, true) : null;
    $doc['official'] = $raw['official'] ?? null;
    if (array_key_exists('official', $raw) && $doc['official'] !== null && (!is_string($doc['official']) || mb_strlen($doc['official']) > 50)) {
        $add('official', 'Must be null or a text of at most 50 characters.');
    }

    if ($errors !== []) {
        throw new ScorekeepingApiException(400, 'invalid_document', 'The game data document is not valid.', $errors);
    }
    return $doc;
}

/**
 * Replace everything a complete upload covers in one game: both rosters,
 * goals, timeouts, spirit stoppages, starting offence, halftime, official and
 * the result. Retained data (cap events, defenses, forfeit, game notes, the
 * clock of an ongoing game) is kept.
 *
 * Runs inside ScorekeepingApiTransaction(), which holds the game lock. Order:
 * event and rights recheck, idempotency receipt, If-Match token, validation
 * against the stored game, forced snapshot, the writes, the receipt, the new
 * token. Every helper's return value is checked, so a failure throws and the
 * transaction rolls back whole.
 *
 * A game that goes from final back to ongoing gets its pool standings
 * recomputed, as clearing a result does, because standings count only final
 * games and GameUpdateResult() does not recompute them.
 *
 * @param int $gameId uo_game.game_id
 * @param array $doc Output of ScorekeeperGameDataNormalize()
 * @param array{api_token: int, link_token: int, key: string, method: string, path: string, if_match: string} $request
 * @return array{status: int, body: array, token: string, replay: bool}
 * @throws ScorekeepingApiException
 */
function ScorekeeperReplaceGameData($gameId, array $doc, array $request)
{
    $gameId = (int) $gameId;
    $season = (string) GameSeason($gameId);
    if (IsSeasonInMaintenance($season)) {
        throw new ScorekeepingApiException(503, 'event_maintenance', 'The event is under maintenance.', null, ['Retry-After: 60']);
    }
    if (isEventReadonly($season)) {
        throw new ScorekeepingApiException(403, 'event_readonly', 'The event is read-only.');
    }
    if (!hasEditGameEventsRight($gameId) || !hasEditGamePlayersRight($gameId)) {
        throw new ScorekeepingApiException(403, 'forbidden', 'The scorekeeping link does not allow editing this game.');
    }

    $requestHash = hash('sha256', (string) json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $receipt = ApiReceiptFind($request['api_token'], $request['link_token'], $gameId, $request['key']);
    if ($receipt !== null) {
        if ($receipt['method'] !== $request['method'] || $receipt['path'] !== $request['path'] || $receipt['request_hash'] !== $requestHash) {
            throw new ScorekeepingApiException(409, 'idempotency_conflict', 'The Idempotency-Key was already used for a different request.');
        }
        return [
            'status' => $receipt['status'],
            'body' => $receipt['response'],
            'token' => (string) ($receipt['response']['data']['token'] ?? ''),
            'replay' => true,
        ];
    }

    if ($request['if_match'] === '*' || !hash_equals(ScorekeeperUploadToken($gameId), (string) $request['if_match'])) {
        throw new ScorekeepingApiException(409, 'stale_token', 'The game has changed since the token was issued. Fetch the entry context again before uploading.');
    }

    $seasonInfo = SeasonInfo($season);
    $hideTime = !empty($seasonInfo['hide_time_on_scoresheet']);
    $requireAccreditation = !empty($seasonInfo['require_accreditation']);
    $spiritEnabled = !empty($seasonInfo['spiritmode']);

    $game = DBQueryToRowUncached(sprintf(
        "SELECT hometeam, visitorteam, forfeit, hasstarted, isongoing, halftime, official FROM uo_game WHERE game_id=%d",
        $gameId,
    ));
    if (!is_array($game)) {
        throw new ScorekeepingApiException(404, 'game_not_found', 'Game not found.');
    }
    $teams = ['home' => $game['hometeam'] === null ? null : (int) $game['hometeam'], 'away' => $game['visitorteam'] === null ? null : (int) $game['visitorteam']];
    if ($teams['home'] !== $doc['home_team_id'] || $teams['away'] !== $doc['away_team_id']) {
        throw new ScorekeepingApiException(409, 'fixture_changed', 'The teams of the game are not the ones in the document.');
    }
    if ((int) $game['forfeit'] > 0) {
        throw new ScorekeepingApiException(409, 'forfeit', 'A forfeited game cannot be changed through the API.');
    }

    // Validation against the stored data. Nothing is written until all of it passes.
    $errors = [];
    $add = function ($path, $message) use (&$errors) {
        if (count($errors) < 50) {
            $errors[] = ['path' => $path, 'message' => $message];
        }
    };

    $submitted = [];
    foreach (['home', 'away'] as $side) {
        foreach ($doc['rosters'][$side] as $i => $entry) {
            if (isset($submitted[$entry['player_id']])) {
                $add("rosters.{$side}[$i].player_id", 'The player is listed more than once.');
            }
            $submitted[$entry['player_id']] = $side;
        }
    }
    $playerTeams = [];
    if ($submitted !== []) {
        $rows = DBQueryToArrayUncached("SELECT player_id, team FROM uo_player WHERE player_id IN (" . implode(',', array_keys($submitted)) . ")");
        foreach ($rows as $row) {
            $playerTeams[(int) $row['player_id']] = $row['team'] === null ? null : (int) $row['team'];
        }
    }
    foreach (['home', 'away'] as $side) {
        foreach ($doc['rosters'][$side] as $i => $entry) {
            $path = "rosters.{$side}[$i].player_id";
            if (!array_key_exists($entry['player_id'], $playerTeams)) {
                $add($path, 'The player does not exist.');
            } elseif ($playerTeams[$entry['player_id']] !== $teams[$side]) {
                $add($path, 'The player does not belong to the ' . $side . ' team.');
            } elseif (!GameAllowsPlayerOnRoster($gameId, $entry['player_id'])) {
                $add($path, $requireAccreditation ? 'The player is not accredited.' : 'The player cannot be put on the roster.');
            }
        }
    }

    $rosterSide = $submitted;
    $scores = ['home' => 0, 'away' => 0];
    $previousTime = 0;
    $goals = [];
    foreach ($doc['goals'] as $i => $goal) {
        $path = "goals[$i]";
        $side = $goal['side'];
        foreach (['scorer_id', 'assist_id'] as $field) {
            if ($goal[$field] !== null && ($rosterSide[$goal[$field]] ?? null) !== $side) {
                $add("$path.$field", 'The player is not on the ' . $side . ' roster in the document.');
            }
        }
        if ($goal['callahan'] && $goal['assist_id'] !== null) {
            $add("$path.assist_id", 'A Callahan goal has no assist.');
        }
        if ($goal['scorer_id'] !== null && $goal['scorer_id'] === $goal['assist_id']) {
            $add("$path.assist_id", 'The scorer and the assist must differ.');
        }
        if ($hideTime) {
            if ($goal['time'] !== null) {
                $add("$path.time", 'Goal times are hidden in this event; send null.');
            }
            $time = $previousTime + 1;
        } else {
            if ($goal['time'] === null) {
                $add("$path.time", 'A goal time is required.');
                $time = $previousTime;
            } elseif ($goal['time'] < $previousTime) {
                $add("$path.time", 'Goal times must not go backwards.');
                $time = $goal['time'];
            } else {
                $time = $goal['time'];
            }
        }
        if ($time > SCOREKEEPING_API_MAX_GOAL_TIME) {
            $add("$path.time", 'The synthesized goal time is too large.');
        }
        $previousTime = $time;
        $scores[$side]++;
        $goals[] = $goal + ['num' => $i + 1, 'stored_time' => $time, 'home_score' => $scores['home'], 'away_score' => $scores['away']];
    }

    if ($doc['result'] === null && $doc['goals'] !== []) {
        $add('result', 'A null result cannot come with goals.');
    } elseif ($doc['result'] !== null && $doc['goals'] !== [] && ($doc['result']['home'] !== $scores['home'] || $doc['result']['away'] !== $scores['away'])) {
        $add('result', sprintf('The result must equal the goal total %d-%d.', $scores['home'], $scores['away']));
    }

    $recorded = ['home' => 0, 'away' => 0];
    $spiritRecorded = ['home' => 0, 'away' => 0];
    foreach (DBQueryToArrayUncached(sprintf("SELECT ishome, COUNT(*) AS c FROM uo_timeout WHERE game=%d GROUP BY ishome", $gameId)) as $row) {
        $recorded[(int) $row['ishome'] === 1 ? 'home' : 'away'] = (int) $row['c'];
    }
    foreach (DBQueryToArrayUncached(sprintf("SELECT ishome, COUNT(*) AS c FROM uo_spirit_timeout WHERE game=%d GROUP BY ishome", $gameId)) as $row) {
        $spiritRecorded[(int) $row['ishome'] === 1 ? 'home' : 'away'] = (int) $row['c'];
    }
    $perTeam = GameTimeoutsPerTeam($gameId);
    foreach (['home', 'away'] as $side) {
        $limit = max($perTeam, $recorded[$side]);
        if (count($doc['timeouts'][$side]) > $limit) {
            $add("timeouts.$side", sprintf('At most %d timeouts are allowed.', $limit));
        }
        $limit = max(4, $spiritRecorded[$side]);
        if (count($doc['spirit_stoppages'][$side]) > $limit) {
            $add("spirit_stoppages.$side", sprintf('At most %d spirit stoppages are allowed.', $limit));
        }
        if (!$spiritEnabled && $doc['spirit_stoppages'][$side] !== []) {
            $add("spirit_stoppages.$side", 'Spirit stoppages are not used in this event.');
        }
    }

    if ($errors !== []) {
        throw new ScorekeepingApiException(400, 'validation_failed', 'The game data does not match the game.', $errors);
    }

    $removedDefenders = [];
    foreach (DBQueryToArrayUncached(sprintf("SELECT DISTINCT author FROM uo_defense WHERE game=%d AND author IS NOT NULL", $gameId)) as $row) {
        if (!isset($submitted[(int) $row['author']])) {
            $removedDefenders[] = (int) $row['author'];
        }
    }
    if ($removedDefenders !== []) {
        sort($removedDefenders);
        throw new ScorekeepingApiException(409, 'retained_defense', 'Players recorded on the defense sheet must stay on the roster.', ['player_ids' => $removedDefenders]);
    }

    // Writes.
    $must = function ($done, $step) {
        if (!$done) {
            throw new ScorekeepingApiException(500, 'write_failed', 'The game could not be saved (' . $step . ').');
        }
    };
    $wasFinal = (int) $game['hasstarted'] > 0 && (int) $game['isongoing'] === 0;
    $must(ScoresheetHistorySnapshotIfNeeded($gameId, true), 'snapshot');

    $must(GameRemoveAllScores($gameId), 'goals');

    $current = [];
    foreach (DBQueryToArrayUncached(sprintf("SELECT player, num FROM uo_played WHERE game=%d", $gameId)) as $row) {
        $current[(int) $row['player']] = $row['num'] === null ? null : (int) $row['num'];
    }
    foreach (array_keys($current) as $playerId) {
        if (!isset($submitted[$playerId])) {
            $must(GameRemovePlayer($gameId, $playerId), 'roster');
        }
    }
    foreach (['home', 'away'] as $side) {
        foreach ($doc['rosters'][$side] as $entry) {
            if (!array_key_exists($entry['player_id'], $current)) {
                $must(GameAddPlayer($gameId, $entry['player_id'], $entry['number']), 'roster');
            } elseif ($current[$entry['player_id']] !== $entry['number']) {
                $must(GameSetPlayerNumber($gameId, $entry['player_id'], $entry['number']), 'roster');
            }
        }
        $captains = [];
        $spiritCaptains = [];
        foreach ($doc['rosters'][$side] as $entry) {
            if ($entry['captain']) {
                $captains[] = $entry['player_id'];
            }
            if ($entry['spirit_captain']) {
                $spiritCaptains[] = $entry['player_id'];
            }
        }
        $must(GameSetCaptains($gameId, $teams[$side], $captains), 'captains');
        $must(GameSetSpiritCaptains($gameId, $teams[$side], $spiritCaptains), 'spirit captains');
    }

    foreach ($goals as $goal) {
        $must(GameAddScore(
            $gameId,
            $goal['assist_id'],
            $goal['scorer_id'],
            $goal['stored_time'],
            $goal['num'],
            $goal['home_score'],
            $goal['away_score'],
            $goal['side'] === 'home' ? 1 : 0,
            $goal['callahan'] ? 1 : 0,
        ), 'goals');
    }

    $must(GameRemoveAllTimeouts($gameId), 'timeouts');
    foreach (['home' => 1, 'away' => 0] as $side => $isHome) {
        foreach ($doc['timeouts'][$side] as $i => $timeout) {
            $must(GameAddTimeout($gameId, $i + 1, $timeout['time'], $isHome), 'timeouts');
        }
    }
    if ($spiritEnabled) {
        $must(GameRemoveAllSpiritTimeouts($gameId), 'spirit stoppages');
        foreach (['home' => 1, 'away' => 0] as $side => $isHome) {
            foreach ($doc['spirit_stoppages'][$side] as $i => $stoppage) {
                $must(GameAddSpiritTimeout($gameId, $i + 1, $stoppage['time'], $isHome), 'spirit stoppages');
            }
        }
    }

    $offence = $doc['starting_offence'] === null ? null : ($doc['starting_offence'] === 'home' ? 1 : 0);
    $must(GameSetStartingTeam($gameId, $offence), 'starting offence');

    $storedHalftime = (int) ($game['halftime'] ?? 0) > 0 ? (int) $game['halftime'] : null;
    $wantedHalftime = $doc['halftime'] !== null && $doc['halftime'] > 0 ? $doc['halftime'] : null;
    if ($storedHalftime !== $wantedHalftime) {
        $must(GameSetHalftime($gameId, $doc['halftime']), 'halftime');
    }
    if ((string) ($game['official'] ?? '') !== (string) ($doc['official'] ?? '')) {
        $must(GameSetScoreSheetKeeper($gameId, ($doc['official'] ?? '') === '' ? null : $doc['official']), 'official');
    }

    if ($doc['result'] === null) {
        $must(GameClearResult($gameId), 'result');
    } elseif ($doc['result']['state'] === 'final') {
        $must(GameSetResult($gameId, $doc['result']['home'], $doc['result']['away']), 'result');
    } else {
        $must(GameUpdateResult($gameId, $doc['result']['home'], $doc['result']['away']), 'result');
        if ($wasFinal) {
            $poolId = GamePool($gameId);
            ResolvePoolStandings($poolId);
            PoolResolvePlayed($poolId);
        }
    }

    $token = ScorekeeperUploadToken($gameId);
    $body = [
        'status' => 'ok',
        'data' => [
            'game_id' => $gameId,
            'token' => $token,
            'counts' => [
                'roster_home' => count($doc['rosters']['home']),
                'roster_away' => count($doc['rosters']['away']),
                'goals' => count($doc['goals']),
                'timeouts' => count($doc['timeouts']['home']) + count($doc['timeouts']['away']),
                'spirit_stoppages' => count($doc['spirit_stoppages']['home']) + count($doc['spirit_stoppages']['away']),
            ],
        ],
    ];
    ApiReceiptStore($request['api_token'], $request['link_token'], $gameId, $request['key'], $request['method'], $request['path'], $requestHash, 200, $body);
    return ['status' => 200, 'body' => $body, 'token' => $token, 'replay' => false];
}
