<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/common.functions.php';
require_once("lib/HSVClass.php");


$LAYOUT_ID = SCHEDULE;
$title = _("Team selection");
define("MIN_HEIGHT", 0.5);
define("TEAM_HEIGHT", 15);

$seriesId = 0;
if (isset($_GET['series'])) {
    $seriesId = intval($_GET['series']);
}

$pools = SeriesPools($seriesId, false, true, false);
$not_started = array();
foreach ($pools as $pool) {
    if (!IsPoolStarted($pool['pool_id'])) {
        $not_started[] = $pool['pool_id'];
    }
}
$pools = $not_started;
$teamsNotInPool = SeriesTeamsWithoutPool($seriesId);

//common page
pageTopHeadOpen($title);

include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "dragdrop"));
?>

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
echo "<ul id='unpooled' class='draglist' style='height:400px'>\n";
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
    if ($areacount % 3 == 0)
        echo "</tr><tr>\n";

    echo "<td>\n";
    $poolinfo = PoolInfo($poolId);
    $total_teams = 10;
    echo "<table><tr>\n";
    echo "<td style='vertical-align:top;padding:5px'>\n";
    echo "<div style='vertical-align:bottom;height:100%'><h3>" . $poolinfo['name'] . "</h3></div>\n";
    echo "<div class='workarea' >\n";
    echo "<ol id='res" . $poolId . "' class='draglist' style='height:" . ($total_teams * TEAM_HEIGHT) . "px'>\n";
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
echo "<table><tr><td id='user_actions' style='float:left;padding:20px'>\n";
echo "<input type='button' id='saveButton' value='" . _("Save") . "'/>\n";
echo "</td><td class='center'><div id='responseStatus'></div></td></tr></table>\n";
echo "</td></tr></table>\n";

?>
<script type="text/javascript">
    //<![CDATA[

    var Dom = YAHOO.util.Dom;

    function hide(id) {
        var elem = Dom.get(id);
        var list = Dom.getAncestorByTagName(elem, "ul");
        list.removeChild(elem);
    }

    (function() {

        var Event = YAHOO.util.Event;
        var DDM = YAHOO.util.DragDropMgr;
        var pauseIndex = 1;
        var minHeight = <?php echo TEAM_HEIGHT; ?>;


        YAHOO.example.ScheduleApp = {
            init: function() {
                <?php
                echo "		new YAHOO.util.DDTarget(\"unpooled\");\n";
                foreach ($pools as $poolId) {
                    echo "		new YAHOO.util.DDTarget(\"res" . $poolId . "\");\n";
                    $poolteams = PoolTeams($poolId);
                    foreach ($poolteams as $team) {
                        if (hasEditTeamsRight($seriesId)) {
                            echo "		new YAHOO.example.DDList(\"team" . $team['team_id'] . "\");\n";
                        }
                    }
                }

                foreach ($teamsNotInPool as $team) {
                    if (hasEditTeamsRight($seriesId)) {
                        echo "		new YAHOO.example.DDList(\"team" . $team['team_id'] . "\");\n";
                    }
                }

                ?>
                Event.on("saveButton", "click", this.requestString);
            },

            requestString: function() {
                var parseList = function(ul, id) {
                    var items = ul.getElementsByTagName("li");
                    var out = id;
                    if (items.length) {
                        out += "/";
                    }
                    var offset = 0;
                    for (i = 0; i < items.length; i = i + 1) {
                        var nextId = items[i].id.substring(4);

                        if (!isNaN(nextId)) {
                            out += nextId;
                        }
                        if ((i + 1) < items.length) {
                            out += "/";
                        }

                    }
                    return out;
                };
                <?php
                echo "	var unpooled=Dom.get(\"unpooled\");\n";
                foreach ($pools as $poolId) {
                    echo "	var res" . $poolId . "=Dom.get(\"res" . $poolId . "\");\n";
                }
                echo "	var request = parseList(unpooled, \"0\") + \"\\n\"";
                foreach ($pools as $poolId) {
                    echo " + \"|\" + parseList(res" . $poolId . ", \"" . $poolId . "\")";
                }
                echo ";\n";
                ?>
                var responseDiv = Dom.get("responseStatus");
                Dom.setStyle(responseDiv, "background-image", "url('images/indicator.gif')");
                Dom.setStyle(responseDiv, "background-repeat", "no-repeat");
                Dom.setStyle(responseDiv, "background-position", "top right");
                Dom.setStyle(responseDiv, "height", "20px");
                Dom.setStyle(responseDiv, "width", "20px");
                Dom.setStyle(responseDiv, "class", "inprogress");
                responseDiv.innerHTML = '&nbsp;';

                var transaction = YAHOO.util.Connect.asyncRequest('POST', 'index.php?view=admin/saveteampools', callback, request);
            },
        };

        var callback = {
            success: function(o) {
                var responseDiv = Dom.get("responseStatus");
                Dom.setStyle(responseDiv, "background-image", "");
                Dom.setStyle(responseDiv, "color", "#00aa00");
                responseDiv.innerHTML = o.responseText;
            },

            failure: function(o) {
                var responseDiv = Dom.get("responseStatus");
                Dom.setStyle(responseDiv, "background-image", "");
                Dom.setStyle(responseDiv, "color", "#aa0000");
                responseDiv.innerHTML = o.responseText;
            }
        }

        YAHOO.example.DDList = function(id, sGroup, config) {

            YAHOO.example.DDList.superclass.constructor.call(this, id, sGroup, config);

            this.logger = this.logger || YAHOO;
            var el = this.getDragEl();
            Dom.setStyle(el, "opacity", 0.57); // The proxy is slightly transparent

            this.goingUp = false;
            this.lastY = 0;
        };

        YAHOO.extend(YAHOO.example.DDList, YAHOO.util.DDProxy, {

            startDrag: function(x, y) {
                this.logger.log(this.id + " startDrag");

                // make the proxy look like the source element
                var dragEl = this.getDragEl();
                var clickEl = this.getEl();
                Dom.setStyle(clickEl, "visibility", "hidden");

                dragEl.innerHTML = clickEl.innerHTML;

                Dom.setStyle(dragEl, "color", Dom.getStyle(clickEl, "color"));
                Dom.setStyle(dragEl, "backgroundColor", Dom.getStyle(clickEl, "backgroundColor"));
                Dom.setStyle(dragEl, "font-size", Dom.getStyle(clickEl, "font-size"));
                Dom.setStyle(dragEl, "font-family", Dom.getStyle(clickEl, "font-family"));
                Dom.setStyle(dragEl, "border", "2px solid gray");
                Dom.setStyle(dragEl, "text-align", "center");
            },

            endDrag: function(e) {

                var srcEl = this.getEl();
                var proxy = this.getDragEl();

                // Show the proxy element and animate it to the src element's location
                Dom.setStyle(proxy, "visibility", "");
                var a = new YAHOO.util.Motion(
                    proxy, {
                        points: {
                            to: Dom.getXY(srcEl)
                        }
                    },
                    0.2,
                    YAHOO.util.Easing.easeOut
                )
                var proxyid = proxy.id;
                var thisid = this.id;

                // Hide the proxy and show the source element when finished with the animation
                a.onComplete.subscribe(function() {
                    Dom.setStyle(proxyid, "visibility", "hidden");
                    Dom.setStyle(thisid, "visibility", "");
                });
                a.animate();
            },

            onDragDrop: function(e, id) {

                // If there is one drop interaction, the li was dropped either on the list,
                // or it was dropped on the current location of the source element.
                if (DDM.interactionInfo.drop.length === 1) {

                    // The position of the cursor at the time of the drop (YAHOO.util.Point)
                    var pt = DDM.interactionInfo.point;

                    // The region occupied by the source element at the time of the drop
                    var region = DDM.interactionInfo.sourceRegion;

                    // Check to see if we are over the source element's location.  We will
                    // append to the bottom of the list once we are sure it was a drop in
                    // the negative space (the area of the list without any list items)
                    if (!region.intersect(pt)) {
                        var destEl = Dom.get(id);
                        var destDD = DDM.getDDById(id);
                        destEl.appendChild(this.getEl());
                        destDD.isEmpty = false;
                        DDM.refreshCache();
                    }

                }
            },

            onDrag: function(e) {

                // Keep track of the direction of the drag for use during onDragOver
                var y = Event.getPageY(e);

                if (y < this.lastY) {
                    this.goingUp = true;
                } else if (y > this.lastY) {
                    this.goingUp = false;
                }

                this.lastY = y;
            },

            onDragOver: function(e, id) {

                var srcEl = this.getEl();
                var destEl = Dom.get(id);

                // We are only concerned with list items, we ignore the dragover
                // notifications for the list.
                if (destEl.nodeName.toLowerCase() == "li") {
                    var orig_p = srcEl.parentNode;
                    var p = destEl.parentNode;

                    if (this.goingUp) {
                        p.insertBefore(srcEl, destEl); // insert above
                    } else {
                        p.insertBefore(srcEl, destEl.nextSibling); // insert below
                    }

                    DDM.refreshCache();
                }
            }
        });

        Event.onDOMReady(YAHOO.example.ScheduleApp.init, YAHOO.example.ScheduleApp, true);

    })();

    //]]>
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