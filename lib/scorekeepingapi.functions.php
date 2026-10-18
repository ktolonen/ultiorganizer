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
        'num' => $row['num'] === null ? null : (int) $row['num'],
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
 * Collects field errors while a scorekeeping API request body is checked, so
 * one answer can list every offending field path.
 */
final class ScorekeepingApiFields
{
    /** @var array<int, array{path: string, message: string}> */
    public array $errors = [];

    public function add($path, $message)
    {
        if (count($this->errors) < 50) {
            $this->errors[] = ['path' => $path, 'message' => $message];
        }
    }

    public function isObject($value)
    {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }

    /**
     * Report unknown and missing keys.
     *
     * @param string[] $required Keys that must be present
     * @param string[] $optional Keys that may be present
     * @return bool Whether $value is an object with every required key and no unknown one
     */
    public function keys($value, $path, array $required, array $optional = [])
    {
        if (!$this->isObject($value)) {
            $this->add($path, 'Must be an object.');
            return false;
        }
        $ok = true;
        foreach (array_keys($value) as $key) {
            if (!in_array($key, $required, true) && !in_array($key, $optional, true)) {
                $this->add($path === '' ? (string) $key : $path . '.' . $key, 'Unknown field.');
                $ok = false;
            }
        }
        foreach ($required as $key) {
            if (!array_key_exists($key, $value)) {
                $this->add($path === '' ? $key : $path . '.' . $key, 'Required field is missing.');
                $ok = false;
            }
        }
        return $ok;
    }

    public function int($value, $path, $min, $max, $nullable = false)
    {
        if ($value === null && $nullable) {
            return null;
        }
        if (!is_int($value) || $value < $min || $value > $max) {
            $this->add($path, sprintf('Must be %swhole number from %d to %d.', $nullable ? 'null or a ' : 'a ', $min, $max));
            return null;
        }
        return $value;
    }

    public function bool($value, $path)
    {
        if (!is_bool($value)) {
            $this->add($path, 'Must be true or false.');
        }
        return $value === true;
    }

    public function side($value, $path)
    {
        if ($value !== 'home' && $value !== 'away') {
            $this->add($path, 'Must be "home" or "away".');
            return null;
        }
        return $value;
    }

    public function list($value, $path, $max)
    {
        if (!is_array($value) || !array_is_list($value)) {
            $this->add($path, 'Must be a list.');
            return [];
        }
        if (count($value) > $max) {
            $this->add($path, sprintf('Must have at most %d items.', $max));
            return [];
        }
        return $value;
    }

    /**
     * @throws ScorekeepingApiException 400 when any error was collected
     */
    public function throwIfAny($code, $message)
    {
        if ($this->errors !== []) {
            throw new ScorekeepingApiException(400, $code, $message, $this->errors);
        }
    }
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
            // Maintenance and read-only flags may have changed while waiting for the lock.
            CacheForgetNamespace("season_info");
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
 * Run one idempotent scorekeeping API mutation: ScorekeepingApiTransaction(),
 * then under the lock the event state and rights recheck, the receipt lookup,
 * the forfeit refusal, $write, and in the same transaction the receipt of the
 * answer.
 *
 * An exact replay of a stored request returns its stored answer and writes
 * nothing; the same key with another method, path or body is a 409.
 * $write returns ['status' => int, 'data' => array]; the answer body is
 * {status: ok, data: {game_id, token, result} + data}, the token being the
 * resulting ScorekeeperUploadToken().
 *
 * @param int $gameId uo_game.game_id
 * @param array{api_token: int, link_token: int, key: string, method: string, path: string, body: mixed} $request
 *     body is the normalized request body that the request hash covers
 * @param string[] $rights 'events' and/or 'players': the edit rights the write needs
 * @param callable $write Validates and writes; throws ScorekeepingApiException to refuse
 * @return array{status: int, body: array, token: string, replay: bool}
 * @throws ScorekeepingApiException
 */
function ScorekeepingApiWrite($gameId, array $request, array $rights, callable $write)
{
    $gameId = (int) $gameId;
    return ScorekeepingApiTransaction($gameId, function () use ($gameId, $request, $rights, $write) {
        $season = (string) GameSeason($gameId);
        if (IsSeasonInMaintenance($season)) {
            throw new ScorekeepingApiException(503, 'event_maintenance', 'The event is under maintenance.', null, ['Retry-After: 60']);
        }
        if (isEventReadonly($season)) {
            throw new ScorekeepingApiException(403, 'event_readonly', 'The event is read-only.');
        }
        if ((in_array('events', $rights, true) && !hasEditGameEventsRight($gameId))
            || (in_array('players', $rights, true) && !hasEditGamePlayersRight($gameId))) {
            throw new ScorekeepingApiException(403, 'forbidden', 'The scorekeeping link does not allow editing this game.');
        }

        $requestHash = hash('sha256', (string) json_encode($request['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

        $forfeit = DBQueryToValueUncached(sprintf("SELECT forfeit FROM uo_game WHERE game_id=%d", $gameId));
        if ((int) $forfeit > 0) {
            throw new ScorekeepingApiException(409, 'forfeit', 'A forfeited game cannot be changed through the API.');
        }

        $outcome = $write();

        $token = ScorekeeperUploadToken($gameId);
        $stored = DBQueryToRowUncached(sprintf(
            "SELECT homescore, visitorscore, hasstarted, isongoing FROM uo_game WHERE game_id=%d",
            $gameId,
        ));
        $result = null;
        if (is_array($stored) && (int) $stored['hasstarted'] > 0) {
            $result = [
                'home' => (int) $stored['homescore'],
                'away' => (int) $stored['visitorscore'],
                'state' => (int) $stored['isongoing'] === 1 ? 'ongoing' : 'final',
            ];
        }
        $body = [
            'status' => 'ok',
            'data' => ['game_id' => $gameId, 'token' => $token, 'result' => $result] + $outcome['data'],
        ];
        ApiReceiptStore($request['api_token'], $request['link_token'], $gameId, $request['key'], $request['method'], $request['path'], $requestHash, $outcome['status'], $body);
        return ['status' => $outcome['status'], 'body' => $body, 'token' => $token, 'replay' => false];
    });
}

/**
 * The stored state of a game the live services decide on, read under the lock.
 *
 * @return array{home_team: ?int, away_team: ?int, homescore: ?int, visitorscore: ?int, started: bool, final: bool}
 */
function ScorekeeperLockedGameState($gameId)
{
    $row = DBQueryToRowUncached(sprintf(
        "SELECT hometeam, visitorteam, homescore, visitorscore, hasstarted, isongoing FROM uo_game WHERE game_id=%d",
        (int) $gameId,
    ));
    if (!is_array($row)) {
        throw new ScorekeepingApiException(404, 'game_not_found', 'Game not found.');
    }
    return [
        'home_team' => $row['hometeam'] === null ? null : (int) $row['hometeam'],
        'away_team' => $row['visitorteam'] === null ? null : (int) $row['visitorteam'],
        'homescore' => $row['homescore'] === null ? null : (int) $row['homescore'],
        'visitorscore' => $row['visitorscore'] === null ? null : (int) $row['visitorscore'],
        'started' => (int) $row['hasstarted'] > 0,
        'final' => (int) $row['hasstarted'] > 0 && (int) $row['isongoing'] === 0,
    ];
}

/**
 * Check roster entries against the stored players before a write: no player
 * listed twice, each one existing, on the side's team and allowed on the game
 * roster (accreditation).
 *
 * @param array<string, array> $rosters Normalized entries keyed by side
 * @param array<string, ?int> $teams Team id by side
 * @param array<string, string> $paths Field path of each side's list, for errors
 * @return array<int, string> Side by submitted player id
 */
function ScorekeeperRosterCheck($gameId, array $rosters, array $teams, array $paths, ScorekeepingApiFields $f)
{
    $submitted = [];
    foreach ($rosters as $side => $entries) {
        foreach ($entries as $i => $entry) {
            if (isset($submitted[$entry['player_id']])) {
                $f->add("{$paths[$side]}[$i].player_id", 'The player is listed more than once.');
            }
            $submitted[$entry['player_id']] = $side;
        }
    }
    $playerTeams = [];
    if ($submitted !== []) {
        $rows = DBQueryToArrayUncached("SELECT player_id, team FROM uo_player WHERE player_id IN (" . implode(',', array_map('intval', array_keys($submitted))) . ")");
        foreach ($rows as $row) {
            $playerTeams[(int) $row['player_id']] = $row['team'] === null ? null : (int) $row['team'];
        }
    }
    $requireAccreditation = !empty(SeasonInfo(GameSeason($gameId))['require_accreditation']);
    foreach ($rosters as $side => $entries) {
        foreach ($entries as $i => $entry) {
            $path = "{$paths[$side]}[$i].player_id";
            if (!array_key_exists($entry['player_id'], $playerTeams)) {
                $f->add($path, 'The player does not exist.');
            } elseif ($playerTeams[$entry['player_id']] !== $teams[$side]) {
                $f->add($path, 'The player does not belong to the ' . $side . ' team.');
            } elseif (!GameAllowsPlayerOnRoster($gameId, $entry['player_id'])) {
                $f->add($path, $requireAccreditation ? 'The player is not accredited.' : 'The player cannot be put on the roster.');
            }
        }
    }
    return $submitted;
}

/**
 * Write one side's checked roster entries: add new players, renumber changed
 * ones, set captains and spirit captains. Removing players is the caller's.
 *
 * @param array<int, ?int> $current Stored number by player id of the game roster
 */
function ScorekeeperRosterWrite($gameId, $teamId, array $entries, array $current)
{
    foreach ($entries as $entry) {
        if (!array_key_exists($entry['player_id'], $current)) {
            ScorekeeperMust(GameAddPlayer($gameId, $entry['player_id'], $entry['number']), 'roster');
        } elseif ($current[$entry['player_id']] !== $entry['number']) {
            ScorekeeperMust(GameSetPlayerNumber($gameId, $entry['player_id'], $entry['number']), 'roster');
        }
    }
    $captains = [];
    $spiritCaptains = [];
    foreach ($entries as $entry) {
        if ($entry['captain']) {
            $captains[] = $entry['player_id'];
        }
        if ($entry['spirit_captain']) {
            $spiritCaptains[] = $entry['player_id'];
        }
    }
    ScorekeeperMust(GameSetCaptains($gameId, $teamId, $captains), 'captains');
    ScorekeeperMust(GameSetSpiritCaptains($gameId, $teamId, $spiritCaptains), 'spirit captains');
}

/**
 * The stored game roster: number by player id.
 *
 * @return array<int, ?int>
 */
function ScorekeeperRosterNumbers($gameId)
{
    $current = [];
    foreach (DBQueryToArrayUncached(sprintf("SELECT player, num FROM uo_played WHERE game=%d", (int) $gameId)) as $row) {
        $current[(int) $row['player']] = $row['num'] === null ? null : (int) $row['num'];
    }
    return $current;
}

/**
 * Turn a failed write helper into an exception, so the transaction rolls back whole.
 *
 * @throws ScorekeepingApiException 500 when $done is falsy
 */
function ScorekeeperMust($done, $step)
{
    if (!$done) {
        throw new ScorekeepingApiException(500, 'write_failed', 'The game could not be saved (' . $step . ').');
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
    $f = new ScorekeepingApiFields();
    $doc = [];
    $f->keys($raw, '', [
        'version', 'home_team_id', 'away_team_id', 'rosters', 'goals', 'timeouts',
        'spirit_stoppages', 'result', 'starting_offence', 'halftime', 'official',
    ]);
    $raw = $f->isObject($raw) ? $raw : [];

    if (array_key_exists('version', $raw) && $raw['version'] !== 1) {
        $f->add('version', 'Unsupported version; this server accepts version 1.');
    }
    $doc['version'] = 1;
    $doc['home_team_id'] = array_key_exists('home_team_id', $raw) ? $f->int($raw['home_team_id'], 'home_team_id', 1, 2147483647) : null;
    $doc['away_team_id'] = array_key_exists('away_team_id', $raw) ? $f->int($raw['away_team_id'], 'away_team_id', 1, 2147483647) : null;

    $doc['rosters'] = ['home' => [], 'away' => []];
    if (array_key_exists('rosters', $raw) && $f->keys($raw['rosters'], 'rosters', ['home', 'away'])) {
        foreach (['home', 'away'] as $side) {
            $doc['rosters'][$side] = ScorekeeperRosterEntriesNormalize($f, $raw['rosters'][$side], "rosters.$side");
        }
    }

    $doc['goals'] = [];
    $sideGoals = ['home' => 0, 'away' => 0];
    if (array_key_exists('goals', $raw)) {
        foreach ($f->list($raw['goals'], 'goals', 2 * SCOREKEEPING_API_MAX_SIDE_GOALS) as $i => $entry) {
            $path = "goals[$i]";
            if (!$f->keys($entry, $path, ['side', 'scorer_id', 'assist_id', 'callahan', 'time'])) {
                continue;
            }
            if ($f->side($entry['side'], "$path.side") !== null && ++$sideGoals[$entry['side']] > SCOREKEEPING_API_MAX_SIDE_GOALS) {
                $f->add($path, sprintf('A side can have at most %d goals.', SCOREKEEPING_API_MAX_SIDE_GOALS));
            }
            $doc['goals'][] = ScorekeeperGoalEntryNormalize($f, $entry, $path);
        }
    }

    foreach (['timeouts', 'spirit_stoppages'] as $name) {
        $doc[$name] = ['home' => [], 'away' => []];
        if (array_key_exists($name, $raw) && $f->keys($raw[$name], $name, ['home', 'away'])) {
            foreach (['home', 'away'] as $side) {
                foreach ($f->list($raw[$name][$side], "$name.$side", 255) as $i => $entry) {
                    $path = "{$name}.{$side}[$i]";
                    if ($f->keys($entry, $path, ['time'])) {
                        $doc[$name][$side][] = ['time' => $f->int($entry['time'], "$path.time", 0, SCOREKEEPING_API_MAX_EVENT_TIME)];
                    }
                }
            }
        }
    }

    $doc['result'] = null;
    if (array_key_exists('result', $raw) && $raw['result'] !== null) {
        if ($f->keys($raw['result'], 'result', ['home', 'away', 'state'])) {
            if ($raw['result']['state'] !== 'ongoing' && $raw['result']['state'] !== 'final') {
                $f->add('result.state', 'Must be "ongoing" or "final".');
            }
            $doc['result'] = [
                'home' => $f->int($raw['result']['home'], 'result.home', 0, MAX_GAME_SCORE),
                'away' => $f->int($raw['result']['away'], 'result.away', 0, MAX_GAME_SCORE),
                'state' => $raw['result']['state'],
            ];
        }
    }

    $doc += ScorekeeperMetadataNormalize($f, $raw, true);

    $f->throwIfAny('invalid_document', 'The game data document is not valid.');
    return $doc;
}

/**
 * Normalize a list of roster entries for ScorekeeperGameDataNormalize() and
 * ScorekeeperLiveRequestNormalize().
 *
 * @return array<int, array{player_id: ?int, number: ?int, captain: bool, spirit_captain: bool}>
 */
function ScorekeeperRosterEntriesNormalize(ScorekeepingApiFields $f, $value, $path)
{
    $entries = [];
    foreach ($f->list($value, $path, 100) as $i => $entry) {
        $entryPath = "{$path}[$i]";
        if (!$f->keys($entry, $entryPath, ['player_id', 'number', 'captain', 'spirit_captain'])) {
            continue;
        }
        $entries[] = [
            'player_id' => $f->int($entry['player_id'], "$entryPath.player_id", 1, 2147483647),
            'number' => $f->int($entry['number'], "$entryPath.number", 0, SCOREKEEPING_API_MAX_JERSEY_NUMBER, true),
            'captain' => $f->bool($entry['captain'], "$entryPath.captain"),
            'spirit_captain' => $f->bool($entry['spirit_captain'], "$entryPath.spirit_captain"),
        ];
    }
    return $entries;
}

/**
 * Normalize one goal object whose keys were already checked: side, scorer_id,
 * assist_id, callahan and time (null when the event hides goal times).
 *
 * @return array{side: ?string, scorer_id: ?int, assist_id: ?int, callahan: bool, time: ?int}
 */
function ScorekeeperGoalEntryNormalize(ScorekeepingApiFields $f, array $entry, $path)
{
    $prefix = $path === '' ? '' : $path . '.';
    return [
        'side' => $entry['side'] === 'home' || $entry['side'] === 'away' ? $entry['side'] : null,
        'scorer_id' => $f->int($entry['scorer_id'], $prefix . 'scorer_id', 1, 2147483647, true),
        'assist_id' => $f->int($entry['assist_id'], $prefix . 'assist_id', 1, 2147483647, true),
        'callahan' => $f->bool($entry['callahan'], $prefix . 'callahan'),
        'time' => $f->int($entry['time'], $prefix . 'time', 0, SCOREKEEPING_API_MAX_GOAL_TIME, true),
    ];
}

/**
 * Normalize starting_offence, halftime and official from a request body.
 *
 * @param bool $all true to return every field (null when absent), false for only the present ones
 * @return array{starting_offence?: ?string, halftime?: ?int, official?: ?string}
 */
function ScorekeeperMetadataNormalize(ScorekeepingApiFields $f, array $raw, $all)
{
    $out = [];
    if (array_key_exists('starting_offence', $raw) || $all) {
        $out['starting_offence'] = $raw['starting_offence'] ?? null;
        if ($out['starting_offence'] !== null && $out['starting_offence'] !== 'home' && $out['starting_offence'] !== 'away') {
            $f->add('starting_offence', 'Must be null, "home" or "away".');
        }
    }
    if (array_key_exists('halftime', $raw) || $all) {
        $out['halftime'] = array_key_exists('halftime', $raw) ? $f->int($raw['halftime'], 'halftime', 0, SCOREKEEPING_API_MAX_EVENT_TIME, true) : null;
    }
    if (array_key_exists('official', $raw) || $all) {
        $out['official'] = $raw['official'] ?? null;
        if ($out['official'] !== null && (!is_string($out['official']) || mb_strlen($out['official']) > 50)) {
            $f->add('official', 'Must be null or text of at most 50 characters.');
        }
    }
    return $out;
}

/**
 * Replace everything a complete upload covers in one game: both rosters,
 * goals, timeouts, spirit stoppages, starting offence, halftime, official and
 * the result. Retained data (cap events, defenses, forfeit, game notes, the
 * clock of an ongoing game) is kept.
 *
 * The $write of ScorekeepingApiWrite(), which has already taken the lock,
 * rechecked rights and found no receipt. Order: If-Match token, validation
 * against the stored game, forced snapshot, the writes. Every helper's return
 * value is checked, so a failure throws and the transaction rolls back whole.
 *
 * A game that goes from final back to ongoing gets its pool standings
 * recomputed, as clearing a result does, because standings count only final
 * games and GameUpdateResult() does not recompute them.
 *
 * @param int $gameId uo_game.game_id
 * @param array $doc Output of ScorekeeperGameDataNormalize()
 * @param string $ifMatch The bare token from If-Match; '*' never matches
 * @return array{status: int, data: array}
 * @throws ScorekeepingApiException
 */
function ScorekeeperReplaceGameData($gameId, array $doc, $ifMatch)
{
    $gameId = (int) $gameId;
    if ($ifMatch === '*' || !hash_equals(ScorekeeperUploadToken($gameId), (string) $ifMatch)) {
        throw new ScorekeepingApiException(409, 'stale_token', 'The game has changed since the token was issued. Fetch the entry context again before uploading.');
    }

    $seasonInfo = SeasonInfo(GameSeason($gameId));
    $hideTime = !empty($seasonInfo['hide_time_on_scoresheet']);
    $spiritEnabled = !empty($seasonInfo['spiritmode']);

    $state = ScorekeeperLockedGameState($gameId);
    $teams = ['home' => $state['home_team'], 'away' => $state['away_team']];
    if ($teams['home'] !== $doc['home_team_id'] || $teams['away'] !== $doc['away_team_id']) {
        throw new ScorekeepingApiException(409, 'fixture_changed', 'The teams of the game are not the ones in the document.');
    }

    // Validation against the stored data. Nothing is written until all of it passes.
    $f = new ScorekeepingApiFields();
    $submitted = ScorekeeperRosterCheck($gameId, $doc['rosters'], $teams, ['home' => 'rosters.home', 'away' => 'rosters.away'], $f);

    $scores = ['home' => 0, 'away' => 0];
    $previousTime = 0;
    $goals = [];
    foreach ($doc['goals'] as $i => $goal) {
        $path = "goals[$i]";
        $side = $goal['side'];
        foreach (['scorer_id', 'assist_id'] as $field) {
            if ($goal[$field] !== null && ($submitted[$goal[$field]] ?? null) !== $side) {
                $f->add("$path.$field", 'The player is not on the ' . $side . ' roster in the document.');
            }
        }
        ScorekeeperGoalPlayersCheck($goal, $path, $f);
        $time = ScorekeeperGoalTime($goal['time'], $previousTime, $hideTime, "$path.time", $f);
        $previousTime = $time;
        $scores[$side]++;
        $goals[] = $goal + ['num' => $i + 1, 'stored_time' => $time, 'home_score' => $scores['home'], 'away_score' => $scores['away']];
    }

    if ($doc['result'] === null && $doc['goals'] !== []) {
        $f->add('result', 'A null result cannot come with goals.');
    } elseif ($doc['result'] !== null && $doc['goals'] !== [] && ($doc['result']['home'] !== $scores['home'] || $doc['result']['away'] !== $scores['away'])) {
        $f->add('result', sprintf('The result must equal the goal total %d-%d.', $scores['home'], $scores['away']));
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
            $f->add("timeouts.$side", sprintf('At most %d timeouts are allowed.', $limit));
        }
        $limit = max(SCOREKEEPING_API_SPIRIT_STOPPAGES_PER_TEAM, $spiritRecorded[$side]);
        if (count($doc['spirit_stoppages'][$side]) > $limit) {
            $f->add("spirit_stoppages.$side", sprintf('At most %d spirit stoppages are allowed.', $limit));
        }
        if (!$spiritEnabled && $doc['spirit_stoppages'][$side] !== []) {
            $f->add("spirit_stoppages.$side", 'Spirit stoppages are not used in this event.');
        }
    }

    if ($f->errors !== []) {
        throw new ScorekeepingApiException(400, 'validation_failed', 'The game data does not match the game.', $f->errors);
    }

    $removedDefenders = [];
    foreach (DBQueryToArrayUncached(sprintf("SELECT DISTINCT author FROM uo_defense WHERE game=%d AND author IS NOT NULL", $gameId)) as $row) {
        if (!isset($submitted[(int) $row['author']])) {
            $removedDefenders[] = (int) $row['author'];
        }
    }
    if ($removedDefenders !== []) {
        sort($removedDefenders);
        throw new ScorekeepingApiException(409, 'retained_defense', 'Players recorded on the defence sheet must stay on the roster.', ['player_ids' => $removedDefenders]);
    }

    // Writes.
    ScorekeeperMust(ScoresheetHistorySnapshotIfNeeded($gameId, true), 'snapshot');

    ScorekeeperMust(GameRemoveAllScores($gameId), 'goals');

    $current = ScorekeeperRosterNumbers($gameId);
    foreach (array_keys($current) as $playerId) {
        if (!isset($submitted[$playerId])) {
            ScorekeeperMust(GameRemovePlayer($gameId, $playerId), 'roster');
        }
    }
    foreach (['home', 'away'] as $side) {
        ScorekeeperRosterWrite($gameId, $teams[$side], $doc['rosters'][$side], $current);
    }

    foreach ($goals as $goal) {
        ScorekeeperMust(GameAddScore(
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

    ScorekeeperMust(GameRemoveAllTimeouts($gameId), 'timeouts');
    foreach (['home' => 1, 'away' => 0] as $side => $isHome) {
        foreach ($doc['timeouts'][$side] as $i => $timeout) {
            ScorekeeperMust(GameAddTimeout($gameId, $i + 1, $timeout['time'], $isHome), 'timeouts');
        }
    }
    if ($spiritEnabled) {
        ScorekeeperMust(GameRemoveAllSpiritTimeouts($gameId), 'spirit stoppages');
        foreach (['home' => 1, 'away' => 0] as $side => $isHome) {
            foreach ($doc['spirit_stoppages'][$side] as $i => $stoppage) {
                ScorekeeperMust(GameAddSpiritTimeout($gameId, $i + 1, $stoppage['time'], $isHome), 'spirit stoppages');
            }
        }
    }

    ScorekeeperUpdateMetadata($gameId, [
        'starting_offence' => $doc['starting_offence'],
        'halftime' => $doc['halftime'],
        'official' => $doc['official'],
    ]);

    if ($doc['result'] === null) {
        ScorekeeperMust(GameClearResult($gameId), 'result');
    } elseif ($doc['result']['state'] === 'final') {
        ScorekeeperMust(GameSetResult($gameId, $doc['result']['home'], $doc['result']['away']), 'result');
    } else {
        ScorekeeperMust(GameUpdateResult($gameId, $doc['result']['home'], $doc['result']['away']), 'result');
        if ($state['final']) {
            $poolId = GamePool($gameId);
            ResolvePoolStandings($poolId);
            PoolResolvePlayed($poolId);
        }
    }

    return [
        'status' => 200,
        'data' => [
            'counts' => [
                'roster_home' => count($doc['rosters']['home']),
                'roster_away' => count($doc['rosters']['away']),
                'goals' => count($doc['goals']),
                'timeouts' => count($doc['timeouts']['home']) + count($doc['timeouts']['away']),
                'spirit_stoppages' => count($doc['spirit_stoppages']['home']) + count($doc['spirit_stoppages']['away']),
            ],
        ],
    ];
}

/** Spirit stoppages a team may call in one game. */
const SCOREKEEPING_API_SPIRIT_STOPPAGES_PER_TEAM = 4;

/**
 * Check the player rules of one goal: a Callahan has no assist, and the scorer
 * and the assist differ.
 */
function ScorekeeperGoalPlayersCheck(array $goal, $path, ScorekeepingApiFields $f)
{
    $field = $path === '' ? 'assist_id' : $path . '.assist_id';
    if ($goal['callahan'] && $goal['assist_id'] !== null) {
        $f->add($field, 'A Callahan goal has no assist.');
    }
    if ($goal['scorer_id'] !== null && $goal['scorer_id'] === $goal['assist_id']) {
        $f->add($field, 'The scorer and the assist must differ.');
    }
}

/**
 * The time to store for a goal after one at $previousTime. With hidden goal
 * times the client sends null and the time is synthesized one second later;
 * otherwise the time is required and must not go backwards.
 *
 * @return int
 */
function ScorekeeperGoalTime($time, $previousTime, $hideTime, $path, ScorekeepingApiFields $f)
{
    if ($hideTime) {
        if ($time !== null) {
            $f->add($path, 'Goal times are hidden in this event; send null.');
        }
        $time = $previousTime + 1;
    } elseif ($time === null) {
        $f->add($path, 'A goal time is required.');
        $time = $previousTime;
    } elseif ($time < $previousTime) {
        $f->add($path, 'Goal times must not go backwards.');
    }
    if ($time > SCOREKEEPING_API_MAX_GOAL_TIME) {
        $f->add($path, 'The synthesized goal time is too large.');
    }
    return $time;
}

/**
 * Set the present keys among starting_offence, halftime and official through
 * the unchanged-guarded setters. A halftime of 0 and an empty official count
 * as unset.
 *
 * The $write of ScorekeepingApiWrite() for PATCH metadata, and part of the
 * complete upload.
 *
 * @param int $gameId uo_game.game_id
 * @param array{starting_offence?: ?string, halftime?: ?int, official?: ?string} $meta
 * @return array{status: int, data: array}
 */
function ScorekeeperUpdateMetadata($gameId, array $meta)
{
    if (array_key_exists('starting_offence', $meta)) {
        $offence = $meta['starting_offence'] === null ? null : ($meta['starting_offence'] === 'home' ? 1 : 0);
        ScorekeeperMust(GameSetStartingTeam($gameId, $offence), 'starting offence');
    }
    $stored = DBQueryToRowUncached(sprintf("SELECT halftime, official FROM uo_game WHERE game_id=%d", (int) $gameId));
    if (array_key_exists('halftime', $meta)) {
        $storedHalftime = (int) ($stored['halftime'] ?? 0) > 0 ? (int) $stored['halftime'] : null;
        $wantedHalftime = $meta['halftime'] !== null && $meta['halftime'] > 0 ? $meta['halftime'] : null;
        if ($storedHalftime !== $wantedHalftime) {
            ScorekeeperMust(GameSetHalftime($gameId, $meta['halftime']), 'halftime');
        }
    }
    if (array_key_exists('official', $meta) && (string) ($stored['official'] ?? '') !== (string) ($meta['official'] ?? '')) {
        ScorekeeperMust(GameSetScoreSheetKeeper($gameId, ($meta['official'] ?? '') === '' ? null : $meta['official']), 'official');
    }
    return ['status' => 200, 'data' => []];
}

/**
 * Check the body of a live scorekeeping request and return it normalized.
 * Every listed field is required unless noted, and unknown fields are refused.
 *
 * - goal: side, scorer_id, assist_id, callahan, time (null when times are hidden)
 * - timeout, spirit_stoppage: side, time
 * - score_tap: side, delta (1 or -1)
 * - roster: team_id, players (roster entries as in the complete upload)
 * - metadata: at least one of starting_offence, halftime, official
 * - finalize: home, away
 *
 * @param string $operation One of the operations above
 * @param mixed $raw The decoded JSON body
 * @return array<string, mixed>
 * @throws ScorekeepingApiException 400 with the paths of the offending fields
 */
function ScorekeeperLiveRequestNormalize($operation, $raw)
{
    $f = new ScorekeepingApiFields();
    $required = [
        'goal' => ['side', 'scorer_id', 'assist_id', 'callahan', 'time'],
        'timeout' => ['side', 'time'],
        'spirit_stoppage' => ['side', 'time'],
        'score_tap' => ['side', 'delta'],
        'roster' => ['team_id', 'players'],
        'metadata' => [],
        'finalize' => ['home', 'away'],
    ];
    if (!isset($required[$operation])) {
        throw new LogicException('Unknown scorekeeping operation ' . $operation . '.');
    }
    $optional = $operation === 'metadata' ? ['starting_offence', 'halftime', 'official'] : [];
    if (!$f->keys($raw, '', $required[$operation], $optional)) {
        $f->throwIfAny('invalid_body', 'The request body is not valid.');
    }

    $body = [];
    switch ($operation) {
        case 'goal':
            $f->side($raw['side'], 'side');
            $body = ScorekeeperGoalEntryNormalize($f, $raw, '');
            break;
        case 'timeout':
        case 'spirit_stoppage':
            $body = [
                'side' => $f->side($raw['side'], 'side'),
                'time' => $f->int($raw['time'], 'time', 0, SCOREKEEPING_API_MAX_EVENT_TIME),
            ];
            break;
        case 'score_tap':
            if ($raw['delta'] !== 1 && $raw['delta'] !== -1) {
                $f->add('delta', 'Must be 1 or -1.');
            }
            $body = ['side' => $f->side($raw['side'], 'side'), 'delta' => $raw['delta'] === -1 ? -1 : 1];
            break;
        case 'roster':
            $body = [
                'team_id' => $f->int($raw['team_id'], 'team_id', 1, 2147483647),
                'players' => ScorekeeperRosterEntriesNormalize($f, $raw['players'], 'players'),
            ];
            break;
        case 'metadata':
            $body = ScorekeeperMetadataNormalize($f, $raw, false);
            if ($body === [] && $f->errors === []) {
                $f->add('', 'Send at least one of starting_offence, halftime and official.');
            }
            break;
        case 'finalize':
            $body = [
                'home' => $f->int($raw['home'], 'home', 0, MAX_GAME_SCORE),
                'away' => $f->int($raw['away'], 'away', 0, MAX_GAME_SCORE),
            ];
            break;
    }
    $f->throwIfAny('invalid_body', 'The request body is not valid.');
    return $body;
}

/**
 * Lock and return the last goal of a game, reading the latest committed row
 * rather than the transaction's snapshot.
 *
 * @return array{num: int, time: int, homescore: int, visitorscore: int}|null
 */
function ScorekeeperLastGoalForUpdate($gameId)
{
    $row = DBQueryToRowUncached(sprintf(
        "SELECT num, time, homescore, visitorscore FROM uo_goal WHERE game=%d ORDER BY num DESC LIMIT 1 FOR UPDATE",
        (int) $gameId,
    ));
    if (!is_array($row)) {
        return null;
    }
    return [
        'num' => (int) $row['num'],
        'time' => (int) $row['time'],
        'homescore' => (int) $row['homescore'],
        'visitorscore' => (int) $row['visitorscore'],
    ];
}

/**
 * Append one goal, as the Scorekeeper goal form does: the server assigns the
 * next number and the cumulative score, and the result moves up to the new
 * score unless a team's stored score is already higher. Refused on a final game.
 *
 * The $write of ScorekeepingApiWrite(). A browser append that takes the same
 * number first is retried once with the next number, then answered with 409.
 *
 * @param int $gameId uo_game.game_id
 * @param array $goal Output of ScorekeeperLiveRequestNormalize('goal', ...)
 * @return array{status: int, data: array}
 * @throws ScorekeepingApiException
 */
function ScorekeeperAddGoal($gameId, array $goal)
{
    $gameId = (int) $gameId;
    $state = ScorekeeperLockedGameState($gameId);
    if ($state['final']) {
        throw new ScorekeepingApiException(409, 'game_final', 'The game has a final result.');
    }
    $side = $goal['side'];
    $teamId = $side === 'home' ? $state['home_team'] : $state['away_team'];
    if ($teamId === null) {
        throw new ScorekeepingApiException(409, 'team_not_set', 'The ' . $side . ' team of the game is not set yet.');
    }
    $hideTime = !empty(SeasonInfo(GameSeason($gameId))['hide_time_on_scoresheet']);

    $f = new ScorekeepingApiFields();
    $roster = [];
    foreach (DBQueryToArrayUncached(sprintf(
        "SELECT pl.player FROM uo_played pl INNER JOIN uo_player p ON (p.player_id=pl.player) WHERE pl.game=%d AND p.team=%d",
        $gameId,
        $teamId,
    )) as $row) {
        $roster[(int) $row['player']] = true;
    }
    foreach (['scorer_id', 'assist_id'] as $field) {
        if ($goal[$field] !== null && !isset($roster[$goal[$field]])) {
            $f->add($field, 'The player is not on the ' . $side . ' game roster.');
        }
    }
    ScorekeeperGoalPlayersCheck($goal, '', $f);

    for ($attempt = 1; ; $attempt++) {
        $last = ScorekeeperLastGoalForUpdate($gameId);
        $num = $last === null ? 1 : $last['num'] + 1;
        $home = ($last['homescore'] ?? 0) + ($side === 'home' ? 1 : 0);
        $away = ($last['visitorscore'] ?? 0) + ($side === 'away' ? 1 : 0);
        $check = clone $f;
        $time = ScorekeeperGoalTime($goal['time'], $last['time'] ?? 0, $hideTime, 'time', $check);
        if (max($home, $away) > SCOREKEEPING_API_MAX_SIDE_GOALS) {
            $check->add('side', sprintf('A side can have at most %d goals.', SCOREKEEPING_API_MAX_SIDE_GOALS));
        }
        if ($check->errors !== []) {
            throw new ScorekeepingApiException(400, 'validation_failed', 'The goal does not match the game.', $check->errors);
        }
        try {
            ScorekeeperMust(GameAddScoreEntry([
                'game' => $gameId,
                'num' => $num,
                'assist' => $goal['assist_id'],
                'scorer' => $goal['scorer_id'],
                'time' => $time,
                'homescore' => $home,
                'visitorscore' => $away,
                'ishomegoal' => $side === 'home' ? 1 : 0,
                'iscallahan' => $goal['callahan'] ? 1 : 0,
            ]), 'goal');
            break;
        } catch (mysqli_sql_exception | DBOperationException $e) {
            if ((int) $e->getCode() !== 1062) {
                throw $e;
            }
            if ($attempt > 1) {
                throw new ScorekeepingApiException(409, 'goal_changed', 'Another device added a goal at the same time. Fetch the entry context again.');
            }
        }
    }

    // Per team, so a point tapped on the result page for the other team is kept.
    $resultHome = max($home, (int) $state['homescore']);
    $resultAway = max($away, (int) $state['visitorscore']);
    if ($resultHome > (int) $state['homescore'] || $resultAway > (int) $state['visitorscore']) {
        ScorekeeperMust(GameUpdateResult($gameId, $resultHome, $resultAway, false), 'result');
    }

    return [
        'status' => 201,
        'data' => [
            'goal' => [
                'num' => $num,
                'side' => $side,
                'scorer_id' => $goal['scorer_id'],
                'assist_id' => $goal['assist_id'],
                'callahan' => $goal['callahan'],
                'time' => $hideTime ? null : $time,
                'home_score' => $home,
                'away_score' => $away,
            ],
        ],
    ];
}

/**
 * Delete a game's last goal, as the Scorekeeper delete page does, and lower
 * the result onto the remaining goals when it was tracking them.
 *
 * The $write of ScorekeepingApiWrite(). Refused with 409 when goal $num is no
 * longer the last one, so a device cannot delete a goal another device added.
 *
 * @param int $gameId uo_game.game_id
 * @param int $num uo_goal.num the client believes is last
 * @return array{status: int, data: array}
 * @throws ScorekeepingApiException
 */
function ScorekeeperDeleteLastGoal($gameId, $num)
{
    $gameId = (int) $gameId;
    $last = ScorekeeperLastGoalForUpdate($gameId);
    if ($last === null || $last['num'] !== (int) $num) {
        throw new ScorekeepingApiException(409, 'goal_changed', 'Only the last goal can be deleted, and that goal is not the last one now.');
    }
    ScorekeeperMust(GameRemoveScore($gameId, $last['num']), 'goal');
    GameSyncResultFromGoals($gameId, $last['homescore'], $last['visitorscore']);
    return ['status' => 200, 'data' => ['deleted' => ['num' => $last['num']]]];
}

/**
 * Append one timeout or spirit stoppage for a team, numbered after the team's
 * last one and within the per-team limit.
 *
 * The $write of ScorekeepingApiWrite().
 *
 * @param int $gameId uo_game.game_id
 * @param string $kind 'timeout' or 'spirit_stoppage'
 * @param array{side: string, time: int} $stoppage
 * @return array{status: int, data: array}
 * @throws ScorekeepingApiException
 */
function ScorekeeperAddStoppage($gameId, $kind, array $stoppage)
{
    $gameId = (int) $gameId;
    $isHome = $stoppage['side'] === 'home' ? 1 : 0;
    if ($kind === 'timeout') {
        $table = 'uo_timeout';
        $limit = GameTimeoutsPerTeam($gameId);
    } else {
        if (empty(SeasonInfo(GameSeason($gameId))['spiritmode'])) {
            throw new ScorekeepingApiException(409, 'spirit_stoppages_disabled', 'Spirit stoppages are not used in this event.');
        }
        $table = 'uo_spirit_timeout';
        $limit = SCOREKEEPING_API_SPIRIT_STOPPAGES_PER_TEAM;
    }
    $count = (int) DBQueryToValueUncached(sprintf(
        "SELECT COUNT(*) FROM %s WHERE game=%d AND ishome=%d FOR UPDATE",
        $table,
        $gameId,
        $isHome,
    ));
    if ($count >= $limit) {
        throw new ScorekeepingApiException(
            409,
            $kind . '_limit',
            sprintf($kind === 'timeout' ? 'The team has used all %d timeouts.' : 'The team has used all %d spirit stoppages.', $limit),
        );
    }
    $num = $count + 1;
    if ($kind === 'timeout') {
        ScorekeeperMust(GameAddTimeout($gameId, $num, $stoppage['time'], $isHome), 'timeout');
    } else {
        ScorekeeperMust(GameAddSpiritTimeout($gameId, $num, $stoppage['time'], $isHome), 'spirit stoppage');
    }
    return ['status' => 201, 'data' => [$kind => ['side' => $stoppage['side'], 'num' => $num, 'time' => $stoppage['time']]]];
}

/**
 * Add one point to, or take one from, a team's score for result-only keeping,
 * as the Scorekeeper result page's taps do. Refused on a final game.
 *
 * The $write of ScorekeepingApiWrite().
 *
 * @param int $gameId uo_game.game_id
 * @param array{side: string, delta: int} $tap
 * @return array{status: int, data: array}
 * @throws ScorekeepingApiException
 */
function ScorekeeperApplyScoreTap($gameId, array $tap)
{
    $gameId = (int) $gameId;
    $state = ScorekeeperLockedGameState($gameId);
    if ($state['final']) {
        throw new ScorekeepingApiException(409, 'game_final', 'The game has a final result.');
    }
    $score = (int) ($tap['side'] === 'home' ? $state['homescore'] : $state['visitorscore']) + $tap['delta'];
    if ($score < 0 || $score > MAX_GAME_SCORE || !GameApplyScoreTap($gameId, $tap['side'] === 'home', $tap['delta'])) {
        throw new ScorekeepingApiException(409, 'score_out_of_range', sprintf('A score must stay between 0 and %d.', MAX_GAME_SCORE));
    }
    return ['status' => 200, 'data' => []];
}

/**
 * Replace one team's game roster: players, numbers, captains and spirit
 * captains. A player the game's goals or defence sheet refer to cannot be
 * removed.
 *
 * The $write of ScorekeepingApiWrite().
 *
 * @param int $gameId uo_game.game_id
 * @param string $side 'home' or 'away'
 * @param array $doc Output of ScorekeeperLiveRequestNormalize('roster', ...)
 * @return array{status: int, data: array}
 * @throws ScorekeepingApiException
 */
function ScorekeeperReplaceRoster($gameId, $side, array $doc)
{
    $gameId = (int) $gameId;
    $state = ScorekeeperLockedGameState($gameId);
    $teamId = $side === 'home' ? $state['home_team'] : $state['away_team'];
    if ($teamId === null || $teamId !== $doc['team_id']) {
        throw new ScorekeepingApiException(409, 'fixture_changed', 'The ' . $side . ' team of the game is not the one in the request.');
    }

    $f = new ScorekeepingApiFields();
    $submitted = ScorekeeperRosterCheck($gameId, [$side => $doc['players']], [$side => $teamId], [$side => 'players'], $f);
    if ($f->errors !== []) {
        throw new ScorekeepingApiException(400, 'validation_failed', 'The roster does not match the game.', $f->errors);
    }

    $removed = [];
    foreach (DBQueryToArrayUncached(sprintf(
        "SELECT pl.player FROM uo_played pl INNER JOIN uo_player p ON (p.player_id=pl.player) WHERE pl.game=%d AND p.team=%d",
        $gameId,
        $teamId,
    )) as $row) {
        if (!isset($submitted[(int) $row['player']])) {
            $removed[(int) $row['player']] = true;
        }
    }
    $referenced = [];
    if ($removed !== []) {
        $ids = implode(',', array_keys($removed));
        foreach (DBQueryToArrayUncached(sprintf(
            "SELECT scorer AS player FROM uo_goal WHERE game=%1\$d AND scorer IN (%2\$s)
             UNION SELECT assist FROM uo_goal WHERE game=%1\$d AND assist IN (%2\$s)
             UNION SELECT author FROM uo_defense WHERE game=%1\$d AND author IN (%2\$s)",
            $gameId,
            $ids,
        )) as $row) {
            $referenced[] = (int) $row['player'];
        }
    }
    if ($referenced !== []) {
        sort($referenced);
        throw new ScorekeepingApiException(409, 'player_referenced', 'Players recorded on a goal or the defence sheet must stay on the roster.', ['player_ids' => $referenced]);
    }

    $current = ScorekeeperRosterNumbers($gameId);
    foreach (array_keys($removed) as $playerId) {
        ScorekeeperMust(GameRemovePlayer($gameId, $playerId), 'roster');
    }
    ScorekeeperRosterWrite($gameId, $teamId, $doc['players'], $current);
    return ['status' => 200, 'data' => ['roster' => ['side' => $side, 'players' => count($doc['players'])]]];
}

/**
 * Finalize the game at the score the client has been keeping. The stored
 * score must still be exactly that, so a point another device recorded since
 * is not finalized over; a game with no score yet cannot be finalized this way.
 *
 * The $write of ScorekeepingApiWrite(). Recomputes the pool standings and
 * playoff moves inside the transaction, as GameSetResult() does.
 *
 * @param int $gameId uo_game.game_id
 * @param array{home: int, away: int} $score
 * @return array{status: int, data: array}
 * @throws ScorekeepingApiException
 */
function ScorekeeperFinalize($gameId, array $score)
{
    if (!GameSetResult((int) $gameId, $score['home'], $score['away'], true, [$score['home'], $score['away']])) {
        throw new ScorekeepingApiException(409, 'score_changed', 'The stored score is not the one sent. Fetch the entry context again.');
    }
    return ['status' => 200, 'data' => []];
}
