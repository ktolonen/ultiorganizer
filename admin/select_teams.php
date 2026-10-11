<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/common.functions.php';
require_once("lib/hsvclass/HSVClass.php");


$LAYOUT_ID = SCHEDULE;
$title = _("Team selection");
define("MIN_HEIGHT", 0.5);
define("TEAM_HEIGHT", 15);

$seriesId = 0;
if (isset($_GET['series'])) {
    $seriesId = intval($_GET['series']);
}

$pools = SeriesPools($seriesId, false, true, true);
$not_started = [];
foreach ($pools as $pool) {
    if ($pool['type'] == 2 && PoolPlayoffRoot($pool['pool_id']) != $pool['pool_id']) {
        continue;
    }
    if (!IsPoolStarted($pool['pool_id'])) {
        $not_started[] = $pool['pool_id'];
    }
}
$pools = $not_started;
$teamsNotInPool = SeriesTeamsWithoutPool($seriesId);

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
        float: left
    }


    ul.draglist {
        position: relative;
        width: 200px;
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

    ol.draglist {
        position: relative;
        width: 200px;
        background: #f7f7f7;
        border: 1px solid gray;
        margin: 0;
        padding: 0;
    }

    ol.draglist li {
        margin: 0px;
        cursor: move;
        zoom: 1;
        text-align: left;
        vertical-align: center;
    }

    li.list1 {
        background-color: #aaaaaa;
        border: 1px solid #7EA6B2;
    }
</style>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
echo "<table><tr><td style='width:250px'>";

//teams without pool
echo "<table><tr><td>\n";
echo "<h3>" . _("Without pool") . "</h3>\n";
echo "<div class='workarea' >\n";
echo "<ul id='unpooled' class='draglist' style='min-height:400px'>\n";
foreach ($teamsNotInPool as $team) {
    if (hasEditTeamsRight($seriesId)) {
        teamEntry("ffffff", TEAM_HEIGHT, $team['team_id'], $team['name'], $team['rank']);
    }
}
echo "</ul>\n";
echo "</div>\n</td>\n";
echo "</tr>\n</table>\n";
echo "</td>\n";

//pools with teams
echo "<td style='vertical-align:top'>\n";
echo "<table><tr>\n";
$areacount = 0;

foreach ($pools as $poolId) {
    $areacount++;
    if ($areacount % 3 == 0) {
        echo "</tr><tr>\n";
    }

    echo "<td>\n";
    $poolinfo = PoolInfo($poolId);
    $total_teams = 10;
    echo "<table><tr>\n";
    echo "<td style='vertical-align:top;padding:5px'>\n";
    echo "<div style='vertical-align:bottom;height:100%'><h3>" . $poolinfo['name'] . "</h3></div>\n";
    echo "<div class='workarea' >\n";
    echo "<ol id='res" . $poolId . "' class='draglist' style='min-height:" . ($total_teams * TEAM_HEIGHT) . "px'>\n";
    $poolteams = PoolTeams($poolId);
    foreach ($poolteams as $team) {
        teamEntry("ffffff", TEAM_HEIGHT, $team['team_id'], $team['name'], $team['seed']);
    }
    echo "</ol>\n";
    echo "</div>\n</td>\n";
    echo "</tr>\n</table>\n";
    echo "</td>\n";
}

echo "</tr></table>\n";
echo "</td></tr>\n";
echo "<tr><td>\n";
//save button
$seriesinfo = SeriesInfo($seriesId);
echo "<table><tr><td id='user_actions' style='float:left;padding:20px'>\n";
echo "<input type='button' id='saveButton' value='" . _("Save") . "'/>\n";
echo "</td><td style='padding:20px'><a href='?view=admin/seasonpools&amp;season=" . $seriesinfo['season'] . "&amp;series=" . $seriesId . "'>" . _("Back") . "</a></td>";
echo "<td class='center'><div id='responseStatus'></div></td></tr></table>\n";
echo "</td></tr></table>\n";

?>
<script type="text/javascript">
    function hide(id) {
        var elem = document.getElementById(id);
        elem.parentNode.removeChild(elem);
    }

    (function() {
        var lists = document.querySelectorAll(".draglist");
        <?php if (hasEditTeamsRight($seriesId)) { ?>
            makeSortableLists(lists, {
                filter: "a"
            });
        <?php } ?>
        document.getElementById("saveButton").addEventListener("click", function() {
            var request = Array.prototype.map.call(lists, function(list) {
                var poolId = list.id === "unpooled" ? "0" : list.id.substring(3);
                return [poolId].concat(sortedListIds(list, "team")).join("/");
            }).join("|");
            postSortedLists("index.php?view=admin/saveteampools", request, document.getElementById("responseStatus"));
        });
    })();
</script>


<?php
contentEnd();
pageEnd();

function teamEntry($color, $height, $teamId, $name, $seed, $editable = true)
{
    $textColor = textColor($color);
    echo "<li class='list1' style='color:#" . $textColor . ";background-color:#" . $color . ";height:" . $height . "px' id='team" . $teamId . "'>" . $name . " (" . $seed . ")";
    if ($editable) {
        echo "<span style='align:right;float:right'><a href='javascript:hide(\"team" . $teamId . "\");'>x</a></span>";
    }
    echo "</li>\n";
}
?>
