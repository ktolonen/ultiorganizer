<?php
include_once 'lib/database.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = SERIEFORMATS;

$title = _("Rule Templates");
$html = "";

//common page
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

//process itself on submit
if (!empty($_POST['remove_x'])) {
  $id = $_POST['hiddenDeleteId'];
  DeletePoolTemplate($id);
}

$html .= "<form method='post' action='?view=admin/serieformats'>";
$html .= "<h2>" . _("Rule templates") . "</h2>\n";
$html .= "<table style='width:100%' border='0' cellpadding='4'>\n";
$html .= "<tr>";
$html .= "<th>" . _("Name") . "</th> <th class='center'>" . _("Winning points") . "</th> <th class='center'>" . _("Point cap") . "</th> <th class='center'>" . _("Draws allowed") . "</th>  <th class='center'>" . _("Time cap") . "</th><th class='center'>" . _("Time-outs") . "</th><th></th>";
$html .= "</tr>\n";

$templates = PoolTemplates();

foreach ($templates as $row) {
  $html .= "<tr>";

  $html .= "<td><a href='?view=admin/addserieformats&amp;template=" . $row['template_id'] . "'>" . utf8entities(U_($row['name'])) . "</a></td>";
  $html .= "<td class='center'>" . $row['winningscore'] . "</td>";
  $html .= "<td class='center'>" . $row['scorecap'] . "</td>";
  $html .= "<td class='center'>" . $row['drawsallowed'] . "</td>";
  $html .= "<td class='center'>" . $row['timecap'] . "</td>";
  if (!empty($row['timeouts'])) {
    $html .= "<td class='center'>" . $row['timeouts'] . "</td>";
  } else {
    $html .= "<td class='center'>-</td>";
  }

  $html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"setId(" . $row['template_id'] . ");\"/></td>";
  $html .= "</tr>\n";
}

$html .= "</table><p><input class='button' name='add' type='button' value='" . _("Add") . "' onclick=\"window.location.href='?view=admin/addserieformats'\"/></p>";

//stores id to delete
$html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
$html .= "</form>\n";

echo $html;

contentEnd();
pageEnd();
