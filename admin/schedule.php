<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$LAYOUT_ID = SCHEDULE;
$title = _("Scheduling");

if (isset($_GET['reservations'])) {
    $reservations = explode(",", $_GET['reservations']);
} else {
    $reservations = array_flip($_SESSION['userproperties']['userrole']['resadmin']);
}
$reservationData = ReservationInfoArray($reservations);
$numOfreservations = count($reservations);

define("MIN_HEIGHT", 1.0);
$MAX_COLUMNS = 4;

$maxtimeslot = 30;
$seriesId = 0;
$poolId = 0;
$seasonId = "";

if (!empty($_GET["series"])) {
    $seriesId = intval($_GET["series"]);
}

if (!empty($_GET["pool"])) {
    $poolId = intval($_GET["pool"]);
}

if (!empty($_GET["season"])) {
    $seasonId = $_GET["season"];
}

$backurl = "?view=admin/reservations";
if (!empty($seasonId)) {
    $backurl .= "&season=$seasonId";
}
if (!empty($seriesId)) {
    $backurl .= "&series=$seriesId";
}
if (!empty($pool)) {
    $backurl .= "&pool=$poolId";
}


$seasonfilter = [];
$seriesfilter = [];
$poolfilter = [];

$seasons = Seasons();
foreach ($seasons as $season) {
    $seasonfilter[] = ['id' => $season['season_id'], 'name' => U_(SeasonName($season['season_id']))];
}

$series = SeasonSeries($seasonId);
foreach ($series as $ser) {
    $seriesfilter[] = ['id' => $ser['series_id'], 'name' => U_($ser['name'])];
}

$pools = SeriesPools($seriesId);
foreach ($pools as $tmppool) {
    $poolfilter[] = ['id' => $tmppool['pool_id'], 'name' => U_($tmppool['name'])];
}

//common page
pageTopHeadOpen($title);

?>
<script type="text/javascript" src="script/sortablejs/Sortable.min.js"></script>
<script type="text/javascript" src="script/sortable-lists.js"></script>
<link rel="stylesheet" type="text/css" href="script/sortable-lists.css" />

<style type="text/css">
  body {
    margin: 0;
    padding: 0;
  }
</style>


<style type="text/css">
  div.workarea {
    padding: 0px;
    float: left;
    min-width: 150px;
    /* min-width:150px; */
  }

  td.scheduling_column {
    vertical-align: top;
    /* padding:5px; */
    font-size: 8px;
  }

  ul.draglist {
    position: relative;
    background: #f7f7f7;
    border: 1px solid gray;
    list-style: none;
    margin: 0;
    padding: 0;
  }

  ul.draglist li {
    margin: 0px;
    cursor: move;
    zoom: 1;
    text-align: center;
    vertical-align: center;
  }

  li.list1 {
    background-color: #aaaaaa;
    border: 1px solid #7EA6B2;
    width: 150px;
  }

  th.scheduling {
    width: 150px
  }

  ;

  td.timecolumn {
    vertical-align: top;
  }

  ul.timelist {
    position: relative;
    list-style: none;
    margin: 0;
    padding: 0;
  }

  ul.timelist li {
    border: 1px solid #7EA6B2;
  }


  #user_actions {
    float: right;
  }
</style>
<script type="text/javascript">
  var with_ctrl_key = false;
  var with_shift_key = false;

  function KeyDown(event) {
    var KeyID = event.keyCode;

    switch (KeyID) {

      case 16:
        with_shift_key = true;
        break;

      case 17:
        with_ctrl_key = true;
        break;

      case 37:
        //Arrow Left
        if (with_ctrl_key) {
          window.scrollBy(2 * -window.innerWidth + 50, 0);
        } else if (with_shift_key) {
          window.scrollBy(-50, 0);
        } else {
          window.scrollBy(-window.innerWidth + 50, 0);
        }

        break;

      case 38:
        //Arrow Up
        if (with_ctrl_key) {
          window.scrollBy(0, 2 * -window.innerHeight + 50);
        } else if (with_shift_key) {
          window.scrollBy(0, -50);
        } else {
          window.scrollBy(0, -window.innerHeight + 50);
        }
        break;

      case 39:
        //Arrow Right
        if (with_ctrl_key) {
          window.scrollBy(2 * window.innerWidth - 50, 0);
        } else if (with_shift_key) {
          window.scrollBy(50, 0);
        } else {
          window.scrollBy(window.innerWidth - 50, 0);
        }
        break;

      case 40:
        //Arrow Down
        if (with_ctrl_key) {
          window.scrollBy(0, 2 * window.innerHeight - 50);
        } else if (with_shift_key) {
          window.scrollBy(0, 50);
        } else {
          window.scrollBy(0, window.innerHeight - 50);
        }
        break;
    }
  }

  function KeyUp(event) {
    var KeyID = event.keyCode;

    switch (KeyID) {

      case 16:
        with_shift_key = false;
        break;

      case 17:
        with_ctrl_key = false;
        break;
    }
  }
  //-->
</script>

<?php

function gameDuration($gameInfo)
{
    return empty($gameInfo['gametimeslot']) ? $gameInfo['timeslot'] : $gameInfo['gametimeslot'];
}

function gameHeight($duration)
{
    return max(intval($duration * MIN_HEIGHT), intval(15 * MIN_HEIGHT)) - 2;
}

function pauseHeight($duration)
{
    // echo "<!--".EpocToMysql($gameStart)." ".EpocToMysql($nextStart)."-->\n";
    return ($duration * MIN_HEIGHT) - 2;
}

$scrolling = "onkeydown='KeyDown(event);' onkeyup='KeyUp(event);'";
pageTopHeadClose($title, false, $scrolling);
pageMainStart();
contentStart();

echo "<a href='" . utf8entities($backurl) . "'>" . _("Return") . "</a>";

echo "<table><tr><td class='scheduling_column'>";

//$teams = UnscheduledTeams();
//$unscheduledTeams = array_flip(UnscheduledTeams());
if ($poolId) {
    $gameData = UnscheduledPoolGameInfo($poolId);
} elseif ($seriesId) {
    $gameData = UnscheduledSeriesGameInfo($seriesId);
} elseif (!empty($seasonId)) {
    $gameData = UnscheduledSeasonGameInfo($seasonId);
} else {
    $gameData = [];
}

function jsSecure($string)
{
    return str_replace(['"', "\n"], ['\"', ''], $string);
}

function pauseEntry($height, $duration, $gameId)
{
    $html = "<li class='list1' id='pause" . $gameId . "' style='min-height:" . $height . "px'>";
    $html .= "<input type='hidden' id='ptime" . $gameId . "' name='ptimes[]' value='" . $duration . "'/>";
    $html .= sprintf(_("Pause: %s&thinsp;min."), $duration);
    $html .= "<span style='align:right;float:right'><a href='javascript:hide(\"pause" . $gameId . "\");'>x</a></span></li>\n";
    return $html;
}

function gameEntry($gameInfo, $height, $duration, $poolname, $editable = true)
{
    $color = $gameInfo['color'];
    $textColor = textColor($color);
    $gameId = $gameInfo['game_id'];
    $gamename = utf8entities(getGameName($gameInfo, true));
    $tooltip = utf8entities(getGameName($gameInfo));
    if ($tooltip == $gamename) {
        $tooltip = "";
    } else {
        $tooltip = " title='" . $tooltip . "'";
    }
    $html = "<li class='list1" . ($editable ? "" : " locked") . "' style='color:#" . $textColor . ";background-color:#" . $color . ";min-height:" . $height .
      "px' id='game" . $gameId . "'" . $tooltip . ">";
    $html .= "<input type='hidden' id='gtime" . $gameId . "' name='gtimes[]' value='" . $duration . "'/>";
    $html .= $poolname;
    if ($editable) {
        $html .= "<span style='align:right;float:right;'><a href='javascript:hide(\"game" . $gameId . "\");'>x</a></span>";
    } else {
        $html .= "<span style='align:right;float:right;'>#</span>";
    }
    $html .= "<br/>" . (empty($gamename) ? "" : "<b>$gamename</b> ") . sprintf(_("%d&nbsp;min."), $duration);
    $html .= "</li>\n";
    return $html;
}

function getHName($gameInfo)
{
    return empty($gameInfo['hometeamshortname']) ? $gameInfo['hometeamname'] : $gameInfo['hometeamshortname'];
}

function getVName($gameInfo)
{
    return empty($gameInfo['visitorteamshortname']) ? $gameInfo['visitorteamname'] : $gameInfo['visitorteamshortname'];
}

function getGameName($gameInfo, $short = false)
{
    if ($gameInfo['hometeam'] && $gameInfo['visitorteam']) {
        if ($short) {
            $gametitle = substr(getHName($gameInfo), 0, 12) . " - " . substr(getVName($gameInfo), 0, 12);
        } else {
            $gametitle = $gameInfo['hometeamname'] . " - " . $gameInfo['visitorteamname'];
        }
    } else {
        $gametitle = $gameInfo['phometeamname'] . " - " . $gameInfo['pvisitorteamname'];
    }
    return $gametitle;
}

function gamePoolName($gameInfo)
{
    return U_($gameInfo['seriesname']) . ", " . U_($gameInfo['poolname']);
}

function tableStart($dayArray, $skip, $max)
{
    echo "<table><tr>\n";
    $index = 0;
    $startTime = 0;
    $dateHeader = "";
    foreach ($dayArray as $reservationId => $reservationArray) {
        if (++$index <= $skip) {
            continue;
        }
        if ($index > $skip + $max) {
            break;
        }
        $startTime = strtotime($reservationArray['starttime']);
        if ($dateHeader == "") {
            $dateHeader = JustDate($reservationArray['starttime']);
        }
        echo "<th class='scheduling'>" . ReservationPlaceText($reservationArray['name'], $reservationArray['fieldname']) .
          " " . date("H:i", $startTime) . "</th>\n";
    }
    echo "<th>" . $dateHeader . "</th></tr><tr>\n";
    return $startTime;
}

function tableEnd($firstStart, $lastEnd)
{
    echo "<td class='timecolumn'>";
    if (isset($firstStart)) {
        echo "<ul class='timelist'>\n";
        for ($t = $firstStart; $t < $lastEnd; $t += 60 * 60) {
            echo "<li style='min-height:" . (min(60, ($lastEnd - $t) / 60) * MIN_HEIGHT - 2) . "px'>" . date("H:i", $t) . "</li>\n";
        }
        echo "</ul>";
    }
    echo "</td>\n";
    echo "</tr>\n</table>\n";
}

echo "<table><tr><td class='scheduling_column'>\n";
echo "<h3>" . _("Unscheduled") . "</h3>\n";
echo "<form action='' method='get'>";
echo "<p><select class='dropdown' style='width:100%' name='eventfilter' onchange='OnEventSelect(this);'>\n";
echo "<option class='dropdown' value=''>" . _("Select event") . "</option>";
foreach ($seasonfilter as $season) {
    if ($seasonId == $season['id']) {
        echo "<option class='dropdown' selected='selected' value='" . utf8entities($season['id']) . "'>" . utf8entities($season['name']) . "</option>";
    } else {
        echo "<option class='dropdown' value='" . utf8entities($season['id']) . "'>" . utf8entities($season['name']) . "</option>";
    }
}
echo "</select><br/>\n";
$disabled = "";
if (empty($seasonId)) {
    $disabled = "disabled='disabled'";
}
echo "<select class='dropdown' $disabled style='width:100%' name='seriesfilter' onchange='OnSeriesSelect(this);'>\n";
echo "<option class='dropdown' value='0'>" . _("All divisions") . "</option>";
foreach ($seriesfilter as $series) {
    if ($seriesId == $series['id']) {
        echo "<option class='dropdown' selected='selected' value='" . utf8entities($series['id']) . "'>" . utf8entities($series['name']) . "</option>";
    } else {
        echo "<option class='dropdown' value='" . utf8entities($series['id']) . "'>" . utf8entities($series['name']) . "</option>";
    }
}
echo "</select><br/>\n";
$disabled = "";
if (!$seriesId) {
    $disabled = "disabled='disabled'";
}
echo "<select class='dropdown' $disabled style='width:100%' name='poolfilter' onchange='OnPoolSelect(this);'>\n";
echo "<option class='dropdown' value='0'>" . _("All pools") . "</option>";
foreach ($poolfilter as $pool) {
    if ($poolId == $pool['id']) {
        echo "<option class='dropdown' selected='selected' value='" . utf8entities($pool['id']) . "'>" . utf8entities($pool['name']) . "</option>";
    } else {
        echo "<option class='dropdown' value='" . utf8entities($pool['id']) . "'>" . utf8entities($pool['name']) . "</option>";
    }
}
echo "</select></p>\n";
echo "</form>";

$zeroGames = [];

echo "<div class='workarea' >\n";
echo "<ul class='draglist' id='unscheduled'  style='min-height:600px'>\n";
foreach ($gameData as $gameId => $gameInfo) {
    if (hasEditGamesRight($gameInfo['series'])) {
        $duration = gameDuration($gameInfo);
        if ($duration <= 0) {
            $zeroGames[] = count($zeroGames) == 0 ? $gameInfo : $gameId;
        }
        $height = gameHeight($duration);
        $poolname = gamePoolName($gameInfo);
        $maxtimeslot = max($maxtimeslot, $duration);
        if ($duration > 0) {
            echo gameEntry($gameInfo, $height, $duration, $poolname);
        } else {
            echo gameEntry($gameInfo, $height, $duration, $poolname, false);
        }
    }
}
echo "</ul>\n";
echo "</div>\n</td>\n";
echo "</tr>\n</table>\n";
echo "<p>&nbsp;</p>";
echo "<input type='button' id='pauseButton' value='" . _("Add pause") . "'/>";
echo "<div style='white-space:nowrap'><input type='text' id='pauseLen' value='$maxtimeslot' size='3'/>&thinsp;" . _("minutes") . "</div>\n";
echo "</td><td style='vertical-align:top'>\n";
$reservedPauses = [];

$MINTOP = 30;

if (count($reservationData) > 0) {
    foreach ($reservationData as $day => $dayArray) {
        $lastEnd = 0;
        $columnCount = $lastBreak = 0;
        tableStart($dayArray, 0, $MAX_COLUMNS);

        foreach ($dayArray as $reservationId => $reservationArray) {
            if (!isset($firstStart)) {
                $firstStart = strtotime($reservationArray['starttime']);
            }
            echo "<td class='scheduling_column'>\n";
            $offset = intval((strtotime($reservationArray['starttime']) - $firstStart) / 60) + $MINTOP;
            $lastEnd = max($lastEnd, strtotime($reservationArray['endtime']));
            $startTime = strtotime($reservationArray['starttime']);
            $endTime =  strtotime($reservationArray['endtime']);
            $duration = ($endTime - $startTime) / 60;

            echo "<div class='workarea' >\n";
            echo "<ul id='res" . $reservationId . "' class='draglist' style='min-height:" . ($duration * MIN_HEIGHT) . "px'>\n";
            $nextStart = $startTime;
            foreach ($reservationArray['games'] as $gameId => $gameInfo) {
                $gameStart = strtotime($gameInfo['time']);
                $duration = ($gameStart - $nextStart) / 60;
                $height = pauseHeight($duration);
                if ($nextStart < $gameStart) {
                    echo pauseEntry($height, $duration, $gameId);
                    $reservedPauses[] = "pause" . $gameId;
                }
                $duration = gameDuration($gameInfo);
                if ($duration <= 0) {
                    $zeroGames[] = count($zeroGames) == 0 ? $gameInfo : $gameId;
                }
                $nextStart = $gameStart + ($duration * 60);
                $height = gameHeight($duration);
                $gametitle = getGameName($gameInfo);
                $pooltitle = gamePoolName($gameInfo);
                if ($duration > 0 && hasEditGamesRight($gameInfo['series'])) {
                    echo gameEntry($gameInfo, $height, $duration, $pooltitle);
                } else {
                    echo gameEntry($gameInfo, $height, $duration, $pooltitle, false);
                }
            }
            echo "</ul>\n";
            echo "</div>\n</td>\n";

            ++$columnCount;
            if (count($dayArray) > $MAX_COLUMNS && ($columnCount - $lastBreak >= $MAX_COLUMNS)) {
                tableEnd($firstStart, $lastEnd);
                unset($firstStart);
                $lastEnd = 0;
                if ($columnCount < count($dayArray)) {
                    tableStart($dayArray, $columnCount, $MAX_COLUMNS);
                }
                $lastBreak = $columnCount;
            }
        }
        if (isset($firstStart)) {
            tableEnd($firstStart, $lastEnd);
        }
        unset($firstStart);
        $lastEnd = 0;
    }
} else {
    foreach ($reservationData as $day => $dayArray) {
        $rowcount = 2;

        foreach ($dayArray as $reservationId => $reservationArray) {

            if (($rowcount % 2) == 0) {
                echo "<table><tr>\n";
            }
            $rowcount++;
            if (!isset($firstStart)) {
                $firstStart = strtotime($reservationArray['starttime']);
            }
            echo "<td style='vertical-align:top;padding:5px'>\n";
            $offset = intval((strtotime($reservationArray['starttime']) - $firstStart) / 60) + $MINTOP;
            $startTime = strtotime($reservationArray['starttime']);
            $endTime =  strtotime($reservationArray['endtime']);
            $duration = ($endTime - $startTime) / 60;

            echo "<div style='vertical-align:bottom;min-height:" . intval($offset * MIN_HEIGHT) . "px'>";
            echo "<h3>" . ReservationPlaceText($reservationArray['name'], $reservationArray['fieldname']) . " " . date("H:i", $startTime) . "</h3></div>\n";
            echo "<div class='workarea' >\n";
            echo "<ul id='res" . $reservationId . "' class='draglist' style='min-height:" . ($duration * MIN_HEIGHT) . "px'>\n";
            $nextStart = $startTime;
            foreach ($reservationArray['games'] as $gameId => $gameInfo) {
                $gameStart = strtotime($gameInfo['time']);
                if ($gameStart > $nextStart) {
                    $duration = ($gameStart - $nextStart) / 60;
                    $height = pauseHeight($duration);
                    echo pauseEntry($height, $duration, $gameId);
                    $reservedPauses[] = "pause" . $gameId;
                }
                $duration = gameDuration($gameInfo);
                if ($duration <= 0) {
                    $zeroGames[] = count($zeroGames) == 0 ? $gameInfo : $gameId;
                }
                $nextStart = $gameStart + (max($duration, 60) * 60);
                $height = gameHeight($duration);
                $gametitle = getGameName($gameInfo);
                $pooltitle = gamePoolName($gameInfo);
                if ($duration > 0 && hasEditGamesRight($gameInfo['series'])) {
                    echo gameEntry($gameInfo, $height, $duration, $pooltitle);
                } else {
                    echo gameEntry($gameInfo, $height, $duration, $pooltitle, false);
                }
            }
            echo "</ul>\n";
            echo "</div>\n</td>\n";
            if (($rowcount % 2) == 0) {
                echo "</tr>\n</table>\n";
            }
        }
    }
}
echo "<table><tr>";
echo "<td id='user_actions' style='float: left; padding: 20px'>";
echo "<input type='button' id='showButton' value='" . _("Save") . "' /></td>";
echo "<td class='left'><div id='responseStatus'></div>";
if (!empty($zeroGames)) {
    echo "<p>" . sprintf(
        _("Warning: Games with a duration of 0 minutes were found. They cannot be scheduled. Edit the game duration or the time slot length in %s."),
        gamePoolName($zeroGames[0]),
    ) . "</p>";
}
echo "</td></tr></table>\n";
echo "<a href='" . utf8entities($backurl) . "'>" . _("Return") . "</a>";
?>
<script type="text/javascript">
  var redirecturl = "";
  var modified = 0;

  function hide(id) {
    var elem = document.getElementById(id);
    elem.parentNode.removeChild(elem);
    setModified(true);
  }

  function setModified(newValue) {
    modified = newValue;
    if (modified)
      window.onbeforeunload = function() {
        return "";
      }
    else
      window.onbeforeunload = null;
  }

  function paramExp(param) {
    return new RegExp("([?|&])" + param + "=([^&]*)?(&|$)", "i")
  }

  function getParam(url, param) {
    var found = 0;
    var re = paramExp(param);
    if (url.match(re)) {
      found = url.match(re)[2];
    }
    return found;
  }

  function changeParam(url, param, value) {
    var re = paramExp(param);
    if (url.match(re)) {
      url = url.replace(re, '$1' + param + "=" + value + '$3');
    } else {
      url = url + '&' + param + "=" + value;
    }
    return url;
  }

  function getUrl(url, season, series, pool) {
    if (season != null) {
      url = changeParam(url, "season", season);
    }
    if (series != null) {
      url = changeParam(url, "series", series);
    }
    if (pool != null) {
      url = changeParam(url, "pool", pool);
    }
    return url;
  }

  //ajax based solution would be better, but at now this feels simpler because also dropdown fields need to be updated according user selection.
  function OnEventSelect(dropdown) {
    var myindex = dropdown.selectedIndex;
    var SelValue = dropdown.options[myindex].value;
    var event = getParam(location.href, "season");

    redirecturl = getUrl(location.href, SelValue, 0, 0);
    if (!redirectWithConfirm()) {
      dropdown.value = event;
    }
  }

  function OnSeriesSelect(dropdown) {
    var myindex = dropdown.selectedIndex;
    var SelValue = dropdown.options[myindex].value;
    var series = getParam(location.href, "series");

    redirecturl = getUrl(location.href, null, SelValue, 0);
    if (!redirectWithConfirm()) {
      dropdown.value = series;
    }
  }

  function OnPoolSelect(dropdown) {
    var myindex = dropdown.selectedIndex;
    var SelValue = dropdown.options[myindex].value;
    var pool = getParam(location.href, "pool");

    redirecturl = getUrl(location.href, null, null, SelValue);
    if (!redirectWithConfirm()) {
      dropdown.value = pool;
    }
  }

  function redirectWithConfirm() {
    if (modified) {
      var answer = confirm('<?php echo _("Save changes?"); ?>');
      if (answer) {
        saveSchedule();
      } else {
        answer = confirm('<?php echo _("Do you want to leave anyway? If you select Yes, you will lose all your changes."); ?>');
        if (answer) {
          setModified(false);
          location.href = redirecturl;
        } else
          return false;
      }
    } else {
      location.href = redirecturl;
    }
    return true;
  }

  var scheduleLists = document.querySelectorAll("ul.draglist");
  var pauseIndex = 1;

  makeSortableLists(scheduleLists, {
    draggable: "li.list1",
    filter: ".locked, a",
    onChange: function() {
      setModified(true);
    }
  });

  function listRequest(list) {
    var out = list.id === "unscheduled" ? "0" : list.id.substring(3);
    var offset = 0;
    Array.prototype.forEach.call(list.children, function(item) {
      var time = item.querySelector("input");
      if (!time) {
        return;
      }
      if (item.id.indexOf("game") === 0) {
        out += ":" + item.id.substring(4) + "/" + offset;
      }
      offset += parseInt(time.value, 10) || 0;
    });
    return out;
  }

  function saveSchedule() {
    var request = Array.prototype.map.call(scheduleLists, listRequest).join("|");
    postSortedLists("index.php?view=admin/saveschedule", request, document.getElementById("responseStatus"), {
      html: true,
      onSuccess: function() {
        setModified(false);
        if (redirecturl) {
          location.href = redirecturl;
        }
      }
    });
  }

  document.getElementById("showButton").addEventListener("click", saveSchedule);

  document.getElementById("pauseButton").addEventListener("click", function() {
    var duration = parseInt(document.getElementById("pauseLen").value, 10);
    if (!(duration > 0)) {
      return;
    }
    var html = "<?php echo jsSecure(pauseEntry('%h%', '%d%', '%i%')); ?>";
    html = html.replace(/%h%/g, (duration * <?php echo MIN_HEIGHT; ?>) - 2);
    html = html.replace(/%d%/g, duration);
    html = html.replace(/%i%/g, "new" + pauseIndex);
    var holder = document.createElement("ul");
    holder.innerHTML = html;
    var unscheduled = document.getElementById("unscheduled");
    unscheduled.insertBefore(holder.firstChild, unscheduled.firstChild);
    pauseIndex++;
  });
</script>


<?php
echo "</td></tr>\n</table>\n";
contentEnd();
pageEnd();
?>
