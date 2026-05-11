<?php

require_once __DIR__ . '/lib/include_only.guard.php';
denyDirectFileAccess(__FILE__);

include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/statistical.functions.php';

if (is_file('cust/' . CUSTOMIZATIONS . '/head.php')) {
    include_once 'cust/' . CUSTOMIZATIONS . '/head.php';
} else {
    include_once 'cust/default/head.php';
}

/**
 * Shows html content with ultiorganizer menus and layout.
 *
 * @param string $title page's title
 * @param string $html page's content
 */
function showPage($title, $html, $mobile = false)
{
    if ($mobile) {
        mobilePageTop($title);
        echo $html;
        mobilePageEnd();
    } else {
        pageTop($title);
        leftMenu();
        contentStart();
        echo $html;
        contentEnd();
        pageEnd();
    }
}

/**
 * Shows html content without ultiorganizer menus and layout.
 *
 * @param string $title page's title
 * @param string $html page's content
 */
function showPrintablePage($title, $html)
{
    pageTop($title, true);
    leftMenu(0, true, true);
    contentStart();
    echo $html;
    contentEnd();
    pageEnd();
}

/**
 * Produce html code for page top.
 *
 * @param string $title - title of the page
 * @param boolean $printable - if true then no header produced.
 */
function pageTop($title, $printable = false)
{
    pageTopHeadOpen($title);
    pageTopHeadClose($title, $printable);
}

/**
 * HTML code with page meta information. Leaves <head> tag open.
 *
 * @param string $title - the page title
 */
function pageTopHeadOpen($title)
{
    global $include_prefix;
    $lang = explode("_", getSessionLocale());
    $lang = $lang[0];
    $icon = $include_prefix . "cust/" . CUSTOMIZATIONS . "/favicon.png";

    echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
		<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='" . $lang . "' lang='" . $lang . "'>\n<head>
		<meta http-equiv=\"Content-Style-Type\" content=\"text/css\"/>
		<meta http-equiv=\"Content-Script-Type\" content=\"text/javascript\"/>
		<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"/>";
    //no cache
    echo "<meta http-equiv=\"Pragma\" content=\"no-cache\"/>";
    echo "<meta http-equiv=\"Expires\" content=\"-1\"/>";

    echo  "<link rel='icon' type='image/png' href='$icon' />
		<title>" . GetPageTitle() . "" . $title . "</title>\n";
    echo styles();
    include $include_prefix . 'script/common.js.inc';
    global $include_prefix;
    include_once $include_prefix . 'script/help.js.inc';
}


/**
 * HTML code for header and navigation bar.
 *
 * @param string $title - title of the page
 * @param boolean $printable - if true then no header produced.
 * @param string $bodyfunctions - insert additional attributes/functions in body tag
 */
function pageTopHeadClose($title, $printable = false, $bodyfunctions = "")
{

    if (isset($_SESSION['uid'])) {
        $user = $_SESSION['uid'];
    } else {
        $user = "anonymous";
    }

    if ((!empty($_GET['view']) && $_GET['view'] == 'logout') || !isset($_SERVER['QUERY_STRING'])) {
        $query_string = "view=frontpage";
    } else {
        $query_string = $_SERVER['QUERY_STRING'];
    }

    global $serverConf;
    global $styles_prefix;
    global $include_prefix;

    if (!isset($styles_prefix)) {
        $styles_prefix = $include_prefix;
    }

    echo "</head><body style='overflow-y:scroll;' " . $bodyfunctions . ">\n";
    echo "<div class='page'>\n";

    if (!$printable) {
        echo "<div class='page_top'>\n";

        echo "<div class='top_banner_space'></div>\n";
        echo "<form action='?" . utf8entities($query_string) . "' method='post'>\n";
        echo "<table border='0' cellpadding='0' cellspacing='0' style='width:100%;white-space: nowrap;'><tr>\n";

        //top header left part can be customized
        echo "<td class='topheader_left'>\n";
        echo pageHeader();
        echo "</td><!--topheader_left-->";

        //top header right part contains common elements
        echo "<td class='topheader_right'>";
        echo "<table border='0' cellpadding='0' cellspacing='0' style='width:95%;white-space: nowrap;'>\n";

        //1st row: Locale selection
        echo "<tr>";
        echo "<td class='right' style='vertical-align:top;'>" . localeSelection() . "</td>";
        echo "</tr>";

        //2nd row: User Log in
        echo "<tr>\n";
        echo "<td class='right' style='padding-top:5px'>";

        $hideInlineLogin = ($user == 'anonymous' && function_exists('IsSelfRegistrationDisabled') && IsSelfRegistrationDisabled());
        $hideRegistration = ($user == 'anonymous' && function_exists('IsPublicRegistrationDisabled') && IsPublicRegistrationDisabled());

        if ($user == 'anonymous') {
            if (!$hideInlineLogin) {
                echo "<input class='input' type='text' id='myusername' name='myusername' size='10' style='border:1px solid #555555'/>&nbsp;";
                echo "<input class='input' type='password' id='mypassword' name='mypassword' size='10' style='border:1px solid #555555'/>&nbsp;";
                echo "<input class='button' type='submit' name='login' value='" . utf8entities(_("Log in")) . "' style='border:1px solid #000000'/>";
            }
        } else {
            $userinfo = UserInfo($user);
            echo "<span class='topheadertext'>" . utf8entities(_("User")) . ": <a class='topheaderlink' href='?view=user/userinfo'>" . utf8entities($userinfo['name']) . "</a></span>";
        }

        echo "&nbsp;";

        if ($user == 'anonymous') {
            if (!$hideRegistration) {
                echo "<span class='topheadertext'><a class='topheaderlink' href='?view=register'>" . utf8entities(_("New user?")) . "</a></span>";
            }
        } else {
            echo "<span class='topheadertext'><a class='topheaderlink' href='?view=logout'>&raquo; " . utf8entities(_("Log out")) . "</a></span>";
        }
        echo "</td></tr>\n";
        echo "</table>";
        echo "</td></tr></table>";
        echo "</form>\n";
        echo "</div><!--page_top-->\n";

        //navigation bar
        echo "<div class='navigation_bar'><p class='navigation_bar_text'><span style='color: grey;'>Nav. History: </span>";
        echo navigationBar($title) . "</p></div>";
    }

    echo "<div class='page_middle'>\n";
}

/**
 * Start of page content.
 */
function contentStart()
{
    echo "\n<td align='left' valign='top' class='tdcontent'><div class='content'>\n";
}

/**
 * End of page content.
 */
function contentEnd()
{
    echo "\n</div><!--content--></td></tr></table></div><!--page_middle-->\n";
}

/**
 * End of the page.
 */
function pageEnd()
{
    echo "<div class='page_bottom'></div>";
    echo "</div></body></html>";
}


/**
 * Adds on page help.
 *
 * @param string $html - html-text shown when help button pressed.
 */
function onPageHelpAvailable($html)
{
    return "<div style='float:right;'>
	<input type='image' class='helpbutton' id='helpbutton' src='images/help-icon.png'/></div>\n
	<div id='helptext' class='yui-pe-content'>$html<hr/></div>";
}


/**
 * Top of Mobile page.
 *
 * @param String $title - page title
 */
function mobilePageTop($title)
{
    pageTopHeadOpen($title);

    $spiritkeeperUrl = './spiritkeeper/';
    if (function_exists('SpiritkeeperHomeUrl')) {
        $spiritkeeperUrl = SpiritkeeperHomeUrl();
    }

    echo "</head><body style='overflow-y:scroll;'>\n";
    echo "<div class='mobile_page'>\n";
    echo "<p class='warning'>";
    echo utf8entities(_("The legacy mobile administration interface is deprecated."));
    echo " ";
    echo utf8entities(_("Use"));
    echo " <a href='./scorekeeper/'>" . utf8entities(_("Scorekeeper")) . "</a> ";
    echo utf8entities(_("for score entry and"));
    echo " <a href='" . $spiritkeeperUrl . "'>" . utf8entities(_("Spiritkeeper")) . "</a> ";
    echo utf8entities(_("for spirit entry."));
    echo "</p>\n";
}

function mobilePageEnd($query = "")
{
    if ($query == "") {
        $query = $_SERVER['QUERY_STRING'];
    }
    if (!isset($_SESSION['uid']) || $_SESSION['uid'] == "anonymous") {
        $isMobileLoginView = (!empty($_GET['view']) && $_GET['view'] === 'mobile/index');
        $hidePublicAuth = function_exists('IsSelfRegistrationDisabled') && IsSelfRegistrationDisabled() && !$isMobileLoginView;

        $html = "";
        if (!$hidePublicAuth) {
            $html .= "<form action='?" . utf8entities($query) . "' method='post'>\n";
            $html .= "<table cellpadding='2'>\n";
            $html .= "<tr><td>\n";
            $html .= utf8entities(_("Username")) . ":";
            $html .= "</td></tr><tr><td>\n";
            $html .= "<input class='input' type='text' id='myusername' name='myusername' size='15'/> ";
            $html .= "</td></tr><tr><td>\n";
            $html .= utf8entities(_("Password")) . ":";
            $html .= "</td></tr><tr><td>\n";
            $html .= "<input class='input' type='password' id='mypassword' name='mypassword' size='15'/> ";
            $html .= "</td></tr><tr><td>\n";
            $html .= "<input class='button' type='submit' name='login' value='" . utf8entities(_("Log in")) . "'/>";
            $html .= "</td></tr><tr><td>\n";
            $html .= "<hr/>\n";
            $html .= "</td></tr>\n";
            $html .= "<tr><td>\n";
            $html .= "<a href='?view=frontpage'>" . utf8entities(_("Back to the Ultiorganizer")) . "</a>";
            $html .= "</td></tr>\n";
            $html .= "</table>\n";
            $html .= "</form>";
        } else {
            $html .= "<table cellpadding='2'>\n";
            $html .= "<tr><td>\n";
            $html .= "<a href='?view=frontpage'>" . utf8entities(_("Back to the Ultiorganizer")) . "</a>";
            $html .= "</td></tr>\n";
            $html .= "</table>\n";
        }
    } else {
        if ($query != "") {
            header($query);
        }
        // $user = $_SESSION['uid'];
        // $userinfo = UserInfo($user);
        $html = "<table cellpadding='2'>\n";
        $html .= "<tr><td></td></tr>\n";
        $html .= "<tr><td><hr /></td></tr><tr><td>\n";
        $html .= "<a href='?view=frontpage'>" . utf8entities(_("Back to the Ultiorganizer")) . "</a>";
        $html .= "</td></tr><tr><td>\n";
        $html .= "<a href='?view=mobile/logout'>" . utf8entities(_("Log out")) . "</a></td></tr></table>";
    }

    $html .= "<div class='page_bottom'></div>";
    $html .= "</div></body></html>";
    echo $html;
}

/**
 * Creates locale selection html-code.
 */
function localeSelection()
{
    global $locales;

    $ret = "";

    foreach ($locales as $localestr => $localename) {
        $query_string = StripFromQueryString($_SERVER['QUERY_STRING'], "locale");
        $query_string = StripFromQueryString($query_string, "goindex");
        $ret .= "<a href='?" . utf8entities($query_string) . "&amp;";
        $ret .= "locale=" . $localestr . "'><img class='localeselection' src='locale/" . $localestr . "/flag.png' alt='" . utf8entities($localename) . "'/></a>\n";
    }

    return $ret;
}

/**
 * Navigation bar functionality and html-code.
 *
 * @param string $title - page title
 */
function navigationBar($title)
{
    $ret = "";
    $ptitle = "";
    if (isset($_SERVER['QUERY_STRING'])) {
        $query_string = $_SERVER['QUERY_STRING'];
    } else {
        $query_string = "";
    }

    if (isset($_GET['goindex']) && $_GET['goindex'] > 1 && isset($_SESSION['navigation'])) {
        $goindex = $_GET['goindex'];
        $count = count($_SESSION['navigation']);
        $i = 0;
        foreach ($_SESSION['navigation'] as $pview => $ptitle) {

            if ($i >= $goindex) {
                unset($_SESSION['navigation'][$pview]);
            }
            $i++;
        }
    } elseif (isset($_GET['goindex']) && $_GET['goindex'] <= 1) {
        $_SESSION['navigation'] = ["view=frontpage" => _("Homepage")];
    } else {
        if (!isset($_SESSION['navigation'])) {
            if (strlen($query_string) == 0 || (isset($_GET['view']) && $_GET['view'] == 'logout')) {
                $_SESSION['navigation'] = ["view=frontpage" => _("Homepage")];
            } elseif (!empty($title)) {
                $_SESSION['navigation'] = [$query_string => $title];
            }
        } else {
            if (strlen($query_string) == 0) {
                $_SESSION['navigation']["view=frontpage"] = _("Homepage");
            } elseif (!empty($title)) {
                unset($_SESSION['navigation'][$query_string]);

                //if previous view was having same title, remove it. e.g. when navigating back and forth in profiles or in case of sorting pages trough url parameter

                $lastvalue = end($_SESSION['navigation']);
                if ($lastvalue) {
                    if ($lastvalue == $title) {
                        $tmp = array_keys($_SESSION['navigation']);
                        $lastkey = end($tmp);
                        unset($_SESSION['navigation'][$lastkey]);
                    }
                }
                $_SESSION['navigation'][$query_string] = $title;
            }
        }
    }

    $i = 1;
    $needsdots = false;
    if (isset($_SESSION['navigation'])) {

        foreach ($_SESSION['navigation'] as $view => $ptitle) {

            if ($i < count($_SESSION['navigation'])) {
                if ($i > 1 && $i < (count($_SESSION['navigation']) - 3)) {
                    $needsdots = true;
                } else {
                    if ($needsdots) {
                        $ret .= "... &raquo; ";
                        $needsdots = false;
                    }
                    $ret .= "<a href='?" . utf8entities($view) . "&amp;goindex=" . $i . "'>" . $ptitle . "</a> &raquo; ";
                }
            }
            $i++;
        }
    }
    $ret = $ret . " " . $ptitle;

    return $ret;
}

/**
 * Season selection html-code.
 */
function seasonSelection()
{
    $seasons = CurrentSeasons();
    if (count($seasons) > 1) {
        echo "<table><tr><td>";
        echo "<form action='?view=index' method='get' id='seasonsels'>";
        echo "<div><select class='seasondropdown' name='selseason'
			onchange='changeseason(selseason.options[selseason.options.selectedIndex].value);'>";
        foreach ($seasons as $row) {
            $selected = "";
            if (isset($_SESSION['userproperties']['selseason']) && $_SESSION['userproperties']['selseason'] == $row['season_id']) {
                $selected = "selected='selected'";
            }
            echo  "<option class='dropdown' $selected value='" . utf8entities($row['season_id']) . "'>" . SeasonName($row['season_id']) . "</option>";
        }
        echo "</select>";
        echo "<noscript><div><input type='submit' value='" . utf8entities(_("Go")) . "' name='selectseason'/></div></noscript>";
        echo "</div></form>";
        echo "</td></tr></table>";
    }
}

function pageMainStart($printable = false)
{
    if ($printable) {
        echo "<table style='width:100%;'><tr>";
        return;
    }

    echo "<table style='border:1px solid #fff;background-color: #ffffff;'><tr>\n";
}
/**
 * Creates menus on left side of page.
 *
 * @param int $id - page id (not used now days)
 * @param boolean $printable - if true, menu is not drawn.
 */
function leftMenu($id = 0, $pagestart = true, $printable = false)
{

    if ($pagestart) {
        pageMainStart($printable);
    }
    if ($printable) {
        return;
    }
    echo "<td id='menu_left' class='menu_left'>";

    // Administration menu
    if (hasScheduleRights() || isSuperAdmin() || hasTranslationRight()) {
        echo "<table class='leftmenulinks'>\n";
        echo "<tr><td class='menuseasonlevel'>" . utf8entities(_("Administration")) . "</td></tr>";
    }
    if (isSuperAdmin()) {
        echo "<tr><td>\n";
        echo "<a class='subnav' href='?view=admin/seasons'>&raquo; " . utf8entities(_("Events")) . "</a>\n";
        echo "<a class='subnav' href='?view=admin/serieformats'>&raquo; " . utf8entities(_("Rule templates")) . "</a>\n";
        echo "<a class='subnav' href='?view=admin/clubs'>&raquo; " . utf8entities(_("Clubs & Countries")) . "</a>\n";
        echo "<a class='subnav' href='?view=admin/locations'>&raquo; " . utf8entities(_("Field locations")) . "</a>\n";
        echo "<a class='subnav' href='?view=admin/reservations'>&raquo; " . utf8entities(_("Field reservations")) . "</a>\n";
    }
    if (hasScheduleRights()) {
        echo "<tr><td><a class='subnav' href='?view=admin/schedule'>&raquo; " . utf8entities(_("Scheduling")) . "</a>";
    }

    if (hasTranslationRight()) {
        echo "<a class='subnav' href='?view=admin/translations'>&raquo; " . utf8entities(_("Translations")) . "</a>\n";
    }
    if (isSuperAdmin()) {
        echo "<a class='subnav' href='?view=admin/users'>&raquo; " . utf8entities(_("Users")) . "</a>\n";
        echo "<a class='subnav' href='?view=admin/apitokens'>&raquo; " . utf8entities(_("API Tokens")) . "</a>\n";
        echo "<a class='subnav' href='?view=admin/eventviewer'>&raquo; " . utf8entities(_("Logs")) . "</a>\n";
        echo "<a class='subnav' href='?view=admin/dbadmin'>&raquo; " . utf8entities(_("Database")) . "</a>\n";
        echo "<a class='subnav' href='?view=admin/serverconf'>&raquo; " . utf8entities(_("Settings")) . "</a>\n";
    }

    if (hasScheduleRights() || isSuperAdmin() || hasTranslationRight()) {
        echo "</td></tr>\n";
        echo "</table>\n";
    }
    if ($_SESSION['uid'] != 'anonymous') {
        echo "<table class='leftmenulinks_plain'>\n";
        echo "<tr><td>\n";
        echo "<a class='subnav' href='?view=admin/help'>&raquo; " . utf8entities(_("Helps")) . "</a>\n";
        echo "</td></tr>\n";
        echo "</table>\n";
    }

    //Event administration menu
    $editlinks = getEditSeasonLinks();
    if (count($editlinks)) {
        foreach ($editlinks as $season => $links) {
            $readonlyLabel = "";
            if (isEventReadonly($season)) {
                $readonlyLabel = "<br/>" . utf8entities(_("Read-only"));
            }
            $maintenanceLabel = "";
            if (IsSeasonInMaintenance($season)) {
                $maintenanceLabel = "<br/><span style='color:#ff0000;'>" . utf8entities(_("Maintenance")) . "</span>";
            }
            echo "<table class='leftmenulinks'>\n";
            echo "<tr><td class='menuseasonlevel'>" . utf8entities(SeasonName($season)) . " " . utf8entities(_("Administration")) . $readonlyLabel . $maintenanceLabel . "</td>";
            echo "<td class='menuseasonlevel'><a style='text-decoration: none;' href='?view=frontpage&amp;hideseason=$season'>x</a></td>";
            echo "</tr><tr><td>\n";
            foreach ($links as $href => $name) {
                echo "<a class='subnav' href='" . $href . "'>&raquo; " . utf8entities($name) . "</a>\n";
            }
            echo "</td></tr>\n";
            echo "</table>\n";
        }
    }

    //Create new event menu
    if (isSuperAdmin()) {
        echo "<table class='leftmenulinks'>\n";
        //echo "<tr><td class='menuseasonlevel'>".utf8entities(_("New Event"))."</td></tr>";
        //echo "</td></tr>\n";
        echo "<tr><td>\n";
        echo "<a class='subnav' href='?view=admin/addseasons'>&raquo; " . utf8entities(_("Create new event")) . "</a>\n";
        echo "</td></tr>\n";
        echo "</table>\n";
    }

    //Team registration
    if ($_SESSION['uid'] != 'anonymous') {
        $enrollSeasons = EnrollSeasons();
        if (count($enrollSeasons) > 0) {
            echo "<table class='leftmenulinks'>\n";
            echo "<tr><td class='menuseasonlevel'>" . utf8entities(_("Team registration")) . "</td></tr>\n";
            echo "<tr><td>\n";
            foreach ($enrollSeasons as $seasonId => $seasonName) {
                echo "<a class='subnav' href='?view=user/enrollteam&amp;season=" . $seasonId . "'>&raquo; " . utf8entities(U_($seasonName)) . "</a>\n";
            }
            echo "</td></tr>\n";
            echo "</table>\n";
        }
    }
    // Player profiles
    if (hasPlayerAdminRights()) {
        echo "<table class='leftmenulinks'>\n";
        echo "<tr><td class='menuseasonlevel'>" . utf8entities(_("Player profiles")) . "</td></tr>\n";
        echo "<tr><td>\n";
        foreach ($_SESSION['userproperties']['userrole']['playeradmin'] as $profile_id => $propid) {
            $playerInfo = PlayerProfile($profile_id);
            if (!$playerInfo || !isset($playerInfo['profile_id'])) {
                continue;
            }
            $first = isset($playerInfo['firstname']) ? trim($playerInfo['firstname']) : "";
            $last = isset($playerInfo['lastname']) ? trim($playerInfo['lastname']) : "";
            $displayName = trim($first . " " . $last);
            if ($displayName === "") {
                $displayName = _("Unnamed player");
            }
            echo "<a class='subnav' href='?view=user/playerprofile&amp;profile=" . $playerInfo['profile_id'] . "'>&raquo; " . $displayName . "</a>\n";
        }
        echo "</td></tr>";
        echo "</table>\n";
    }

    //event public part: schedule, played games, teams, divisions, pools...
    seasonSelection();
    $curseason = CurrentSeason();

    echo "<table class='leftmenulinks'>\n";
    $pools = getViewPools($curseason);
    if ($pools) {
        $lastseason = "";
        $lastseries = "";
        $seasoninfo = null;
        $lastseriesSeasonInfo = null;
        foreach ($pools as $row) {
            $season = $row['season'];
            $seasonParam = urlencode((string) $season);
            $series = $row['series'];
            if ($lastseason != $season) {
                $lastseason = $season;
                $seasoninfo = SeasonInfo($season);
                echo "<tr><td class='menuseasonlevel'><a class='seasonnav' style='text-align:center;' href='?view=teams&amp;season=" . $seasonParam . "&amp;list=bystandings'>";
                echo utf8entities(U_($row['season_name'])) . "</a></td></tr>\n";
                echo "<tr><td><a class='nav' href='?view=teams&amp;season=" . $seasonParam . "&amp;list=bystandings'>" . utf8entities(_("Standings")) . "</a></td></tr>\n";
                echo "<tr><td><a class='nav' href='?view=games&amp;season=" . $seasonParam . "&amp;filter=tournaments&amp;group=all'>" . utf8entities(_("Games")) . "</a></td></tr>\n";
                //echo "<tr><td><a class='nav' href='?view=played&amp;season=".urlencode($season)."'>".utf8entities(_("Played games"))."</a></td></tr>\n";
                echo "<tr><td><a class='nav' href='?view=teams&amp;season=" . $seasonParam . "&amp;list=allteams'>" . utf8entities(_("Teams")) . "</a></td></tr>\n";
                echo "<tr><td class='menuseparator'></td></tr>\n";
            }

            if ($lastseries != $series) {
                if (
                    !empty($lastseries)
                    && ShowSpiritScoresForSeason($lastseriesSeasonInfo)
                ) {
                    echo "<tr><td class='menupoollevel'><a class='navpoollink' href='?view=spiritstatus&amp;series=" . $lastseries . "'>" . _("Spirit scores") . "</a></td></tr>\n";
                }
                $lastseries = $series;
                $lastseriesSeasonInfo = $seasoninfo;
                echo "<tr><td class='menuserieslevel'>";
                echo "<a class='subnav' href='?view=seriesstatus&amp;series=" . $series . "'>" . utf8entities(U_($row['series_name'])) . "</a></td></tr>\n";
                echo "<tr><td class='navpoollink'>\n";
                echo "<a class='subnav' href='?view=poolstatus&amp;series=" . $series . "'>&raquo; " . utf8entities(_("Show all pools")) . "</a></td></tr>\n";
            }
            echo "<tr><td class='menupoollevel'>\n";
            echo "<a class='navpoollink' href='?view=poolstatus&amp;pool=" . $row['pool'] . "'>&raquo; " . utf8entities(U_($row['pool_name'])) . "</a>\n";
            echo "</td></tr>\n";
        }
        if (ShowSpiritScoresForSeason($lastseriesSeasonInfo)) {
            echo "<tr><td class='menupoollevel'><a class='navpoollink' href='?view=spiritstatus&amp;series=" . $lastseries . "'>" . _("Spirit scores") . "</a></td></tr>\n";
        }
    } else {
        $season = CurrentSeason();
        $seasonName = (string) CurrentSeasonName();
        if ($season === null || $season === "") {
            echo "<tr><td class='menuseasonlevel'><span class='seasonnav' style='display:block;text-align:center;'>&nbsp;</span></td></tr>\n";
            echo "<tr><td>" . utf8entities(_("Log in to create an event")) . "</td></tr>\n";
            $tmpseries = [];
        } else {
            $seasonParam = urlencode((string) $season);
            echo "<tr><td class='menuseasonlevel'><a class='seasonnav' style='text-align:center;' href='?view=teams&amp;season=" .
              $seasonParam . "&amp;list=bystandings'>" . utf8entities($seasonName) . "</a></td></tr>\n";
            echo "<tr><td><a class='nav' href='?view=timetables&amp;season=" . $seasonParam . "&amp;filter=tournaments&amp;group=all'>" . utf8entities(_("Games")) . "</a></td></tr>\n";
            //  echo "<tr><td><a class='nav' href='?view=played&amp;season=".urlencode($season)."'>".utf8entities(_("Played games"))."</a></td></tr>\n";
            echo "<tr><td><a class='nav' href='?view=teams&amp;season=" . $seasonParam . "'>" . utf8entities(_("Teams")) . "</a></td></tr>\n";
            echo "<tr><td class='menuseparator'></td></tr>\n";
            $tmpseries = SeasonSeries($season, true);
        }
        foreach ($tmpseries as $row) {
            echo "<tr><td class='menuserieslevel'>" . utf8entities(U_($row['name'])) . "</td></tr>\n";
            echo "<tr><td class='menupoollevel'>\n";
            echo utf8entities(_("Pools not yet created"));
            echo "</td></tr>\n";
        }
    }
    echo "</table>\n";

    //event links
    echo "<table class='leftmenulinks'>\n";
    echo "<tr><td class='menuseasonlevel'>" . utf8entities(_("Event Links")) . "</td></tr>\n";
    echo "<tr><td>";

    $urls = GetUrlListByTypeArray(["menulink", "menumail"], $curseason);
    foreach ($urls as $url) {
        if ($url['type'] == "menulink") {
            echo "<a class='subnav' href='" . $url['url'] . "'>&raquo; " . U_($url['name']) . "</a>\n";
        } elseif ($url['type'] == "menumail") {
            echo "<a class='subnav' href='mailto:" . $url['url'] . "'>@ " . U_($url['name']) . "</a>\n";
        }
    }
    echo "</td></tr>\n";
    echo "<tr><td>";
    echo "<a class='subnav' style='background: url(./images/linkicons/feed_14x14.png) no-repeat 0 50%; padding: 0 0 0 19px;' href='./ext/rss.php?feed=all'>" . utf8entities(_("Result Feed")) . "</a>\n";
    echo "</td></tr>\n";
    echo "</table>\n";

    //event history
    if (IsStatsDataAvailable()) {
        echo "<table class='leftmenulinks'>\n";
        echo "<tr><td class='menuseasonlevel'>" . utf8entities(_("Statistics")) . "</td></tr>\n";
        echo "<tr><td>";
        echo "<a class='subnav' href=\"?view=seasonlist\">&raquo; " . utf8entities(_("Events")) . "</a>\n";
        echo "<a class='subnav' href=\"?view=allplayers\">&raquo; " . utf8entities(_("Players")) . "</a>\n";
        echo "<a class='subnav' href=\"?view=allteams\">&raquo; " . utf8entities(_("Teams")) . "</a>\n";
        echo "<a class='subnav' href=\"?view=allclubs\">&raquo; " . utf8entities(_("Clubs")) . "</a>\n";
        if (HasPlayableCountries()) {
            echo "<a class='subnav' href=\"?view=allcountries\">&raquo; " . utf8entities(_("Countries")) . "</a>\n";
        }
        echo "<a class='subnav' href=\"?view=statistics&amp;list=teamstandings\">&raquo; " . utf8entities(_("All time")) . "</a></td></tr>\n";
        echo "</table>";
    }

    //External access
    echo "<table class='leftmenulinks'>\n";
    echo "<tr><td class='menuseasonlevel'>" . utf8entities(_("Client access")) . "</td></tr>\n";
    echo "<tr><td>";
    echo "<a class='subnav' href='?view=ext/index'>&raquo; " . utf8entities(_("Ultiorganizer links")) . "</a>\n";
    echo "<a class='subnav' href='?view=ext/export'>&raquo; " . utf8entities(_("Data export")) . "</a>\n";
    echo "<a class='subnav' href='./scorekeeper/'>&raquo; " . utf8entities(_("Scorekeeper")) . "</a>\n";
    $spiritkeeperUrl = './spiritkeeper/';
    if (function_exists('SpiritkeeperHomeUrl')) {
        $spiritkeeperUrl = SpiritkeeperHomeUrl();
    }
    echo "<a class='subnav' href='" . $spiritkeeperUrl . "'>&raquo; " . utf8entities(_("Spiritkeeper")) . "</a>\n";
    echo "</td></tr>\n";
    echo "</table>";

    $urls = GetUrlListByTypeArray(["menulink", "menumail"], 0);
    if (count($urls) > 0) {
        echo "<table class='leftmenulinks'>\n";
        echo "<tr><td class='menuseasonlevel'>" . utf8entities(_("Links")) . "</td></tr>\n";
        echo "<tr><td>";
        foreach ($urls as $url) {
            if ($url['type'] == "menulink") {
                echo "<a class='subnav' href='" . $url['url'] . "'>&raquo; " . U_($url['name']) . "</a>\n";
            } elseif ($url['type'] == "menumail") {
                echo "<a class='subnav' href='mailto:" . $url['url'] . "'>@ " . U_($url['name']) . "</a>\n";
            }
        }
        echo "</td></tr>\n";
        echo "</table>";
    }

    //draw customizable logo if any
    echo "<table class='leftmenulinks_plain'>\n";
    echo "<tr><td class='guides'>";
    echo logo();
    echo "</td></tr>";
    echo "</table>";

    echo "<table class='leftmenulinks_plain'>\n";
    echo "<tr><td class='guides'>";
    echo "<a href='?view=user_guide'>" . utf8entities(_("User Guide")) . "</a> | \n";
    if (count($editlinks) || isSuperAdmin()) {
        echo "<a href='?view=admin/help'>" . utf8entities(_("Admin Help")) . "</a> | \n";
    }

    echo "<a href='?view=privacy'>" . utf8entities(_("Privacy Policy")) . "</a>\n";
    echo "</td></tr>";
    echo "</table>";

    echo "</td>\n";
}

/**
 * Get event administration links.
 */
function getEditSeasonLinks()
{
    $ret = [];
    if (isset($_SESSION['userproperties']['editseason'])) {
        $editSeasons = getEditSeasons($_SESSION['uid']);
        foreach ($editSeasons as $season => $propid) {
            $ret[$season] = [];
        }
        $respgamesset = [];
        foreach ($ret as $season => $links) {
            if (isSeasonAdmin($season)) {
                $links['?view=admin/seasonadmin&amp;season=' . $season] = _("Event");
                $links['?view=admin/seasonseries&amp;season=' . $season] = _("Divisions");
                $links['?view=admin/seasonteams&amp;season=' . $season] = _("Teams");
                $links['?view=admin/seasonpools&amp;season=' . $season] = _("Pools");
                $links['?view=admin/reservations&amp;season=' . $season] = _("Scheduling");
                $links['?view=admin/seasongames&amp;season=' . $season] = _("Games");
                $seasonInfo = SeasonInfo($season);
                if (!empty($seasonInfo['spiritmode'])) {
                    $links['?view=admin/spirit&amp;season=' . $season] = _("Spirit");
                }
                $links['?view=admin/seasonstandings&amp;season=' . $season] = _("Standings");
                if (!empty($seasonInfo['use_season_points'])) {
                    $links['?view=admin/seasonpoints&amp;season=' . $season] = _("Season points");
                }
                $links['?view=admin/accreditation&amp;season=' . $season] = _("Accreditation");
                $respgamesset[$season] = "set";
            }
            $ret[$season] = $links;
        }
        if (isset($_SESSION['userproperties']['userrole']['seriesadmin'])) {
            foreach ($_SESSION['userproperties']['userrole']['seriesadmin'] as $series => $param) {
                $seriesseason = SeriesSeasonId($series);
                // Links already added if superadmin or seasonadmin
                if (isset($ret[$seriesseason]) && !isSeasonAdmin($seriesseason)) {
                    $links = $ret[$seriesseason];
                    $seriesname = U_(getSeriesName($series));
                    $links['?view=admin/seasonteams&amp;season=' . $seriesseason . '&amp;series=' . $series] = $seriesname . " " . _("Teams");
                    $links['?view=admin/seasongames&amp;season=' . $seriesseason . '&amp;series=' . $series] = $seriesname . " " . _("Games");
                    $links['?view=admin/seasonstandings&amp;season=' . $seriesseason . '&amp;series=' . $series] = $seriesname . " " . _("Pool standings");
                    $links['?view=admin/accreditation&amp;season=' . $seriesseason] = _("Accreditation");
                    $ret[$seriesseason] = $links;
                    $respgamesset[$seriesseason] = "set";
                }
            }
        }

        $spiritAdmins = [];
        if (isset($_SESSION['userproperties']['userrole']['spiritadmin'])) {
            $spiritAdmins += $_SESSION['userproperties']['userrole']['spiritadmin'];
        }
        foreach ($spiritAdmins as $season => $param) {
            if (!isset($ret[$season]) || isSeasonAdmin($season)) {
                continue;
            }
            $seasonInfo = SeasonInfo($season);
            if (empty($seasonInfo['spiritmode'])) {
                continue;
            }
            $links = $ret[$season];
            $links['?view=admin/spirit&amp;season=' . $season] = _("Spirit");
            $ret[$season] = $links;
        }

        $teamPlayersSet = [];
        if (isset($_SESSION['userproperties']['userrole']['teamadmin'])) {

            foreach ($_SESSION['userproperties']['userrole']['teamadmin'] as $team => $param) {
                $teamseason = getTeamSeason($team);
                $teamresps = TeamResponsibilities($_SESSION['uid'], $teamseason);
                if (isset($ret[$teamseason])) {
                    if (count($teamresps) < 2) {
                        $teamname = getTeamName($team);
                        $links = $ret[$teamseason];
                        $links['?view=user/teamplayers&amp;team=' . $team] = _("Team") . ": " . $teamname;
                        $respgamesset[$teamseason] = "set";
                        $teamPlayersSet["" . $team] = "set";
                        $ret[$teamseason] = $links;
                    } else {
                        $links = $ret[$teamseason];
                        $links['?view=user/respteams&amp;season=' . $teamseason] = _("Team responsibilities");
                        $respgamesset[$teamseason] = "set";
                        $ret[$teamseason] = $links;
                    }
                }
            }
        }
        if (isset($_SESSION['userproperties']['userrole']['accradmin'])) {
            $teamAdminRoles = $_SESSION['userproperties']['userrole']['teamadmin'] ?? [];
            if (count($teamAdminRoles) <= 4) {
                foreach ($_SESSION['userproperties']['userrole']['accradmin'] as $team => $param) {
                    if (!isset($teamPlayersSet[$team])) {
                        $teamseason = getTeamSeason($team);
                        if (isset($ret[$teamseason])) {
                            $teamname = getTeamName($team);
                            $links = $ret[$teamseason];
                            $links['?view=user/teamplayers&amp;team=' . $team] = _("Team") . ": " . $teamname;
                            $links['?view=admin/accreditation&amp;season=' . $teamseason] = _("Accreditation");
                            $teamPlayersSet["" . $team] = "set";
                            $ret[$teamseason] = $links;
                        }
                    }
                }
            } else {
                foreach ($_SESSION['userproperties']['userrole']['accradmin'] as $team => $param) {
                    $teamseason = getTeamSeason($team);
                    if (isset($ret[$teamseason])) {
                        $links = $ret[$teamseason];
                        $links['?view=user/respteams&amp;season=' . $teamseason] = _("Team responsibilities");
                        $links['?view=admin/accreditation&amp;season=' . $teamseason] = _("Accreditation");
                        $ret[$teamseason] = $links;
                    }
                }
            }
        }
        if (isset($_SESSION['userproperties']['userrole']['gameadmin'])) {
            foreach ($_SESSION['userproperties']['userrole']['gameadmin'] as $game => $param) {
                $gameseason = GameSeason($game);
                if (isset($ret[$gameseason])) {
                    $respgamesset[$gameseason] = "set";
                }
            }
        }
        if (isset($_SESSION['userproperties']['userrole']['resgameadmin'])) {
            foreach ($_SESSION['userproperties']['userrole']['resgameadmin'] as $resId => $param) {
                foreach (ReservationSeasons($resId) as $resSeason) {
                    if (isset($ret[$resSeason])) {
                        $respgamesset[$resSeason] = "set";
                    }
                }
            }
        }
        foreach ($respgamesset as $season => $set) {
            $links = $ret[$season];
            $links['?view=user/respgames&amp;season=' . $season] = _("Game responsibilities");
            $links['?view=user/contacts&amp;season=' . $season] = _("Contacts");
            $ret[$season] = $links;
        }
    }

    foreach ($ret as $season => $links) {
        if (empty($links) || count($links) == 0) {
            unset($ret[$season]);
        }
    }
    return $ret;
}

/**
 * Creates on page menu. Typically top of the page.
 *
 * @param array $menuitems - key is link name, value is url.
 * @param string $current - links to this url obtain the class 'current'
 * @param boolean $echoed if true (the default), the menu is echoed
 * @return string the menu
 */
function pageMenu($menuitems, $current = "", $echoed = true)
{

    $html = "\n<!-- on page menu -->\n";
    $html .= "<div class='pagemenu_container'>\n";
    $line = "";
    foreach ($menuitems as $name => $url) {
        $line .= utf8entities($name);
        $line .= " - ";
    }
    if (strlen($line) < 100) {
        $html .= "<table id='pagemenu'><tr>\n";
        $first = true;
        foreach ($menuitems as $name => $url) {
            if (!$first) {
                $html .= "<td> - </td>";
            }
            $first = false;
            if ($url == $current || (empty($current) && strrpos($_SERVER["REQUEST_URI"], $url) !== false)) {
                $html .= "<th><a class='current' href='" . htmlentities($url) . "'>" . utf8entities($name) . "</a></th>\n";
            } else {
                $html .= "<th><a href='" . htmlentities($url) . "'>" . utf8entities($name) . "</a></th>\n";
            }
        }
        $html .= "</tr></table>";
    } else {
        $html .= "<ul id='pagemenu'>\n";

        foreach ($menuitems as $name => $url) {

            if ($url == $current || (empty($current) && strrpos($_SERVER["REQUEST_URI"], $url) !== false)) {
                $html .= "<li><a class='current' href='" . htmlentities($url) . "'>" . utf8entities($name) . "</a></li>\n";
            } else {
                $html .= "<li><a href='" . htmlentities($url) . "'>" . utf8entities($name) . "</a></li>\n";
            }
        }
        $html .= "</ul>\n";
    }
    $html .= "</div>\n";
    $html .= "<p style='clear:both'></p>\n";

    if ($echoed) {
        echo $html;
    }
    return $html;
}
