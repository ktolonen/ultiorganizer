<?php

include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/player.functions.php';
include_once $include_prefix . 'lib/reservation.functions.php';

// function to search missing jersey numbers on the rosters
function TableMissingNumbers($season)
{
    $ret = "";

    $resultsTable = SeasonPlayersMissingNumbers($season);

    if (!empty($resultsTable)) {
        $ret .= "<p>" . _("Missing shirt numbers found! (click the team name to edit the roster)") . "</p>";
        $ret .= "<table width='100%'><tr>";
        $ret .= "<th class='center'>" . _("Number") . "</th>";
        $ret .= "<th class='center'>" . _("First Name") . "</th>";
        $ret .= "<th class='center'>" . _("Last Name") . "</th>";
        $ret .= "<th class='center'>" . _("Team") . "</th>";
        $ret .= "<th class='center'>" . _("Division") . "</th>";
        $ret .= "<th class='center'>" . _("Link") . "</th>";
        $ret .= "</tr>";

        foreach ($resultsTable as $row) {
            $ret .= "<tr>";
            $ret .= "<td class='center'>" . $row['num'] . "</td>";
            $ret .= "<td>" . $row['firstname'] . "</td>";
            $ret .= "<td>" . $row['lastname'] . "</td>";
            $ret .= "<td><a href='?view=user/teamplayers&team=" . $row['team_id'] . "'>" . $row['team'] . "</a></td>";
            $ret .= "<td>" . $row['division'] . "</td>";
            $ret .= "</tr>";
        }

        $ret .= "</table>";
    } else {
        $ret = "<p>" . _("No missing shirt numbers found.") . "</p>";
    }

    return $ret;
}

// function to search for duplicate jersey numbers on the rosters and print a table with the results
function TableDuplicateNumbers($season)
{
    $ret = "";

    $resultsTable = SeasonPlayersDuplicateNumbers($season);

    if (!empty($resultsTable)) {
        $ret .= "<p>" . _("Duplicates found! (click the team name to edit the roster)") . "</p>";
        $ret .= "<table width='100%'><tr>";
        $ret .= "<th class='center'>" . _("Number") . "</th>";
        $ret .= "<th class='center'>" . _("First Name") . "</th>";
        $ret .= "<th class='center'>" . _("Last Name") . "</th>";
        $ret .= "<th class='center'>" . _("Team") . "</th>";
        $ret .= "<th class='center'>" . _("Division") . "</th>";
        $ret .= "<th class='center'>" . _("Link") . "</th>";
        $ret .= "</tr>";

        foreach ($resultsTable as $row) {
            $ret .= "<tr>";
            $ret .= "<td class='center'>" . $row['num'] . "</td>";
            $ret .= "<td>" . $row['firstname'] . "</td>";
            $ret .= "<td>" . $row['lastname'] . "</td>";
            $ret .= "<td><a href='?view=user/teamplayers&team=" . $row['team_id'] . "'>" . $row['team'] . "</a></td>";
            $ret .= "<td>" . $row['division'] . "</td>";
            $ret .= "</tr>";
        }

        $ret .= "</table>";
    } else {
        $ret = "<p>" . _("No duplicates found.") . "</p>";
    }

    return $ret;
}

function TableForfeitGames($season)
{
    $ret = "";
    $games = SeasonForfeitGames($season);

    if (!empty($games)) {
        $ret .= "<table border='1' width='100%'><tr>";
        $ret .= "<th>" . _("Date") . "</th>";
        $ret .= "<th>" . _("Game") . "</th>";
        $ret .= "<th>" . _("Result") . "</th>";
        $ret .= "<th>" . _("Pool") . "</th>";
        $ret .= "<th>" . _("Edit") . "</th>";
        $ret .= "</tr>";
        foreach ($games as $game) {
            $ret .= "<tr>";
            $ret .= "<td style='white-space:nowrap'>" . ShortDate($game['time']) . " " . DefHourFormat($game['time']) . "</td>";
            $ret .= "<td>" . utf8entities($game['hometeamname']) . " - " . utf8entities($game['visitorteamname']) . "</td>";
            $ret .= "<td>" . intval($game['homescore']) . " - " . intval($game['visitorscore']) . "</td>";
            $ret .= "<td>" . utf8entities(U_($game['seriesname'])) . ": " . utf8entities(U_($game['poolname'])) . "</td>";
            $ret .= "<td><a href='?view=admin/editgame&amp;season=$season&amp;game=" . $game['game_id'] . "'>" . _("Edit") . "</a></td>";
            $ret .= "</tr>";
        }
        $ret .= "</table>";
    } else {
        $ret = "<p>" . _("No forfeit games found.") . "</p>";
    }

    return $ret;
}

function TableTimeoutStats()
{
    $ret = "";

    $reservations = ReservationGroupTimeoutStats();

    $ret .= "<table width=50% border=1>";
    $ret .= "<tr>";
    $ret .= "<th class='center'>Day</th>";
    $ret .= "<th class='center'>Games</th>";
    $ret .= "<th class='center'>Timeouts</th>";
    $ret .= "<th class='center'>Average</th>";
    $ret .= "<tr>";

    foreach ($reservations as $r) {
        $group = $r['reservationgroup'];
        $games = (int) $r['games'];
        $timeouts = (int) $r['timeouts'];

        $ret .= "<tr>";
        $ret .= "<td>" . $group . "</td>";
        $ret .= "<td class='right'>" . $games . "</td>";
        $ret .= "<td class='right'>" . $timeouts . "</td>";
        $ret .= "<td class='right'>" . sprintf("%.2f", (($games > 0) ? $timeouts / $games : 0)) . "</td>";
        $ret .= "</tr>";
    }

    $ret .= "</table>";

    return $ret;
}

$season = GetString("season");

$title = _("TD Tools");
$html = "";

$html .= "<h1>" . $title . "</h1>\n";

if (!empty($season) && isSeasonAdmin($season)) {
    if (isset($_POST['game'])) {
        $gameId = intval($_POST['game']);
        $linktype = $_POST['directlink'];
        if (!$gameId || !GamePool($gameId)) {
            $html .= "<p class='warning'>" . _("Invalid game number.") . "</p>";
        } else {
            switch ($linktype) {
                case 'result':
                    $linkto = "?view=user/addresult&game=$gameId";
                    break;
                case 'players':
                    $linkto = "?view=user/addplayerlists&game=$gameId";
                    break;
                case 'scoresheet':
                    $linkto = "?view=user/addscoresheet&game=$gameId";
                    break;
                default:
                    $linkto = "?view=admin/tdtools&season=$season";
                    break;
            }
            header("Location: $linkto");
        }
    }

    $html .= "<hr />";

    $html .= "<h2>" . _("Direct Links") . "</h2>";
    $html .= "<div class='tdtools-box bg-td1'>";
    $html .= "<p>" . _("Enter the game ref. # on the paper scoresheet for a direct link: ") . "</p>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<input class='input' type='text' size='10' maxlength='10' name='game'/>";
    $html .= "<button class='button' type='submit' name='directlink' value='result'>" . _("Result") . "</button> ";
    $html .= "<button class='button' type='submit' name='directlink' value='players'>" . _("Players") . "</button> ";
    $html .= "<button class='button' type='submit' name='directlink' value='scoresheet'>" . _("Scoresheet") . "</button> ";
    $html .= "</form></p>";
    $html .= "</div>";

    $html .= "<hr />";

    $html .= "<h2>" . _("Timeout Stats") . "</h2>";
    $html .= "<div class='tdtools-box bg-td3'>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<button class='button' type='submit' name='timeouts' value='search'>" . _("Show Timeout Stats") . "</button> ";
    $html .= "</form></p>";

    if (isset($_POST['timeouts'])) {
        $html .= TableTimeoutStats();
    }

    $html .= "</div>";

    $html .= "<hr />";

    $html .= "<h2>" . _("Missing and duplicate shirt numbers") . "</h2>";
    $html .= "<div class='tdtools-box bg-td4'>";
    $html .= "<p>" . _("To search for players with the same shirt number on the same team, or who do not have a shirt number assigned, press the appropriate button: ") . "</p>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<button class='button' type='submit' name='dups' value='search'>" . _("Duplicates") . "</button> ";
    $html .= "<button class='button' type='submit' name='missing' value='search'>" . _("Missing") . "</button> ";
    $html .= "</form></p>";

    if (isset($_POST['dups'])) {
        $html .= TableDuplicateNumbers($season);
    }

    if (isset($_POST['missing'])) {
        $html .= TableMissingNumbers($season);
    }

    $html .= "</div>";

    $html .= "<hr />";

    $html .= "<h2>" . _("Forfeit games") . "</h2>";
    $html .= "<div class='tdtools-box bg-td2'>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<button class='button' type='submit' name='forfeits' value='search'>" . _("Show forfeit games") . "</button> ";
    $html .= "</form></p>";

    if (isset($_POST['forfeits'])) {
        $html .= TableForfeitGames($season);
    }

    $html .= "</div>";
} else {
    $html .= "<p>" . _("Insufficient user rights") . "</p>";
}

showPage($title, $html);
