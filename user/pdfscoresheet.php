<?php

include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/reservation.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/user.functions.php';
include_once $include_prefix . 'lib/timetable.functions.php';
include_once $include_prefix . 'lib/pdf.interfaces.php';

function pdf_slug($value)
{
    $slug = strtolower((string) $value);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim($slug, '-');
    return $slug === '' ? 'pdf' : $slug;
}

if (is_file('cust/' . CUSTOMIZATIONS . '/pdfscoresheet.php')) {
    include_once 'cust/' . CUSTOMIZATIONS . '/pdfscoresheet.php';
} else {
    include_once 'cust/default/pdfscoresheet.php';
}
$season = "";
$filter1 = "";
$filter2 = "";
$gameId = 0;
$teamId = 0;
$seriesId = 0;
$games = null;
$filename = null;

if (!empty($_GET["game"])) {
    $games = TimetableGames($_GET["game"], "game", "all", "place");
    $filename = "scoresheet-game-" . pdf_slug($_GET["game"]) . ".pdf";
}

if (!empty($_GET["season"])) {
    $season = $_GET["season"];
} else {
    $season = CurrentSeason();
}

if (!empty($_GET["series"])) {
    $seriesId = $_GET["series"];
}

if (!empty($_GET["pool"])) {
    $poolId = $_GET["pool"];
    $games = TimetableGames($poolId, "pool", "all", "time", "");
    $filename = "scoresheets-pool-" . pdf_slug($poolId) . ".pdf";
}

if (!empty($_GET["filter1"])) {
    $filter1  = $_GET["filter1"];
}

if (!empty($_GET["filter2"])) {
    $filter2  = $_GET["filter2"];
}

if (!empty($_GET["time"])) {
    $time = $_GET["time"];
    $games = TimetableGames(CurrentSeason(), "season", $time, "places", "");

    if (!empty($_GET["timefilter1"])) {
        $timefilter1  = $_GET["timefilter1"];
    }

    if (!empty($_GET["timefilter2"])) {
        $timefilter2  = $_GET["timefilter2"];
    }
}
if (!empty($_GET["reservation"])) {
    $gameResponsibilities = GameResponsibilities($season);
    $responsibilities = [];
    foreach ($gameResponsibilities as $row) {
        $responsibilities[] = $row['game_id'];
    }
    $games = ResponsibleReservationGames($_GET["reservation"] == "none" ? null : $_GET["reservation"], $responsibilities);
    $resSlug = $_GET["reservation"] == "none" ? "none" : pdf_slug($_GET["reservation"]);
    $filename = "scoresheets-reservation-" . $resSlug . ".pdf";
}
if (!empty($_GET["group"])) {
    if ($filter1 == "coming") {
        $games = TimetableGames($season, "season", "coming", "places", $_GET["group"]);
    } else {
        $games = TimetableGames($season, "season", "all", "places", $_GET["group"]);
    }
}

if (!empty($_GET["team"])) {
    $teamId  = $_GET["team"];
}

// Default to all games of the season if no specific filter populated $games.
if ($games === null) {
    $games = TimetableGames($season, "season", "all", "places");
}

$pdf = new PDF();
// @phpstan-ignore instanceof.alwaysTrue, instanceof.alwaysFalse (PDF resolves through runtime customization includes)
if (!$pdf instanceof ScoreSheetPdf) {
    throw new UnexpectedValueException('Scoresheet PDF customization must implement ScoreSheetPdf.');
}
// @phpstan-ignore deadCode.unreachable
$printScoreSheet = new ReflectionMethod($pdf, 'PrintScoreSheet');
$scoreSheetAcceptsPlayerLists = $printScoreSheet->getNumberOfParameters() >= 9;

if ($teamId) {
    $seasonSlug = pdf_slug(TeamSeason($teamId));
} else {
    $seasonSlug = pdf_slug(SeasonName($season));
}

if ($filename === null) {
    $filename = "scoresheets-" . $seasonSlug . ".pdf";
}


if ($teamId) {
    $teaminfo = TeamInfo($teamId);
    $players = [];
    $players = TeamPlayerList($teamId);
    $pdf->PrintRoster($teaminfo['name'], $teaminfo['seriesname'], $teaminfo['poolname'], $players);
    $filename = "roster-" . pdf_slug($teaminfo['name']) . "-" . $seasonSlug . ".pdf";
} elseif ($seriesId) {

    $teams = SeriesTeams($seriesId, true);

    foreach ($teams as $team) {
        $teaminfo = TeamInfo($team['team_id']);
        $players = [];
        $players = TeamPlayerList($team['team_id']);
        $pdf->PrintRoster($teaminfo['name'], $teaminfo['seriesname'], $teaminfo['poolname'], $players);
    }
    $filename = "rosters-series-" . pdf_slug($seriesId) . "-" . $seasonSlug . ".pdf";
} elseif (isset($_GET['blank'])) {

    $seasonname = SeasonName($season);
    if ($scoreSheetAcceptsPlayerLists) {
        $pdf->PrintScoreSheet(U_($seasonname), "", "", "", "", "", "", [], []);
    } else {
        $pdf->PrintScoreSheet(U_($seasonname), "", "", "", "", "", "");
    }

} else {
    $seasonname = SeasonName($season);

    // Bail out gracefully if no games were returned.
    if (!$games || !is_array($games)) {
        $pdf->Output('I', $filename);
        return;
    }

    foreach ($games as $gameRow) {

        if ($filter2 == "teams") {
            if (!$gameRow['hometeam'] || !$gameRow['visitorteam']) {
                continue;
            }
        }

        $sGid = $gameRow['game_id'];
        //$sGid .= getChkNum($sGid);

        $homeplayers = [];

        $i = 0;
        foreach (TeamPlayerList($gameRow["hometeam"]) as $player) {
            $homeplayers[$i]['name'] = $player['firstname'] . " " . $player['lastname'];
            $homeplayers[$i]['accredited'] = $player['accredited'];
            $homeplayers[$i]['num'] = $player['num'];
            $i++;
        }
        $visitorplayers = [];
        $i = 0;
        foreach (TeamPlayerList($gameRow["visitorteam"]) as $player) {
            $visitorplayers[$i]['name'] = $player['firstname'] . " " . $player['lastname'];
            $visitorplayers[$i]['accredited'] = $player['accredited'];
            $visitorplayers[$i]['num'] = $player['num'];
            $i++;
        }

        $home = empty($gameRow["hometeamname"]) ? U_($gameRow["phometeamname"]) : $gameRow["hometeamname"];
        $visitor = empty($gameRow["visitorteamname"]) ? U_($gameRow["pvisitorteamname"]) : $gameRow["visitorteamname"];
        $placeLabel = ReservationPlaceText(U_($gameRow["placename"]), U_($gameRow['fieldname']));

        if ($scoreSheetAcceptsPlayerLists) {
            $pdf->PrintScoreSheet(
                U_($seasonname),
                $sGid,
                $home,
                $visitor,
                U_($gameRow['seriesname']) . ", " . U_($gameRow['poolname']),
                $gameRow["time"],
                $placeLabel,
                $homeplayers,
                $visitorplayers,
            );
        } else {
            $pdf->PrintScoreSheet(
                U_($seasonname),
                $sGid,
                $home,
                $visitor,
                U_($gameRow['seriesname']) . ", " . U_($gameRow['poolname']),
                $gameRow["time"],
                $placeLabel,
            );
            $pdf->PrintPlayerList($homeplayers, $visitorplayers);
        }
    }
}

$pdf->Output('I', $filename);
