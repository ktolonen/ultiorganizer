<?php
include_once __DIR__ . '/auth.php';
$html = "";
$season = CurrentSeason();
$seasoninfo = SeasonInfo($season);
$reservationgroup = "";
$location = "";
$hideplayed = false;
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

if (isset($_GET['hide'])) {
  $hideplayed = intval($_GET['hide']) === 1;
} elseif (isset($_GET['all'])) {
  $hideplayed = intval($_GET['all']) !== 1;
}

$showtoday = false;
if (isset($_GET['today'])) {
  $showtoday = intval($_GET['today']) === 1;
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
$hidePlayedChecked = $hideplayed ? "checked='checked'" : "";
$showTodayChecked = $showtoday ? "checked='checked'" : "";
$html .= "<div class='ui-grid-solo resp-filter resp-filter-row'>";
$html .= "<label><input type='checkbox' id='showToday' name='showToday' value='1' $showTodayChecked onchange=\"toggleRespFilters();\"/>" . _("Show today only") . "</label>";
$html .= "<label><input type='checkbox' id='hidePlayed' name='hidePlayed' value='1' $hidePlayedChecked onchange=\"toggleRespFilters();\"/>" . _("Hide played games") . "</label>";
$html .= "</div>";
$html .= "<script type='text/javascript'>
  function toggleRespFilters() {
    var hidePlayed = document.getElementById('hidePlayed');
    var showToday = document.getElementById('showToday');
    var hideValue = (hidePlayed && hidePlayed.checked) ? '1' : '0';
    var todayValue = (showToday && showToday.checked) ? '1' : '0';
    window.location = '?view=respgames&hide=' + hideValue + '&today=' + todayValue;
  }
</script>";
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
      $isPlayed = GameHasStarted($game) && empty($game['isongoing']);
      if ($hideplayed && $isPlayed) {
        continue;
      }
      if ($showtoday) {
        $startTime = $game['starttime'];
        $isToday = !empty($startTime) && date('Y-m-d') === date('Y-m-d', strtotime($startTime));
        if (!$isToday) {
          continue;
        }
      }

      if ($prevrg != $game['reservationgroup']) {

        if (!empty($prevloc)) {
          $html .= "</ul></details></li>\n";
          $prevloc = "";
        }

        if (!empty($prevrg)) {
          $html .= "</ul></details></li>\n";
        }
        $html .= "<li class='resp-group'>\n";
        $html .= "<details class='resp-group-toggle' open>\n";
        $html .= "<summary class='resp-group-title'>" . utf8entities($game['reservationgroup']) . "</summary>";
        $html .= "<ul class='resp-location-list'>\n";
        $prevrg = $game['reservationgroup'];
      }

      if ($prevrg == $game['reservationgroup']) {

        $gameloc = $game['location'] . "#" . $game['fieldname'];

        if ($prevloc != $gameloc) {

          if (!empty($prevloc)) {
            $html .= "</ul></details></li>\n";
          }

          $html .= "<li class='resp-location'>\n";
          $html .= "<details class='resp-location-toggle'>\n";
          $html .= "<summary class='resp-location-title'>" . utf8entities($game['locationname']) . " " . _("Field") . " " . utf8entities($game['fieldname']) . "</summary>";
          $html .= "<ul class='resp-game-list'>\n";
          $prevloc = $gameloc;
        }


        if ($prevloc == $gameloc) {
          $gameClass = "resp-game";
          if (GameHasStarted($game)) {
            if ($game['isongoing']) {
              $gameClass .= " resp-game--ongoing";
            } else {
              $gameClass .= " resp-game--played";
            }
          } else {
            $gameClass .= " resp-game--pending";
          }
          $html .= "<li class='" . $gameClass . "'>";


          if ($game['hometeam'] && $game['visitorteam']) {
            $html .= "<div class='resp-game-meta'>";
            $html .= "<span class='resp-time'>" . JustDate($game['starttime']) . " " . DefHourFormat($game['time']) . "</span>";
            $html .= "<span class='resp-teams'>" . utf8entities($game['hometeamname']) . " - " . utf8entities($game['visitorteamname']) . "</span>";
            $html .= "<span class='resp-score'>";
            $gameplayHref = "?view=gameplay&amp;game=" . $gameId;
            $html .= "<a href='" . $gameplayHref . "'>";
            if (GameHasStarted($game)) {
              $html .= intval($game['homescore']) . " - " . intval($game['visitorscore']);
            } else {
              $html .= "? - ?";
            }
            $html .= "</a>";
            $html .= "</span>";
            if (GameHasStarted($game)) {
              if ($game['isongoing']) {
                $html .=  "<span class='resp-status ongoing'>" . _("Ongoing") . "</span>";
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
if (!empty($prevloc)) {
  $html .= "</ul></details></li>\n";
}

if (!empty($prevrg)) {
  $html .= "</ul></details></li>\n";
}

$html .= "</ul>\n";
$html .= "</div>";

$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
