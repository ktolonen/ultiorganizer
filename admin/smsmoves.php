<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = SEASONPOOLS;
$seriesId = 0;
$season = 0;
$title = _("Create SMS");

if (!empty($_GET["season"]))
	$season = $_GET["season"];

if (!empty($_GET["series"])) {
	$seriesId = $_GET["series"];
	if (empty($season)) {
		$season = SeriesSeasonId($seriesId);
	}
}

if (!empty($_POST["pool"]))
	$poolId = $_POST["pool"];


//common page
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();



//process itself on submit
if (!empty($poolId)) {
	$poolinfo = PoolInfo($poolId);
	if ($poolinfo['placementpool']) { // create SMS for final rankings
		$teams = PoolTeams($poolId);
		if (count($teams)) {
			echo "<table border='0' width='500'><tr>
				<th>" . _("Pool") . "</th>
				<th>" . _("Final Rank") . "</th>
				<th>" . _("Team") . "</th>
				<th>" . _("Outcome") . "</th>
				<th>" . _("SMS") . "</th></tr>";
		}
		foreach ($teams as $row) {
			$sms = "";

			echo "<tr>";
			echo "<td>" . utf8entities(PoolName($poolId)) . "</td>";
			echo "<td class='center'>" . PoolPlacementString($poolId, $row['activerank'], false) . "</td>";
			echo "<td class='center'>" . utf8entities($row['name']) . "</td>";

			// get this pool's last result
			$lastgame = TeamPoolLastGame($row['team_id'], $poolId);
			if (!empty($lastgame)) {
				if ($lastgame['hometeam'] == $row['team_id']) {
					$lastgame['ownscore'] = $lastgame['homescore'];
					$lastgame['oppscore'] = $lastgame['visitorscore'];
				} else {
					$lastgame['ownscore'] = $lastgame['visitorscore'];
					$lastgame['oppscore'] = $lastgame['homescore'];
				}
				$lastgame['outcome'] = $lastgame['ownscore'] . "-" . $lastgame['oppscore'];
				if ($lastgame['ownscore'] > $lastgame['oppscore']) {
					$lastgame['outcome'] .= " win";
				} elseif ($lastgame['ownscore'] < $lastgame['oppscore']) {
					$lastgame['outcome'] .= " loss";
				} elseif ($lastgame['ownscore'] == $lastgame['oppscore']) {
					$lastgame['outcome'] .= " tie";
				}
			}
			//		debugvar($lastgame);
			echo "<td>" . utf8entities($lastgame['outcome']) . "</td>";

			// create SMS
			if (!empty($lastgame)) {
				$sms = "After a " . $lastgame['outcome'] . " in the final game, you finish Windmill 2011 in place " . PoolPlacementString($poolId, $row['activerank'], false) . ".";
				$sms .= "Congratulations! Please hand in today's spirit scores, see you next year!";
			} else { // team just had a BYE in playoffs
				$sms = "After a BYE in the final game, you finish Windmill 2011 in place " . PoolPlacementString($poolId, $row['activerank'], false) . ".";
				$sms .= "Congratulations! Please hand in today's spirit scores, see you next year!";
			}

			echo "<td>" . utf8entities($sms) . "</td>";


			echo "</tr>\n";
			$smsarray[] = $sms;
		}
		if (count($teams)) {
			echo "</table>\n";
		}
	} else { // create SMS from moves
		$moves = PoolMovingsFromPoolWithTeams($poolId);

		if (count($moves)) {
			echo "<table border='0' width='500'><tr>
				<th>" . _("From pool") . "</th>
				<th>" . _("From position") . "</th>
				<th>" . _("To pool") . "</th>
				<th>" . _("To position") . "</th>
				<th>" . _("Team") . "</th>
				<th>" . _("Outcome") . "</th>
				<th>" . _("Next Opp.") . "</th>
				<th>" . _("Next Opp. Rank") . "</th>
				<th>" . _("Time") . "</th>
				<th>" . _("Field") . "</th>
				<th>" . _("SMS") . "</th></tr>";
		}

		$smscount = 0;
		//		while($row = mysqli_fetch_assoc($moves))	{
		foreach ($moves as $row) {
			//		$poolinfo = PoolInfo($row['topool']);

			$sms = "";

			echo "<tr>";
			echo "<td>" . utf8entities(PoolName($row['frompool'])) . "</td>";
			echo "<td class='center'>" . intval($row['fromplacing']) . "</td>";
			echo "<td>" . utf8entities(PoolName($row['topool'])) . "</td>";
			echo "<td class='center'>" . intval($row['torank']) . "</td>";
			echo "<td class='center'>" . utf8entities($row['teamname']) . "</td>";

			// get this pool's last result
			$lastgame = TeamPoolLastGame($row['team_id'], $poolId);
			if (!empty($lastgame)) {
				if ($lastgame['hometeam'] == $row['team_id']) {
					$lastgame['ownscore'] = $lastgame['homescore'];
					$lastgame['oppscore'] = $lastgame['visitorscore'];
				} else {
					$lastgame['ownscore'] = $lastgame['visitorscore'];
					$lastgame['oppscore'] = $lastgame['homescore'];
				}
				$lastgame['outcome'] = $lastgame['ownscore'] . "-" . $lastgame['oppscore'];
				if ($lastgame['ownscore'] > $lastgame['oppscore']) {
					$lastgame['outcome'] .= " win";
				} elseif ($lastgame['ownscore'] < $lastgame['oppscore']) {
					$lastgame['outcome'] .= " loss";
				} elseif ($lastgame['ownscore'] == $lastgame['oppscore']) {
					$lastgame['outcome'] .= " tie";
				}
			}
			//		debugvar($lastgame);
			echo "<td>" . utf8entities($lastgame['outcome']) . "</td>";

			// get next opponent
			$nextgame = TeamGetNextGames($row['team_id'], $row['topool']);
			if ($nextgame['hometeam'] == $row['team_id']) {
				$nextgame['opp_id'] = $nextgame['visitorteam'];
			} elseif ($nextgame['visitorteam'] == $row['team_id']) {
				$nextgame['opp_id'] = $nextgame['hometeam'];
			}


			// opponent's rank in CURRENT pool
			$oppinfo = TeamPoolInfo($nextgame['opp_id'], $row['topool']); //$poolId);
			echo "<td>" . utf8entities($oppinfo['name']) . "</td>";
			echo "<td>" . utf8entities($oppinfo['activerank']) . "</td>";

			// get next timeslot and field number
			echo "<td>" . utf8entities($nextgame['time']) . "</td>";
			echo "<td>" . utf8entities($nextgame['fieldname']) . "</td>";

			// some more time formatting

			if (JustDate($lastgame['time']) < JustDate($nextgame['time'])) {
				$timestring = " tomorrow, at " . DefHourFormat($nextgame['time']) . ".Pls hand in spirit scrs!";
			} else {
				$timestring = " at " . DefHourFormat($nextgame['time']) . ".";
			}


			// create SMS
			if (!empty($oppinfo) && !empty($lastgame)) {
				$sms = "After a " . $lastgame['outcome'] . " in " . PoolShortName($row['frompool']) . ",you're ranked " . ordinal(intval($row['fromplacing'])) . ".";
				$sms .= "In" . PoolShortName($row['topool']) . ",you'll play " . $oppinfo['name'] . " (ranked " . ordinal(intval($oppinfo['activerank'])) . ")";
				if (!empty($nextgame['fieldname'])) {
					$sms .= " on Field " . $nextgame['fieldname'] . $timestring . ".";
				} else {
					$sms .= ", field yet unknown.";
				}
			} elseif (!empty($lastgame)) { // team will have a BYE in playoffs
				$sms = "After a " . $lastgame['outcome'] . " in " . PoolShortName($row['frompool']) . ",you're ranked " . ordinal(intval($row['fromplacing'])) . ".";
				$sms .= "In" . PoolShortName($row['topool']) . ",you'll have a BYE. Go see another game!";
			} elseif (!empty($oppinfo)) { // team just had a BYE in playoffs
				$sms = "After a BYE in " . PoolShortName($row['frompool']) . ",you're ranked " . ordinal(intval($row['fromplacing'])) . ".";
				$sms .= "In" . PoolShortName($row['topool']) . ",you'll play " . $oppinfo['name'] . "(ranked " . ordinal(intval($oppinfo['activerank'])) . ")";
				if (!empty($nextgame['fieldname'])) {
					$sms .= " on Field " . $nextgame['fieldname'] . $timestring . ".";
				} else {
					$sms .= ", field yet unknown.";
				}
			} else {
				die("something's wrong in smsmoves.php, we should have never arrived here ...");
			}

			echo "<td>" . utf8entities($sms) . "</td>";
			echo "</tr>\n";

			$smscount++;
			// get phone numbers
			$smsarray[$smscount]['msg'] = $sms;
			$smsarray[$smscount]['to1'] = "12345678";
			$smsarray[$smscount]['to2'] = "9876543210";
			$smsarray[$smscount]['to2'] = "9876543210";
			$smsarray[$smscount]['to3'] = "";
			$smsarray[$smscount]['to4'] = "";
			$smsarray[$smscount]['to5'] = "";
		}
		if (count($moves)) {
			echo "</table>\n";
		}
	}
	echo "<form method='post' action='?view=admin/sms'>";
	$smscount = 0;
	foreach ($smsarray as $sms) {
		$smscount++;
		$single_value = implode(";", $sms);
		echo "<p><input type='hidden' name='sms_$smscount' value='" . htmlspecialchars($single_value, ENT_QUOTES) . "'>";
	}
	echo "<p><input class='button' type='submit' name='sendsms' value='" . ("Send these SMS") . "'/>";
	echo "<input class='button' type='button' name='back'  value='" . _("Return without sending") . "' onclick=\"window.location.href='?view=admin/smsmoves&amp;season=$season&amp;series=$seriesId'\"/></p>";

	echo "</form>";
} else {
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
			foreach ($pools as $pool) {
				$moves = PoolMovingsFromPool($pool['pool_id']);

				if (PoolIsAllMoved($pool['pool_id'])) {
					$pool['moves'] = count($moves);
					$selectablepools[] = $pool;
				}
			}
		}
	}

	echo "<form method='post' action='?view=admin/smsmoves&amp;season=$season&amp;series=$seriesId'>";

	echo "<p>" . ("Select source pool") . ": <select class='dropdown' name='pool'>\n";

	foreach ($selectablepools as $pool) {
		echo "<option class='dropdown' value='" . utf8entities($pool['pool_id']) . "'>" . utf8entities($pool['name']) . " (" . utf8entities($pool['moves']) . " moves)</option>";
	}

	echo "</select></p>\n";
	echo "<p><input class='button' type='submit' name='createsms' value='" . ("Create SMS") . "'/></p>";

	echo "</form>";
}

contentEnd();
pageEnd();
