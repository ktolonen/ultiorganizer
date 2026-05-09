<?php

include_once '../lib/database.php';
OpenConnection();

include_once $include_prefix . 'lib/session.functions.php';
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/reservation.functions.php';
include_once $include_prefix . 'localization.php';

startSecureSession();
if (!isset($_SESSION['uid'])) {
    SetUserSessionData('anonymous');
}

if (isset($_POST['myusername']) && !isset($_GET['token'])) {
    UserAuthenticate($_POST['myusername'], $_POST['mypassword'], "");
}

setSessionLocale();

$styles_prefix = '../';
$token = SpiritkeeperGetToken();
$teamId = 0;
$teamInfo = [];
$pageTitle = _("Spiritkeeper");
$pageHtml = '';
$showLogout = false;

if ($token !== '') {
    $teamId = SpiritTeamIdByToken($token);
    $teamInfo = $teamId > 0 ? TeamInfo($teamId) : [];
    $pageTitle = !empty($teamInfo['seasonname']) ? $teamInfo['seasonname'] . " - " . _("Spiritkeeper") : _("Spiritkeeper");
    $view = iget('view');
    if (!in_array($view, ['teamgames', 'submitsotg'], true)) {
        $view = 'teamgames';
    }

    if ($teamId > 0) {
        include __DIR__ . '/' . $view . '.php';
    } else {
        $pageHtml .= "<div class='mobile-notice mobile-notice--error'><p>" . _("Invalid token. Please use the full link provided by the Tournament Director or the Spirit Director.") . "</p></div>";
    }
} else {
    $view = iget('view');
    if (empty($view)) {
        $view = isLoggedIn() ? 'home' : 'login';
    }

    if (!isLoggedIn() && $view !== 'login') {
        $redirect = '?view=login';
        if (!empty($view)) {
            $redirect .= '&nextview=' . urlencode($view);
        }
        if (GetInt('game') > 0) {
            $redirect .= '&game=' . GetInt('game');
        }
        if (GetInt('team') > 0) {
            $redirect .= '&team=' . GetInt('team');
        }
        header("location:" . $redirect);
        exit();
    }

    if (!in_array($view, ['login', 'logout', 'home', 'teamgames', 'editgame'], true)) {
        $view = isLoggedIn() ? 'home' : 'login';
    }

    $showLogout = isLoggedIn();
    include __DIR__ . '/' . $view . '.php';
}

$sessionLocale = getSessionLocale();
$lang = explode('_', $sessionLocale);
$lang = !empty($lang[0]) ? $lang[0] : 'en';

echo "<!DOCTYPE html>\n";
echo "<html lang='" . utf8entities($lang) . "'>\n";
echo "<head>\n";
echo "<meta charset='UTF-8'/>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1, viewport-fit=cover'/>\n";
echo "<title>" . utf8entities($pageTitle) . "</title>\n";
echo mobileStyles();
echo "</head>\n";
echo "<body>\n";
echo "<div data-role='page'>\n";
echo "<div data-role='header'>\n";
echo "<h1>" . utf8entities($pageTitle) . "</h1>\n";
if ($token !== '' && $teamId > 0 && !empty($teamInfo['name'])) {
    echo "<p class='mobile-header-subtitle'>" . _("Your team") . ": " . utf8entities($teamInfo['name']);
    if (!empty($teamInfo['seriesname'])) {
        echo " [" . utf8entities($teamInfo['seriesname']) . "]";
    }
    echo "</p>\n";
} elseif ($showLogout && !empty($_SESSION['uid']) && $_SESSION['uid'] !== 'anonymous') {
    echo "<p class='mobile-header-subtitle'>" . _("Logged in as") . ": " . utf8entities($_SESSION['uid']) . "</p>\n";
}
echo "</div>\n";
echo "<div data-role='content'>\n";
echo $pageHtml;
echo "</div>\n";
echo "<div data-role='footer' class='ui-bar' data-position='fixed'>\n";
echo "<a class='footer-compact' href='" . BASEURL . "/' data-role='button' rel='external' data-icon='home'>" . _("Ultiorganizer") . "</a>";
if ($showLogout) {
    echo "<a class='footer-compact' href='?view=logout' data-role='button'>" . _("Log out") . "</a>";
}
echo "</div>\n";
echo "</div>\n";
echo "</body>\n";
echo "</html>\n";

CloseConnection();
