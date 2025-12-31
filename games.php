<?php
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/timetable.functions.php';

function pdf_slug($value)
{
  $slug = strtolower((string)$value);
  $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
  $slug = trim($slug, '-');
  return $slug === '' ? 'pdf' : $slug;
}

if (is_file('cust/' . CUSTOMIZATIONS . '/pdfprinter.php')) {
  include_once 'cust/' . CUSTOMIZATIONS . '/pdfprinter.php';
} else {
  include_once 'cust/default/pdfprinter.php';
}

$html = "";
$filter = 'tournaments';
$baseurl = "?view=games";
$id = 0;
$print = 0;
$gamefilter = "season";
$format = "html";
$group = "";
$groupheader = true;
$games;

if (iget("series")) {
  $id = iget("series");
  $baseurl .= "&series=$id";
  $gamefilter = "series";
  $title = _("Schedule") . " " . utf8entities(U_(SeriesName($id)));
} elseif (iget("pool")) {
  $id = iget("pool");
  $baseurl .= "&pool=$id";
  $gamefilter = "pool";
  $title = _("Schedule") . " " . utf8entities(U_(PoolSeriesName($id)) . ", " . U_(PoolName($id)));
} elseif (iget("pools")) {
  $poolIds = array_filter(array_map('intval', explode(',', iget("pools"))), function ($val) {
    return $val > 0;
  });
  if (!empty($poolIds)) {
    $id = implode(",", $poolIds);
    $baseurl .= "&pools=$id";
    $gamefilter = "poolgroup";
    $title = _("Schedule") . " " . utf8entities(U_(PoolSeriesName($id)) . ", " . U_(PoolName($id)));
  } else {
    // Fall back to season view if pool ids are invalid/empty
    $id = CurrentSeason();
    $baseurl .= "&season=$id";
    $gamefilter = "season";
    $title = _("Schedule") . " " . utf8entities(U_(SeasonName($id)));
  }
} elseif (iget("team")) {
  $id = iget("team");
  $baseurl .= "&team=$id";
  $gamefilter = "team";
  $filter = 'places';
  $title = _("Schedule") . " " . utf8entities(TeamName($id));
} elseif (iget("season")) {
  $id = iget("season");
  $baseurl .= "&season=$id";
  $gamefilter = "season";
  $title = _("Schedule") . " " . utf8entities(U_(SeasonName($id)));
  $comment = CommentHTML(1, $id);
} else {
  $id = CurrentSeason();
  $baseurl .= "&season=$id";
  $gamefilter = "season";
  $title = _("Schedule") . " " . utf8entities(U_(SeasonName($id)));
}

$filter  = iget("filter");
if (empty($filter)) {
  $filter = 'tournaments';
}

$group  = iget("group");
if (empty($group)) {
  $group = "all";
}

if (iget("print")) {
  $print = intval(iget("print"));
  $format = "paper";
}

$singleview = 0;

if (iget("singleview")) {
  $singleview = intval(iget("singleview"));
}

$timefilter = "all";
$order = "tournaments";

switch ($filter) {
  case "today":
    $timefilter = "today";
    $order = "series";
    break;

  case "tomorrow":
    $timefilter = "tomorrow";
    $order = "series";
    break;

  case "yesterday":
    $timefilter = "yesterday";
    $order = "series";
    break;

  case "next":
    $timefilter = "all";
    $order = "tournaments";
    break;

  case "tournaments":
    $timefilter = "all";
    $order = "tournaments";
    break;

  case "series":
    $timefilter = "all";
    $order = "series";
    break;

  case "places":
    $timefilter = "all";
    $order = "places";
    break;

  case "season":
    $timefilter = "all";
    $order = "places";
    $format = "pdf";
    break;

  case "onepage":
    $timefilter = "all";
    $order = "onepage";
    $format = "pdf";
    break;

  case "timeslot":
    $timefilter = "all";
    $order = "time";
    break;

  default:
    $timefilter = "all";
    $order = "tournaments";
    break;
}

$games = TimetableGames($id, $gamefilter, $timefilter, $order, $group);
$groups = TimetableGrouping($id, $gamefilter, $timefilter);

if ($format == "pdf") {
  $nameLabel = "";
  switch ($gamefilter) {
    case "season":
      $nameLabel = SeasonName($id);
      break;
    case "series":
      $nameLabel = SeriesName($id);
      break;
    case "pool":
      $nameLabel = PoolName($id);
      break;
    case "team":
      $nameLabel = TeamName($id);
      break;
    case "poolgroup":
      $nameLabel = "pools-" . $id;
      break;
    default:
      $nameLabel = $id;
      break;
  }
  $layout = $filter == "onepage" ? "grid" : "list";
  $filename = "schedule-" . $layout . "-" . pdf_slug($nameLabel) . ".pdf";
  $pdf = new PDF();
  if ($filter == "onepage") {
    $pdf->PrintOnePageSchedule($gamefilter, $id, $games);
  } else {
    $pdf->PrintSchedule($gamefilter, $id, $games);
  }
  $pdf->Output('I', $filename);
}

if (!$print && !$singleview) {
  $menutabs[_("By grouping")] = ($baseurl) . "&filter=tournaments&group=$group";
  $menutabs[_("By timeslot")] = ($baseurl) . "&filter=timeslot&group=$group";
  $menutabs[_("By division")] = ($baseurl) . "&filter=series&group=$group";
  $menutabs[_("By location")] = ($baseurl) . "&filter=places&group=$group";
  $menutabs[_("Today")] = ($baseurl) . "&filter=today&group=$group";
  $menutabs[_("Tomorrow")] = ($baseurl) . "&filter=tomorrow&group=$group";
  $menutabs[_("Yesterday")] = ($baseurl) . "&filter=yesterday&group=$group";

  $html .= pageMenu($menutabs, "", false);

  if (count($groups) > 1) {
    $html .= "<p>\n";
    foreach ($groups as $grouptmp) {
      $groupLabel = isset($grouptmp['reservationgroup']) ? (string)$grouptmp['reservationgroup'] : '';
      $encodedGroup = urlencode($groupLabel);
      if ($group == $groupLabel) {
        $html .= "<a class='groupinglink' href='" . utf8entities($baseurl) . "&amp;filter=" . $filter . "&amp;group=" . $encodedGroup . "'><span class='selgroupinglink'>" . U_($groupLabel) . "</span></a>";
      } else {
        $html .= "<a class='groupinglink' href='" . utf8entities($baseurl) . "&amp;filter=" . $filter . "&amp;group=" . $encodedGroup . "'>" . U_($groupLabel) . "</a>";
      }
      $html .= "&nbsp;&nbsp;&nbsp;&nbsp;";
    }
    if ($group == "all") {
      $html .= "<a class='groupinglink' href='" . utf8entities($baseurl) . "&amp;filter=" . $filter . "&amp;group=all'><span class='selgroupinglink'>" . _("All") . "</span></a>";
    } else {
      $html .= "<a class='groupinglink' href='" . utf8entities($baseurl) . "&amp;filter=" . $filter . "&amp;group=all'>" . _("All") . "</a>";
    }
    $html .= "</p>\n";
  }
}
if (!empty($group) && $group != "all") {
  $groupheader = false;
}

if (mysqli_num_rows($games) == 0) {
  $html .= "\n<p>" . _("No games") . ".</p>\n";
} elseif ($filter == 'tournaments') {
  $html .= TournamentView($games, $groupheader);
} elseif ($filter == 'series') {
  $html .= SeriesView($games);
} elseif ($filter == 'today') {
  $html .= SeriesView($games, false);
} elseif ($filter == 'next') {
  $html .= TournamentView($games, $groupheader);
} elseif ($filter == 'tomorrow') {
  $html .= SeriesView($games, false);
} elseif ($filter == 'places') {
  $html .= PlaceView($games, $groupheader);
} elseif ($filter == 'all') {
  $html .= SeriesView($games);
} elseif ($filter == 'timeslot') {
  $html .= TimeView($games);
}


$querystring = $_SERVER['QUERY_STRING'];
$querystring = preg_replace('/(&|^)print=[^&]*/i', '', $querystring);
$querystring = ltrim($querystring, '&');
if ($print) {
  $html .= "<hr/><div style='text-align:right'><a href='?" . utf8entities($querystring) . "'>" . _("Return") . "</a></div>";
} elseif (mysqli_num_rows($games)) {
  $html .= "<hr/>\n";
  $html .= "<p>";
  $html .= "<a href='?view=ical&amp;$gamefilter=$id&amp;time=$timefilter&amp;order=$order'>" . _("iCalendar (.ical)") . "</a> | ";
  $html .= "<a href='" . utf8entities($baseurl) . "&filter=onepage&group=$group' target='_blank' rel='noopener'>" . _("Grid (PDF)") . "</a> | ";
  $html .= "<a href='" . utf8entities($baseurl) . "&filter=season&group=$group' target='_blank' rel='noopener'>" . _("List (PDF)") . "</a> | ";
  $html .= "<a href='?" . utf8entities($querystring) . "&amp;print=1'>" . _("Printable version") . "</a>";
  $html .= "</p>\n";
}
if ($print) {
  showPrintablePage($title, $html);
} else {
  showPage($title, $html);
}
