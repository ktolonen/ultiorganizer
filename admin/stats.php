<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/season.functions.php';
include_once 'lib/statistical.functions.php';

$title = _("Event statistics");
$LAYOUT_ID = CALCSEASONSTATISTICS;
$html = "";

$season = $_GET["season"];
$seasons = array();
$series = array();
$teams = array();

if (!empty($_POST['calc'])) {
    set_time_limit(120);
    CalcSeasonStats($season);
    CalcSeriesStats($season);
    CalcTeamStats($season);
    CalcPlayerStats($season);
}

//common page
pageTopHeadOpen($title);
?>

<style type="text/css">
    div.workarea {
        padding-left: 25px;
        float: left
    }

    ol.draglist {
        position: relative;
        width: 100px;
        height: 600px;
        background: #f7f7f7;
        border: 1px solid gray;
        margin: 0;
        padding: 0;
    }

    ol.draglist li {
        margin: 1px;
        cursor: move;
        zoom: 1;
    }

    li.list {
        background-color: #D1E6EC;
        border: 1px solid #7EA6B2;
    }
</style>


<?php
include_once 'lib/yui.functions.php';
echo yuiLoad(array("animation", "dragdrop", "connection"));

pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
$html .= "<form method='post' action='?view=admin/stats&amp;season=$season'>\n";
$html .= "<p>" . _("Calculation of statistics takes some time; please wait without closing browser.") . "</p>\n";

if (!IsSeasonStatsCalculated($season)) {
    $html .= "<p><input class='button' name='calc' type='submit' value='" . _("Calculate") . "'/></p>\n";
} else {
    $seasons = SeasonStatistics($season);
    $series = SeriesStatistics($season);
    $teams = SeasonTeamStatistics($season);

    $html .= "<ul>";
    $html .= "<li>" . _("Teams") . ": " . $seasons['teams'] . "</li>\n";
    $html .= "<li>" . _("Players") . ": " . $seasons['players'] . "</li>\n";
    $html .= "<li>" . _("Games") . ": " . $seasons['games'] . "</li>\n";
    $html .= "<li>" . _("Division") . ": " . count($series) . "</li>\n";
    $html .= "</ul>";
    $html .= "<p><input class='button' name='calc' type='submit' value='" . _("Re-Calculate") . "'/></p>\n";

    $prevseries = -1;
    $html .= "<h1>" . _("Final Standings") . "</h1>";
    $html .= "<p>" . _("If standings are not correct, use mouse to drag teams into correct order and press save standings.") . "\n";
    $html .= "<input type='button' id='saveButton' value='" . _("Save standings") . "'/></p>\n";
    $html .= "<div id='responseStatus'></div>\n";

    foreach ($teams as $team) {
        if ($team['series'] != $prevseries) {
            if ($prevseries != -1) {
                $html .= "</ol>";
                $html .= "</div>";
            }
            $html .= "<div class='workarea'>";
            $html .= "<h3>" . U_($team['seriesname']) . "</h3>";
            $html .= "<ol id='ol" . $team['series'] . "' class='draglist'>\n";
            $html .= "<li class='list' id='li" . $team['team_id'] . "'>" . $team['teamname'] . "</li>\n";

            $prevseries = $team['series'];
        } else {
            $html .= "<li class='list' id='li" . $team['team_id'] . "'>" . $team['teamname'] . "</li>\n";
        }
    }

    if ($prevseries != -1) {
        $html .= "</ol>";
        $html .= "</div>";
    }
}

$html .= "</form>\n";
echo $html;
?>
<script type="text/javascript">
    (function() {

        var Dom = YAHOO.util.Dom;
        var Event = YAHOO.util.Event;
        var DDM = YAHOO.util.DragDropMgr;
        var sourceId = 0;

        YAHOO.example.DDApp = {
            init: function() {
                <?php
                $prevseries = -1;

                foreach ($teams as $team) {
                    if ($team['series'] != $prevseries) {
                        echo "new YAHOO.util.DDTarget('ol" . $team['series'] . "');\n";
                        echo "new YAHOO.example.DDList('li" . $team['team_id'] . "');\n";
                        $prevseries = $team['series'];
                    } else {
                        echo "new YAHOO.example.DDList('li" . $team['team_id'] . "');\n";
                    }
                }
                ?>
                Event.on("saveButton", "click", this.requestString);
            },

            requestString: function() {
                var parseList = function(ul, id) {
                    var items = ul.getElementsByTagName("li");
                    var out = "";
                    var offset = 0;
                    for (i = 0; i < items.length; i = i + 1) {
                        var nextId = items[i].id.substring(2);
                        if (!isNaN(nextId)) {
                            out += nextId + ":";
                        }
                    }
                    return out;
                };
                <?php

                foreach ($series as $ser) {
                    echo "	var ol" . $ser['series_id'] . "=Dom.get(\"ol" . $ser['series_id'] . "\");\n";
                }
                echo "var request = ";
                foreach ($series as $ser) {
                    echo " parseList(ol" . $ser['series_id'] . ", \"" . $ser['series_id'] . "\")+ \"|\" + ";
                }
                echo "\"\";\n";
                ?>
                var responseDiv = Dom.get("responseStatus");
                Dom.setStyle(responseDiv, "background-image", "url('images/indicator.gif')");
                Dom.setStyle(responseDiv, "background-repeat", "no-repeat");
                Dom.setStyle(responseDiv, "background-position", "top right");
                Dom.setStyle(responseDiv, "height", "20px");
                Dom.setStyle(responseDiv, "width", "20px");
                Dom.setStyle(responseDiv, "class", "inprogress");
                responseDiv.innerHTML = '&nbsp;';
                var transaction = YAHOO.util.Connect.asyncRequest('POST', 'index.php?view=admin/saveteamstandings', callback, request);
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
        //////////////////////////////////////////////////////////////////////////////
        // custom drag and drop implementation
        //////////////////////////////////////////////////////////////////////////////

        YAHOO.example.DDList = function(id, sGroup, config) {

            YAHOO.example.DDList.superclass.constructor.call(this, id, sGroup, config);

            this.logger = this.logger || YAHOO;
            var el = this.getDragEl();
            Dom.setStyle(el, "opacity", 0.67); // The proxy is slightly transparent

            this.goingUp = false;
            this.lastY = 0;
        };

        YAHOO.extend(YAHOO.example.DDList, YAHOO.util.DDProxy, {

            startDrag: function(x, y) {
                this.logger.log(this.id + " startDrag");

                // make the proxy look like the source element
                var dragEl = this.getDragEl();
                sourceEl = this.getDragEl();
                var clickEl = this.getEl();
                Dom.setStyle(clickEl, "visibility", "hidden");
                this.setXConstraint(10, 10);
                dragEl.innerHTML = clickEl.innerHTML;

                Dom.setStyle(dragEl, "color", Dom.getStyle(clickEl, "color"));
                Dom.setStyle(dragEl, "backgroundColor", Dom.getStyle(clickEl, "backgroundColor"));
                Dom.setStyle(dragEl, "border", "2px solid gray");
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



        Event.onDOMReady(YAHOO.example.DDApp.init, YAHOO.example.DDApp, true);

    })();
</script>
<?php
contentEnd();
pageEnd();

?>