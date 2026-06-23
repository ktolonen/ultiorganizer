<?php

require_once __DIR__ . '/include_only.guard.php';
require_once __DIR__ . '/game.functions.php';
require_once __DIR__ . '/player.functions.php';
require_once __DIR__ . '/season.functions.php';
require_once __DIR__ . '/spirit.functions.php';
require_once __DIR__ . '/statistical.functions.php';
denyDirectLibAccess(__FILE__);

define('EVENT_SNAPSHOT_FORMAT', 'ultiorganizer.event-snapshot');
define('EVENT_SNAPSHOT_VERSION', 2);

class EventSnapshotException extends RuntimeException {}

/**
 * Export an event as a JSON v2 event snapshot.
 *
 * @param string $seasonId uo_season.season_id
 * @return string JSON document
 */
function EventSnapshotExportJson($seasonId)
{
    $service = new EventSnapshotService();
    return $service->exportJson($seasonId);
}

/**
 * Import a JSON v2 event snapshot.
 *
 * @param string $filename Uploaded JSON snapshot file
 * @param string $eventId Target event for replace mode, or empty for a new event
 * @param string $mode new|replace
 * @return array import result
 */
function EventSnapshotImportJson($filename, $eventId = "", $mode = "new")
{
    $service = new EventSnapshotService();
    return $service->importFile($filename, $eventId, $mode);
}

class EventSnapshotService
{
    private $warnings = [];
    private $idMap = [];
    private $snapshot = [];
    private $targetSeasonId = '';
    private $mode = 'new';
    private $transactionStarted = false;

    public function exportJson($seasonId)
    {
        if (!isSeasonAdmin($seasonId)) {
            throw new EventSnapshotException(_("Insufficient rights to export data"));
        }

        $season = DBQueryToRow(sprintf(
            "SELECT * FROM uo_season WHERE season_id='%s'",
            DBEscapeString($seasonId),
        ), true);
        if (!$season) {
            throw new EventSnapshotException(_("Event to export doesn't exist"));
        }

        $ids = $this->collectEventIds($seasonId);
        $tables = $this->exportTables($seasonId, $ids);

        $snapshot = [
            'format' => EVENT_SNAPSHOT_FORMAT,
            'version' => EVENT_SNAPSHOT_VERSION,
            'source_db_version' => DB_VERSION,
            'exported_at' => gmdate('c'),
            'event' => [
                'season_id' => $season['season_id'],
                'name' => $season['name'],
                'stats_calculated' => IsSeasonStatsCalculated($seasonId),
            ],
            'tables' => $tables,
            'warnings' => $this->warnings,
        ];

        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new EventSnapshotException(_("Could not create event snapshot."));
        }
        return $json . "\n";
    }

    public function importFile($filename, $eventId = "", $mode = "new")
    {
        $this->mode = $mode;
        $this->targetSeasonId = $eventId;
        $this->warnings = [];
        $this->idMap = [];

        $contents = file_get_contents($filename);
        if ($contents === false) {
            throw new EventSnapshotException(_("Could not read import file."));
        }
        $typeCheckContents = $contents;
        if (strncmp($typeCheckContents, "\xEF\xBB\xBF", 3) === 0) {
            $typeCheckContents = substr($typeCheckContents, 3);
        }
        $typeCheckContents = ltrim($typeCheckContents);
        if ($typeCheckContents !== '' && $typeCheckContents[0] === '<') {
            throw new EventSnapshotException(_("Legacy XML event exports are no longer supported. Export a JSON event snapshot from a current Ultiorganizer installation and import that file."));
        }

        $snapshot = json_decode($contents, true);
        if (!is_array($snapshot)) {
            throw new EventSnapshotException(_("The import file is not a valid JSON event snapshot."));
        }
        $this->snapshot = $snapshot;

        try {
            $this->validateSnapshot();
        } catch (EventSnapshotException $e) {
            throw new EventSnapshotException($this->withSnapshotVersionDetails($e->getMessage()));
        }
        $target = $this->prepareTargetSeason();
        try {
            $this->validateReferences($target);
        } catch (EventSnapshotException $e) {
            throw new EventSnapshotException($this->withSnapshotVersionDetails($e->getMessage()));
        }

        try {
            DBSetExceptionMode(true);
            DBQuery('START TRANSACTION');
            $this->transactionStarted = true;

            if ($mode === 'replace') {
                $this->deleteEventOwnedRows($target['season_id']);
            }

            $this->importSnapshotRows($target);
            $this->refreshDerivedData($target['season_id']);

            DBQuery('COMMIT');
            $this->transactionStarted = false;
        } catch (Throwable $e) {
            if ($this->transactionStarted) {
                DBQuery('ROLLBACK');
                $this->transactionStarted = false;
            }
            DBSetExceptionMode(false);
            if ($e instanceof EventSnapshotException) {
                throw $e;
            }
            throw new EventSnapshotException($e->getMessage());
        }
        DBSetExceptionMode(false);

        $this->clearImportCaches();

        return [
            'season_id' => $target['season_id'],
            'warnings' => $this->warnings,
        ];
    }

    private function manifest()
    {
        return [
            'uo_pooltemplate' => ['pk' => ['template_id'], 'auto' => true],
            'uo_spirit_category' => ['pk' => ['category_id'], 'auto' => true],
            'uo_location' => ['pk' => ['id'], 'auto' => true],
            'uo_location_info' => ['pk' => ['location_id', 'locale']],
            'uo_season' => ['pk' => ['season_id']],
            'uo_series' => ['pk' => ['series_id'], 'auto' => true],
            'uo_scheduling_name' => ['pk' => ['scheduling_id'], 'auto' => true],
            'uo_reservation' => ['pk' => ['id'], 'auto' => true],
            'uo_movingtime' => ['pk' => ['season', 'fromlocation', 'fromfield', 'tolocation', 'tofield']],
            'uo_pool' => ['pk' => ['pool_id'], 'auto' => true],
            'uo_team' => ['pk' => ['team_id'], 'auto' => true],
            'uo_team_profile' => ['pk' => ['team_id']],
            'uo_player_profile' => ['pk' => ['profile_id'], 'auto' => true],
            'uo_player' => ['pk' => ['player_id'], 'auto' => true],
            'uo_team_pool' => ['pk' => ['team', 'pool']],
            'uo_game' => ['pk' => ['game_id'], 'auto' => true],
            'uo_urls' => ['pk' => ['url_id'], 'auto' => true],
            'uo_game_pool' => ['pk' => ['game', 'pool']],
            'uo_moveteams' => ['pk' => ['frompool', 'fromplacing']],
            'uo_specialranking' => ['pk' => ['frompool', 'fromplacing']],
            'uo_goal' => ['pk' => ['game', 'num']],
            'uo_played' => ['pk' => ['player', 'game']],
            'uo_gameevent' => ['pk' => ['game', 'num']],
            'uo_timeout' => ['pk' => ['timeout_id'], 'auto' => true],
            'uo_spirit_timeout' => ['pk' => ['spirit_timeout_id'], 'auto' => true],
            'uo_spirit_score' => ['pk' => ['game_id', 'team_id', 'category_id']],
            'uo_defense' => ['pk' => ['game', 'num']],
            'uo_season_round' => ['pk' => ['round_id'], 'auto' => true],
            'uo_season_points' => ['pk' => ['round_id', 'team_id']],
            'uo_team_final_standing' => ['pk' => ['team_id']],
            'uo_comment' => ['pk' => ['type', 'id']],
        ];
    }

    private function collectEventIds($seasonId)
    {
        $seasonSafe = DBEscapeString($seasonId);
        $season = DBQueryToRow("SELECT spiritmode FROM uo_season WHERE season_id='$seasonSafe'", true);
        $series = $this->columnValues(DBQueryToArray("SELECT series_id FROM uo_series WHERE season='$seasonSafe'", true), 'series_id');
        $seriesSql = $this->intList($series);

        $pools = empty($series)
            ? []
            : $this->columnValues(DBQueryToArray("SELECT pool_id FROM uo_pool WHERE series IN ($seriesSql)", true), 'pool_id');
        $poolSql = $this->intList($pools);

        $teams = empty($series)
            ? []
            : $this->columnValues(DBQueryToArray("SELECT team_id FROM uo_team WHERE series IN ($seriesSql)", true), 'team_id');
        $teamSql = $this->intList($teams);

        $players = empty($teams)
            ? []
            : $this->columnValues(DBQueryToArray("SELECT player_id FROM uo_player WHERE team IN ($teamSql)", true), 'player_id');
        $playerSql = $this->intList($players);

        $profiles = empty($players)
            ? []
            : $this->columnValues(DBQueryToArray("SELECT DISTINCT profile_id FROM uo_player WHERE player_id IN ($playerSql) AND profile_id IS NOT NULL", true), 'profile_id');

        $reservations = $this->columnValues(DBQueryToArray("SELECT id FROM uo_reservation WHERE season='$seasonSafe'", true), 'id');

        $locations = $this->columnValues(DBQueryToArray("SELECT DISTINCT location FROM uo_reservation WHERE season='$seasonSafe' AND location IS NOT NULL", true), 'location');
        $movingRows = DBQueryToArray("SELECT fromlocation, tolocation FROM uo_movingtime WHERE season='$seasonSafe'", true);
        foreach ($movingRows as $row) {
            $locations[] = (int) $row['fromlocation'];
            $locations[] = (int) $row['tolocation'];
        }
        $locations = $this->uniqueInts($locations);

        $games = empty($pools)
            ? []
            : $this->columnValues(DBQueryToArray("SELECT DISTINCT game FROM uo_game_pool WHERE pool IN ($poolSql)", true), 'game');
        $gameSql = $this->intList($games);

        $scheduling = [];
        if (!empty($games)) {
            $gameScheduling = DBQueryToArray("SELECT scheduling_name_home, scheduling_name_visitor, name, respteam, hometeam FROM uo_game WHERE game_id IN ($gameSql)", true);
            foreach ($gameScheduling as $row) {
                foreach (['scheduling_name_home', 'scheduling_name_visitor', 'name'] as $field) {
                    if (!empty($row[$field])) {
                        $scheduling[] = (int) $row[$field];
                    }
                }
                if (empty($row['hometeam']) && !empty($row['respteam'])) {
                    $scheduling[] = (int) $row['respteam'];
                }
            }
        }
        if (!empty($pools)) {
            $moveScheduling = DBQueryToArray("SELECT scheduling_id FROM uo_moveteams WHERE (frompool IN ($poolSql) OR topool IN ($poolSql)) AND scheduling_id IS NOT NULL", true);
            $scheduling = array_merge($scheduling, $this->columnValues($moveScheduling, 'scheduling_id'));
        }
        $scheduling = $this->uniqueInts($scheduling);

        $poolTemplates = empty($series)
            ? []
            : $this->columnValues(DBQueryToArray("SELECT DISTINCT pool_template FROM uo_series WHERE series_id IN ($seriesSql) AND pool_template IS NOT NULL", true), 'pool_template');

        $rounds = $this->columnValues(DBQueryToArray("SELECT round_id FROM uo_season_round WHERE season='$seasonSafe'", true), 'round_id');

        $urlIds = [];
        $urlOwnerWhere = $this->urlOwnerWhere($seasonId, $series, $pools, $teams, $games);
        if ($urlOwnerWhere !== '') {
            $urlIds = $this->columnValues(DBQueryToArray("SELECT url_id FROM uo_urls WHERE $urlOwnerWhere", true), 'url_id');
        }

        $spiritCategories = [];
        if (!empty($games)) {
            $spiritCategories = $this->columnValues(DBQueryToArray("SELECT DISTINCT category_id FROM uo_spirit_score WHERE game_id IN ($gameSql)", true), 'category_id');
        }
        if (!empty($season['spiritmode'])) {
            $spiritCategories = array_merge($spiritCategories, $this->columnValues(DBQueryToArray(
                "SELECT category_id FROM uo_spirit_category WHERE mode=" . (int) $season['spiritmode'],
                true,
            ), 'category_id'));
        }

        return [
            'series' => $this->uniqueInts($series),
            'pools' => $this->uniqueInts($pools),
            'teams' => $this->uniqueInts($teams),
            'players' => $this->uniqueInts($players),
            'profiles' => $this->uniqueInts($profiles),
            'reservations' => $this->uniqueInts($reservations),
            'locations' => $this->uniqueInts($locations),
            'games' => $this->uniqueInts($games),
            'scheduling' => $this->uniqueInts($scheduling),
            'pooltemplates' => $this->uniqueInts($poolTemplates),
            'rounds' => $this->uniqueInts($rounds),
            'urls' => $this->uniqueInts($urlIds),
            'spirit_categories' => $this->uniqueInts($spiritCategories),
        ];
    }

    private function exportTables($seasonId, $ids)
    {
        $seasonSafe = DBEscapeString($seasonId);
        $tables = [];
        foreach (array_keys($this->manifest()) as $table) {
            $tables[$table] = [];
        }

        $tables['uo_season'] = $this->selectRows("SELECT * FROM uo_season WHERE season_id='$seasonSafe'", ['reg_id']);
        $tables['uo_pooltemplate'] = empty($ids['pooltemplates']) ? [] : $this->selectRows("SELECT * FROM uo_pooltemplate WHERE template_id IN (" . $this->intList($ids['pooltemplates']) . ") ORDER BY template_id");
        $tables['uo_spirit_category'] = empty($ids['spirit_categories']) ? [] : $this->selectRows("SELECT * FROM uo_spirit_category WHERE category_id IN (" . $this->intList($ids['spirit_categories']) . ") ORDER BY category_id");
        $tables['uo_location'] = empty($ids['locations']) ? [] : $this->selectRows("SELECT * FROM uo_location WHERE id IN (" . $this->intList($ids['locations']) . ") ORDER BY id");
        $tables['uo_location_info'] = empty($ids['locations']) ? [] : $this->selectRows("SELECT * FROM uo_location_info WHERE location_id IN (" . $this->intList($ids['locations']) . ") ORDER BY location_id, locale");
        $tables['uo_series'] = empty($ids['series']) ? [] : $this->selectRows("SELECT * FROM uo_series WHERE series_id IN (" . $this->intList($ids['series']) . ") ORDER BY series_id");
        $tables['uo_scheduling_name'] = empty($ids['scheduling']) ? [] : $this->selectRows("SELECT * FROM uo_scheduling_name WHERE scheduling_id IN (" . $this->intList($ids['scheduling']) . ") ORDER BY scheduling_id");
        $tables['uo_reservation'] = $this->selectRows("SELECT * FROM uo_reservation WHERE season='$seasonSafe' ORDER BY id");
        $tables['uo_movingtime'] = $this->selectRows("SELECT * FROM uo_movingtime WHERE season='$seasonSafe' ORDER BY fromlocation, fromfield, tolocation, tofield");
        $tables['uo_pool'] = empty($ids['pools']) ? [] : $this->selectRows("SELECT * FROM uo_pool WHERE pool_id IN (" . $this->intList($ids['pools']) . ") ORDER BY pool_id");
        $tables['uo_team'] = empty($ids['teams']) ? [] : $this->selectRows("SELECT * FROM uo_team WHERE team_id IN (" . $this->intList($ids['teams']) . ") ORDER BY team_id", ['club', 'reg_id', 'sotg_token']);
        $tables['uo_team_profile'] = empty($ids['teams']) ? [] : $this->selectRows("SELECT team_id, coach, story, achievements, captain FROM uo_team_profile WHERE team_id IN (" . $this->intList($ids['teams']) . ") ORDER BY team_id");
        $tables['uo_player_profile'] = empty($ids['profiles']) ? [] : $this->selectRows("SELECT profile_id, firstname, lastname, num, accreditation_id, birthdate, gender, nickname, nationality, throwing_hand, height, weight, position, public FROM uo_player_profile WHERE profile_id IN (" . $this->intList($ids['profiles']) . ") ORDER BY profile_id");
        $tables['uo_player'] = empty($ids['players']) ? [] : $this->selectRows("SELECT * FROM uo_player WHERE player_id IN (" . $this->intList($ids['players']) . ") ORDER BY player_id", ['reg_id']);
        $tables['uo_team_pool'] = empty($ids['teams']) && empty($ids['pools']) ? [] : $this->selectRows("SELECT * FROM uo_team_pool WHERE team IN (" . $this->intList($ids['teams']) . ") OR pool IN (" . $this->intList($ids['pools']) . ") ORDER BY pool, team");
        $tables['uo_game'] = empty($ids['games']) ? [] : $this->selectRows("SELECT * FROM uo_game WHERE game_id IN (" . $this->intList($ids['games']) . ") ORDER BY game_id", ['resppers']);
        $urlOwnerWhere = $this->urlOwnerWhere($seasonId, $ids['series'], $ids['pools'], $ids['teams'], $ids['games']);
        $tables['uo_urls'] = $urlOwnerWhere === '' ? [] : $this->selectRows("SELECT * FROM uo_urls WHERE $urlOwnerWhere ORDER BY owner, owner_id, ordering, url_id", ['publisher_id']);
        $tables['uo_game_pool'] = empty($ids['games']) ? [] : $this->selectRows("SELECT * FROM uo_game_pool WHERE game IN (" . $this->intList($ids['games']) . ") ORDER BY game, pool");
        $tables['uo_moveteams'] = empty($ids['pools']) ? [] : $this->selectRows("SELECT * FROM uo_moveteams WHERE frompool IN (" . $this->intList($ids['pools']) . ") OR topool IN (" . $this->intList($ids['pools']) . ") ORDER BY frompool, fromplacing");
        $tables['uo_specialranking'] = empty($ids['pools']) ? [] : $this->selectRows("SELECT * FROM uo_specialranking WHERE frompool IN (" . $this->intList($ids['pools']) . ") ORDER BY frompool, fromplacing");
        $tables['uo_goal'] = empty($ids['games']) ? [] : $this->selectRows("SELECT * FROM uo_goal WHERE game IN (" . $this->intList($ids['games']) . ") ORDER BY game, num");
        $tables['uo_played'] = empty($ids['games']) ? [] : $this->selectRows("SELECT * FROM uo_played WHERE game IN (" . $this->intList($ids['games']) . ") ORDER BY game, player");
        $tables['uo_gameevent'] = empty($ids['games']) ? [] : $this->selectRows("SELECT * FROM uo_gameevent WHERE game IN (" . $this->intList($ids['games']) . ") ORDER BY game, num");
        $tables['uo_timeout'] = empty($ids['games']) ? [] : $this->selectRows("SELECT * FROM uo_timeout WHERE game IN (" . $this->intList($ids['games']) . ") ORDER BY game, num");
        $tables['uo_spirit_timeout'] = empty($ids['games']) ? [] : $this->selectRows("SELECT * FROM uo_spirit_timeout WHERE game IN (" . $this->intList($ids['games']) . ") ORDER BY game, num");
        $tables['uo_spirit_score'] = empty($ids['games']) ? [] : $this->selectRows("SELECT * FROM uo_spirit_score WHERE game_id IN (" . $this->intList($ids['games']) . ") ORDER BY game_id, team_id, category_id");
        $tables['uo_defense'] = empty($ids['games']) ? [] : $this->selectRows("SELECT * FROM uo_defense WHERE game IN (" . $this->intList($ids['games']) . ") ORDER BY game, num");
        $tables['uo_season_round'] = $this->selectRows("SELECT * FROM uo_season_round WHERE season='$seasonSafe' ORDER BY series, round_no, round_id");
        $tables['uo_season_points'] = empty($ids['rounds']) ? [] : $this->selectRows("SELECT * FROM uo_season_points WHERE round_id IN (" . $this->intList($ids['rounds']) . ") ORDER BY round_id, team_id");
        $tables['uo_team_final_standing'] = $this->selectRows("SELECT * FROM uo_team_final_standing WHERE season='$seasonSafe' ORDER BY series, standing, team_id", ['updated_at']);
        $tables['uo_comment'] = $this->exportComments($seasonId, $ids);
        $this->completeExportedPlayerProfiles($tables);

        return $tables;
    }

    private function completeExportedPlayerProfiles(&$tables)
    {
        $profileByAccreditation = [];
        foreach ($tables['uo_player_profile'] as $profile) {
            $accreditationId = trim((string) ($profile['accreditation_id'] ?? ''));
            if ($accreditationId !== '') {
                $profileByAccreditation[$accreditationId] = $profile['profile_id'];
            }
        }

        $syntheticByAccreditation = [];
        $nextSyntheticId = -1;
        foreach ($tables['uo_player'] as $idx => $player) {
            if (!empty($player['profile_id'])) {
                continue;
            }

            $accreditationId = trim((string) ($player['accreditation_id'] ?? ''));
            if ($accreditationId !== '' && isset($profileByAccreditation[$accreditationId])) {
                $tables['uo_player'][$idx]['profile_id'] = $profileByAccreditation[$accreditationId];
                continue;
            }
            if ($accreditationId !== '' && isset($syntheticByAccreditation[$accreditationId])) {
                $tables['uo_player'][$idx]['profile_id'] = $syntheticByAccreditation[$accreditationId];
                continue;
            }

            $syntheticProfileId = $nextSyntheticId--;
            $tables['uo_player_profile'][] = [
                'profile_id' => $syntheticProfileId,
                'firstname' => $player['firstname'] ?? null,
                'lastname' => $player['lastname'] ?? null,
                'num' => $player['num'] ?? null,
                'accreditation_id' => $accreditationId === '' ? null : $accreditationId,
                'birthdate' => null,
                'gender' => null,
                'nickname' => null,
                'nationality' => null,
                'throwing_hand' => null,
                'height' => null,
                'weight' => null,
                'position' => null,
                'public' => '',
            ];
            $tables['uo_player'][$idx]['profile_id'] = $syntheticProfileId;

            if ($accreditationId !== '') {
                $syntheticByAccreditation[$accreditationId] = $syntheticProfileId;
            }
        }
    }

    private function validateSnapshot()
    {
        if (($this->snapshot['format'] ?? '') !== EVENT_SNAPSHOT_FORMAT) {
            throw new EventSnapshotException(_("The import file is not an Ultiorganizer event snapshot."));
        }
        if ((int) ($this->snapshot['version'] ?? 0) !== EVENT_SNAPSHOT_VERSION) {
            throw new EventSnapshotException(_("Unsupported event snapshot version."));
        }
        $this->warnSchemaVersionMismatch();
        if (!isset($this->snapshot['tables']) || !is_array($this->snapshot['tables'])) {
            throw new EventSnapshotException(_("The event snapshot does not contain table data."));
        }
        $manifest = $this->manifest();
        foreach (array_keys($this->snapshot['tables']) as $table) {
            if (!isset($manifest[$table])) {
                throw new EventSnapshotException(sprintf(_("Unsupported event snapshot table: %s"), $table));
            }
        }
        foreach (array_keys($manifest) as $table) {
            if (!array_key_exists($table, $this->snapshot['tables'])) {
                throw new EventSnapshotException(sprintf(_("The event snapshot is missing required table data: %s"), $table));
            }
            if (!is_array($this->snapshot['tables'][$table])) {
                throw new EventSnapshotException(sprintf(_("Invalid event snapshot table: %s"), $table));
            }
        }
        $this->dropObsoleteSnapshotColumns($manifest);

        if (count($this->snapshot['tables']['uo_season']) !== 1) {
            throw new EventSnapshotException(_("The event snapshot must contain exactly one event row."));
        }

        $disallowedFields = $this->disallowedSnapshotFields();
        foreach ($manifest as $table => $rule) {
            $columns = GetTableColumns($table);
            $seen = [];
            foreach ($this->snapshot['tables'][$table] as $row) {
                if (!is_array($row)) {
                    throw new EventSnapshotException(sprintf(_("Invalid event snapshot row in %s."), $table));
                }
                foreach ($rule['pk'] as $field) {
                    if (!array_key_exists($field, $row) || $row[$field] === null || $row[$field] === 'NULL') {
                        throw new EventSnapshotException(sprintf(_("Invalid event snapshot row in %s."), $table));
                    }
                }
                foreach ($row as $field => $_) {
                    $fieldName = strtolower((string) $field);
                    if (isset($disallowedFields[$table][$fieldName])) {
                        throw new EventSnapshotException(sprintf(_("Private field %s.%s cannot be imported from an event snapshot."), $table, $fieldName));
                    }
                    if (!isset($columns[$fieldName])) {
                        throw new EventSnapshotException(sprintf(_("Invalid event snapshot column %s.%s."), $table, $fieldName));
                    }
                }
                $key = $this->rowKey($row, $rule['pk']);
                if ($key !== null) {
                    if (isset($seen[$key])) {
                        throw new EventSnapshotException(sprintf(_("Duplicate event snapshot key in %s."), $table));
                    }
                    $seen[$key] = true;
                }
            }
        }
    }

    private function warnSchemaVersionMismatch()
    {
        $sourceDbVersion = $this->snapshot['source_db_version'] ?? null;
        if ((string) $sourceDbVersion === (string) DB_VERSION) {
            return;
        }
        $this->warnings[] = $this->snapshotVersionDetails();
    }

    private function withSnapshotVersionDetails($message)
    {
        return $message . " " . $this->snapshotVersionDetails();
    }

    private function snapshotVersionDetails()
    {
        return sprintf(
            _("Import file snapshot version: %s; supported snapshot version: %d; import file database schema version: %s; current database schema version: %d."),
            $this->versionValue($this->snapshot['version'] ?? null),
            EVENT_SNAPSHOT_VERSION,
            $this->versionValue($this->snapshot['source_db_version'] ?? null),
            DB_VERSION,
        );
    }

    private function versionValue($value)
    {
        if ($value === null || $value === '') {
            return _("unknown");
        }
        return (string) $value;
    }

    private function dropObsoleteSnapshotColumns($manifest)
    {
        $disallowedFields = $this->disallowedSnapshotFields();
        $warned = [];
        foreach (array_keys($manifest) as $table) {
            $columns = GetTableColumns($table);
            foreach ($this->snapshot['tables'][$table] as $idx => $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($row as $field => $_) {
                    $fieldName = strtolower((string) $field);
                    if (isset($columns[$fieldName]) || isset($disallowedFields[$table][$fieldName])) {
                        continue;
                    }
                    unset($this->snapshot['tables'][$table][$idx][$field]);

                    $warningKey = $table . '.' . $fieldName;
                    if (isset($warned[$warningKey])) {
                        continue;
                    }
                    $this->warnings[] = sprintf(
                        _("Snapshot column %s.%s is not used by this Ultiorganizer version and was skipped."),
                        $table,
                        $fieldName,
                    );
                    $warned[$warningKey] = true;
                }
            }
        }
    }

    private function prepareTargetSeason()
    {
        $season = $this->snapshot['tables']['uo_season'][0];
        $sourceId = (string) ($season['season_id'] ?? '');
        $sourceName = (string) ($season['name'] ?? $sourceId);

        if ($this->mode === 'new') {
            if (!isSuperAdmin()) {
                throw new EventSnapshotException(_("Insufficient rights to import data"));
            }
            $identity = $this->uniqueSeasonIdentity($sourceId, $sourceName);
            return [
                'season_id' => $identity['season_id'],
                'name' => $identity['name'],
                'source_id' => $sourceId,
            ];
        }

        if ($this->mode === 'replace') {
            if (empty($this->targetSeasonId) || !isSeasonAdmin($this->targetSeasonId)) {
                throw new EventSnapshotException(_("Insufficient rights to import data"));
            }
            if (!SeasonExists($this->targetSeasonId)) {
                throw new EventSnapshotException(_("Event to replace doesn't exist"));
            }
            return [
                'season_id' => $this->targetSeasonId,
                'name' => $sourceName,
                'source_id' => $sourceId,
            ];
        }

        throw new EventSnapshotException(_("Unknown event import mode."));
    }

    private function validateReferences($target)
    {
        $tables = $this->snapshot['tables'];
        $this->requireProfileRows($tables);

        $this->validateFkSet('uo_series', 'season', 'uo_season', 'season_id');
        $this->validateFkSet('uo_series', 'pool_template', 'uo_pooltemplate', 'template_id', true);
        $this->validateFkSet('uo_pool', 'series', 'uo_series', 'series_id', true);
        $this->validateFkSet('uo_pool', 'follower', 'uo_pool', 'pool_id', true);
        $this->validateFkSet('uo_team', 'series', 'uo_series', 'series_id', true);
        $this->validateFkSet('uo_team', 'pool', 'uo_pool', 'pool_id', true);
        $this->validateFkSet('uo_player', 'team', 'uo_team', 'team_id', false);
        $this->validateFkSet('uo_game', 'hometeam', 'uo_team', 'team_id', true);
        $this->validateFkSet('uo_game', 'visitorteam', 'uo_team', 'team_id', true);
        $this->validateFkSet('uo_game', 'reservation', 'uo_reservation', 'id', true);
        $this->validateFkSet('uo_game', 'scheduling_name_home', 'uo_scheduling_name', 'scheduling_id', true);
        $this->validateFkSet('uo_game', 'scheduling_name_visitor', 'uo_scheduling_name', 'scheduling_id', true);
        $this->validateFkSet('uo_game', 'name', 'uo_scheduling_name', 'scheduling_id', true);
        $this->validateFkSet('uo_reservation', 'season', 'uo_season', 'season_id');
        $this->validateFkSet('uo_reservation', 'location', 'uo_location', 'id', true);
        $this->validateFkSet('uo_location_info', 'location_id', 'uo_location', 'id');
        $this->validateFkSet('uo_movingtime', 'season', 'uo_season', 'season_id');
        $this->validateFkSet('uo_movingtime', 'fromlocation', 'uo_location', 'id');
        $this->validateFkSet('uo_movingtime', 'tolocation', 'uo_location', 'id');
        $this->validateFkSet('uo_team_profile', 'team_id', 'uo_team', 'team_id');
        $this->validateFkSet('uo_team_pool', 'team', 'uo_team', 'team_id');
        $this->validateFkSet('uo_team_pool', 'pool', 'uo_pool', 'pool_id');
        $this->validateFkSet('uo_game_pool', 'game', 'uo_game', 'game_id');
        $this->validateFkSet('uo_game_pool', 'pool', 'uo_pool', 'pool_id');
        $this->validateFkSet('uo_goal', 'game', 'uo_game', 'game_id');
        $this->validateFkSet('uo_goal', 'assist', 'uo_player', 'player_id', true);
        $this->validateFkSet('uo_goal', 'scorer', 'uo_player', 'player_id', true);
        $this->validateFkSet('uo_played', 'game', 'uo_game', 'game_id');
        $this->validateFkSet('uo_played', 'player', 'uo_player', 'player_id');
        $this->validateFkSet('uo_gameevent', 'game', 'uo_game', 'game_id');
        $this->validateFkSet('uo_timeout', 'game', 'uo_game', 'game_id', true);
        $this->validateFkSet('uo_spirit_timeout', 'game', 'uo_game', 'game_id', true);
        $this->validateFkSet('uo_spirit_score', 'game_id', 'uo_game', 'game_id');
        $this->validateFkSet('uo_spirit_score', 'team_id', 'uo_team', 'team_id');
        $this->validateFkSet('uo_spirit_score', 'category_id', 'uo_spirit_category', 'category_id');
        $this->validateFkSet('uo_defense', 'game', 'uo_game', 'game_id');
        $this->validateFkSet('uo_defense', 'author', 'uo_player', 'player_id', true);
        $this->validateFkSet('uo_moveteams', 'frompool', 'uo_pool', 'pool_id');
        $this->validateFkSet('uo_moveteams', 'topool', 'uo_pool', 'pool_id');
        $this->validateFkSet('uo_moveteams', 'scheduling_id', 'uo_scheduling_name', 'scheduling_id', true);
        $this->validateFkSet('uo_specialranking', 'frompool', 'uo_pool', 'pool_id');
        $this->validateFkSet('uo_specialranking', 'scheduling_id', 'uo_scheduling_name', 'scheduling_id', true);
        $this->validateFkSet('uo_season_round', 'season', 'uo_season', 'season_id');
        $this->validateFkSet('uo_season_round', 'series', 'uo_series', 'series_id');
        $this->validateFkSet('uo_season_points', 'round_id', 'uo_season_round', 'round_id');
        $this->validateFkSet('uo_season_points', 'team_id', 'uo_team', 'team_id');
        $this->validateFkSet('uo_team_final_standing', 'season', 'uo_season', 'season_id');
        $this->validateFkSet('uo_team_final_standing', 'series', 'uo_series', 'series_id');
        $this->validateFkSet('uo_team_final_standing', 'team_id', 'uo_team', 'team_id');

        foreach ($tables['uo_player'] as $player) {
            if (empty($player['profile_id']) || !$this->snapshotContains('uo_player_profile', 'profile_id', $player['profile_id'])) {
                throw new EventSnapshotException(_("Every imported player must have a player profile in the event snapshot."));
            }
        }

        $this->validateProfileIdentities($tables['uo_player_profile']);

        foreach ($tables['uo_player_profile'] as $profile) {
            $this->profileMatchId($profile, true);
        }

        foreach ($tables['uo_gameevent'] as $event) {
            if (($event['type'] ?? '') === 'media' && !empty($event['info']) && !$this->snapshotContains('uo_urls', 'url_id', $event['info'])) {
                throw new EventSnapshotException(_("The event snapshot has a media event without its media link."));
            }
        }

        foreach ($tables['uo_game'] as $game) {
            if (!empty($game['respteam']) && !$this->snapshotContains('uo_team', 'team_id', $game['respteam']) && !$this->snapshotContains('uo_scheduling_name', 'scheduling_id', $game['respteam'])) {
                throw new EventSnapshotException(_("Missing required parent row for uo_game.respteam."));
            }
        }

        foreach ($tables['uo_urls'] as $url) {
            $this->validateUrlOwner($url);
        }

        foreach ($tables['uo_comment'] as $comment) {
            $this->validateCommentOwner($comment);
        }
    }

    private function importSnapshotRows($target)
    {
        $tables = $this->snapshot['tables'];
        $this->idMap = [];

        foreach (array_keys($this->manifest()) as $table) {
            $this->idMap[$table] = [];
        }

        $this->importGlobalRows('uo_pooltemplate');
        $this->importGlobalRows('uo_spirit_category');
        $this->importLocations();
        $this->importSeason($target);
        $this->importSeries();
        $this->importSchedulingNames();
        $this->importReservations();
        $this->importMovingTimes();
        $this->importPools();
        $this->fixPoolFollowers($tables['uo_pool']);
        $this->importTeams();
        $this->importTeamProfiles();
        $this->importPlayerProfiles();
        $this->importPlayers();
        $this->importTeamPools();
        $this->importGames();
        $this->importUrls();
        $this->importGamePools();
        $this->importMoveTables();
        $this->importGoals();
        $this->importPlayed();
        $this->importGameEvents();
        $this->importSimpleGameChildRows('uo_timeout', 'timeout_id');
        $this->importSimpleGameChildRows('uo_spirit_timeout', 'spirit_timeout_id');
        $this->importSpiritScores();
        $this->importDefenses();
        $this->importSeasonRounds();
        $this->importSeasonPoints();
        $this->importFinalStandings();
        $this->importComments();

        if ($this->mode === 'new') {
            AddEditSeason($_SESSION['uid'], $target['season_id']);
            AddUserRole($_SESSION['uid'], 'seasonadmin:' . $target['season_id']);
        }
    }

    private function importGlobalRows($table)
    {
        foreach ($this->snapshot['tables'][$table] as $row) {
            $pk = $this->manifest()[$table]['pk'][0];
            $oldId = $row[$pk];
            $existingId = $this->globalExistingId($table, $row);
            if ($existingId !== null) {
                $this->idMap[$table][$oldId] = $existingId;
                continue;
            }
            $newRow = $row;
            unset($newRow[$pk]);
            $newId = $this->insertRow($table, $newRow);
            $this->idMap[$table][$oldId] = $newId;
        }
    }

    private function importLocations()
    {
        $createdLocations = [];
        foreach ($this->snapshot['tables']['uo_location'] as $row) {
            $oldId = $row['id'];
            $existingId = $this->globalExistingId('uo_location', $row);
            if ($existingId !== null) {
                $this->idMap['uo_location'][$oldId] = $existingId;
                $createdLocations[$oldId] = false;
                continue;
            }
            $newRow = $row;
            unset($newRow['id']);
            $newId = $this->insertRow('uo_location', $newRow);
            $this->idMap['uo_location'][$oldId] = $newId;
            $createdLocations[$oldId] = true;
        }

        foreach ($this->snapshot['tables']['uo_location_info'] as $row) {
            $oldLocationId = $row['location_id'];
            $row['location_id'] = $this->mapRequired('uo_location', $oldLocationId);
            if (!empty($createdLocations[$oldLocationId])) {
                $this->upsertByPk('uo_location_info', $row);
                continue;
            }
            if (!$this->rowExistsByPk('uo_location_info', $row)) {
                $this->insertRow('uo_location_info', $row);
            }
        }
    }

    private function importSeason($target)
    {
        $row = $this->snapshot['tables']['uo_season'][0];
        $oldId = $row['season_id'];
        $row['season_id'] = $target['season_id'];
        $row['name'] = $target['name'];
        $row['reg_id'] = null;
        if ($this->mode === 'new') {
            $row['iscurrent'] = 0;
            $this->insertRow('uo_season', $row);
        } else {
            $this->updateRow('uo_season', $row, sprintf("season_id='%s'", DBEscapeString($target['season_id'])));
        }
        $this->idMap['uo_season'][$oldId] = $target['season_id'];
    }

    private function importSeries()
    {
        foreach ($this->snapshot['tables']['uo_series'] as $row) {
            $oldId = $row['series_id'];
            unset($row['series_id']);
            $row['season'] = $this->targetSeasonId();
            $row['pool_template'] = $this->mapNullable('uo_pooltemplate', $row['pool_template'] ?? null);
            $newId = $this->insertRow('uo_series', $row);
            $this->idMap['uo_series'][$oldId] = $newId;
        }
    }

    private function importSchedulingNames()
    {
        foreach ($this->snapshot['tables']['uo_scheduling_name'] as $row) {
            $oldId = $row['scheduling_id'];
            unset($row['scheduling_id']);
            $newId = $this->insertRow('uo_scheduling_name', $row);
            $this->idMap['uo_scheduling_name'][$oldId] = $newId;
        }
    }

    private function importReservations()
    {
        foreach ($this->snapshot['tables']['uo_reservation'] as $row) {
            $oldId = $row['id'];
            unset($row['id']);
            $row['season'] = $this->targetSeasonId();
            $row['location'] = $this->mapNullable('uo_location', $row['location'] ?? null);
            $newId = $this->insertRow('uo_reservation', $row);
            $this->idMap['uo_reservation'][$oldId] = $newId;
        }
    }

    private function importMovingTimes()
    {
        foreach ($this->snapshot['tables']['uo_movingtime'] as $row) {
            $row['season'] = $this->targetSeasonId();
            $row['fromlocation'] = $this->mapRequired('uo_location', $row['fromlocation']);
            $row['tolocation'] = $this->mapRequired('uo_location', $row['tolocation']);
            $this->insertRow('uo_movingtime', $row);
        }
    }

    private function importPools()
    {
        foreach ($this->snapshot['tables']['uo_pool'] as $row) {
            $oldId = $row['pool_id'];
            unset($row['pool_id']);
            $row['series'] = $this->mapNullable('uo_series', $row['series'] ?? null);
            $row['follower'] = null;
            $newId = $this->insertRow('uo_pool', $row);
            $this->idMap['uo_pool'][$oldId] = $newId;
        }
    }

    private function fixPoolFollowers($rows)
    {
        foreach ($rows as $row) {
            if (empty($row['follower'])) {
                continue;
            }
            $newPool = $this->mapRequired('uo_pool', $row['pool_id']);
            $newFollower = $this->mapNullable('uo_pool', $row['follower']);
            if ($newFollower !== null) {
                DBQuery(sprintf(
                    "UPDATE uo_pool SET follower=%d WHERE pool_id=%d",
                    (int) $newFollower,
                    (int) $newPool,
                ));
            }
        }
    }

    private function importTeams()
    {
        foreach ($this->snapshot['tables']['uo_team'] as $row) {
            $oldId = $row['team_id'];
            unset($row['team_id']);
            $row['series'] = $this->mapNullable('uo_series', $row['series'] ?? null);
            $row['pool'] = $this->mapNullable('uo_pool', $row['pool'] ?? null);
            $row['country'] = $this->existingCountryOrNull($row['country'] ?? null);
            $row['club'] = null;
            $row['reg_id'] = null;
            $row['sotg_token'] = null;
            $newId = $this->insertRow('uo_team', $row);
            $this->idMap['uo_team'][$oldId] = $newId;
        }
    }

    private function importTeamProfiles()
    {
        foreach ($this->snapshot['tables']['uo_team_profile'] as $row) {
            $row['team_id'] = $this->mapRequired('uo_team', $row['team_id']);
            $row['image'] = null;
            $row['profile_image'] = null;
            $row['ffindr_id'] = null;
            $this->insertRow('uo_team_profile', $row);
        }
    }

    private function importPlayerProfiles()
    {
        foreach ($this->snapshot['tables']['uo_player_profile'] as $row) {
            $oldId = $row['profile_id'];
            $matchId = $this->profileMatchId($row, true);
            if ($matchId > 0) {
                $this->idMap['uo_player_profile'][$oldId] = $matchId;
                continue;
            }
            unset($row['profile_id']);
            $newId = $this->insertRow('uo_player_profile', $row);
            $this->idMap['uo_player_profile'][$oldId] = $newId;
        }
    }

    private function importPlayers()
    {
        foreach ($this->snapshot['tables']['uo_player'] as $row) {
            $oldId = $row['player_id'];
            unset($row['player_id']);
            $row['team'] = $this->mapRequired('uo_team', $row['team']);
            $row['profile_id'] = $this->mapRequired('uo_player_profile', $row['profile_id']);
            $row['reg_id'] = null;
            $newId = $this->insertRow('uo_player', $row);
            $this->idMap['uo_player'][$oldId] = $newId;
        }
    }

    private function importTeamPools()
    {
        foreach ($this->snapshot['tables']['uo_team_pool'] as $row) {
            $row['team'] = $this->mapRequired('uo_team', $row['team']);
            $row['pool'] = $this->mapRequired('uo_pool', $row['pool']);
            $this->insertRow('uo_team_pool', $row);
        }
    }

    private function importGames()
    {
        foreach ($this->snapshot['tables']['uo_game'] as $row) {
            $oldId = $row['game_id'];
            unset($row['game_id']);
            $row['hometeam'] = $this->mapNullable('uo_team', $row['hometeam'] ?? null);
            $row['visitorteam'] = $this->mapNullable('uo_team', $row['visitorteam'] ?? null);
            $row['reservation'] = $this->mapNullable('uo_reservation', $row['reservation'] ?? null);
            $row['scheduling_name_home'] = $this->mapNullable('uo_scheduling_name', $row['scheduling_name_home'] ?? null);
            $row['scheduling_name_visitor'] = $this->mapNullable('uo_scheduling_name', $row['scheduling_name_visitor'] ?? null);
            $row['name'] = $this->mapNullable('uo_scheduling_name', $row['name'] ?? null);
            $row['respteam'] = $this->mapGameResponsible($row, $this->snapshotRowById('uo_game', 'game_id', $oldId));
            $row['resppers'] = null;
            $newId = $this->insertRow('uo_game', $row);
            $this->idMap['uo_game'][$oldId] = $newId;
        }
    }

    private function importUrls()
    {
        foreach ($this->snapshot['tables']['uo_urls'] as $row) {
            $oldId = $row['url_id'];
            unset($row['url_id']);
            $row['owner_id'] = $this->mapUrlOwnerId($row['owner'], $row['owner_id']);
            $row['publisher_id'] = null;
            $newId = $this->insertRow('uo_urls', $row);
            $this->idMap['uo_urls'][$oldId] = $newId;
        }
    }

    private function importGamePools()
    {
        foreach ($this->snapshot['tables']['uo_game_pool'] as $row) {
            $row['game'] = $this->mapRequired('uo_game', $row['game']);
            $row['pool'] = $this->mapRequired('uo_pool', $row['pool']);
            $this->insertRow('uo_game_pool', $row);
        }
    }

    private function importMoveTables()
    {
        foreach (['uo_moveteams', 'uo_specialranking'] as $table) {
            foreach ($this->snapshot['tables'][$table] as $row) {
                $row['frompool'] = $this->mapRequired('uo_pool', $row['frompool']);
                if ($table === 'uo_moveteams') {
                    $row['topool'] = $this->mapRequired('uo_pool', $row['topool']);
                }
                $row['scheduling_id'] = $this->mapNullable('uo_scheduling_name', $row['scheduling_id'] ?? null);
                $this->insertRow($table, $row);
            }
        }
    }

    private function importGoals()
    {
        foreach ($this->snapshot['tables']['uo_goal'] as $row) {
            $row['game'] = $this->mapRequired('uo_game', $row['game']);
            $row['assist'] = $this->mapNullable('uo_player', $row['assist'] ?? null);
            $row['scorer'] = $this->mapNullable('uo_player', $row['scorer'] ?? null);
            $this->insertRow('uo_goal', $row);
        }
    }

    private function importPlayed()
    {
        foreach ($this->snapshot['tables']['uo_played'] as $row) {
            $row['game'] = $this->mapRequired('uo_game', $row['game']);
            $row['player'] = $this->mapRequired('uo_player', $row['player']);
            $this->insertRow('uo_played', $row);
        }
    }

    private function importGameEvents()
    {
        foreach ($this->snapshot['tables']['uo_gameevent'] as $row) {
            $row['game'] = $this->mapRequired('uo_game', $row['game']);
            if (($row['type'] ?? '') === 'media' && !empty($row['info'])) {
                $row['info'] = $this->mapRequired('uo_urls', $row['info']);
            }
            $this->insertRow('uo_gameevent', $row);
        }
    }

    private function importSimpleGameChildRows($table, $pk)
    {
        foreach ($this->snapshot['tables'][$table] as $row) {
            unset($row[$pk]);
            $row['game'] = $this->mapNullable('uo_game', $row['game'] ?? null);
            $this->insertRow($table, $row);
        }
    }

    private function importSpiritScores()
    {
        foreach ($this->snapshot['tables']['uo_spirit_score'] as $row) {
            $row['game_id'] = $this->mapRequired('uo_game', $row['game_id']);
            $row['team_id'] = $this->mapRequired('uo_team', $row['team_id']);
            $row['category_id'] = $this->mapRequired('uo_spirit_category', $row['category_id']);
            $this->insertRow('uo_spirit_score', $row);
        }
    }

    private function importDefenses()
    {
        foreach ($this->snapshot['tables']['uo_defense'] as $row) {
            $row['game'] = $this->mapRequired('uo_game', $row['game']);
            $row['author'] = $this->mapNullable('uo_player', $row['author'] ?? null);
            $this->insertRow('uo_defense', $row);
        }
    }

    private function importSeasonRounds()
    {
        foreach ($this->snapshot['tables']['uo_season_round'] as $row) {
            $oldId = $row['round_id'];
            unset($row['round_id']);
            $row['season'] = $this->targetSeasonId();
            $row['series'] = $this->mapRequired('uo_series', $row['series']);
            $newId = $this->insertRow('uo_season_round', $row);
            $this->idMap['uo_season_round'][$oldId] = $newId;
        }
    }

    private function importSeasonPoints()
    {
        foreach ($this->snapshot['tables']['uo_season_points'] as $row) {
            $row['round_id'] = $this->mapRequired('uo_season_round', $row['round_id']);
            $row['team_id'] = $this->mapRequired('uo_team', $row['team_id']);
            $this->insertRow('uo_season_points', $row);
        }
    }

    private function importFinalStandings()
    {
        foreach ($this->snapshot['tables']['uo_team_final_standing'] as $row) {
            $row['season'] = $this->targetSeasonId();
            $row['series'] = $this->mapRequired('uo_series', $row['series']);
            $row['team_id'] = $this->mapRequired('uo_team', $row['team_id']);
            unset($row['updated_at']);
            $this->insertRow('uo_team_final_standing', $row);
        }
    }

    private function importComments()
    {
        foreach ($this->snapshot['tables']['uo_comment'] as $row) {
            $row['id'] = $this->mapCommentOwnerId($row['type'], $row['id']);
            $this->insertRow('uo_comment', $row);
        }
    }

    private function deleteEventOwnedRows($seasonId)
    {
        $ids = $this->collectEventIds($seasonId);
        $this->deleteComments($seasonId, $ids);
        $this->deleteUrls($seasonId, $ids);

        $gameSql = $this->intList($ids['games']);
        $playerSql = $this->intList($ids['players']);
        $teamSql = $this->intList($ids['teams']);
        $poolSql = $this->intList($ids['pools']);
        $seriesSql = $this->intList($ids['series']);
        $roundSql = $this->intList($ids['rounds']);
        $schedulingSql = $this->intList($ids['scheduling']);
        $seasonSafe = DBEscapeString($seasonId);

        DBQuery("DELETE FROM uo_spirit_score WHERE game_id IN ($gameSql)");
        DBQuery("DELETE FROM uo_defense WHERE game IN ($gameSql)");
        DBQuery("DELETE FROM uo_spirit_timeout WHERE game IN ($gameSql)");
        DBQuery("DELETE FROM uo_timeout WHERE game IN ($gameSql)");
        DBQuery("DELETE FROM uo_gameevent WHERE game IN ($gameSql)");
        DBQuery("DELETE FROM uo_goal WHERE game IN ($gameSql)");
        DBQuery("DELETE FROM uo_played WHERE game IN ($gameSql) OR player IN ($playerSql)");
        DBQuery("DELETE FROM uo_game_pool WHERE game IN ($gameSql) OR pool IN ($poolSql)");
        DBQuery("DELETE FROM uo_moveteams WHERE frompool IN ($poolSql) OR topool IN ($poolSql)");
        DBQuery("DELETE FROM uo_specialranking WHERE frompool IN ($poolSql)");
        DBQuery("DELETE FROM uo_season_points WHERE round_id IN ($roundSql) OR team_id IN ($teamSql)");
        DBQuery("DELETE FROM uo_team_final_standing WHERE season='$seasonSafe' OR series IN ($seriesSql) OR team_id IN ($teamSql)");
        DBQuery("DELETE FROM uo_team_pool WHERE team IN ($teamSql) OR pool IN ($poolSql)");
        DBQuery("DELETE FROM uo_game WHERE game_id IN ($gameSql)");
        DBQuery("DELETE FROM uo_player WHERE player_id IN ($playerSql)");
        DBQuery("DELETE FROM uo_team_profile WHERE team_id IN ($teamSql)");
        DBQuery("DELETE FROM uo_team WHERE team_id IN ($teamSql)");
        DBQuery("DELETE FROM uo_pool WHERE pool_id IN ($poolSql)");
        DBQuery("DELETE FROM uo_reservation WHERE season='$seasonSafe'");
        DBQuery("DELETE FROM uo_movingtime WHERE season='$seasonSafe'");
        DBQuery("DELETE FROM uo_season_round WHERE season='$seasonSafe'");
        DBQuery("DELETE FROM uo_series WHERE season='$seasonSafe'");
        DBQuery("DELETE FROM uo_scheduling_name
            WHERE scheduling_id IN ($schedulingSql)
            AND scheduling_id NOT IN (
                SELECT referenced_scheduling_id FROM (
                    SELECT scheduling_name_home AS referenced_scheduling_id FROM uo_game WHERE scheduling_name_home IS NOT NULL
                    UNION
                    SELECT scheduling_name_visitor FROM uo_game WHERE scheduling_name_visitor IS NOT NULL
                    UNION
                    SELECT name FROM uo_game WHERE name IS NOT NULL
                    UNION
                    SELECT respteam FROM uo_game WHERE hometeam IS NULL AND respteam IS NOT NULL
                    UNION
                    SELECT scheduling_id FROM uo_moveteams WHERE scheduling_id IS NOT NULL
                    UNION
                    SELECT scheduling_id FROM uo_specialranking WHERE scheduling_id IS NOT NULL
                ) referenced_names
            )");
        DeleteSeasonStats($seasonId);
    }

    private function refreshDerivedData($seasonId)
    {
        DeleteSeasonStats($seasonId);
        if (!empty($this->snapshot['event']['stats_calculated'])) {
            CalcSeasonStats($seasonId);
            CalcSeriesStats($seasonId);
            CalcTeamStats($seasonId);
            CalcPlayerStats($seasonId);
        }
        if (function_exists('RefreshSeasonSpiritData')) {
            RefreshSeasonSpiritData($seasonId);
        }
    }

    private function clearImportCaches()
    {
        ClearSeasonRuntimeCache();
        CacheForgetNamespace('pool_moved_placings');
        CacheForgetNamespace('stats_data_available');
        CacheForgetNamespace('spirit_required_category_count');
        if (function_exists('CacheWipePersistent')) {
            CacheWipePersistent();
        }
    }

    private function requireProfileRows($tables)
    {
        foreach ($tables['uo_player'] as $player) {
            if (empty($player['profile_id'])) {
                throw new EventSnapshotException(_("Every imported player must have a player profile in the event snapshot."));
            }
        }
    }

    private function disallowedSnapshotFields()
    {
        return [
            'uo_season' => array_fill_keys(['reg_id'], true),
            'uo_team' => array_fill_keys(['club', 'reg_id', 'sotg_token'], true),
            'uo_team_profile' => array_fill_keys(['image', 'profile_image', 'ffindr_id'], true),
            'uo_player' => array_fill_keys(['reg_id'], true),
            'uo_player_profile' => array_fill_keys([
                'email',
                'national_id',
                'image',
                'profile_image',
                'birthplace',
                'story',
                'achievements',
                'info',
                'ffindr_id',
            ], true),
            'uo_game' => array_fill_keys(['resppers'], true),
            'uo_urls' => array_fill_keys(['publisher_id'], true),
            'uo_team_final_standing' => array_fill_keys(['updated_at'], true),
        ];
    }

    private function validateUrlOwner($url)
    {
        $owner = $url['owner'] ?? '';
        $ownerId = $url['owner_id'] ?? null;
        switch ($owner) {
            case 'ultiorganizer':
                if (!$this->snapshotContains('uo_season', 'season_id', $ownerId)) {
                    throw new EventSnapshotException(_("Missing required parent row for uo_urls.owner_id."));
                }
                return;
            case 'series':
                $table = 'uo_series';
                $column = 'series_id';
                break;
            case 'pool':
                $table = 'uo_pool';
                $column = 'pool_id';
                break;
            case 'team':
                $table = 'uo_team';
                $column = 'team_id';
                break;
            case 'game':
                $table = 'uo_game';
                $column = 'game_id';
                break;
            default:
                throw new EventSnapshotException(_("Unsupported event snapshot link owner."));
        }

        if ($this->isEmptyValue($ownerId) || !$this->snapshotContains($table, $column, $ownerId)) {
            throw new EventSnapshotException(_("Missing required parent row for uo_urls.owner_id."));
        }
    }

    private function validateCommentOwner($comment)
    {
        $type = (int) ($comment['type'] ?? 0);
        $ownerId = $comment['id'] ?? null;
        switch ($type) {
            case 1:
                if (!$this->snapshotContains('uo_season', 'season_id', $ownerId)) {
                    throw new EventSnapshotException(_("Missing required parent row for uo_comment.id."));
                }
                return;
            case 2:
                $table = 'uo_series';
                $column = 'series_id';
                break;
            case 3:
                $table = 'uo_pool';
                $column = 'pool_id';
                break;
            case 4:
            case 5:
            case 6:
                $table = 'uo_game';
                $column = 'game_id';
                break;
            default:
                throw new EventSnapshotException(_("Unsupported event snapshot comment type."));
        }

        if ($this->isEmptyValue($ownerId) || !$this->snapshotContains($table, $column, $ownerId)) {
            throw new EventSnapshotException(_("Missing required parent row for uo_comment.id."));
        }
    }

    private function validateProfileIdentities($profiles)
    {
        $seen = [];
        foreach ($profiles as $profile) {
            $profileId = (string) ($profile['profile_id'] ?? '');
            $accreditationId = trim((string) ($profile['accreditation_id'] ?? ''));
            if ($accreditationId !== '') {
                $key = 'accreditation:' . $accreditationId;
                if (isset($seen[$key]) && $seen[$key] !== $profileId) {
                    throw new EventSnapshotException(_("Conflicting player profile identities in the event snapshot."));
                }
                $seen[$key] = $profileId;
            }

            $firstname = trim((string) ($profile['firstname'] ?? ''));
            $lastname = trim((string) ($profile['lastname'] ?? ''));
            $birthdate = $this->datePart($profile['birthdate'] ?? '');
            if ($firstname !== '' && $lastname !== '' && $birthdate !== '') {
                $key = 'birthdate:' . $firstname . '|' . $lastname . '|' . $birthdate;
                if (isset($seen[$key]) && $seen[$key] !== $profileId) {
                    throw new EventSnapshotException(_("Conflicting player profile identities in the event snapshot."));
                }
                $seen[$key] = $profileId;
            }
        }
    }

    private function profileMatchId($profile, $strict)
    {
        $conditions = [];
        $accreditationId = trim((string) ($profile['accreditation_id'] ?? ''));
        if ($accreditationId !== '') {
            $conditions[] = sprintf("accreditation_id='%s'", DBEscapeString($accreditationId));
        }

        $firstname = trim((string) ($profile['firstname'] ?? ''));
        $lastname = trim((string) ($profile['lastname'] ?? ''));
        $birthdate = $this->datePart($profile['birthdate'] ?? '');
        if ($firstname !== '' && $lastname !== '' && $birthdate !== '') {
            $conditions[] = sprintf(
                "firstname='%s' AND lastname='%s' AND DATE(birthdate)='%s'",
                DBEscapeString($firstname),
                DBEscapeString($lastname),
                DBEscapeString($birthdate),
            );
        }

        $matches = [];
        foreach ($conditions as $condition) {
            $rows = DBQueryToArray("SELECT profile_id FROM uo_player_profile WHERE $condition ORDER BY profile_id", true);
            foreach ($rows as $row) {
                $matches[(int) $row['profile_id']] = true;
            }
        }

        if (count($matches) > 1 && $strict) {
            throw new EventSnapshotException(sprintf(
                _("Ambiguous player profile match for %s %s."),
                $firstname,
                $lastname,
            ));
        }
        if (count($matches) === 1) {
            return (int) array_key_first($matches);
        }
        return 0;
    }

    private function validateFkSet($table, $column, $parentTable, $parentColumn, $nullable = false)
    {
        foreach ($this->snapshot['tables'][$table] as $row) {
            $value = $row[$column] ?? null;
            if ($this->isEmptyValue($value)) {
                if ($nullable) {
                    continue;
                }
                throw new EventSnapshotException(sprintf(_("Missing required reference %s.%s."), $table, $column));
            }
            if (!$this->snapshotContains($parentTable, $parentColumn, $value)) {
                throw new EventSnapshotException(sprintf(_("Missing required parent row for %s.%s."), $table, $column));
            }
        }
    }

    private function snapshotContains($table, $column, $value)
    {
        foreach ($this->snapshot['tables'][$table] ?? [] as $row) {
            if ((string) ($row[$column] ?? '') === (string) $value) {
                return true;
            }
        }
        return false;
    }

    private function snapshotRowById($table, $column, $value)
    {
        foreach ($this->snapshot['tables'][$table] ?? [] as $row) {
            if ((string) ($row[$column] ?? '') === (string) $value) {
                return $row;
            }
        }
        return [];
    }

    private function mapRequired($table, $oldValue)
    {
        if ($this->isEmptyValue($oldValue) || !isset($this->idMap[$table][$oldValue])) {
            throw new EventSnapshotException(sprintf(_("Missing required imported row in %s."), $table));
        }
        return $this->idMap[$table][$oldValue];
    }

    private function mapNullable($table, $oldValue)
    {
        if ($this->isEmptyValue($oldValue)) {
            return null;
        }
        return $this->idMap[$table][$oldValue] ?? null;
    }

    private function mapGameResponsible($newRow, $oldRow)
    {
        $oldResp = $oldRow['respteam'] ?? null;
        if ($this->isEmptyValue($oldResp)) {
            return null;
        }
        if (empty($oldRow['hometeam']) && isset($this->idMap['uo_scheduling_name'][$oldResp])) {
            return $this->idMap['uo_scheduling_name'][$oldResp];
        }
        if (isset($this->idMap['uo_team'][$oldResp])) {
            return $this->idMap['uo_team'][$oldResp];
        }
        if (isset($this->idMap['uo_scheduling_name'][$oldResp])) {
            return $this->idMap['uo_scheduling_name'][$oldResp];
        }
        return null;
    }

    private function mapUrlOwnerId($owner, $ownerId)
    {
        switch ($owner) {
            case 'ultiorganizer':
                return $this->targetSeasonId();
            case 'series':
                return $this->mapRequired('uo_series', $ownerId);
            case 'pool':
                return $this->mapRequired('uo_pool', $ownerId);
            case 'team':
                return $this->mapRequired('uo_team', $ownerId);
            case 'game':
                return $this->mapRequired('uo_game', $ownerId);
            default:
                throw new EventSnapshotException(_("Unsupported event snapshot link owner."));
        }
    }

    private function mapCommentOwnerId($type, $ownerId)
    {
        switch ((int) $type) {
            case 1:
                return $this->targetSeasonId();
            case 2:
                return $this->mapRequired('uo_series', $ownerId);
            case 3:
                return $this->mapRequired('uo_pool', $ownerId);
            case 4:
            case 5:
            case 6:
                return $this->mapRequired('uo_game', $ownerId);
            default:
                throw new EventSnapshotException(_("Unsupported event snapshot comment type."));
        }
    }

    private function existingCountryOrNull($countryId)
    {
        if ($this->isEmptyValue($countryId)) {
            return null;
        }
        $exists = DBQueryToValue(sprintf(
            "SELECT 1 FROM uo_country WHERE country_id=%d LIMIT 1",
            (int) $countryId,
        ));
        if ($exists) {
            return (int) $countryId;
        }
        $this->warnings[] = sprintf(_("Country %d is not present in the target installation and was cleared."), (int) $countryId);
        return null;
    }

    private function globalExistingId($table, $row)
    {
        $pk = $this->manifest()[$table]['pk'][0];
        $columns = GetTableColumns($table);
        $conditions = [];
        foreach ($row as $field => $value) {
            $field = strtolower((string) $field);
            if ($field === $pk || !isset($columns[$field])) {
                continue;
            }
            if ($value === null) {
                $conditions[] = $this->identifier($field) . " IS NULL";
            } else {
                $conditions[] = $this->identifier($field) . "=" . $this->sqlValue($value);
            }
        }
        if (empty($conditions)) {
            return null;
        }

        $existing = DBQueryToValue(sprintf(
            "SELECT %s FROM %s WHERE %s LIMIT 1",
            $this->identifier($pk),
            $this->identifier($table),
            implode(" AND ", $conditions),
        ));
        return $existing === null ? null : (int) $existing;
    }

    private function insertRow($table, $row)
    {
        $columns = GetTableColumns($table);
        $fields = [];
        $values = [];
        foreach ($row as $field => $value) {
            $field = strtolower((string) $field);
            if (!isset($columns[$field])) {
                continue;
            }
            $fields[] = $this->identifier($field);
            $values[] = $this->sqlValue($value);
        }
        if (empty($fields)) {
            throw new EventSnapshotException(sprintf(_("No importable columns for %s."), $table));
        }

        $query = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->identifier($table),
            implode(",", $fields),
            implode(",", $values),
        );
        return DBQueryInsert($query);
    }

    private function updateRow($table, $row, $condition)
    {
        $columns = GetTableColumns($table);
        $assignments = [];
        foreach ($row as $field => $value) {
            $field = strtolower((string) $field);
            if (!isset($columns[$field])) {
                continue;
            }
            $assignments[] = $this->identifier($field) . "=" . $this->sqlValue($value);
        }
        if (empty($assignments)) {
            return;
        }
        DBQuery(sprintf(
            "UPDATE %s SET %s WHERE %s",
            $this->identifier($table),
            implode(",", $assignments),
            $condition,
        ));
    }

    private function upsertByPk($table, $row)
    {
        $conditions = $this->pkConditions($table, $row);
        if ($this->rowExistsByPk($table, $row)) {
            $this->updateRow($table, $row, implode(" AND ", $conditions));
        } else {
            $this->insertRow($table, $row);
        }
    }

    private function rowExistsByPk($table, $row)
    {
        $conditions = $this->pkConditions($table, $row);
        $exists = DBQueryToValue(sprintf(
            "SELECT 1 FROM %s WHERE %s LIMIT 1",
            $this->identifier($table),
            implode(" AND ", $conditions),
        ));
        return !empty($exists);
    }

    private function pkConditions($table, $row)
    {
        $conditions = [];
        foreach ($this->manifest()[$table]['pk'] as $field) {
            $conditions[] = $this->identifier($field) . "=" . $this->sqlValue($row[$field]);
        }
        return $conditions;
    }

    private function selectRows($query, $exclude = [])
    {
        $rows = DBQueryToArray($query, true);
        $exclude = array_flip($exclude);
        foreach ($rows as $idx => $row) {
            foreach ($exclude as $field => $_) {
                unset($rows[$idx][$field]);
            }
        }
        return $rows;
    }

    private function exportComments($seasonId, $ids)
    {
        $conditions = [sprintf("(type=1 AND id='%s')", DBEscapeString($seasonId))];
        if (!empty($ids['series'])) {
            $conditions[] = "type=2 AND id IN (" . $this->quotedList($ids['series']) . ")";
        }
        if (!empty($ids['pools'])) {
            $conditions[] = "type=3 AND id IN (" . $this->quotedList($ids['pools']) . ")";
        }
        if (!empty($ids['games'])) {
            $conditions[] = "type IN (4,5,6) AND id IN (" . $this->quotedList($ids['games']) . ")";
        }
        return DBQueryToArray("SELECT * FROM uo_comment WHERE " . implode(" OR ", array_map(fn($c) => "(" . $c . ")", $conditions)) . " ORDER BY type, id", true);
    }

    private function deleteComments($seasonId, $ids)
    {
        $conditions = [sprintf("(type=1 AND id='%s')", DBEscapeString($seasonId))];
        if (!empty($ids['series'])) {
            $conditions[] = "type=2 AND id IN (" . $this->quotedList($ids['series']) . ")";
        }
        if (!empty($ids['pools'])) {
            $conditions[] = "type=3 AND id IN (" . $this->quotedList($ids['pools']) . ")";
        }
        if (!empty($ids['games'])) {
            $conditions[] = "type IN (4,5,6) AND id IN (" . $this->quotedList($ids['games']) . ")";
        }
        DBQuery("DELETE FROM uo_comment WHERE " . implode(" OR ", array_map(fn($c) => "(" . $c . ")", $conditions)));
    }

    private function deleteUrls($seasonId, $ids)
    {
        $where = $this->urlOwnerWhere($seasonId, $ids['series'], $ids['pools'], $ids['teams'], $ids['games']);
        if ($where !== '') {
            DBQuery("DELETE FROM uo_urls WHERE $where");
        }
    }

    private function urlOwnerWhere($seasonId, $series, $pools, $teams, $games)
    {
        $conditions = [sprintf("(owner='ultiorganizer' AND owner_id='%s')", DBEscapeString($seasonId))];
        if (!empty($series)) {
            $conditions[] = "owner='series' AND owner_id IN (" . $this->quotedList($series) . ")";
        }
        if (!empty($pools)) {
            $conditions[] = "owner='pool' AND owner_id IN (" . $this->quotedList($pools) . ")";
        }
        if (!empty($teams)) {
            $conditions[] = "owner='team' AND owner_id IN (" . $this->quotedList($teams) . ")";
        }
        if (!empty($games)) {
            $conditions[] = "owner='game' AND owner_id IN (" . $this->quotedList($games) . ")";
        }
        return implode(" OR ", array_map(fn($c) => "(" . $c . ")", $conditions));
    }

    private function uniqueSeasonIdentity($sourceId, $sourceName)
    {
        $baseId = preg_replace('/[^A-Za-z0-9_]/', '', (string) $sourceId);
        if ($baseId === '') {
            $baseId = 'event';
        }
        $baseId = substr($baseId, 0, 10);
        $baseName = $sourceName === '' ? $baseId : $sourceName;

        $candidateId = $baseId;
        $candidateName = $baseName;
        $max = 1;
        while (SeasonExists($candidateId) || SeasonNameExists($candidateName)) {
            $modifier = rand(1, ++$max);
            $candidateId = substr($baseId, 0, 7) . "_" . $modifier;
            $candidateName = $baseName . " (" . $modifier . ")";
        }

        return ['season_id' => $candidateId, 'name' => $candidateName];
    }

    private function targetSeasonId()
    {
        return $this->idMap['uo_season'][$this->snapshot['tables']['uo_season'][0]['season_id']];
    }

    private function rowKey($row, $fields)
    {
        $pieces = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $row)) {
                return null;
            }
            $pieces[] = (string) $row[$field];
        }
        return implode("\0", $pieces);
    }

    private function columnValues($rows, $column)
    {
        $values = [];
        foreach ($rows as $row) {
            if (isset($row[$column]) && $row[$column] !== '') {
                $values[] = (int) $row[$column];
            }
        }
        return $this->uniqueInts($values);
    }

    private function uniqueInts($values)
    {
        $ret = [];
        foreach ($values as $value) {
            $value = (int) $value;
            if ($value > 0) {
                $ret[$value] = $value;
            }
        }
        return array_values($ret);
    }

    private function intList($values)
    {
        $values = $this->uniqueInts($values);
        if (empty($values)) {
            return "0";
        }
        return implode(",", $values);
    }

    private function quotedList($values)
    {
        $quoted = [];
        foreach ($values as $value) {
            $quoted[] = "'" . DBEscapeString($value) . "'";
        }
        if (empty($quoted)) {
            return "''";
        }
        return implode(",", $quoted);
    }

    private function identifier($identifier)
    {
        return "`" . str_replace("`", "``", (string) $identifier) . "`";
    }

    private function sqlValue($value)
    {
        if ($value === null || $value === "NULL") {
            return "NULL";
        }
        return "'" . DBEscapeString($value) . "'";
    }

    private function isEmptyValue($value)
    {
        return $value === null || $value === '' || $value === 'NULL' || (is_numeric($value) && (int) $value === 0);
    }

    private function datePart($value)
    {
        $value = trim((string) $value);
        if ($value === '' || isEmptyDate($value)) {
            return '';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }
        return date('Y-m-d', $timestamp);
    }
}
