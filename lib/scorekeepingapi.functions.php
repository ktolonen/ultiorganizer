<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/cache.functions.php';
require_once __DIR__ . '/persistent-cache.functions.php';
require_once __DIR__ . '/scoresheethistory.functions.php';

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
 * @return string 64 hex characters
 */
function ScorekeeperUploadToken($gameId)
{
    // A post-write call must read the rows the write just changed, and on a
    // GET the snapshot reads would otherwise come from the cross-request cache.
    DisablePersistentCacheForRequest();
    CacheForgetNamespace("scoresheet_history_snapshot");
    $snapshot = ScoresheetHistoryBuildSnapshot((int) $gameId);
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
