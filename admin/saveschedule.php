<?php

include_once __DIR__ . '/auth.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/timetable.functions.php';


function ConflictGameContext($gameInfo)
{
    $poolLabel = trim((string) $gameInfo['poolname']);
    if (!empty($gameInfo['seriesname']) && !empty($gameInfo['poolname'])) {
        $poolLabel = $gameInfo['seriesname'] . ", " . $gameInfo['poolname'];
    }

    $fieldLabel = ReservationPlaceText(
        isset($gameInfo['placename']) ? U_($gameInfo['placename']) : '',
        isset($gameInfo['fieldname']) ? U_($gameInfo['fieldname']) : '',
    );

    return [
        'game' => utf8entities(GameName($gameInfo)),
        'pool' => utf8entities($poolLabel),
        'field' => utf8entities($fieldLabel),
    ];
}

function ConflictMessage($game1Context, $game2Context)
{
    if ($game1Context['pool'] === $game2Context['pool']) {
        return sprintf(
            _('Game %1$s at %2$s conflicts with %3$s at %4$s in %5$s.'),
            $game2Context['game'],
            $game2Context['field'],
            $game1Context['game'],
            $game1Context['field'],
            $game1Context['pool'],
        );
    }

    return sprintf(
        _('Game %1$s at %2$s (%3$s) conflicts with %4$s at %5$s (%6$s).'),
        $game2Context['game'],
        $game2Context['field'],
        $game2Context['pool'],
        $game1Context['game'],
        $game1Context['field'],
        $game1Context['pool'],
    );
}


$body = file_get_contents('php://input');
if ($body === false) {
    $body = '';
}

$season = "";
$warningResponse = "";
$errorResponse = "";

$places = explode("|", $body);
foreach ($places as $placeGameStr) {
    $games = explode(":", $placeGameStr);
    if (intval($games[0]) != 0) {

        ClearReservation($games[0]);
        $resInfo = ReservationInfo($games[0]);
        $firstStart = strtotime($resInfo['starttime']);
        $resEnd = strtotime($resInfo['endtime']);
        for ($i = 1; $i < count($games); $i++) {
            $gameArr = explode("/", $games[$i]);
            $gameInfo = GameInfo($gameArr[0]);
            $season = $gameInfo['season'];
            $time = $firstStart + (60 * (int) $gameArr[1]);
            if (!empty($gameInfo['gametimeslot'])) {
                $gameEnd = $time + ($gameInfo['gametimeslot'] * 60);
            } else {
                $gameEnd = $time + ($gameInfo['timeslot'] * 60);
            }
            if ($gameEnd > $resEnd) {
                $warningResponse .= "<p>" . sprintf(_("Game %s exceeds the reserved end time %s."), GameName($gameInfo), ShortTimeFormat($resInfo['endtime'])) . "</p>";
            }
            ScheduleGame($gameArr[0], $time, $games[0]);
        }
    } else {
        for ($i = 1; $i < count($games); $i++) {
            $gameArr = explode("/", $games[$i]);
            $gameInfo = GameInfo($gameArr[0]);
            $season = $gameInfo['season'];
            UnScheduleGame($gameArr[0]);
        }
    }
}

if ($season) {

    $movetimes = TimetableMoveTimes($season);
    $gameOverlapMessages = [];
    $transferOverlapMessages = [];
    $seenConflicts = [];
    $conflicts = TimetableIntraPoolConflicts($season);

    foreach ($conflicts as $conflict) {
        if (!empty($conflict['time2']) && !empty($conflict['time1'])) {
            $game1End = strtotime($conflict['time1']) + $conflict['slot1'] * 60;
            $travelEnd = $game1End + TimetableMoveTime($movetimes, $conflict['location1'], $conflict['field1'], $conflict['location2'], $conflict['field2']);
            $game2Start = strtotime($conflict['time2']);
            if ($travelEnd > $game2Start) {
                $game1 = GameInfo($conflict['game1']);
                $game2 = GameInfo($conflict['game2']);
                $conflictKey = min((int) $game1['game_id'], (int) $game2['game_id']) . ":" . max((int) $game1['game_id'], (int) $game2['game_id']);
                if (!isset($seenConflicts[$conflictKey])) {
                    $seenConflicts[$conflictKey] = true;
                    $game1Context = ConflictGameContext($game1);
                    $game2Context = ConflictGameContext($game2);
                    if ($game1End > $game2Start) {
                        $gameOverlapMessages[] = ConflictMessage($game1Context, $game2Context);
                    } else {
                        $transferOverlapMessages[] = ConflictMessage($game1Context, $game2Context);
                    }
                }
            }
        }
    }

    $conflicts = TimetableInterPoolConflicts($season);

    foreach ($conflicts as $conflict) {
        if (!empty($conflict['time2']) && !empty($conflict['time1'])) {
            $game1End = strtotime($conflict['time1']) + $conflict['slot1'] * 60;
            $travelEnd = $game1End + TimetableMoveTime($movetimes, $conflict['location1'], $conflict['field1'], $conflict['location2'], $conflict['field2']);
            $game2Start = strtotime($conflict['time2']);
            if ($travelEnd > $game2Start) {
                $game1 = GameInfo($conflict['game1']);
                $game2 = GameInfo($conflict['game2']);
                $conflictKey = min((int) $game1['game_id'], (int) $game2['game_id']) . ":" . max((int) $game1['game_id'], (int) $game2['game_id']);
                if (!isset($seenConflicts[$conflictKey])) {
                    $seenConflicts[$conflictKey] = true;
                    $game1Context = ConflictGameContext($game1);
                    $game2Context = ConflictGameContext($game2);
                    if ($game1End > $game2Start) {
                        $gameOverlapMessages[] = ConflictMessage($game1Context, $game2Context);
                    } else {
                        $transferOverlapMessages[] = ConflictMessage($game1Context, $game2Context);
                    }
                }
            }
        }
    }

    if (!empty($gameOverlapMessages)) {
        $warningResponse .= "<p>" . _("Warning: Scheduling conflicts detected due to overlapping game times.") . "</p>\n<ul style='margin: 0; padding-left: 1.5em; list-style-position: outside;'>";
        foreach ($gameOverlapMessages as $conflictMessage) {
            $warningResponse .= "<li style='white-space: nowrap; margin: 0; padding: 0;'>" . $conflictMessage . "</li>";
        }
        $warningResponse .= "</ul>";
    }

    if (!empty($transferOverlapMessages)) {
        $warningResponse .= "<p>" . _("Warning: Scheduling conflicts detected based on transfer times.") . "</p>\n<ul style='margin: 0; padding-left: 1.5em; list-style-position: outside;'>";
        foreach ($transferOverlapMessages as $conflictMessage) {
            $warningResponse .= "<li style='white-space: nowrap; margin: 0; padding: 0;'>" . $conflictMessage . "</li>";
        }
        $warningResponse .= "</ul>";
    }
} else {
    $errorResponse .= "<p>" . _("Error: unknown event.") . "</p>";
}

if (!empty($errorResponse)) {
    echo "<p>" . _("Schedule saved with errors:") . "</p>\n" . $errorResponse . $warningResponse;
} elseif (!empty($warningResponse)) {
    echo "<p>" . _("Schedule saved with warnings:") . "</p>\n" . $warningResponse;
} else {
    echo _("Schedule saved and validated.");
}
