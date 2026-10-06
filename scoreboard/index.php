<?php

$include_prefix = "../";

include_once '../lib/database.php';
OpenConnection();

include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/session.functions.php';
include_once $include_prefix . 'lib/configuration.functions.php';
include_once $include_prefix . 'lib/user.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'localization.php';

//Public display: a session is used only to remember the chosen language.
startSecureSession();
if (!isset($_SESSION['uid'])) {
    SetUserSessionData("anonymous");
}
setSessionLocale();

// JSON feed polled by script/scoreboard.js.
$feed = $_GET['json'] ?? '';
if ($feed !== '') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($feed === 'list') {
        $games = [];
        foreach (ScoreboardGames() as $game) {
            $games[] = [
                'id' => (int) $game['game_id'],
                'home' => $game['home'],
                'visitor' => $game['visitor'],
                'homescore' => (int) $game['homescore'],
                'visitorscore' => (int) $game['visitorscore'],
                'ongoing' => (int) $game['isongoing'] === 1,
                'time' => $game['time'] ? DefHourFormat($game['time']) : '',
                'place' => trim(($game['placename'] ?? '') . ' ' . ($game['fieldname'] ?? '')),
            ];
        }
        echo json_encode(['games' => $games]);
    } else {
        // A pause must not show up seconds late on the clock.
        DisablePersistentCacheForRequest();
        $game = ScoreboardGame($_GET['game'] ?? 0);
        if ($game === null) {
            http_response_code(404);
            echo json_encode(['error' => 'not found']);
        } else {
            // Null unless a scorekeeper is running the live clock; the game
            // end and "No game clock" both leave timer_start unset.
            $timer = empty($game['hide_time_on_scoresheet']) ? GameTimerState($game['game_id']) : ['ongoing' => false];
            echo json_encode([
                'id' => (int) $game['game_id'],
                'home' => $game['home'],
                'visitor' => $game['visitor'],
                'homescore' => (int) $game['homescore'],
                'visitorscore' => (int) $game['visitorscore'],
                'ongoing' => (int) $game['isongoing'] === 1,
                'finished' => (int) $game['hasstarted'] === 2 && (int) $game['isongoing'] === 0,
                'clock' => $timer['ongoing'] ? ['elapsed' => $timer['elapsed'], 'paused' => $timer['paused']] : null,
            ]);
        }
    }
    exit;
}

$styles_prefix = '../';
$favicon = $styles_prefix . "cust/" . CUSTOMIZATIONS . "/favicon.png";
if (!is_file($include_prefix . "cust/" . CUSTOMIZATIONS . "/favicon.png")) {
    $favicon = $styles_prefix . "cust/default/favicon.png";
}
$lang = explode('_', getSessionLocale());
$lang = !empty($lang[0]) ? $lang[0] : 'en';

$i18n = [
    'ongoing' => _("Ongoing"),
    'upcoming' => _("Upcoming"),
    'noGames' => _("No games"),
    'games' => _("Games"),
    'fullscreen' => _("Fullscreen"),
];

echo "<!DOCTYPE html>\n";
echo "<html lang='" . utf8entities($lang) . "'>\n";
echo "<head>\n";
echo "<meta charset='UTF-8'/>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1, viewport-fit=cover'/>\n";
echo "<link rel='icon' type='image/png' href='" . utf8entities($favicon) . "'/>\n";
echo "<title>" . utf8entities(_("Scoreboard")) . "</title>\n";
echo "<link rel='stylesheet' href='scoreboard.css' type='text/css'/>\n";
echo "</head>\n";
echo "<body>\n";
echo "<main id='sb-picker'>\n";
echo "<h1>" . utf8entities(_("Scoreboard")) . "</h1>\n";
echo "<div id='sb-list'></div>\n";
echo "<p><a class='sb-link' href='" . BASEURL . "/'>" . utf8entities(_("Ultiorganizer")) . "</a></p>\n";
echo "</main>\n";
echo "<main id='sb-board' hidden>\n";
echo "<div id='sb-clock' hidden></div>\n";
echo "<div class='sb-team' id='sb-home'><div class='sb-name'></div><div class='sb-score'>0</div></div>\n";
echo "<div class='sb-team' id='sb-visitor'><div class='sb-name'></div><div class='sb-score'>0</div></div>\n";
echo "<div id='sb-controls'>\n";
echo "<button type='button' id='sb-back'>" . utf8entities(_("Games")) . "</button>\n";
echo "<button type='button' id='sb-full'>" . utf8entities(_("Fullscreen")) . "</button>\n";
echo "</div>\n";
echo "</main>\n";
echo "<script>var SCOREBOARD_I18N = " . json_encode($i18n) . ";</script>\n";
echo "<script src='../script/scoreboard.js'></script>\n";
echo "</body>\n</html>\n";
