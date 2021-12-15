<?php
include_once 'lib/team.functions.php';
include_once 'lib/country.functions.php';

$html = "";
$countryId = intval(iget("country"));
$profile = CountryInfo($countryId);

$title = utf8entities(_($profile['name']));

$html .= "<h1>" . utf8entities(_($profile['name'])) . "</h1>";
$html .= "<img class='flag' src='images/flags/medium/" . $profile['flagfile'] . "' alt=''/>";
$season = CurrentSeason();
if (!empty($season)) {
  $teams = CountryTeams($countryId, $season);
  if (count($teams)) {
    $html .= "<h2>" . CurrentSeasonName() . ":</h2>\n";
    $html .= "<table style='white-space: nowrap;' border='0' cellspacing='0' cellpadding='2' width='90%'>\n";
    $html .= "<tr><th>" . _("Team") . "</th><th>" . _("Division") . "</th><th colspan='4'></th></tr>\n";

    foreach ($teams as $team) {
      $html .= "<tr>\n";
      $html .= "<td style='width:30%'><a href='?view=teamcard&amp;team=" . $team['team_id'] . "'>" . utf8entities($team['name']) . "</a></td>";
      $html .=  "<td  style='width:30%'><a href='?view=poolstatus&amp;series=" . $team['series_id'] . "'>" . utf8entities(U_($team['seriesname'])) . "</a></td>";
      if (IsStatsDataAvailable()) {
        $html .=  "<td class='right' style='width:10%'><a href='?view=playerlist&amp;team=" . $team['team_id'] . "'>" . _("Roster") . "</a></td>";
        $html .=  "<td class='right' style='width:10%'><a href='?view=scorestatus&amp;team=" . $team['team_id'] . "'>" . _("Scoreboard") . "</a></td>";
      } else {
        $html .=  "<td class='right' style='width:20%'><a href='?view=scorestatus&amp;team=" . $team['team_id'] . "'>" . _("Players") . "</a></td>";
      }
      $html .=  "<td class='right' style='width:20%'><a href='?view=games&amp;team=" . $team['team_id'] . "'>" . _("Games") . "</a></td>";
      $html .= "</tr>\n";
    }
    $html .= "</table>\n";
  }
}

$teams = CountryTeams($countryId);
if (count($teams)) {

  $national_html = "";
  $clubs_html = "";
  foreach ($teams as $team) {
    $tmphtml = "";
    $tmphtml .= "<tr>\n";
    $tmphtml .= "<td style='width:20%'>" . utf8entities(U_(SeasonName($team['season']))) . "</td>";
    $tmphtml .= "<td style='width:30%'><a href='?view=teamcard&amp;team=" . $team['team_id'] . "'>" . utf8entities($team['name']) . "</a></td>";
    $tmphtml .=  "<td style='width:20%'><a href='?view=poolstatus&amp;series=" . $team['series_id'] . "'>" . utf8entities(U_($team['seriesname'])) . "</a></td>";

    if (IsStatsDataAvailable()) {
      $tmphtml .=  "<td style='width:15%'><a href='?view=playerlist&amp;team=" . $team['team_id'] . "'>" . _("Roster") . "</a></td>";
      $tmphtml .=  "<td style='width:15%'><a href='?view=scorestatus&amp;team=" . $team['team_id'] . "'>" . _("Scoreboard") . "</a></td>";
    } else {
      $tmphtml .=  "<td style='width:30%'><a href='?view=scorestatus&amp;team=" . $team['team_id'] . "'>" . _("Players") . "</a></td>";
    }
    $tmphtml .=  "<td style='width:10%'><a href='?view=games&amp;team=" . $team['team_id'] . "'>" . _("Games") . "</a></td>";

    $tmphtml .= "</tr>\n";
    //do not list club ids here
    if ($team['club'] > 0) {
      $clubs_html .= $tmphtml;
    } else {
      $national_html .= $tmphtml;
    }
  }


  if (!empty($national_html)) {
    $html .= "<h2>" . _("History") . ":</h2>\n";
    $html .= "<table style='white-space: nowrap;' border='0' cellspacing='0' cellpadding='2' width='90%'>\n";
    $html .= "<tr><th>" . _("Event") . "</th><th>" . _("Team") . "</th><th>" . _("Division") . "</th><th colspan='4'></th></tr>\n";
    $html .= $national_html;
    $html .= "</table>\n";
  }

  if (!empty($clubs_html)) {
    $html .= "<h2>" . _("Club teams") . ":</h2>\n";
    $html .= "<table style='white-space: nowrap;' border='0' cellspacing='0' cellpadding='2' width='90%'>\n";
    $html .= "<tr><th>" . _("Event") . "</th><th>" . _("Team") . "</th><th>" . _("Division") . "</th><th colspan='4'></th></tr>\n";
    $html .= $clubs_html;
    $html .= "</table>\n";
  }
}

showPage($title, $html);
