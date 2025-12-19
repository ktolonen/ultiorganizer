<?php
include_once __DIR__ . '/auth.php';
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
$html .= "<ul data-role='listview' class='resp-list'>\n";


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
          $html .= "</ul></li>\n";
          $prevloc = "";
        }

        if (!empty($prevrg)) {
          $html .= "</ul></li>\n";
        }
        $html .= "<li class='resp-group'>\n";
        $html .= "<div class='resp-group-title'>" . utf8entities($game['reservationgroup']) . "</div>";
        $html .= "<ul class='resp-date-list'>\n";
        $prevrg = $game['reservationgroup'];
      }

      if ($prevrg == $game['reservationgroup']) {

        $gameloc = JustDate($game['starttime']) . " " . $game['location'] . "#" . $game['fieldname'];

        if ($prevloc != $gameloc) {

          if (!empty($prevloc)) {
            $html .= "</ul></li>\n";
          }

          $html .= "<li class='resp-day'>\n";
          $html .= "<div class='resp-day-heading'>" . JustDate($game['starttime']) . " " . utf8entities($game['locationname']) . " " . _("Field") . " " . utf8entities($game['fieldname']) . "</div>";
          $html .= "<ul class='resp-game-list'>\n";
          $prevloc = $gameloc;
        }


        if ($prevloc == $gameloc) {
          $html .= "<li class='resp-game'>";


          if ($game['hometeam'] && $game['visitorteam']) {
            $html .= "<div class='resp-game-meta'>";
            $html .= "<span class='resp-time'>" . DefHourFormat($game['time']) . "</span>";
            $html .= "<span class='resp-teams'>" . utf8entities($game['hometeamname']) . " - " . utf8entities($game['visitorteamname']) . "</span>";
            $html .= "<span class='resp-score'>";
            if (GameHasStarted($game)) {
              $html .= intval($game['homescore']) . " - " . intval($game['visitorscore']);
            } else {
              $html .= "? - ?";
            }
            $html .= "</span>";
            if (GameHasStarted($game)) {
              if ($game['isongoing']) {
                $html .=  "<span class='resp-status ongoing'><a href='?view=gameplay&amp;game=" . $gameId . "'>" . _("Ongoing") . "</a></span>";
              } elseif (GameHasStarted($game)) {
                $html .=  "<span class='resp-status'><a href='?view=gameplay&amp;game=" . $gameId . "'>" . _("Game play") . "</a></span>";
              }
            }
            $html .= "</div>";

            $html .= "<div class='resp-actions' data-role='controlgroup' data-type='horizontal'>\n";
            $html .= "<a href='?view=addresult&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Result") . "</a>";
            $html .= "<a href='?view=addplayerlists&amp;game=" . $gameId . "&amp;team=" . $game['hometeam'] . "' data-role='button' data-ajax='false'>" . _("Players") . "</a>";
            $html .= "<a href='?view=addscoresheet&amp;game=$gameId' data-role='button' data-ajax='false'>" . _("Scoresheet") . "</a>";
            if (intval($seasoninfo['spiritmode'] > 0) && isSeasonAdmin($seasoninfo['season_id'])) {
              $html .= "<a href='?view=addspiritpoints&amp;game=$gameId&amp;team=" . $game['hometeam'] . "' data-role='button' data-ajax='false'>" . _("Spirit") . "</a>";
            }
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
  $html .= "</ul></li>\n";
}

if (!empty($prevloc)) {
  $html .= "</ul></li>\n";
}

$html .= "</ul>\n";
$html .= "</div>";

$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
