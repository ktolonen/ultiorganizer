<?php
include_once 'lib/team.functions.php';
include_once 'lib/club.functions.php';
include_once 'lib/country.functions.php';
include_once 'lib/url.functions.php';

$html = "";
$clubId = iget("club");
$profile = ClubInfo($clubId);

$title = _("Club Card").": ".utf8entities($profile['name']);

$html .= "<h1>".utf8entities($profile['name'])."</h1>";

$html .= "<table style='width:100%'><tr>";

if(!empty($profile['profile_image'])){
  $html .= "<td style='width:165px'><a href='".UPLOAD_DIR."clubs/$clubId/".$profile['profile_image']."'>";
  $html .= "<img src='".UPLOAD_DIR."clubs/$clubId/thumbs/".$profile['profile_image']."' alt='"._("Profile image")."'/></a></td>";
}else{
  $html .= "<td></td>";
}

$html .= "<td style='vertical-align:top;text-align:left'><table border='0'>";
$html .= "<tr><td></td></tr>";
if($profile['country']>0){
  $country_info = CountryInfo($profile['country']);
  $html .= "<tr><td class='profileheader'>"._("Country").":</td>";
  $html .= "<td style='white-space: nowrap;'><div style='float: left; clear: left;'>";
  $html .= "<a href='?view=countrycard&amp;country=". $country_info['country_id']."'>".utf8entities($country_info['name'])."</a>";
  $html .= "</div><div>&nbsp;<img src='images/flags/tiny/".$country_info['flagfile']."' alt=''/></div>";
  $html .= "</td></tr>\n";
}
if(!empty($profile['city'])){
  $html .= "<tr><td class='profileheader'>"._("City").":</td>";
  $html .= "<td>".utf8entities($profile['city'])."</td></tr>\n";

}

if(!empty($profile['founded'])){
  $html .= "<tr><td class='profileheader'>"._("Founded").":</td>";
  $html .= "<td>".$profile['founded']."</td></tr>\n";
}

if(!empty($profile['homepage'])){
  $html .= "<tr><td class='profileheader'>"._("Homepage").":</td>";
  if(substr(strtolower($profile['homepage']),0,4)=="http"){
    $html .= "<td><a href='".$profile['homepage']."'>".utf8entities($profile['homepage'])."</a></td></tr>\n";
  }else{
    $html .= "<td><a href='http://".$profile['homepage']."'>".utf8entities($profile['homepage'])."</a></td></tr>\n";
  }
}
if(!empty($profile['contacts'])){
  $contacts = utf8entities($profile['contacts']);
  $contacts = str_replace("\n",'<br/>',$contacts);
  $html .= "<tr><td class='profileheader' style='vertical-align:top'>"._("Contacts").":</td>";
  $html .= "<td>".$contacts."</td></tr>\n";
}

$html .= "</table>";
$html .= "</td></tr>";

if(!empty($profile['story'])){
  $story = utf8entities($profile['story']);
  $story = str_replace("\n",'<br/>',$story);
  $html .= "<tr><td colspan='2'>".$story."</td></tr>\n";
}
if(!empty($profile['achievements'])){
  $html .= "<tr><td colspan='2'>&nbsp;</td></tr>\n";
  $html .= "<tr><td class='profileheader' colspan='2'>"._("Achievements").":</td></tr>\n";
  $html .= "<tr><td colspan='2'></td></tr>\n";
  $achievements = utf8entities($profile['achievements']);
  $achievements = str_replace("\n",'<br/>',$achievements);
  $html .= "<tr><td colspan='2'>".$achievements."</td></tr>\n";
}
$urls = GetUrlList("club", $clubId);
if(count($urls)){
  $html .= "<tr><td colspan='2' class='profileheader' style='vertical-align:top'>"._("Club pages").":</td></tr>";
  $html .= "<tr><td colspan='2'><table>";
  foreach($urls as $url){
    $html .= "<tr>";
    $html .= "<td colspan='2'><img width='16' height='16' src='images/linkicons/".$url['type'].".png' alt='".$url['type']."'/> ";
    $html .= "</td><td>";
    if(!empty($url['name'])){
      $html .="<a href='". $url['url']."'>". $url['name']."</a>";
    }else{
      $html .="<a href='". $url['url']."'>". $url['url']."</a>";
    }
    $html .= "</td>";
    $html .= "</tr>";
  }
  $html .= "</table>";
  $html .= "</td></tr>";
}

$urls = GetMediaUrlList("club", $clubId);
if(count($urls)){
  $html .= "<tr><td colspan='2' class='profileheader' style='vertical-align:top'>"._("Photos and Videos").":</td></tr>";
  $html .= "<tr><td colspan='2'><table>";
  foreach($urls as $url){
    $html .= "<tr>";
    $html .= "<td colspan='2'><img width='16' height='16' src='images/linkicons/".$url['type'].".png' alt='".$url['type']."'/> ";
    $html .= "</td><td>";
    if(!empty($url['name'])){
      $html .="<a href='". $url['url']."'>". $url['name']."</a>";
    }else{
      $html .="<a href='". $url['url']."'>". $url['url']."</a>";
    }
    if(!empty($url['mediaowner'])){
      $html .=" "._("from")." ". $url['mediaowner'];
    }

    $html .= "</td>";
    $html .= "</tr>";
  }
  $html .= "</table>";
  $html .= "</td></tr>";
}

$html .= "</table>";

$teams = ClubTeams($clubId, CurrentSeason());
if(mysqli_num_rows($teams)){
  $html .= "<h2>".U_(CurrentSeasonName()).":</h2>\n";
  $html .= "<table style='white-space: nowrap;' border='0' cellspacing='0' cellpadding='2' width='90%'>\n";
  $html .= "<tr><th>"._("Team")."</th><th>"._("Division")."</th><th colspan='3'></th></tr>\n";

  while($team = mysqli_fetch_assoc($teams)){
    $html .= "<tr>\n";
    $html .= "<td style='width:30%'><a href='?view=teamcard&amp;team=".$team['team_id']."'>".utf8entities($team['name'])."</a></td>";
    $html .=  "<td  style='width:30%'><a href='?view=poolstatus&amp;series=". $team['series_id'] ."'>".utf8entities(U_($team['seriesname']))."</a></td>";
    if(IsStatsDataAvailable()){
      $html .=  "<td class='right' style='width:15%'><a href='?view=playerlist&amp;team=".$team['team_id']."'>"._("Roster")."</a></td>";
      $html .=  "<td class='right' style='width:15%'><a href='?view=scorestatus&amp;team=".$team['team_id']."'>"._("Scoreboard")."</a></td>";
    }else{
      $html .=  "<td class='right' style='width:30%'><a href='?view=scorestatus&amp;team=".$team['team_id']."'>"._("Players")."</a></td>";
    }
    $html .=  "<td class='right' style='width:10%'><a href='?view=games&amp;team=".$team['team_id']."'>"._("Games")."</a></td>";
    $html .= "</tr>\n";
  }
  $html .= "</table>\n";
}

$teams = ClubTeamsHistory($clubId);
if(mysqli_num_rows($teams)){
  $html .= "<h2>"._("History").":</h2>\n";
  $html .= "<table style='white-space: nowrap;' border='0' cellspacing='0' cellpadding='2' width='90%'>\n";
  $html .= "<tr><th>"._("Event")."</th><th>"._("Team")."</th><th>"._("Division")."</th><th colspan='3'></th></tr>\n";

  while($team = mysqli_fetch_assoc($teams)){
    $html .= "<tr>\n";
    $html .= "<td style='width:20%'>".utf8entities(U_(SeasonName($team['season'])))."</td>";
    $html .= "<td style='width:30%'><a href='?view=teamcard&amp;team=".$team['team_id']."'>".utf8entities($team['name'])."</a></td>";
    $html .=  "<td style='width:20%'><a href='?view=poolstatus&amp;series=". $team['series_id'] ."'>".utf8entities(U_($team['seriesname']))."</a></td>";

    if(IsStatsDataAvailable()){
      $html .=  "<td style='width:15%'><a href='?view=playerlist&amp;team=".$team['team_id']."'>"._("Roster")."</a></td>";
      $html .=  "<td style='width:15%'><a href='?view=scorestatus&amp;team=".$team['team_id']."'>"._("Scoreboard")."</a></td>";
    }else{
      $html .=  "<td style='width:30%'><a href='?view=scorestatus&amp;team=".$team['team_id']."'>"._("Players")."</a></td>";
    }
    $html .=  "<td style='width:10%'><a href='?view=games&amp;team=".$team['team_id']."'>"._("Games")."</a></td>";

    $html .= "</tr>\n";
  }
  $html .= "</table>\n";
}

if ($_SESSION['uid'] != 'anonymous') {
  $html .= "<div style='float:left;'><hr/><a href='?view=user/addmedialink&amp;club=$clubId'>"._("Add media")."</a></div>";
}

showPage($title, $html);

?>
