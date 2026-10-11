<?php
include_once __DIR__ . '/auth.php';
pluginRequireAdmin(__FILE__);

ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=simulator
format=any
security=superadmin
customization=all

[DESCRIPTION]
title = "Event simulator"
description = "Creates a complete test event with divisions, teams, players, pools and a pool play schedule."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()) {
    die('Insufficient user rights');
}

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/country.functions.php';
include_once 'lib/spirit.functions.php';

const SIMEVENT_SLOT_MINUTES = 70;
const SIMEVENT_DAY_START = "09:00:00";
const SIMEVENT_DAY_END = "19:00:00";
const SIMEVENT_MAX_DIVISIONS = 10;
const SIMEVENT_MAX_TEAMS = 40;
const SIMEVENT_MAX_PLAYERS = 30;
const SIMEVENT_MAX_POOLS = 8;
const SIMEVENT_MAX_DAYS = 31;
const SIMEVENT_MAX_FIELDS = 12;

/**
 * Reads a bounded integer from the form, falling back to a default.
 */
function simEventInt($key, $default, $min, $max)
{
    $value = isset($_POST[$key]) ? (int) $_POST[$key] : $default;
    return max($min, min($max, $value));
}

/**
 * Picks the first season id of the form SIM001..SIM999 that is not in use.
 */
function simEventFreeSeasonId()
{
    for ($i = 1; $i < 1000; $i++) {
        $id = sprintf("SIM%03d", $i);
        if (!SeasonExists($id)) {
            return $id;
        }
    }
    return null;
}

/**
 * Splits $teams into $pools groups whose sizes differ by at most one.
 */
function simEventPoolSizes($teams, $pools)
{
    $sizes = [];
    for ($i = 0; $i < $pools; $i++) {
        $sizes[] = intdiv($teams, $pools) + ($i < $teams % $pools ? 1 : 0);
    }
    return $sizes;
}

/**
 * Picks the spirit scoring mode: the WFDF official one when present, else the first available.
 *
 * @return int mode id, or 0 when the installation has no spirit modes
 */
function simEventSpiritMode()
{
    $modes = SpiritCategoryModeRows();
    foreach ($modes as $mode) {
        if (stripos($mode['name'], "WFDF official") !== false) {
            return (int) $mode['mode'];
        }
    }
    return empty($modes) ? 0 : (int) $modes[0]['mode'];
}

/**
 * Gives both teams of every game random spirit scores, skewed towards the upper end of each category.
 *
 * @return int number of submissions saved
 */
function simEventSpirit($games, $mode)
{
    if ($mode <= 0) {
        return 0;
    }
    $categories = SpiritCategories($mode);
    $saved = 0;
    foreach ($games as $game) {
        foreach ([$game['home'], $game['away']] as $teamId) {
            $points = [];
            foreach ($categories as $category) {
                if ((int) $category['index'] > 0) {
                    $span = (int) $category['max'] - (int) $category['min'];
                    $drop = (int) round(($span / 4) * (rand(0, 100) / 100) * (rand(0, 100) / 100) * 4);
                    $points[$category['category_id']] = max((int) $category['min'], (int) $category['max'] - $drop);
                }
            }
            $valid = SpiritValidateSubmittedPoints($points, $categories);
            if ($valid !== false) {
                SpiritScoreReplaceByGameTeam($game['id'], $teamId, $valid);
                $saved++;
            }
        }
        RefreshGameSpiritData($game['id']);
    }
    return $saved;
}

/**
 * Creates the whole event and returns a summary, or an error string.
 *
 * @param array $in validated form values
 * @param callable $progress called with a status label before each step
 * @return array{error?: string, season?: string, summary?: array, games?: int, scheduled?: int, spirit?: int}
 */
function simEventCreate($in, $progress)
{
    $seasonId = simEventFreeSeasonId();
    if ($seasonId === null) {
        return ["error" => "No free event id left."];
    }
    $templates = PoolTemplates();
    if (empty($templates)) {
        return ["error" => "Create a pool template first."];
    }
    $templateId = $templates[0]['template_id'];

    $countries = CountryList(true);
    if (empty($countries)) {
        return ["error" => "No countries available for team generation."];
    }

    $spiritMode = simEventSpiritMode();
    $firstDay = strtotime("next saturday");
    $lastDay = strtotime("+" . ($in['days'] - 1) . " days", $firstDay);

    $params = [
        "name" => $in['name'] !== "" ? $in['name'] : "Simulated event " . $seasonId,
        "type" => "outdoor",
        "istournament" => 1,
        "isinternational" => 0,
        "organizer" => "",
        "category" => "",
        "isnationalteams" => 0,
        "starttime" => date("Y-m-d", $firstDay) . " 00:00:00",
        "endtime" => date("Y-m-d", $lastDay) . " 23:59:59",
        "iscurrent" => 0,
        "enrollopen" => 0,
        "enroll_deadline" => date("Y-m-d", $firstDay) . " 00:00:00",
        "spiritmode" => $spiritMode,
        "showspiritpoints" => 1,
        "showspiritcomments" => 0,
        "showspiritpointsonlyoncomplete" => 1,
        "lockteamspiritonsubmit" => 1,
        "use_season_points" => 0,
        "hide_time_on_scoresheet" => 0,
        "hometeammode" => 0,
        "event_readonly" => 0,
        "maintenance_mode" => 0,
        "public_event" => 0,
        "api_public" => 0,
        "showgamecomments" => 0,
        "anonymous_scorekeeping" => 0,
        "require_accreditation" => 0,
        "timezone" => GetDefTimeZone(),
    ];
    $progress("Creating event " . $seasonId);
    if (!AddSeason($seasonId, $params)) {
        return ["error" => "Event could not be created."];
    }

    $seriesTypes = SeriesTypes();
    $divisionNames = ["Open", "Women", "Mixed"];
    $summary = [];
    $games = [];

    for ($d = 0; $d < $in['divisions']; $d++) {
        $divisionName = $divisionNames[$d] ?? "Division " . ($d + 1);
        $progress("Creating teams and players for " . $divisionName);
        $seriesId = AddSeries([
            "name" => $divisionName,
            "type" => $seriesTypes[$d % count($seriesTypes)],
            "season" => $seasonId,
            "valid" => 1,
            "ordering" => chr(ord("A") + $d),
        ]);

        $teamCount = rand($in['teamsMin'], $in['teamsMax']);
        $poolCount = rand($in['poolsMin'], $in['poolsMax']);
        // Keep at least three teams per pool so a round robin is meaningful.
        $poolCount = max(1, min($poolCount, intdiv($teamCount, 3)));
        $playerTotal = 0;

        $teamIds = [];
        for ($t = 1; $t <= $teamCount; $t++) {
            $country = $countries[rand(0, count($countries) - 1)]['name'];
            $enrolled = AddSeriesEnrolledTeam($seriesId, $_SESSION['uid'], "Team " . ($d + 1) . "-" . $t, "Club " . $seasonId . "-" . ($d + 1) . "-" . $t, $country);
            $teamId = ConfirmEnrolledTeam($seriesId, $enrolled);
            $teamIds[] = $teamId;

            $players = rand($in['playersMin'], $in['playersMax']);
            for ($p = 1; $p <= $players; $p++) {
                AddPlayer($teamId, "Player " . $p, $seasonId . "-" . ($d + 1) . "-" . $t, "", $p);
            }
            $playerTotal += $players;
        }

        $progress("Creating pools and games for " . $divisionName);
        shuffle($teamIds);
        $offset = 0;
        $poolSizes = simEventPoolSizes($teamCount, $poolCount);
        foreach ($poolSizes as $i => $size) {
            $poolName = "Pool " . chr(ord("A") + $i);
            $poolId = PoolFromPoolTemplate($seriesId, $poolName, chr(ord("A") + $i), $templateId);
            SetPoolDetails($poolId, [
                "name" => $poolName,
                "type" => 1,
                "ordering" => chr(ord("A") + $i),
                "visible" => 1,
                "continuingpool" => 0,
                "placementpool" => 0,
                "timeslot" => SIMEVENT_SLOT_MINUTES,
            ]);
            foreach (array_slice($teamIds, $offset, $size) as $rank => $teamId) {
                PoolAddTeam($poolId, $teamId, $rank + 1, true);
            }
            $offset += $size;

            GenerateGames($poolId, 1, true);
            foreach (PoolGames($poolId) as $game) {
                $games[] = [
                    "id" => $game['game_id'],
                    "home" => $game['hometeam'],
                    "away" => $game['visitorteam'],
                ];
            }
        }

        $summary[] = [
            "name" => $divisionName,
            "teams" => $teamCount,
            "pools" => $poolCount,
            "players" => $playerTotal,
        ];
    }

    $locationId = AddLocation("Simulator fields", "", [], $in["fields"], 0, 0, 0, $seasonId);
    $progress("Entering spirit scores");
    $spiritScores = simEventSpirit($games, $spiritMode);
    $progress("Scheduling games");
    $scheduled = simEventSchedule($seasonId, $locationId, $firstDay, $in, $games);

    return [
        "season" => $seasonId,
        "summary" => $summary,
        "games" => count($games),
        "scheduled" => $scheduled,
        "spirit" => $spiritScores,
    ];
}

/**
 * Creates one reservation per field and game day and places the games into
 * the time slots so that no team plays twice in the same slot.
 *
 * @return int number of scheduled games
 */
function simEventSchedule($seasonId, $locationId, $firstDay, $in, $games)
{
    $slotsPerDay = intdiv((strtotime("1970-01-01 " . SIMEVENT_DAY_END) - strtotime("1970-01-01 " . SIMEVENT_DAY_START)) / 60, SIMEVENT_SLOT_MINUTES);
    $reservations = [];
    for ($day = 0; $day < $in['days']; $day++) {
        $date = date("Y-m-d", strtotime("+" . $day . " days", $firstDay));
        for ($field = 1; $field <= $in['fields']; $field++) {
            $reservations[$day][$field] = AddReservation([
                "location" => $locationId,
                "fieldname" => $field,
                "reservationgroup" => "Day " . ($day + 1),
                "date" => $date . " 00:00:00",
                "starttime" => $date . " " . SIMEVENT_DAY_START,
                "endtime" => $date . " " . SIMEVENT_DAY_END,
                "season" => $seasonId,
            ]);
        }
    }

    $scheduled = 0;
    $lastSlot = [];
    for ($day = 0; $day < $in['days'] && !empty($games); $day++) {
        $dayStart = strtotime(date("Y-m-d", strtotime("+" . $day . " days", $firstDay)) . " " . SIMEVENT_DAY_START);
        for ($slot = 0; $slot < $slotsPerDay && !empty($games); $slot++) {
            $busy = [];
            $time = $dayStart + $slot * SIMEVENT_SLOT_MINUTES * 60;
            $globalSlot = $day * $slotsPerDay + $slot;
            for ($field = 1; $field <= $in['fields']; $field++) {
                // First pass prefers teams that rested the previous slot.
                $pick = null;
                foreach ([true, false] as $requireRest) {
                    foreach ($games as $index => $game) {
                        if (isset($busy[$game['home']]) || isset($busy[$game['away']])) {
                            continue;
                        }
                        if ($requireRest && (($lastSlot[$game['home']] ?? -2) === $globalSlot - 1 || ($lastSlot[$game['away']] ?? -2) === $globalSlot - 1)) {
                            continue;
                        }
                        $pick = $index;
                        break 2;
                    }
                }
                if ($pick === null) {
                    break;
                }
                $game = $games[$pick];
                ScheduleGame($game['id'], $time, $reservations[$day][$field]);
                $busy[$game['home']] = true;
                $busy[$game['away']] = true;
                $lastSlot[$game['home']] = $globalSlot;
                $lastSlot[$game['away']] = $globalSlot;
                unset($games[$pick]);
                $scheduled++;
            }
        }
    }
    return $scheduled;
}

$title = "Event simulator";
$streaming = false;
$deleteHtml = "";
if (isset($_POST['delete']) && !empty($_POST['deleteSeason'])) {
    $deleteId = (string) $_POST['deleteSeason'];
    if (preg_match('/^SIM\d{3}$/', $deleteId) && DeleteSeasonWithData($deleteId)) {
        $deleteHtml .= "<p>" . sprintf("Event %s deleted.", utf8entities($deleteId)) . "</p>\n";
    } else {
        $deleteHtml .= "<p class='warning'>" . "Event could not be deleted." . "</p>\n";
    }
}
$html = "";
$values = [
    "name" => "",
    "divisions" => 3,
    "teamsMin" => 8,
    "teamsMax" => 16,
    "playersMin" => 12,
    "playersMax" => 18,
    "poolsMin" => 2,
    "poolsMax" => 4,
    "days" => 2,
    "fields" => 4,
    "seed" => "",
];

if (isset($_POST['simulate'])) {
    $values["name"] = trim((string) ($_POST['name'] ?? ""));
    $values["divisions"] = simEventInt("divisions", 3, 1, SIMEVENT_MAX_DIVISIONS);
    $values["teamsMin"] = simEventInt("teamsMin", 8, 3, SIMEVENT_MAX_TEAMS);
    $values["teamsMax"] = simEventInt("teamsMax", 16, 3, SIMEVENT_MAX_TEAMS);
    $values["playersMin"] = simEventInt("playersMin", 12, 1, SIMEVENT_MAX_PLAYERS);
    $values["playersMax"] = simEventInt("playersMax", 18, 1, SIMEVENT_MAX_PLAYERS);
    $values["poolsMin"] = simEventInt("poolsMin", 2, 1, SIMEVENT_MAX_POOLS);
    $values["poolsMax"] = simEventInt("poolsMax", 4, 1, SIMEVENT_MAX_POOLS);
    $values["days"] = simEventInt("days", 2, 1, SIMEVENT_MAX_DAYS);
    $values["fields"] = simEventInt("fields", 4, 1, SIMEVENT_MAX_FIELDS);
    $values["seed"] = trim((string) ($_POST['seed'] ?? ""));

    $labels = [
        "divisions" => "Divisions",
        "teamsMin" => "Minimum teams",
        "teamsMax" => "Maximum teams",
        "playersMin" => "Minimum players",
        "playersMax" => "Maximum players",
        "poolsMin" => "Minimum pools",
        "poolsMax" => "Maximum pools",
        "days" => "Game days",
        "fields" => "Fields",
    ];
    foreach ($labels as $key => $label) {
        if (isset($_POST[$key]) && (int) $_POST[$key] !== $values[$key]) {
            $html .= "<p class='warning'>" . sprintf("%s was adjusted to %d, the highest or lowest allowed value.", $label, $values[$key]) . "</p>\n";
        }
    }

    $ranges = [["teamsMin", "teamsMax"], ["playersMin", "playersMax"], ["poolsMin", "poolsMax"]];
    $valid = true;
    foreach ($ranges as $range) {
        if ($values[$range[0]] > $values[$range[1]]) {
            $html .= "<p class='warning'>" . "The minimum cannot be larger than the maximum." . "</p>\n";
            $valid = false;
            break;
        }
    }

    $token = (string) ($_POST['token'] ?? "");
    $expected = (string) ($_SESSION['simEventToken'] ?? "");
    unset($_SESSION['simEventToken']);
    if ($valid && ($expected === "" || !hash_equals($expected, $token))) {
        $html .= "<p class='warning'>" . "This form was already submitted." . "</p>\n";
        $valid = false;
    }

    if ($valid) {
        set_time_limit(600);
        $streaming = true;
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        header('X-Accel-Buffering: no');
        pageTop($title);
        leftMenu();
        contentStart();
        $totalSteps = 3 + 2 * $values["divisions"];
        $step = 0;
        echo "<progress id='simprogress' max='" . $totalSteps . "' value='0' style='width:20em'></progress>\n";
        echo "<p id='simstatus'>Starting</p>\n";
        flush();
        $progress = function ($label) use (&$step) {
            $step++;
            echo "<script type='text/javascript'>document.getElementById('simprogress').value=" . $step . ";"
                . "document.getElementById('simstatus').textContent=" . json_encode($label) . ";</script>\n";
            echo str_repeat(" ", 1024) . "\n";
            flush();
        };
        if ($values["seed"] !== "") {
            mt_srand((int) $values["seed"]);
        }
        $result = simEventCreate($values, $progress);
        echo "<script type='text/javascript'>document.getElementById('simprogress').value=document.getElementById('simprogress').max;document.getElementById('simstatus').textContent='Done';</script>\n";
        if (isset($result["error"])) {
            $html .= "<p class='warning'>" . utf8entities($result["error"]) . "</p>\n";
        } else {
            $seasonId = $result["season"];
            $html .= "<p>" . sprintf("Event %s created.", "<a href='?view=admin/seasonadmin&amp;season=" . urlencode($seasonId) . "'>" . utf8entities($seasonId) . "</a>") . "</p>\n";
            $html .= "<table class='admintable'><tr><th>" . "Division" . "</th><th>" . "Teams" . "</th><th>" . "Pools" . "</th><th>" . "Players" . "</th></tr>\n";
            foreach ($result["summary"] as $row) {
                $html .= "<tr><td>" . utf8entities($row["name"]) . "</td><td>" . (int) $row["teams"] . "</td><td>" . (int) $row["pools"] . "</td><td>" . (int) $row["players"] . "</td></tr>\n";
            }
            $html .= "</table>\n";
            $html .= "<p>" . sprintf("Games: %1\$d, scheduled: %2\$d.", $result["games"], $result["scheduled"]) . "</p>\n";
            $html .= "<p>" . sprintf("Spirit submissions: %d.", $result["spirit"]) . "</p>\n";
            if ($result["scheduled"] < $result["games"]) {
                $html .= "<p class='warning'>" . "Not all games fit into the game days. Add game days or fields." . "</p>\n";
            }
        }
    }
}

$rangeRow = function ($label, $minKey, $maxKey, $min, $max) use ($values) {
    return "<tr><td class='infocell'>" . $label . ":</td><td>"
        . "<input class='input' type='number' min='" . $min . "' max='" . $max . "' name='" . $minKey . "' value='" . (int) $values[$minKey] . "'/> - "
        . "<input class='input' type='number' min='" . $min . "' max='" . $max . "' name='" . $maxKey . "' value='" . (int) $values[$maxKey] . "'/></td></tr>\n";
};
$numberRow = function ($label, $key, $max) use ($values) {
    return "<tr><td class='infocell'>" . $label . ":</td><td><input class='input' type='number' min='1' max='" . $max . "' name='" . $key . "' value='" . (int) $values[$key] . "'/></td></tr>\n";
};

$_SESSION['simEventToken'] = bin2hex(random_bytes(16));
$html .= "<form method='post' action='?view=plugins/simulate_event'>\n";
$html .= "<input type='hidden' name='token' value='" . $_SESSION['simEventToken'] . "'/>\n";
$html .= "<table>\n";
$html .= "<tr><td class='infocell'>" . "Name" . ":</td><td><input class='input' maxlength='50' name='name' value='" . utf8entities($values["name"]) . "'/></td></tr>\n";
$html .= $numberRow("Divisions", "divisions", SIMEVENT_MAX_DIVISIONS);
$html .= $rangeRow("Teams per division", "teamsMin", "teamsMax", 3, SIMEVENT_MAX_TEAMS);
$html .= $rangeRow("Players per team", "playersMin", "playersMax", 1, SIMEVENT_MAX_PLAYERS);
$html .= $rangeRow("Pools per division", "poolsMin", "poolsMax", 1, SIMEVENT_MAX_POOLS);
$html .= $numberRow("Game days", "days", SIMEVENT_MAX_DAYS);
$html .= $numberRow("Fields", "fields", SIMEVENT_MAX_FIELDS);
$html .= "<tr><td class='infocell'>" . "Random seed" . ":</td><td><input class='input' type='number' name='seed' value='" . utf8entities($values["seed"]) . "'/></td></tr>\n";
$html .= "</table>\n";
$html .= "<p><input class='button' type='submit' name='simulate' value='" . "Generate" . "'/></p>\n";
$html .= "</form>\n";

$simEvents = [];
foreach (Seasons() as $season) {
    if (preg_match('/^SIM\d{3}$/', $season['season_id'])) {
        $simEvents[] = $season;
    }
}
if (!empty($simEvents)) {
    $html .= $deleteHtml;
    $html .= "<form method='post' action='?view=plugins/simulate_event'>\n";
    $html .= "<p>" . "Simulated events" . ": <select class='dropdown' name='deleteSeason'>\n";
    foreach ($simEvents as $season) {
        $html .= "<option class='dropdown' value='" . utf8entities($season['season_id']) . "'>" . utf8entities($season['season_id'] . " " . $season['name']) . "</option>\n";
    }
    $html .= "</select></p>\n";
    $html .= "<p><input class='button' type='submit' name='delete' value='" . "Delete" . "' onclick=\"return confirm('" . "Delete this event and all its data permanently?" . "');\"/></p>\n";
    $html .= "</form>\n";
} else {
    $html .= $deleteHtml;
}

// A repeat press while a long run is still going would start another run.
$html .= "<script type='text/javascript'>\n";
$html .= "for (var f = 0; f < document.forms.length; f++) {\n";
$html .= "  document.forms[f].addEventListener('submit', function (event) {\n";
$html .= "    var form = event.target;\n";
$html .= "    if (form.getAttribute('data-submitting')) {\n";
$html .= "      event.preventDefault();\n";
$html .= "      return;\n";
$html .= "    }\n";
$html .= "    form.setAttribute('data-submitting', '1');\n";
$html .= "    // Disable on the next tick: a disabled button is left out of the POST body.\n";
$html .= "    window.setTimeout(function () {\n";
$html .= "      var buttons = form.querySelectorAll('input[type=submit]');\n";
$html .= "      for (var i = 0; i < buttons.length; i++) {\n";
$html .= "        buttons[i].disabled = true;\n";
$html .= "      }\n";
$html .= "    }, 0);\n";
$html .= "  });\n";
$html .= "}\n";
$html .= "</script>\n";

if ($streaming) {
    echo $html;
    contentEnd();
    pageEnd();
} else {
    showPage($title, $html);
}
