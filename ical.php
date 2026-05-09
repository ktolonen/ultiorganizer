<?php

require_once __DIR__ . '/lib/view.guard.php';
requireRoutedView('ical');

include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/timetable.functions.php';

header("Content-Type: text/calendar; charset=utf-8");

function IcalEscapeText($value)
{
    $value = trim((string) $value);
    $value = str_replace("\\", "\\\\", $value);
    $value = str_replace(["\r\n", "\r", "\n"], "\\n", $value);
    $value = str_replace(",", "\\,", $value);
    $value = str_replace(";", "\\;", $value);
    return $value;
}

function IcalFoldLine($line)
{
    $limit = 75;
    if (strlen($line) <= $limit) {
        return $line;
    }

    $folded = '';
    $currentLine = '';
    $characters = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($characters as $character) {
        if (strlen($currentLine . $character) > $limit) {
            $folded .= $currentLine . "\r\n";
            $currentLine = ' ' . $character;
        } else {
            $currentLine .= $character;
        }
    }

    return $folded . $currentLine;
}

function IcalPrintLine($name, $value = null, $params = [])
{
    $line = $name;
    foreach ($params as $paramName => $paramValue) {
        if ($paramValue !== '') {
            $line .= ';' . $paramName . '=' . $paramValue;
        }
    }
    if ($value !== null) {
        $line .= ':' . $value;
    }

    echo IcalFoldLine($line) . "\r\n";
}

function IcalDateTimeUtc($timestamp, $timezone = '')
{
    if (empty($timestamp)) {
        return '';
    }

    try {
        $dateTimeZone = !empty($timezone) ? new DateTimeZone($timezone) : new DateTimeZone(date_default_timezone_get());
        $dateTime = new DateTime($timestamp, $dateTimeZone);
        $dateTime->setTimezone(new DateTimeZone('UTC'));
        return $dateTime->format('Ymd\THis\Z');
    } catch (Exception $e) {
        return gmdate('Ymd\THis\Z', strtotime($timestamp));
    }
}

$order = 'tournaments';
$timefilter = 'coming';
$id = 0;
$gamefilter = "season";

if (iget("series")) {
    $id = iget("series");
    $gamefilter = "series";
} elseif (iget("pool")) {
    $id = iget("pool");
    $gamefilter = "pool";
} elseif (iget("pools")) {
    $id = iget("pools");
    $gamefilter = "poolgroup";
} elseif (iget("team")) {
    $id = iget("team");
    $gamefilter = "team";
} elseif (iget("season")) {
    $id = iget("season");
    $gamefilter = "season";
} else {
    $id = CurrentSeason();
    $gamefilter = "season";
}


if (iget("order")) {
    $order  = iget("order");
}

if (iget("time")) {
    $timefilter  = iget("time");
}

$games = TimetableGames($id, $gamefilter, $timefilter, $order);

IcalPrintLine('BEGIN', 'VCALENDAR');
IcalPrintLine('VERSION', '2.0');
IcalPrintLine('PRODID', '-//Ultiorganizer//NONSGML v1.0//EN');
IcalPrintLine('CALSCALE', 'GREGORIAN');
IcalPrintLine('METHOD', 'PUBLISH');

foreach ($games as $game) {
    $location = !empty($game['place_id']) ? LocationInfo($game['place_id']) : null;
    $homeTeamName = trim(TeamName($game['hometeam']));
    $visitorTeamName = trim(TeamName($game['visitorteam']));
    if ($homeTeamName !== '' && $visitorTeamName !== '') {
        $summary = $homeTeamName . ' vs ' . $visitorTeamName;
    } elseif ($homeTeamName !== '') {
        $summary = $homeTeamName;
    } elseif ($visitorTeamName !== '') {
        $summary = $visitorTeamName;
    } else {
        $summary = 'TBD';
    }
    $description = trim(U_($game['seriesname']) . ': ' . U_($game['poolname']), ': ');
    $locationName = preg_replace('/\s+/', ' ', trim($game['placename'] . ' ' . $game['fieldname']));
    $uid = 'game-' . (int) $game['game_id'] . '@ultiorganizer';

    IcalPrintLine('BEGIN', 'VEVENT');
    IcalPrintLine('UID', $uid);
    IcalPrintLine('DTSTAMP', gmdate('Ymd\THis\Z'));
    IcalPrintLine('SUMMARY', IcalEscapeText($summary));
    IcalPrintLine('DESCRIPTION', IcalEscapeText($description));
    IcalPrintLine('LOCATION', IcalEscapeText($locationName));
    IcalPrintLine('DTSTART', IcalDateTimeUtc($game['time'], $game['timezone']));
    IcalPrintLine('DURATION', 'PT' . intval($game['timeslot']) . 'M');
    if (!empty($location) && isset($location['lat']) && isset($location['lng'])) {
        IcalPrintLine('GEO', (float) $location['lat'] . ';' . (float) $location['lng']);
    }
    IcalPrintLine('END', 'VEVENT');
}
IcalPrintLine('END', 'VCALENDAR');
