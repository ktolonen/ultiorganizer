<?php
$html = "";
$season = CurrentSeason();
$seasoninfo = SeasonInfo($season);
$reservationgroup = "";
$location = "";
$showall = false;
$day = "";

if (isset($_GET['rg'])) {
  $reservationgroup = urldecode($_GET['rg']);
}

if (isset($_GET['loc'])) {
  $location = urldecode($_GET['loc']);
}

if (isset($_GET['day'])) {
  $day = urldecode($_GET['day']);
}

if (isset($_GET['all'])) {
  $showall = intval($_GET['all']);
}

$html .= "<div data-role='header'>\n";
$html .= "<h1>" . _("Games you are responsible for") . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";

$respGameArray = GameResponsibilityArray($season);
$html .= "<form action='?view=respgames' method='post' data-ajax='false'>\n";

$html .= "<div class='ui-grid-solo'>";
$seasons = SeasonsArray();

if (count($seasons)) {
  $html .=  "<label for='selseason' class='select'>" . _("Select event") . ":</label>\n";
  $html .=  "<select name='selseason' id='selseason' onchange='changeseason(selseason.options[selseason.options.selectedIndex].value);'>\n";
  foreach ($seasons as $row) {
    $selected = "";
    if ($_SESSION['userproperties']['selseason'] == $row['season_id']) {
      $selected = "selected='selected'";
    }
    $html .=   "<option class='dropdown' $selected value='" . utf8entities($row['season_id']) . "'>" . SeasonName($row['season_id']) . "</option>";
  }
  $html .=  "</select>";
}

$html .= "</div>";
$html .= "<div class='ui-grid-solo'>";
$html .= "<p>" . _("Games in selected event") . ":</p>";
$html .= "</div>";
$html .= "<div class='ui-grid-solo'>";
$html .= "<ul data-role='listview'>\n";


$prevdate = "";
$prevrg = "";
$prevloc = "";

foreach ($respGameArray as $tournament => $resArray) {
  foreach ($resArray as $resId => $gameArray) {
    foreach ($gameArray as $gameId => $game) {

      if (!is_numeric($gameId)) {
        continue;
      }

      if ($prevrg != $game['reservationgroup']) {

        if (!empty($prevloc)) {
          $html .= "<li><a href='#' data-role='button' data-rel='back'>" . _("Back") . "</a></li>";
          $html .= "</ul></li>\n";
          $prevloc = "";
        }

        if (!empty($prevrg)) {
          $html .= "<li><a href='#' data-role='button' data-rel='back'>" . _("Back") . "</a></li>";
          $html .= "</ul></li>\n";
        }
        $html .= "<li>\n";
        $html .= "<div>" . utf8entities($game['reservationgroup']) . "</div>";
        $html .= "<ul>\n";
        $prevrg = $game['reservationgroup'];
      }

      if ($prevrg == $game['reservationgroup']) {

        $gameloc = JustDate($game['starttime']) . " " . $game['location'] . "#" . $game['fieldname'];

        if ($prevloc != $gameloc) {

          if (!empty($prevloc)) {
            $html .= "<li><a href='#' data-role='button' data-rel='back'>" . _("Back") . "</a></li>";
            $html .= "</ul></li>\n";
          }

          $html .= "<li>\n";
          $html .= "<div>" . JustDate($game['starttime']) . " " . utf8entities($game['locationname']) . " " . _("Field") . " " . utf8entities($game['fieldname']) . "</div>";
          $html .= "<ul>\n";
          $prevloc = $gameloc;
        }


        if ($prevloc == $gameloc) {
          $html .= "<li>";


          if ($game['hometeam'] && $game['visitorteam']) {
            $html .= "<div>";
            $html .= "<table>";
            $html .= "<tbody>";
            $html .= "<tr>";
            $html .= "<td style='padding-left:10px'>";
            $html .= DefHourFormat($game['time']);
            $html .= "</td>";
            $html .= "<td style='padding-left:10px'>";
            $html .= utf8entities($game['hometeamname']) . " - " . utf8entities($game['visitorteamname']);
            $html .= "</td>";
            $html .= "<td style='padding-left:10px; white-space:nowrap;'>";
            if (GameHasStarted($game)) {
              $html .= intval($game['homescore']) . " - " . intval($game['visitorscore']);
            } else {
              $html .= "? - ?";
            }
            $html .= "</td>";
            $html .= "<td style='padding-left:10px'>";
            if (GameHasStarted($game)) {
              if ($game['isongoing']) {
                $html .=  "<a href='?view=gameplay&amp;game=" . $gameId . "'>" . _("Ongoing") . "</a>";
              } elseif (GameHasStarted($game)) {
                $html .=  "<a href='?view=gameplay&amp;game=" . $gameId . "'>" . _("Game play") . "</a>";
              }
            }
            $html .= "</td>";
            $html .= "</tr>";
            $html .= "</tbody>";
            $html .= "</table>";

            $html .= "<div data-role='controlgroup' data-type='horizontal'>\n";
            $html .= "<a href='?view=addresult&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Result") . "</a>";
            $html .= "<a href='?view=addplayerlists&amp;game=" . $gameId . "&amp;team=" . $game['hometeam'] . "' data-role='button' data-ajax='false'>" . _("Players") . "</a>";
            $html .= "<a href='?view=addscoresheet&amp;game=$gameId' data-role='button' data-ajax='false'>" . _("Scoresheet") . "</a>";
            if (intval($seasoninfo['spiritmode'] > 0) && isSeasonAdmin($seasoninfo['season_id'])) {
              $html .= "<a href='?view=addspiritpoints&amp;game=$gameId&amp;team=" . $game['hometeam'] . "' data-role='button' data-ajax='false'>" . _("Spirit") . "</a>";
            }
            $html .= "</div>\n";
            $html .= "</div>\n";
          } else {
            $html .= utf8entities($game['phometeamname']) . " - " . utf8entities($game['pvisitorteamname']) . " ";
          }
          $html .= "</li>\n";
        }
      }
    }
  }
}
if (!empty($prevrg)) {
  $html .= "<li><a href='#' data-role='button' data-rel='back'>" . _("Back") . "</a></li>";
  $html .= "</ul></li>\n";
}

if (!empty($prevloc)) {
  $html .= "<li><a href='#' data-role='button' data-rel='back'>" . _("Back") . "</a></li>";
  $html .= "</ul></li>\n";
}

$html .= "</ul>\n";
$html .= "</div>";

$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
