<?php

include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/gamehistory.functions.php';
include_once $include_prefix . 'lib/configuration.functions.php';
include_once $include_prefix . 'lib/team.functions.php';

/**
 * Pair two snapshot row lists by $keyField: saved order first, rows that exist
 * only in the current scoresheet appended.
 */
function GameHistoryStatePairs($saved, $current, $keyField)
{
    $pairs = [];
    foreach ($saved as $row) {
        $pairs[(string) ($row[$keyField] ?? "")] = ['saved' => $row, 'current' => null];
    }
    foreach ($current as $row) {
        $key = (string) ($row[$keyField] ?? "");
        if (isset($pairs[$key])) {
            $pairs[$key]['current'] = $row;
        } else {
            $pairs[$key] = ['saved' => null, 'current' => $row];
        }
    }
    return array_values($pairs);
}

/**
 * '-' only in the saved state, '+' only in the current scoresheet, '*' in both
 * but differing, '' identical. Only displayed fields are compared, so a marked
 * row always shows what the mark is about.
 */
function GameHistoryStateMark($pair, $fields)
{
    if ($pair['saved'] === null) {
        return "+";
    }
    if ($pair['current'] === null) {
        return "-";
    }
    foreach ($fields as $field) {
        if ((string) ($pair['saved'][$field] ?? "") !== (string) ($pair['current'][$field] ?? "")) {
            return "*";
        }
    }
    return "";
}

function GameHistoryStateRow($mark, $cells, $marked)
{
    $html = "<tr class='admintablerow" . ($mark === "" ? "" : " state-diff") . "'>";
    if ($marked) {
        $html .= "<td class='state-mark'>" . $mark . "</td>";
    }
    return $html . $cells . "</tr>\n";
}

/**
 * Cell contents for a compared value: the saved one, noting the current one
 * when the two differ. A row present on one side only shows that side's value.
 * $placeholder stands in for an empty value where a blank cell would read as a
 * rendering fault rather than as an unset field. It is a word rather than a
 * dash, which the legend above already gives a meaning of its own.
 */
function GameHistoryStateCell($savedText, $currentText, $hasSaved = true, $hasCurrent = true, $placeholder = "")
{
    if (!$hasSaved) {
        return utf8entities($currentText === "" ? $placeholder : $currentText);
    }
    $text = utf8entities($savedText === "" ? $placeholder : $savedText);
    if ($hasCurrent && $savedText !== $currentText) {
        $current = $currentText === "" ? _("None") : $currentText;
        $text .= " <em>(" . sprintf(_("now %s"), utf8entities($current)) . ")</em>";
    }
    return $text;
}

function GameHistoryStatePlayerText($number, $name)
{
    return trim(($number === null ? "" : $number) . " " . ($name === null ? "" : $name));
}

function GameHistoryStateGoalCells($row)
{
    if (!is_array($row)) {
        return ['num' => "", 'time' => "", 'score' => "", 'assist' => "", 'scorer' => ""];
    }
    return [
        'num' => (string) ($row['num'] ?? ""),
        'time' => empty($row['time']) ? "" : SecToMin($row['time']),
        'score' => ($row['homescore'] ?? 0) . "-" . ($row['visitorscore'] ?? 0),
        'assist' => !empty($row['iscallahan'])
            ? _("Callahan")
            : GameHistoryStatePlayerText($row['assist_num'] ?? null, $row['assist_name'] ?? null),
        'scorer' => GameHistoryStatePlayerText($row['scorer_num'] ?? null, $row['scorer_name'] ?? null),
    ];
}

function GameHistoryStatePlayerCells($row)
{
    if (!is_array($row)) {
        return ['num' => "", 'name' => "", 'roles' => ""];
    }
    $roles = [];
    if (!empty($row['captain'])) {
        $roles[] = _("Captain");
    }
    if (!empty($row['spirit_captain'])) {
        $roles[] = _("Spirit captain");
    }
    return [
        'num' => (string) ($row['num'] ?? ""),
        'name' => (string) ($row['name'] ?? ""),
        'roles' => implode(", ", $roles),
    ];
}

function GameHistoryStateDefenseCells($row, $names, $homeTeam, $visitorTeam)
{
    if (!is_array($row)) {
        return ['team' => "", 'time' => "", 'player' => "", 'result' => ""];
    }
    $result = empty($row['iscaught']) ? _("Touched") : _("Caught");
    if (!empty($row['iscallahan'])) {
        $result .= ", " . _("Callahan");
    }
    return [
        'team' => empty($row['ishomedefense']) ? $visitorTeam : $homeTeam,
        'time' => empty($row['time']) ? "" : SecToMin($row['time']),
        'player' => $names[(int) ($row['author'] ?? 0)] ?? "",
        'result' => $result,
    ];
}

/** Jersey number and name per player id, from both compared states. */
function GameHistoryStatePlayerNames($states)
{
    $names = [];
    foreach ($states as $state) {
        foreach ($state['played'] ?? [] as $row) {
            $names[(int) $row['player']] = GameHistoryStatePlayerText($row['num'] ?? null, $row['name'] ?? null);
        }
    }
    return $names;
}

/** Stoppage times of one side, in the order they were recorded. */
function GameHistoryStateStoppages($rows, $isHome)
{
    $times = [];
    foreach ($rows as $row) {
        if ((int) ($row['ishome'] ?? 0) === ($isHome ? 1 : 0)) {
            $times[] = SecToMin($row['time'] ?? 0);
        }
    }
    return implode(", ", $times);
}

/** The one event of each type, keyed by type. */
function GameHistoryStateEvents($state)
{
    $events = [];
    foreach ($state['events'] ?? [] as $event) {
        $events[(string) ($event['type'] ?? "")] = $event;
    }
    return $events;
}

if (empty($_GET["game"])) {
    showPage(_("History"), "<p class='warning'>" . _("Game not found") . ".</p>");
    return;
}

$gameId = intval($_GET["game"]);

if (!hasEditGameEventsRight($gameId)) {
    die('Insufficient rights to edit game');
}

$game_result = GameInfo($gameId);
$seasoninfo = SeasonInfo($game_result['season']);

$LAYOUT_ID = GAMEHISTORY;
$title = _("History");
$html = "";
$feedback = "";

$canRestore = hasRestoreGameHistoryRight($gameId);

if (!empty($_POST['restore']) && !empty($_POST['history_id'])) {
    $historyId = intval($_POST['history_id']);
    $restoreEntry = GameHistoryEntry($historyId);
    if (!$canRestore) {
        $feedback .= "<p class='warning'>" . _("Insufficient rights.") . "</p>";
    } elseif ($restoreEntry === null || (int) $restoreEntry['game'] !== $gameId) {
        $feedback .= "<p class='warning'>" . _("Restore failed") . ".</p>";
    } else {
        $outcome = GameHistoryRestore($historyId);
        if ($outcome['restored']) {
            $feedback .= "<p>" . _("Restored") . ".</p>";
        } else {
            $feedback .= "<p class='warning'>" . _("Restore failed") . ".</p>";
        }
        // Also rendered for a refusal, whose warning is the only thing saying
        // why the restore did not happen.
        foreach ($outcome['warnings'] as $warning) {
            $feedback .= "<p class='warning'>" . utf8entities($warning) . "</p>";
        }
    }
}

$viewEntry = null;
if (!empty($_GET['entry'])) {
    $entryCandidate = GameHistoryEntry(intval($_GET['entry']));
    if ($entryCandidate !== null && (int) $entryCandidate['game'] === $gameId) {
        $viewEntry = $entryCandidate;
    }
}

pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$menutabs[_("Result")] = "?view=user/addresult&game=$gameId";
$menutabs[_("Players")] = "?view=user/addplayerlists&game=$gameId";
$menutabs[_("Scoresheet")] = "?view=user/addscoresheet&game=$gameId";
if (!empty($seasoninfo['spiritmode'])) {
    $spiritUrl = SpiritEntryUrl($gameId);
    if (!empty($spiritUrl)) {
        $menutabs[_("Spirit score")] = $spiritUrl;
    }
}
if (ShowDefenseStats()) {
    $menutabs[_("Defence sheet")] = "?view=user/adddefensesheet&game=$gameId";
}
pageMenu($menutabs);

$html .= $feedback;

$count = GameHistoryCount($gameId);
$rows = GameHistoryList($gameId, 200);

if ($count === 0) {
    $html .= "<p>" . _("No changes recorded") . ".</p>";
} else {
    $html .= "<table class='admintable'>\n<tr>";
    $html .= "<th style='width:15%'>" . _("Time") . "</th>";
    $html .= "<th style='width:15%'>" . _("User") . "</th>";
    $html .= "<th style='width:10%'>" . _("Source") . "</th>";
    $html .= "<th style='width:40%'>" . _("Description") . "</th>";
    $html .= "<th style='width:20%'></th>";
    $html .= "</tr>\n";

    $confirmText = htmlspecialchars(addslashes(_("This overwrites the current scoresheet with this saved version.")), ENT_QUOTES);

    foreach ($rows as $row) {
        $html .= "<tr class='admintablerow'>";
        $html .= "<td>" . utf8entities(DefTimeFormat($row['time'])) . "</td>";
        $html .= "<td>" . utf8entities($row['user_id']) . "</td>";
        $html .= "<td>" . utf8entities($row['source']) . "</td>";
        $html .= "<td>" . utf8entities(GameHistoryFormatDetail($row)) . "</td>";
        $html .= "<td>";
        if (!empty($row['has_snapshot'])) {
            $html .= "<a href='?view=user/gamehistory&amp;game=$gameId&amp;entry="
                . intval($row['history_id']) . "'>" . _("Show") . "</a> ";
            if ($canRestore) {
                $html .= "<form method='post' style='display:inline'>";
                $html .= "<input type='hidden' name='history_id' value='" . intval($row['history_id']) . "'/>";
                $html .= "<input type='submit' name='restore' value='" . _("Restore this version")
                    . "' onclick='return confirm(\"" . $confirmText . "\");'/>";
                $html .= "</form>";
            }
        }
        $html .= "</td></tr>\n";
    }
    $html .= "</table>\n";

    if ($count > count($rows)) {
        $html .= "<p>" . sprintf(_("Showing the most recent %d of %d changes."), count($rows), $count) . "</p>";
    }
}

if ($viewEntry !== null && is_array($viewEntry['snapshot'])) {
    $saved = $viewEntry['snapshot'];
    $current = GameHistoryBuildSnapshot($gameId);
    $savedGame = $saved['game'] ?? [];
    $currentGame = $current['game'] ?? [];
    $savedEvents = GameHistoryStateEvents($saved);
    $currentEvents = GameHistoryStateEvents($current);
    $homeTeam = (string) $game_result['hometeamname'];
    $visitorTeam = (string) $game_result['visitorteamname'];
    $diffs = 0;
    $state = "";

    $fields = [];
    $fields[] = [
        _("Result"),
        ($savedGame['homescore'] ?? 0) . " - " . ($savedGame['visitorscore'] ?? 0),
        ($currentGame['homescore'] ?? 0) . " - " . ($currentGame['visitorscore'] ?? 0),
    ];
    $fields[] = [
        _("Halftime ended at"),
        empty($savedGame['halftime']) ? "" : SecToMin($savedGame['halftime']),
        empty($currentGame['halftime']) ? "" : SecToMin($currentGame['halftime']),
    ];
    $fields[] = [
        _("Starting offensive team"),
        isset($savedEvents['offence'])
            ? (empty($savedEvents['offence']['ishome']) ? $visitorTeam : $homeTeam) : "",
        isset($currentEvents['offence'])
            ? (empty($currentEvents['offence']['ishome']) ? $visitorTeam : $homeTeam) : "",
    ];
    foreach (['half_cap' => _("Halftime cap"), 'time_cap' => _("Time cap")] as $capType => $capLabel) {
        $savedCap = isset($savedEvents[$capType]) ? SecToMin($savedEvents[$capType]['time'] ?? 0) : "";
        $currentCap = isset($currentEvents[$capType]) ? SecToMin($currentEvents[$capType]['time'] ?? 0) : "";
        if ($savedCap !== "" || $currentCap !== "") {
            $fields[] = [$capLabel, $savedCap, $currentCap];
        }
    }
    if (!empty($savedGame['forfeit']) || !empty($currentGame['forfeit'])) {
        $fields[] = [
            _("Forfeit"),
            empty($savedGame['forfeit']) ? _("No") : _("Yes"),
            empty($currentGame['forfeit']) ? _("No") : _("Yes"),
        ];
    }
    // The defense counts arrived in snapshot format v2; an older snapshot has
    // no value to compare rather than a zero.
    $partial = !array_key_exists('homedefenses', $savedGame);
    if (!$partial) {
        $fields[] = [
            _("Defences"),
            ($savedGame['homedefenses'] ?? 0) . " - " . ($savedGame['visitordefenses'] ?? 0),
            ($currentGame['homedefenses'] ?? 0) . " - " . ($currentGame['visitordefenses'] ?? 0),
        ];
    }
    $fields[] = [
        _("Scorekeeper(s)"),
        (string) ($savedGame['official'] ?? ""),
        (string) ($currentGame['official'] ?? ""),
    ];
    $fields[] = [
        _("Comment"),
        (string) ($saved['comment'] ?? ""),
        (string) ($current['comment'] ?? ""),
    ];

    $fieldMarks = 0;
    foreach ($fields as $field) {
        if ($field[1] !== $field[2]) {
            $fieldMarks++;
        }
    }
    $diffs += $fieldMarks;

    $state .= "<table class='admintable'>\n";
    foreach ($fields as $field) {
        $cells = "<td style='width:30%'>" . $field[0] . "</td>";
        $cells .= "<td>" . nl2br(GameHistoryStateCell($field[1], $field[2], true, true, _("None"))) . "</td>";
        $state .= GameHistoryStateRow($field[1] === $field[2] ? "" : "*", $cells, $fieldMarks > 0);
    }
    $state .= "</table>\n";

    $goalPairs = GameHistoryStatePairs($saved['goals'] ?? [], $current['goals'] ?? [], 'num');
    if (count($goalPairs) > 0) {
        $goalFields = ['time', 'homescore', 'visitorscore', 'assist_num', 'assist_name',
            'scorer_num', 'scorer_name', 'iscallahan'];
        $marks = [];
        $marked = false;
        foreach ($goalPairs as $i => $pair) {
            $marks[$i] = GameHistoryStateMark($pair, $goalFields);
            if ($marks[$i] !== "") {
                $marked = true;
                $diffs++;
            }
        }

        $state .= "<h3>" . _("Points") . "</h3>\n<table class='admintable'>\n<tr>";
        if ($marked) {
            $state .= "<th class='state-mark'></th>";
        }
        $state .= "<th style='width:10%'>" . _("Point") . "</th><th style='width:10%'>" . _("Time")
            . "</th><th style='width:10%'>" . _("Score") . "</th><th style='width:35%'>" . _("Assist")
            . "</th><th style='width:35%'>" . _("Scorer") . "</th></tr>\n";
        foreach ($goalPairs as $i => $pair) {
            $savedCells = GameHistoryStateGoalCells($pair['saved']);
            $currentCells = GameHistoryStateGoalCells($pair['current']);
            $cells = "";
            foreach (['num', 'time', 'score', 'assist', 'scorer'] as $column) {
                $cells .= "<td>" . GameHistoryStateCell(
                    $savedCells[$column],
                    $currentCells[$column],
                    $pair['saved'] !== null,
                    $pair['current'] !== null,
                ) . "</td>";
            }
            $state .= GameHistoryStateRow($marks[$i], $cells, $marked);
        }
        $state .= "</table>\n";
    }

    $playedPairs = GameHistoryStatePairs($saved['played'] ?? [], $current['played'] ?? [], 'player');
    if (count($playedPairs) > 0) {
        $playedFields = ['num', 'name', 'captain', 'spirit_captain'];
        $teams = [];
        foreach ($playedPairs as $pair) {
            $row = $pair['saved'] ?? $pair['current'];
            $teams[(int) ($row['team'] ?? 0)][] = $pair;
        }
        $teamOrder = [];
        foreach ([(int) $game_result['hometeam'], (int) $game_result['visitorteam']] as $teamId) {
            if (isset($teams[$teamId])) {
                $teamOrder[] = $teamId;
            }
        }
        foreach (array_keys($teams) as $teamId) {
            if (!in_array($teamId, $teamOrder, true)) {
                $teamOrder[] = $teamId;
            }
        }

        $marks = [];
        $marked = false;
        foreach ($teams as $teamId => $pairs) {
            foreach ($pairs as $i => $pair) {
                $marks[$teamId][$i] = GameHistoryStateMark($pair, $playedFields);
                if ($marks[$teamId][$i] !== "") {
                    $marked = true;
                    $diffs++;
                }
            }
        }

        $state .= "<h3>" . _("Players") . "</h3>\n";
        foreach ($teamOrder as $teamId) {
            if ($teamId === (int) $game_result['hometeam']) {
                $teamName = $homeTeam;
            } elseif ($teamId === (int) $game_result['visitorteam']) {
                $teamName = $visitorTeam;
            } else {
                $teamName = TeamName($teamId);
            }

            $state .= "<table class='admintable'>\n";
            $state .= "<tr><th colspan='" . ($marked ? 4 : 3) . "'>" . utf8entities($teamName) . "</th></tr>\n<tr>";
            if ($marked) {
                $state .= "<th class='state-mark'></th>";
            }
            $state .= "<th style='width:10%'>" . _("Number") . "</th><th style='width:45%'>" . _("Player")
                . "</th><th></th></tr>\n";
            foreach ($teams[$teamId] as $i => $pair) {
                $savedCells = GameHistoryStatePlayerCells($pair['saved']);
                $currentCells = GameHistoryStatePlayerCells($pair['current']);
                $cells = "";
                foreach (['num', 'name', 'roles'] as $column) {
                    $cells .= "<td>" . GameHistoryStateCell(
                        $savedCells[$column],
                        $currentCells[$column],
                        $pair['saved'] !== null,
                        $pair['current'] !== null,
                    ) . "</td>";
                }
                $state .= GameHistoryStateRow($marks[$teamId][$i], $cells, $marked);
            }
            $state .= "</table>\n";
        }
    }

    $defensePairs = GameHistoryStatePairs($saved['defenses'] ?? [], $current['defenses'] ?? [], 'num');
    if (count($defensePairs) > 0) {
        $defenseFields = ['time', 'author', 'iscaught', 'iscallahan', 'ishomedefense'];
        $names = GameHistoryStatePlayerNames([$saved, $current]);
        $marks = [];
        $marked = false;
        foreach ($defensePairs as $i => $pair) {
            $marks[$i] = GameHistoryStateMark($pair, $defenseFields);
            if ($marks[$i] !== "") {
                $marked = true;
                $diffs++;
            }
        }

        $state .= "<h3>" . _("Defences") . "</h3>\n<table class='admintable'>\n<tr>";
        if ($marked) {
            $state .= "<th class='state-mark'></th>";
        }
        $state .= "<th style='width:30%'>" . _("Team") . "</th><th style='width:10%'>" . _("Time")
            . "</th><th style='width:35%'>" . _("Player") . "</th><th></th></tr>\n";
        foreach ($defensePairs as $i => $pair) {
            $savedCells = GameHistoryStateDefenseCells($pair['saved'], $names, $homeTeam, $visitorTeam);
            $currentCells = GameHistoryStateDefenseCells($pair['current'], $names, $homeTeam, $visitorTeam);
            $cells = "";
            foreach (['team', 'time', 'player', 'result'] as $column) {
                $cells .= "<td>" . GameHistoryStateCell(
                    $savedCells[$column],
                    $currentCells[$column],
                    $pair['saved'] !== null,
                    $pair['current'] !== null,
                ) . "</td>";
            }
            $state .= GameHistoryStateRow($marks[$i], $cells, $marked);
        }
        $state .= "</table>\n";
    }

    foreach (['timeouts' => _("Timeouts"), 'spirit_timeouts' => _("Spirit stoppages")] as $key => $label) {
        if (count($saved[$key] ?? []) === 0 && count($current[$key] ?? []) === 0) {
            continue;
        }
        $sides = [];
        foreach ([true, false] as $isHome) {
            $sides[] = [
                $isHome ? $homeTeam : $visitorTeam,
                GameHistoryStateStoppages($saved[$key] ?? [], $isHome),
                GameHistoryStateStoppages($current[$key] ?? [], $isHome),
            ];
        }
        $marked = false;
        foreach ($sides as $side) {
            if ($side[1] !== $side[2]) {
                $marked = true;
                $diffs++;
            }
        }

        $state .= "<table class='admintable'>\n";
        $state .= "<tr><th colspan='" . ($marked ? 3 : 2) . "'>" . $label . "</th></tr>\n";
        foreach ($sides as $side) {
            $cells = "<td style='width:30%'>" . utf8entities($side[0]) . "</td>";
            $cells .= "<td>" . GameHistoryStateCell($side[1], $side[2], true, true, _("None")) . "</td>";
            $state .= GameHistoryStateRow($side[1] === $side[2] ? "" : "*", $cells, $marked);
        }
        $state .= "</table>\n";
    }

    $html .= "<h2>" . _("Saved state") . "</h2>\n";
    if ($diffs === 0) {
        $html .= "<p>" . _("Identical to the current scoresheet") . ".</p>\n";
    } else {
        $html .= "<p>" . sprintf(_("Differences from the current scoresheet: %d"), $diffs) . "</p>\n";
        $html .= "<p class='table-legend'>*: " . _("Differs") . " | -: " . _("Only in the saved state")
            . " | +: " . _("Only in the current scoresheet") . "</p>\n";
    }
    if ($partial) {
        $html .= "<p>" . _("Some fields were not captured in this snapshot format") . ".</p>\n";
    }
    $html .= $state;
} elseif ($viewEntry !== null && !empty($viewEntry['fixture_mismatch'])) {
    // GameHistoryEntry() withholds the snapshot itself, so without this the
    // "Show" link would render an empty page with no explanation.
    $html .= "<p class='warning'>"
        . _("This saved state is not shown: the home and away teams have changed since it was taken.")
        . "</p>\n";
} elseif ($viewEntry !== null && !empty($viewEntry['has_snapshot'])) {
    $html .= "<p class='warning'>" . _("This saved state could not be read") . ".</p>\n";
}

echo $html;

//common end
contentEnd();
pageEnd();
