<?php
include_once 'menufunctions.php';
include_once 'lib/search.functions.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/timetable.functions.php';
$urlparams = "";
$season = "";
$html = "";
$group = "all";
if (!empty($_GET["group"])) {
  $group  = $_GET["group"];
}

if (!empty($_GET["series"])) {
  $urlparams = "series=" . intval($_GET["series"]);
} elseif (!empty($_GET["pool"])) {
  $urlparams = "pool=" . intval($_GET["pool"]);
} elseif (!empty($_GET["season"])) {
  $urlparams = "season=" . $_GET["season"];
  $season = $_GET["season"];
}

if (!empty($_POST['remove_x'])) {
  $id = $_POST['hiddenDeleteId'];
  RemoveReservation($id, $season);
  $_POST['searchreservation'] = "1"; //do not hide search results
}
if (isset($_POST['schedule']) && isset($_POST['reservations'])) {
  //$url = "location:?view=admin/scheduling_grid&Reservations=".implode(",", $_POST['reservations']);
  $url = "location:?view=admin/schedule&reservations=" . implode(",", $_POST['reservations']);
  if (!empty($urlparams)) {
    $url .= "&" . $urlparams;
  }
  header($url);
  exit();
}
if (!empty($_POST['change_times'])) {
  $times = array();
  foreach ($_POST['loc'] as $i => $loc) {
    $times[$i]['location'] = $loc;
  }
  foreach ($_POST['field'] as $i => $field) {
    $times[$i]['field'] = $field;
  }
  foreach ($_POST['move'] as $from => $row) {
    foreach ($row as $to => $time) {
      $times[$from][$to] = $time;
    }
  }

  TimeTableSetMoveTimes($season, $times);
}

//common page
$title = _("Fields");
$LAYOUT_ID = RESERVATIONS;
pageTopHeadOpen($title);

$html .=  file_get_contents('script/rescalendar.inc');
include 'script/common.js.inc';
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

$searchItems = array();
$searchItems[] = 'searchstart';
$searchItems[] = 'searchend';
$searchItems[] = 'searchgroup';
$searchItems[] = 'searchlocation';

$hidden = array();
foreach ($searchItems as $name) {
  if (isset($_POST[$name])) {
    $hidden[$name] = $_POST[$name];
  }
}

$url = "view=admin/reservations";
if (!empty($urlparams)) {
  $url .= "&amp;" . $urlparams;
}
if (empty($season)) {
  $html .=  SearchReservation($url, $hidden, array('schedule' => _("Schedule selected")));
} else {
  $html .= "<p><a href='?view=admin/reservations'>" . _("Search") . "</a></p>";
  $groups = SeasonReservationgroups($season);
  if (count($groups) > 1) {
    $html .= "<p>\n";
    foreach ($groups as $grouptmp) {
      if ($group == $grouptmp['reservationgroup']) {
        $html .= "<a class='groupinglink' href='?view=admin/reservations&amp;season=$season&amp;group=" . urlencode($grouptmp['reservationgroup']) . "'><span class='selgroupinglink'>" . U_($grouptmp['reservationgroup']) . "</span></a>";
      } else {
        $html .= "<a class='groupinglink' href='?view=admin/reservations&amp;season=$season&amp;group=" . urlencode($grouptmp['reservationgroup']) . "'>" . U_($grouptmp['reservationgroup']) . "</a>";
      }
      $html .= "&nbsp;&nbsp;&nbsp;&nbsp;";
    }
    if ($group == "all") {
      $html .= "<a class='groupinglink' href='?view=admin/reservations&amp;season=$season&amp;group=all'><span class='selgroupinglink'>" . _("All") . "</span></a>";
    } else {
      $html .= "<a class='groupinglink' href='?view=admin/reservations&amp;season=$season&amp;group=all'>" . _("All") . "</a>";
    }
    $html .= "</p>\n";
  }
  $html .= "<form method='post' id='reservations' action='?view=admin/reservations&amp;season=$season&amp;group=" . urlencode($group) . "'>\n";
  $reservations = SeasonReservations($season, $group);
  $html .= "<table class='admintable'><tr><th><input type='checkbox' onclick='checkAll(\"reservations\");'/></th>";
  $html .= "<th>" . _("Group") . "</th><th>" . _("Location") . "</th><th>" . _("Date") . "</th>";
  $html .= "<th>" . _("Starts") . "</th><th>" . _("Ends") . "</th><th>" . _("Games") . "</th>";
  $html .= "<th>" . _("Scoresheets") . "</th><th></th></tr>\n";
  foreach ($reservations as $reservation) {
    $row = ReservationInfo($reservation['id']);
    $html  .= "<tr class='admintablerow'><td><input type='checkbox' name='reservations[]' value='" . utf8entities($row['id']) . "'/></td>";
    $html  .= "<td>" . utf8entities(U_($row['reservationgroup'])) . "</td>";
    $html  .= "<td><a href='?view=admin/addreservation&amp;reservation=" . $row['id'] . "&amp;season=" . $row['season'] . "'>" . utf8entities(U_($row['name'])) . " " . _("Field") . " " . utf8entities(U_($row['fieldname'])) . "</a></td>";
    $html  .= "<td>" . DefWeekDateFormat($row['starttime']) . "</td>";
    $html  .= "<td>" . DefHourFormat($row['starttime']) . "</td>";
    $html  .= "<td>" . DefHourFormat($row['endtime']) . "</td>";
    $html  .= "<td class='center'>" . $row['games'] . "</td>";
    $html  .= "<td class='center'><a href='?view=user/pdfscoresheet&amp;reservation=" . $row['id'] . "'>" . _("PDF") . "</a></td>";
    if (intval($row['games']) == 0) {
      $html  .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' name='remove' alt='" . _("X") . "' onclick=\"setId(" . $row['id'] . ");\"/></td>";
    }

    $html .= "</tr>\n";
  }
  $html .= "</table>\n";

  $html .= "<p>";
  $html .= "<input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/>\n";
  $html .= "<input type='submit' name='schedule' value='" . utf8entities(_("Schedule selected")) . "'/>\n";
  $html .= "</p>";

  $locations = SeasonReservationLocations($season, $group);
  $movetimes = TimetableMoveTimes($season);

  $html .= "<h2>" . _("Transfer times") . "</h2>";
  $html .= "<p>" . _("Minimum times (in minutes) to move between fields") . "</p>\n";
  $i = 0;
  foreach ($locations as $location) {
    $html .= "<input type='hidden' id='loc$i' name='loc[]' value='" . utf8entities($location['location']) . "'/>";
    $html .= "<input type='hidden' id='field$i' name='field[]' value='" . utf8entities($location['fieldname']) . "'/>";
    $html .= "<p>" . ($i + 1) . ": " . $location['name'] . " " . _("Field") . " " . $location['fieldname'] . "</p>\n";
    $i++;
  }

  $html .= "<table class='admintable'><tr><th>" . _("from\\to") . "</th>";
  $i = 0;
  foreach ($locations as $location) {
    $html .=  "<th>" . ($i + 1) . "</th>";
    ++$i;
  }
  $html .= "</tr>\n<tr>";
  $i = 0;
  foreach ($locations as $location1) {
    $html .= "<td>" . ($i + 1) . "</td>";
    $j = 0;
    foreach ($locations as $location2) {

      $html .= "<td><input type='text' size='4' maxlength='5' value='"
        . (TimeTableMoveTime($movetimes, $location1['location'], $location1['fieldname'], $location2['location'], $location2['fieldname']) / 60)
        . "' id='move" . $i . "_" . $j . "' name='move[$i][$j]' onkeypress='ChgTime(" . $i . "," . $j . ")'/></td>";
      $j++;
    }
    $html .= "</tr>\n";

    $i++;
  }
  /*
  $html .= "<input type='text' size='4' maxlength='5' value='0' id='setallvalue' name='setallvalue' />";
  $html .= "<input type='submit' name='setallbutton' value='" . utf8entities(_("Set all to this value")) . "'onkeypress='setTimes()'/>";
  */

  $html .= "</table>";

  $html .= "<input type='submit' name='change_times' value='" . utf8entities(_("Save times")) . "'/>\n";

  $html .= "</form>";
}

$html .= "\n<hr/>\n";
$html .= "<p><a href='?view=admin/addreservation&amp;season=" . $season . "'>" . _("Add reservation") . "</a> | ";
$html .= "<a href='?view=admin/locations&amp;season=" . $season . "'>" . _("Add location") . "</a></p>";

echo $html;

contentEnd();
pageEnd();
