<?php
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/game.functions.php';
include_once $include_prefix.'lib/statistical.functions.php';

if (is_file('cust/'.CUSTOMIZATIONS.'/head.php')) {
  include_once 'cust/'.CUSTOMIZATIONS.'/head.php';
} else {
  include_once 'cust/default/head.php';
}

/**
 * Shows html content with ultiorganizer menus and layout.
 *
 * @param string $title page's title
 * @param string $html page's content
 */
function showPage($title, $html, $mobile = false) {
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
function showPrintablePage($title, $html) {
  pageTop($title,true);
  leftMenu(0,true);
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
function pageTop($title, $printable=false) {
  pageTopHeadOpen($title);
  pageTopHeadClose($title, $printable);
}

/**
 * HTML code with page meta information. Leaves <head> tag open.
 *
 * @param string $title - the page title
 */
function pageTopHeadOpen($title) {
  global $include_prefix;
  $lang = explode("_", getSessionLocale());
  $lang = $lang[0];
  $icon = $include_prefix."cust/".CUSTOMIZATIONS."/favicon.png";
   
  echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
		<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='".$lang."' lang='".$lang."'";
  global $serverConf;
  if (IsFacebookEnabled()) {
    echo "\n		xmlns:fb=\"http://www.facebook.com/2008/fbml\"";

  }
  echo "><head>
		<meta http-equiv=\"Content-Style-Type\" content=\"text/css\"/>
		<meta http-equiv=\"Content-Script-Type\" content=\"text/javascript\"/>";
  //no cache
  echo "<meta http-equiv=\"Pragma\" content=\"no-cache\"/>";
  echo "<meta http-equiv=\"Expires\" content=\"-1\"/>";

  echo	"<link rel='icon' type='image/png' href='$icon' />
		<title>". GetPageTitle(). "" .$title."</title>\n";
  echo styles();
  include $include_prefix.'script/common.js.inc';
  global $include_prefix;
  include_once $include_prefix.'script/help.js.inc';
  
}


/**
 * HTML code for header and navigation bar.
 *
 * @param string $title - title of the page
 * @param boolean $printable - if true then no header produced.
 * @param string $bodyfunctions - insert additional attributes/functions in body tag
 */
function pageTopHeadClose($title, $printable=false, $bodyfunctions=""){

  if(isset($_SESSION['uid'])) {
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

  echo "</head><body style='overflow-y:scroll;' ".$bodyfunctions.">\n";
  echo "<div class='page'>\n";

  if(!$printable)	{
    echo "<div class='page_top'>\n";

    echo "<div class='top_banner_space'></div>\n";
    echo "<form action='?".utf8entities($query_string)."' method='post'>";
    echo "<table border='0' cellpadding='0' cellspacing='0' style='width:100%;white-space: nowrap;'><tr>\n";

    //top header left part can be customized
    echo "<td class='topheader_left'>\n";
    echo pageHeader();
    echo "</td>";

    //top header right part contains common elements
    echo "<td class='topheader_right'>";
    echo "<table border='0' cellpadding='0' cellspacing='0' style='width:95%;white-space: nowrap;'>\n";

    //1st row: Locale selection
    echo "<tr>";
    echo "<td class='right' style='vertical-align:top;'>".localeSelection()."</td>";
    echo "</tr>";

    //2nd row: User Log in
    echo "<tr>\n";
    echo "<td class='right' style='padding-top:5px'>";

    if (IsFacebookEnabled() && $user == 'anonymous') {
      echo "<div id='fb-root'></div>\n";
      echo "<fb:login-button perms='email,publish_stream,offline_access'/>\n";
    }

    if ($user == 'anonymous') {
      echo "<input class='input' type='text' id='myusername' name='myusername' size='10' style='border:1px solid #555555'/>&nbsp;";
      echo "<input class='input' type='password' id='mypassword' name='mypassword' size='10' style='border:1px solid #555555'/>&nbsp;";
      echo "<input class='button' type='submit' name='login' value='"._("Login")."' style='border:1px solid #000000'/>";
    } else {
      $userinfo = UserInfo($user);
      echo "<span class='topheadertext'>"._("User").": <a class='topheaderlink' href='?view=user/userinfo'>".utf8entities($userinfo['name'])."</a></span>";
    }

    echo "&nbsp;";

    if ($user == 'anonymous') {
      echo "<span class='topheadertext'><a class='topheaderlink' href='?view=register'>"._("New user?")."</a></span>";
    }else{
      echo "<span class='topheadertext'><a class='topheaderlink' href='?view=logout'>&raquo; "._("Logout")."</a></span>";
    }
    echo "</td></tr>\n";
    echo "</table>";
    echo "</td></tr></table>";
    echo "</form>\n";
    echo "</div>\n";

    //navigation bar
    echo "<div class='navigation_bar'><p class='navigation_bar_text'>";
    echo navigationBar($title)."</p></div>";
  }

  echo "<div class='page_middle'>\n";

}

/**
 * Start of page content.
 */
function contentStart() {
  echo "\n<td align='left' valign='top' class='tdcontent'><div class='content'>\n";
}

/**
 * End of page content.
 */
function contentEnd() {
  echo "\n</div></td></tr></table></div>\n";
}

/**
 * End of the page.
 */
function pageEnd() {
  global $serverConf;
  if (IsFacebookEnabled()) {
    echo "<script src='http://connect.facebook.net/en_US/all.js'></script>
    <script>
      FB.init({appId: '";
    echo $serverConf['FacebookAppId'];
    echo "', status: true,
               cookie: true, xfbml: true});
      FB.Event.subscribe('auth.login', function(response) {
        window.location.reload();
      });
    </script>";	
  }
  echo "<div class='page_bottom'></div>";
  echo "</div></body></html>";
}


/**
 * Adds on page help.
 *
 * @param string $html - html-text shown when help button pressed.
 */
function onPageHelpAvailable($html) {
  return "<div style='float:right;'>
	<input type='image' class='helpbutton' id='helpbutton' src='images/help-icon.png'/></div>\n
	<div id='helptext' class='yui-pe-content'>$html<hr/></div>";
}


/**
 * Top of Mobile page.
 *
 * @param String $title - page title
 */
function mobilePageTop($title) {
  pageTopHeadOpen($title);

  echo "</head><body style='overflow-y:scroll;'>\n";
  echo "<div class='mobile_page'>\n";
}

function mobilePageEnd($query="") {
  if ($query=="")
    $query=$_SERVER['QUERY_STRING'];
  if (!isset($_SESSION['uid']) || $_SESSION['uid'] == "anonymous") {
    
    $html = "<form action='?" . utf8entities($query) . "' method='post'>\n";
    $html .= "<table cellpadding='2'>\n";
    $html .= "<tr><td>\n";
    $html .= _("Username") . ":";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<input class='input' type='text' id='myusername' name='myusername' size='15'/> ";
    $html .= "</td></tr><tr><td>\n";
    $html .= _("Password") . ":";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<input class='input' type='password' id='mypassword' name='mypassword' size='15'/> ";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<input class='button' type='submit' name='login' value='" . _("Login") . "'/>";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<hr/>\n";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<a href='?view=frontpage'>" . _("Back to the Ultiorganizer") . "</a>";
    $html .= "</td></tr>\n";
    $html .= "</table>\n";
    $html .= "</form>";
  }else {
    if ($query != "") {
      header($query);
    }
    // $user = $_SESSION['uid'];
    // $userinfo = UserInfo($user);
    $html = "<table cellpadding='2'>\n";
    $html .= "<tr><td></td></tr>\n";
    $html .= "<tr><td><hr /></td></tr><tr><td>\n";
    $html .= "<a href='?view=frontpage'>" . _("Back to the Ultiorganizer") . "</a>";
    $html .= "</td></tr><tr><td>\n";
    $html .= "<a href='?view=mobile/logout'>" . _("Logout") . "</a></td></tr></table>";
  }
  
  global $serverConf;
  if (IsFacebookEnabled()) {
    $html .= "<script src='http://connect.facebook.net/en_US/all.js'></script>
    <script>
      FB.init({appId: '";
    $html .= $serverConf['FacebookAppId'];
    $html .= "', status: true,
               cookie: true, xfbml: true});
      FB.Event.subscribe('auth.login', function(response) {
        window.location.reload();
      });
    </script>";
  }
  $html .= "<div class='page_bottom'></div>";
  $html .= "</div></body></html>";
  echo $html;
}

/**
 * Creates locale selection html-code.
 */
function localeSelection() {
  global $locales;

  $ret = "";

  foreach ($locales as $localestr => $localename) {
    $query_string = StripFromQueryString($_SERVER['QUERY_STRING'], "locale");
    $query_string = StripFromQueryString($query_string, "goindex");
    $ret .= "<a href='?".utf8entities($query_string)."&amp;";
    $ret .= "locale=".$localestr."'><img class='localeselection' src='locale/".$localestr."/flag.png' alt='".utf8entities($localename)."'/></a>\n";
  }

  return $ret;
}

/**
 * Navigation bar functionality and html-code.
 *
 * @param string $title - page title
 */
function navigationBar($title) {
  $ret = "";
  $ptitle = "";
  if(isset($_SERVER['QUERY_STRING']))
  $query_string = $_SERVER['QUERY_STRING'];
  else
  $query_string = "";

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
  } else if (isset($_GET['goindex']) && $_GET['goindex'] <= 1) {
    $_SESSION['navigation'] = array("view=frontpage" => _("Home"));
  } else {
    if (!isset($_SESSION['navigation'])) {
      if (strlen($query_string) == 0 || (isset($_GET['view']) && $_GET['view'] == 'logout')) {
        $_SESSION['navigation'] = array("view=frontpage" => _("Home"));
      } elseif(!empty($title)){
        $_SESSION['navigation'] = array($query_string => $title);
      }
    } else {
      if (strlen($query_string) == 0) {
        $_SESSION['navigation']["view=frontpage"] = _("Home");
      } elseif(!empty($title)){
        unset($_SESSION['navigation'][$query_string]);

        //if previous view was having same title, remove it. e.g. when navigating back and forth in profiles or in case of sorting pages trough url parameter
        $lastvalue = end($_SESSION['navigation']);
        if($lastvalue){
          if($lastvalue == $title){
            $lastkey = end((array_keys($_SESSION['navigation'])));
            unset($_SESSION['navigation'][$lastkey]);
          }
        }
        $_SESSION['navigation'][$query_string] = $title;
      }
    }
  }

  $i=1;
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
          $ret .= "<a href='?".utf8entities($view)."&amp;goindex=".$i."'>".$ptitle."</a> &raquo; ";
        }
      }
      $i++;
    }
  }
  $ret = $ret." ".$ptitle;

  return $ret;
}

/**
 * Season selection html-code.
 */
function seasonSelection(){
  $seasons = CurrentSeasons();
  if(mysql_num_rows($seasons)>1){
    echo "<table><tr><td>";
    echo "<form action='?view=index' method='get' id='seasonsels'>";
    echo "<div><select class='seasondropdown' name='selseason'
			onchange='changeseason(selseason.options[selseason.options.selectedIndex].value);'>";
    while($row = mysql_fetch_assoc($seasons)){
      $selected = "";
      if (isset($_SESSION['userproperties']['selseason']) && $_SESSION['userproperties']['selseason']==$row['season_id']) {
        $selected = "selected='selected'";
      }
      echo  "<option class='dropdown' $selected value='".utf8entities($row['season_id'])."'>". SeasonName($row['season_id']) ."</option>";
    }
    echo "</select>";
    echo "<noscript><div><input type='submit' value='"._("Go")."' name='selectseason'/></div></noscript>";
    echo "</div></form>";
    echo "</td></tr></table>";
  }
}

/**
 * Creates menus on left side of page.
 *
 * @param int $id - page id (not used now days)
 * @param boolean $printable - if true, menu is not drawn.
 */
function leftMenu($id=0, $printable=false) {

  if($printable) {
    echo "<table style='width:100%;'><tr>";
    return;
  }

  echo "<table style='border:1px solid #fff;background-color: #ffffff;'><tr><td class='menu_left'>";

  // Administration menu
  if (hasScheduleRights() || isSuperAdmin() || hasTranslationRight()) {
    echo "<table class='leftmenulinks'>\n";
    echo "<tr><td class='menuseasonlevel'>"._("Administration")."</td></tr>";
  }
  if (isSuperAdmin()) {
    echo "<tr><td>\n";
    echo "<a class='subnav' href='?view=admin/seasons'>&raquo; "._("Events")."</a>\n";
    echo "<a class='subnav' href='?view=admin/serieformats'>&raquo; "._("Rule templates")."</a>\n";
    echo "<a class='subnav' href='?view=admin/clubs'>&raquo; "._("Clubs & Countries")."</a>\n";
    echo "<a class='subnav' href='?view=admin/locations'>&raquo; "._("Field locations")."</a>\n";
    echo "<a class='subnav' href='?view=admin/reservations'>&raquo; "._("Field reservations")."</a>\n";
  }
  if (hasScheduleRights()) {
    echo "<tr><td><a class='subnav' href='?view=admin/schedule'>&raquo; "._("Scheduling")."</a>";
  }

  if (hasTranslationRight()) {
    echo "<a class='subnav' href='?view=admin/translations'>&raquo; "._("Translations")."</a>\n";
  }
  if (isSuperAdmin()) {
    echo "<a class='subnav' href='?view=admin/users'>&raquo; "._("Users")."</a>\n";
    echo "<a class='subnav' href='?view=admin/eventviewer'>&raquo; "._("Logs")."</a>\n";
    //echo "<a class='subnav' href='?view=admin/sms'>&raquo; "._("SMS")."</a>\n";
    echo "<a class='subnav' href='?view=admin/dbadmin'>&raquo; "._("Database")."</a>\n";
    echo "<a class='subnav' href='?view=admin/serverconf'>&raquo; "._("Settings")."</a>\n";
    echo "<a class='subnav' href='?view=admin/help'>&raquo; "._("Helps")."</a>\n";
  }

  if (hasScheduleRights() || isSuperAdmin() || hasTranslationRight()) {
    echo "</td></tr>\n";
    echo "</table>\n";
  }

  //Event administration menu
  $editlinks = getEditSeasonLinks();
  if (count($editlinks)) {
    foreach ($editlinks as $season => $links) {
      echo "<table class='leftmenulinks'>\n";
      echo "<tr><td class='menuseasonlevel'>".utf8entities(SeasonName($season))." "._("Administration")."</td>";
      echo "<td class='menuseasonlevel'><a style='text-decoration: none;' href='?view=frontpage&amp;hideseason=$season'>x</a></td>";
      echo "</tr><tr><td>\n";
      foreach ($links as $href => $name) {
        echo "<a class='subnav' href='".$href."'>&raquo; ".$name."</a>\n";
      }
      echo "</td></tr>\n";
      echo "</table>\n";
    }
  }

  //Create new event menu
  if (isSuperAdmin()) {
    echo "<table class='leftmenulinks'>\n";
    //echo "<tr><td class='menuseasonlevel'>"._("New Event")."</td></tr>";
    //echo "</td></tr>\n";
    echo "<tr><td>\n";
    echo "<a class='subnav' href='?view=admin/addseasons'>&raquo; "._("Create new event")."</a>\n";
    echo "</td></tr>\n";
    echo "</table>\n";
  }

  //Team registration
  if ($_SESSION['uid'] != 'anonymous') {
    $enrollSeasons = EnrollSeasons();
    if (count($enrollSeasons) > 0) {
      echo "<table class='leftmenulinks'>\n";
      echo "<tr><td class='menuseasonlevel'>"._("Team registration")."</td></tr>\n";
      echo "<tr><td>\n";
      foreach ($enrollSeasons as $seasonId => $seasonName) {
        echo "<a class='subnav' href='?view=user/enrollteam&amp;season=".$seasonId."'>&raquo; ".utf8entities(U_($seasonName))."</a>\n";
      }
      echo "</td></tr>\n";
      echo "</table>\n";
    }
  }
  // Player profiles
  if (hasPlayerAdminRights()) {
    echo "<table class='leftmenulinks'>\n";
    echo "<tr><td class='menuseasonlevel'>"._("Player profiles")."</td></tr>\n";
    echo "<tr><td>\n";
    foreach ($_SESSION['userproperties']['userrole']['playeradmin'] as $profile_id => $propid) {
      $playerInfo = PlayerProfile($profile_id);
      echo "<a class='subnav' href='?view=user/playerprofile&amp;profile=".$playerInfo['profile_id']."'>&raquo; ".$playerInfo['firstname']." ".$playerInfo['lastname']."</a>\n";
    }
    echo "</td></tr>";
    echo "</table>\n";
  }

  //event public part: schedule, played games, teams, divisions, pools...
  seasonSelection();
  $curseason = CurrentSeason();

  echo "<table class='leftmenulinks'>\n";
  $pools = getViewPools($curseason);
  if ($pools && mysql_num_rows($pools)) {
    $lastseason = "";
    $lastseries = "";
    while ($row = mysql_fetch_assoc($pools)) {
      $season = $row['season'];
      $series = $row['series'];
      if ($lastseason != $season) {
        $lastseason = $season;
        echo "<tr><td class='menuseasonlevel'><a class='seasonnav' style='text-align:center;' href='?view=teams&season=".urlencode($season)."&amp;list=bystandings'>";
        echo utf8entities(U_($row['season_name']))."</a></td></tr>\n";
        echo "<tr><td><a class='nav' href='?view=games&amp;season=".urlencode($season)."&amp;filter=tournaments&amp;group=all'>"._("Games")."</a></td></tr>\n";
        //echo "<tr><td><a class='nav' href='?view=played&amp;season=".urlencode($season)."'>"._("Played games")."</a></td></tr>\n";
        echo "<tr><td><a class='nav' href='?view=teams&amp;season=".urlencode($season)."&amp;list=allteams'>"._("Teams")."</a></td></tr>\n";
        echo "<tr><td class='menuseparator'></td></tr>\n";
      }

      if ($lastseries != $series) {
        $lastseries = $series;
        echo "<tr><td class='menuserieslevel'>";
        echo "<a class='subnav' href='?view=seriesstatus&amp;series=".$series."'>". utf8entities(U_($row['series_name'])) ."</a></td></tr>\n";
        echo "<tr><td class='navpoollink'>\n";
        echo "<a class='subnav' href='?view=poolstatus&amp;series=".$series."'>&raquo; ". _("Show all pools") ."</a></td></tr>\n";
      }
      echo "<tr><td class='menupoollevel'>\n";
      echo "<a class='navpoollink' href='?view=poolstatus&amp;pool=".$row['pool']."'>&raquo; ".utf8entities(U_($row['pool_name']))."</a>\n";
      echo "</td></tr>\n";
    }
  }else{
    $season = CurrentSeason();
    echo "<tr><td class='menuseasonlevel'><a class='seasonnav' style='text-align:center;' href='?view=eventstatus&amp;season=";
    echo urlencode($season)."'>".utf8entities(U_(CurrentSeasonName()))."</a></td></tr>\n";
    echo "<tr><td><a class='nav' href='?view=timetables&amp;season=".urlencode($season)."&amp;filter=tournaments&amp;group=all'>"._("Games")."</a></td></tr>\n";
    //  echo "<tr><td><a class='nav' href='?view=played&amp;season=".urlencode($season)."'>"._("Played games")."</a></td></tr>\n";
    echo "<tr><td><a class='nav' href='?view=teams&amp;season=".urlencode($season)."'>"._("Teams")."</a></td></tr>\n";
    echo "<tr><td class='menuseparator'></td></tr>\n";

    $tmpseries = SeasonSeries($season,true);
    foreach($tmpseries as $row) {
      echo "<tr><td class='menuserieslevel'>".utf8entities(U_($row['name']))."</td></tr>\n";
      echo "<tr><td class='menupoollevel'>\n";
      echo _("Pools not yet created");
      echo "</td></tr>\n";
    }
  }
  echo "</table>\n";

  //event links
  echo "<table class='leftmenulinks'>\n";
  echo "<tr><td class='menuseasonlevel'>"._("Event Links")."</td></tr>\n";
  echo "<tr><td>";

  $urls = GetUrlListByTypeArray(array("menulink","menumail"),$curseason);
  foreach($urls as $url){
    if($url['type']=="menulink"){
      echo "<a class='subnav' href='".$url['url']."'>&raquo; ".U_($url['name'])."</a>\n";
    }elseif($url['type']=="menumail"){
      echo "<a class='subnav' href='mailto:".$url['url']."'>@ ".U_($url['name'])."</a>\n";
    }
  }
  echo "</td></tr>\n";
  echo "<tr><td>";
  echo "<a class='subnav' style='background: url(./images/linkicons/feed_14x14.png) no-repeat 0 50%; padding: 0 0 0 19px;' href='./ext/rss.php?feed=all'>"._("Result Feed")."</a>\n";
  echo "</td></tr>\n";
  if(IsTwitterEnabled()){
    $savedurl = GetUrl("season",$season,"result_twitter");
    if(!empty($savedurl['url'])){
      echo "<tr><td>";
      echo "<a class='subnav' style='background: url(./images/linkicons/twitter_14x14.png) no-repeat 0 50%; padding: 0 0 0 19px;' href='".$savedurl['url']."'>"._("Result Twitter")."</a>\n";
      echo "</td></tr>\n";
    }
  }
  echo "</table>\n";

  //event history
  if(IsStatsDataAvailable()){
    echo "<table class='leftmenulinks'>\n";
    echo "<tr><td class='menuseasonlevel'>"._("Statistics")."</td></tr>\n";
    echo "<tr><td>";
    echo "<a class='subnav' href=\"?view=seasonlist\">&raquo; "._("Events")."</a>\n";
    echo "<a class='subnav' href=\"?view=allplayers\">&raquo; "._("Players")."</a>\n";
    echo "<a class='subnav' href=\"?view=allteams\">&raquo; "._("Teams")."</a>\n";
    echo "<a class='subnav' href=\"?view=allclubs\">&raquo; "._("Clubs")."</a>\n";
    $countries = CountryList(true,true);
    if(count($countries)){
      echo "<a class='subnav' href=\"?view=allcountries\">&raquo; "._("Countries")."</a>\n";
    }
    echo "<a class='subnav' href=\"?view=statistics&amp;list=teamstandings\">&raquo; "._("All time")."</a></td></tr>\n";
    echo "</table>";
  }

  //External access
  echo "<table class='leftmenulinks'>\n";
  echo "<tr><td class='menuseasonlevel'>"._("Client access")."</td></tr>\n";
  echo "<tr><td>";
  echo "<a class='subnav' href='?view=ext/index'>&raquo; "._("Ultiorganizer links")."</a>\n";
  echo "<a class='subnav' href='?view=ext/export'>&raquo; "._("Data export")."</a>\n";
  echo "<a class='subnav' href='?view=mobile/index'>&raquo; "._("Mobile Administration")."</a>\n";
  echo "<a class='subnav' href='./scorekeeper/'>&raquo; "._("Scorekeeper")."</a>\n";
  echo "</td></tr>\n";
  echo "</table>";

  echo "<table class='leftmenulinks'>\n";
  echo "<tr><td class='menuseasonlevel'>"._("Links")."</td></tr>\n";
  echo "<tr><td>";
  $urls = GetUrlListByTypeArray(array("menulink","menumail"),0);
  foreach($urls as $url){
    if($url['type']=="menulink"){
      echo "<a class='subnav' href='".$url['url']."'>&raquo; ".U_($url['name'])."</a>\n";
    }elseif($url['type']=="menumail"){
      echo "<a class='subnav' href='mailto:".$url['url']."'>@ ".U_($url['name'])."</a>\n";
    }
  }
  echo "</td></tr>\n";
  echo "</table>";

  //draw customizable logo if any
  echo logo();

  echo "<table style='width:90%'>\n";
  echo "<tr><td class='guides'>";
  echo "<a href='?view=user_guide'>"._("User Guide")."</a> | \n";
  echo "<a href='?view=privacy'>"._("Privacy Policy")."</a>\n";
  echo "</td></tr>";
  echo "</table>";

  echo "</td>\n";
}

/**
 * Get event administration links.
 */
function getEditSeasonLinks() {
  $ret = array();
  if (isset($_SESSION['userproperties']['editseason'])) {
    $editSeasons = getEditSeasons($_SESSION['uid']);
    foreach ($editSeasons as $season => $propid) {
      $ret[$season] = array();
    }
    $respgamesset = array();
    foreach ($ret as $season => $links) {
      if (isSeasonAdmin($season)) {
        $links['?view=admin/seasonadmin&amp;season='.$season] = _("Event");
        $links['?view=admin/seasonseries&amp;season='.$season] = _("Divisions");
        $links['?view=admin/seasonteams&amp;season='.$season] = _("Teams");
        $links['?view=admin/seasonpools&amp;season='.$season] = _("Pools");
        $links['?view=admin/seasongames&amp;season='.$season] = _("Games");
        $links['?view=admin/seasonstandings&amp;season='.$season] = _("Standings");
        $links['?view=admin/reservations&amp;season='.$season] = _("Field reservations");
        $links['?view=admin/accreditation&amp;season='.$season] = _("Accreditation");
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
          $links['?view=admin/seasonteams&amp;season='.$season.'&amp;series='.$series] = $seriesname." "._("Teams");
          $links['?view=admin/seasongames&amp;season='.$season.'&amp;series='.$series] = $seriesname." "._("Games");
          $links['?view=admin/seasonstandings&amp;season='.$season.'&amp;series='.$series] = $seriesname." "._("Pool standings");
          $links['?view=admin/accreditation&amp;season='.$seriesseason] = _("Accreditation");
          $ret[$seriesseason] = $links;
          $respgamesset[$seriesseason] = "set";
        }
      }
    }

    $teamPlayersSet = array();
    if (isset($_SESSION['userproperties']['userrole']['teamadmin'])) {

      foreach ($_SESSION['userproperties']['userrole']['teamadmin'] as $team => $param) {
        $teamseason = getTeamSeason($team);
        $teamresps = TeamResponsibilities($_SESSION['uid'],$teamseason);
        if (isset($ret[$teamseason])) {
          if(count($teamresps)<2){
            $teamname = getTeamName($team);
            $links = $ret[$teamseason];
            $links['?view=user/teamplayers&amp;team='.$team] = _("Team").": ".utf8entities($teamname);
            $respgamesset[$teamseason] = "set";
            $teamPlayersSet["".$team] = "set";
            $ret[$teamseason] = $links;
          }else{
            $links = $ret[$teamseason];
            $links['?view=user/respteams&amp;season='.$teamseason] = _("Team responsibilities");
            $respgamesset[$teamseason] = "set";
            $ret[$teamseason] = $links;
          }
        }
      }
    }
    if (isset($_SESSION['userproperties']['userrole']['accradmin'])) {
      if(count($_SESSION['userproperties']['userrole']['teamadmin'])<=4){
        foreach ($_SESSION['userproperties']['userrole']['accradmin'] as $team => $param) {
          if (!isset($teamPlayersSet[$team])) {
            $teamseason = getTeamSeason($team);
            if (isset($ret[$teamseason])) {
              $teamname = getTeamName($team);
              $links = $ret[$teamseason];
              $links['?view=user/teamplayers&amp;team='.$team] = _("Team").": ".utf8entities($teamname);
              $links['?view=admin/accreditation&amp;season='.$teamseason] = _("Accreditation");
              $teamPlayersSet["".$team] = "set";
              $ret[$teamseason] = $links;
            }
          }
        }
      }else{
        $links = $ret[$season];
        $links['?view=user/respteams&amp;season='.$season] = _("Team responsibilities");
        $links['?view=admin/accreditation&amp;season='.$season] = _("Accreditation");
        $ret[$season] = $links;
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
        foreach(ReservationSeasons($resId) as $resSeason) {
          if (isset($ret[$resSeason])) {
            $respgamesset[$resSeason] = "set";
          }
        }
      }
    }
    foreach ($respgamesset as $season => $set) {
      $links = $ret[$season];
      $links['?view=user/respgames&amp;season='.$season] = _("Game responsibilities");
      $links['?view=user/contacts&amp;season='.$season] = _("Contacts");
      $ret[$season] = $links;
    }
  }

  foreach ($ret as $season => $links) {
    if (!isset($links) || empty($links) || count($links) == 0) {
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
 * @return the menu
 *
 */
function pageMenu($menuitems, $current="", $echoed=true) {

  $html = "\n<!-- on page menu -->\n";
  $html .= "<div class='pagemenu_container'>\n";
  $line = "";
  foreach ($menuitems as $name => $url) {
    $line .= utf8entities($name);
    $line .= " - ";
  }
  if (strlen($line) < 120) {
    $html .= "<table id='pagemenu'><tr>\n";
    $first = true;
    foreach ($menuitems as $name => $url) {
      if (!$first)
        $html .= "<td> - </td>";
      $first = false;
      if($url==$current || strrpos($_SERVER["REQUEST_URI"],$url)) {
        $html .= "<th><a class='current' href='".htmlentities($url)."'>".utf8entities($name)."</a></th>\n";
      } else {
        $html .= "<th><a href='".htmlentities($url)."'>".utf8entities($name)."</a></th>\n";
      }
    }
    $html .= "</tr></table>";
  } else {
    $html .= "<ul id='pagemenu'>\n";

    foreach ($menuitems as $name => $url) {

      if($url==$current){
        $html .= "<li><a class='current' href='".htmlentities($url)."'>".utf8entities($name)."</a></li>\n";
      } elseif(strrpos($_SERVER["REQUEST_URI"],$url)) {
        $html .= "<li><a class='current' href='".htmlentities($url)."'>".utf8entities($name)."</a></li>\n";
      } else {
        $html .= "<li><a href='".htmlentities($url)."'>".utf8entities($name)."</a></li>\n";
      }
    }
    $html .= "</ul>\n";
  }
  $html .= "</div>\n";
  $html .= "<p style='clear:both'></p>\n";

  if($echoed){
    echo $html;
  }
  return $html;
}
?>
