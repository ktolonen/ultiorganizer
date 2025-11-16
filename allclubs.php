<?php
include_once 'lib/club.functions.php';

$title = _("All clubs");
$html = "";

$filter = "A";

if (iget("list")) {
  $filter = strtoupper(iget("list"));
}

$validletters = array("#", "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z");
$maxcols = 3;

$html .= "<h1>$title</h1>\n";
$html .= "<table style='white-space: nowrap;'><tr>\n";
foreach ($validletters as $let) {
  $letter = (string)$let;
  if ($letter == $filter) {
    $html .= "<td class='selgroupinglink'>&nbsp;" . utf8entities($letter) . "&nbsp;</td>";
  } else {
    $html .= "<td>&nbsp;<a class='groupinglink' href='?view=allclubs&amp;list=" . urlencode($letter) . "'>" . utf8entities($letter) . "</a>&nbsp;</td>";
  }
}
if ($filter == "ALL") {
  $html .= "<td class='selgroupinglink'>&nbsp;" . _("ALL") . "</td>";
} else {
  $html .= "<td>&nbsp;<a class='groupinglink' href='?view=allclubs&amp;list=all'>" . _("ALL") . "</a></td>";
}
$html .= "</tr></table>\n";

$html .= "<table style='white-space: nowrap;width:100%;'>\n";
$clubs = ClubList(true, $filter);

$firstchar = " ";
$listletter = " ";
$counter = 0;

foreach ($clubs as $club) {

  if ($filter == "ALL") {
    $clubName = isset($club['name']) ? (string) $club['name'] : '';
    if (function_exists('mb_substr')) {
      $firstchar = mb_strtoupper(mb_substr($clubName, 0, 1, 'UTF-8'), 'UTF-8');
    } else {
      $firstchar = strtoupper(substr($clubName, 0, 1));
    }
    if ($listletter != $firstchar && in_array($firstchar, $validletters)) {
      $listletter = $firstchar;
      if ($counter > 0 && $counter <= $maxcols) {
        $html .= "</tr>\n";
      }
      $html .= "<tr><td></td></tr>\n";
      $html .= "<tr><td class='list_letter' colspan='$maxcols'>" . utf8entities($listletter) . "</td></tr>\n";
      $counter = 0;
    }
  }
  if ($counter == 0) {
    $html .= "<tr>\n";
  }

  $html .= "<td style='width:33%'>";
  $countryId = isset($club['country']) ? intval($club['country']) : 0;
  $flagFile = isset($club['flagfile']) ? $club['flagfile'] : null;
  if ($countryId && !empty($flagFile)) {
    $html .= "<img height='10' src='images/flags/tiny/" . $flagFile . "' alt=''/>&nbsp;";
  }
  $html .= "<a href='?view=clubcard&amp;club=" . $club['club_id'] . "'>" . utf8entities($club['name']) . "</a>";
  $html .= "</td>";
  $counter++;

  if ($counter >= $maxcols) {
    $html .= "</tr>\n";
    $counter = 0;
  }
}
if ($counter > 0 && $counter <= $maxcols) {
  $html .= "</tr>\n";
}
$html .= "</table>\n";

showPage($title, $html);
