<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/yui.functions.php';

$LAYOUT_ID = SEASONPOOLS;
$seriesId = 0;
$season = 0;
$order = "to";
$title = _("Moved teams");

if (!empty($_GET["season"]))
	$season = $_GET["season"];

if (!empty($_GET["series"])) {
	$seriesId = $_GET["series"];
	if (empty($season)) {
		$season = SeriesSeasonId($seriesId);
	}
}

if (!empty($_GET["order"]))
	$order = $_GET["order"];

//common page
pageTopHeadOpen($title);
echo yuiLoad(array("utilities"));
?>
<script type="text/javascript">
	function setId(id1, id2, id3) {
		var input = document.getElementById("hiddenDeleteId");
		input.value = id1 + ":" + id2 + ":" + id3;
	}

	function setPool(poolid, from) {
		var input = document.getElementById("hiddenPoolId");
		input.value = poolid + ":" + from;
	}

	function ChgName(index) {
		YAHOO.util.Dom.get('schedulingnameEdited' + index).value = 'yes';
		YAHOO.util.Dom.get("save").disabled = false;
	}

	function ChgValue(index) {
		YAHOO.util.Dom.get('moveEdited' + index).value = 'yes';
		YAHOO.util.Dom.get("save").disabled = false;
	}
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$id = -9999;
if (isset($_POST['hiddenDeleteId'])) {
	$id = $_POST['hiddenDeleteId'];
}

//process itself on submit
if (!empty($_POST['remove_x'])) {
	$move = preg_split('/:/', $_POST['hiddenDeleteId']);

	PoolDeleteMove($move[0], $move[1]);
} elseif (!empty($_POST['undo'])) {
	$move = preg_split('/:/', $_POST['hiddenDeleteId']);

	PoolUndoMove($move[0], $move[1], $move[2]);
} elseif (!empty($_POST['removeAll_x'])) {
	$params = preg_split('/:/', $_POST['hiddenPoolId']);

	if ($order == "from") { // FIXME or parms[1]?
		$moves = PoolMovingsFromPool($params[0]);
	} else {
		$moves = PoolMovingsToPool($params[0]);
	}

	foreach ($moves as $row) {
		if (!$row['ismoved']) {
			PoolDeleteMove($row['frompool'], $row['fromplacing']);
		}
	}
} elseif (!empty($_POST['undoPool'])) {
	$params = preg_split('/:/', $_POST['hiddenPoolId']);

	if ($order == "from") { // FIXME or parms[1]?
		$moves = PoolMovingsFromPool($params[0]);
	} else {
		$moves = PoolMovingsToPool($params[0]);
	}

	foreach ($moves as $row) {
		if ($row['ismoved']) {
			$team = PoolTeamFromStandings($row['frompool'], $row['fromplacing']);
			if (CanDeleteTeamFromPool($row['topool'], $team['team_id'])) {
				PoolUndoMove($row['frompool'], $row['fromplacing'], $row['topool']);
			}
		}
	}
} elseif (!empty($_POST['save'])) {
	for ($i = 0; $i < count($_POST['schedulingnameEdited']); $i++) {
		if ($_POST['schedulingnameEdited'][$i] == "yes") {
			$id = $_POST['schedulingnameId'][$i];
			PoolSetSchedulingName($id, $_POST["sn$i"], $season);
		}
	}
	for ($i = 0; $i < count($_POST['moveEdited']); $i++) {
		if ($_POST['moveEdited'][$i] == "yes") {
			$id = $_POST['moveId'][$i];
			//PoolSetSchedulingName($id, $_POST["sn$i"], $season);
			$move = preg_split('/:/', $id);
			$frompool = $move[0];
			$fromplacing = $move[1];
			$newfp =  $_POST["fromplacing$i"];
			$newtp =  $_POST["torank$i"];
			PoolSetMove($frompool, $fromplacing, $newfp, $newtp);
		}
	}
}

echo "[<a href='?view=admin/seasonmoves&amp;season=$season&amp;series=$seriesId&amp;order=to'>" . _("Move to") . "</a>]";
echo "&nbsp;&nbsp;";
echo "[<a href='?view=admin/seasonmoves&amp;season=$season&amp;series=$seriesId&amp;order=from'>" . _("Move from") . "</a>]";



echo "<form method='post' id='moves' action='?view=admin/seasonmoves&amp;season=$season&amp;series=$seriesId&amp;order=$order'>";

$serieslist = array();
//all series from season
if (!$seriesId) {
	$series = SeasonSeries($season);
	foreach ($series as $row) {
		$serieslist[] = $row;
	}
} else {
	$serieslist[] = array("series_id" => $seriesId, "name" => SeriesName($seriesId));
}

foreach ($serieslist as $series) {
	$pools = SeriesPools($series['series_id']);

	if (count($pools)) {
		echo "<h2>" . utf8entities(U_($series['name'])) . "</h2>\n";
		$i = 0;
		foreach ($pools as $pool) {
			if ($order == "from") {
				$moves = PoolMovingsFromPool($pool['pool_id']);
			} else {
				$moves = PoolMovingsToPool($pool['pool_id']);
			}

			if (count($moves)) {
				echo "<table class='admintable'><tr>
					<th style='width:25%'>" . _("From pool") . "</th>
					<th style='width:4%'>" . _("From position") . "</th>
					<th style='width:25%'>" . _("To pool") . "</th>
					<th style='width:4%'>" . _("To position") . "</th>
					<th style='width:18%'>" . _("Scheduling name") . "</th>
					<th style='width:10%'>" . _("Move games") . "</th>
					<th style='width:14%'><input class='button' type='submit' name='undoPool' value='" . _("Undo") . "' onclick=\"setPool(" . $pool['pool_id'] . "," . ($order == "from" ? "true" : "false") . ");\"/>
						<input class='deletebutton' type='image' src='images/remove.png' alt='X' name='removeAll' value='" . _("X") . "' onclick=\"setPool(" . $pool['pool_id'] . "," . ($order == "from" ? "true" : "false") . ");\"/></th></tr>";
			}

			foreach ($moves as $row) {
				$poolinfo = PoolInfo($row['topool']);
				if ($row['ismoved']) {
					echo "<tr class='highlight'>";
				} else {
					echo "<tr>";
				}
				echo "<td>" . utf8entities(PoolName($row['frompool'])) . "</td>";
				echo "<td class='center'>";
				echo "<input type='hidden' id='moveEdited" . $i . "' name='moveEdited[]' value='no'/>\n";
				echo "<input type='hidden' name='moveId[]' value='" . utf8entities($row['frompool']) . ":" . $row['fromplacing'] . "'/>\n";
				echo "<input type='text' size='3' maxlength='3' name='fromplacing$i' value='" . utf8entities($row['fromplacing']) . "' onkeypress='ChgValue(" . $i . ")'/></td>";
				echo "<td>" . utf8entities(PoolName($row['topool'])) . "</td>";
				echo "<td class='center'><input type='text' size='3' maxlength='3' name='torank$i' value='" . utf8entities($row['torank']) . "' onkeypress='ChgValue(" . $i . ")'/></td>";
				echo "<td class='left'>";
				echo "<input type='hidden' id='schedulingnameEdited" . $i . "' name='schedulingnameEdited[]' value='no'/>\n";
				echo "<input type='hidden' name='schedulingnameId[]' value='" . utf8entities($row['scheduling_id']) . "'/>\n";
				echo "<input type='text' size='22' maxlength='50' value='" . utf8entities($row['sname']) . "' name='sn$i' onkeypress='ChgName(" . $i . ")'/>";
				echo "</td>";
				if (intval($poolinfo['mvgames']) == 0)
					echo "<td>" . _("all") . "</td>";
				else if (intval($poolinfo['mvgames']) == 1)
					echo "<td>" . _("nothing") . "</td>";
				else if (intval($poolinfo['mvgames']) == 2)
					echo "<td>" . _("mutual") . "</td>";
				if ($row['ismoved']) {
					$team = PoolTeamFromStandings($row['frompool'], $row['fromplacing']);
					if (CanDeleteTeamFromPool($row['topool'], $team['team_id'])) {
						echo "<td class='right'><input class='button' type='submit' name='undo' value='" . _("Undo") . "' onclick=\"setId(" . $row['frompool'] . "," . $row['fromplacing'] . "," . $row['topool'] . ");\"/></td>";
					} else {
						echo "<td class='right'></td>";
					}
				} else {
					echo "<td class='right'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='X' onclick=\"setId(" . $row['frompool'] . "," . $row['fromplacing'] . ");\"/></td>";
				}
				echo "</tr>\n";
				$i++;
			}
			if (count($moves)) {
				echo "</table>\n";
			}
		}
	}
}
//stores id to delete
echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/><input type='hidden' id='hiddenPoolId' name='hiddenPoolId'/>";
echo "<input disabled='disabled' id='save' class='button' name='save' type='submit' value='" . _("Save") . "'/>";
echo "<input class='button' type='button' name='back'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/seasonpools&amp;season=$season'\"/></p>";
echo "</form>\n";

contentEnd();
pageEnd();
?>