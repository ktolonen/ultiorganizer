<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/season.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/club.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/series.functions.php';
$LAYOUT_ID = DBEQUALIZER;
$title = _("Database equalization");
$filter = 'teams';
$baseurl = "?view=admin/dbequalize";
$html = "";
$result = "";

if (!isSuperAdmin()) {
	Forbidden(isset($_SESSION['uid']) ? $_SESSION['uid'] : 'anonymous');
}


if (!empty($_GET["filter"])) {
	$filter = $_GET["filter"];
} elseif (!empty($_POST["filter"])) {
	$filter = $_POST["filter"];
}

if (isset($_POST['deleteorphan']) && isSuperAdmin() && $filter == 'profiles') {
	$orphanCount = (int)DBQueryToValue("
		SELECT COUNT(*) FROM uo_player_profile pr
		LEFT JOIN uo_player p ON p.profile_id = pr.profile_id
		LEFT JOIN uo_player_stats ps ON ps.profile_id = pr.profile_id
		WHERE p.profile_id IS NULL AND ps.profile_id IS NULL
	");

	if ($orphanCount > 0) {
		DBQuery("
			DELETE pr FROM uo_player_profile pr
			LEFT JOIN uo_player p ON p.profile_id = pr.profile_id
			LEFT JOIN uo_player_stats ps ON ps.profile_id = pr.profile_id
			WHERE p.profile_id IS NULL AND ps.profile_id IS NULL
		");
		$result .= "<p>" . sprintf(_("Deleted %d orphan profiles"), $orphanCount) . "</p>";
	} else {
		$result .= "<p>" . _("No orphan profiles found") . "</p>";
	}
	$result .= "<hr/>";
} elseif (isset($_POST['mergeprofiles']) && !empty($_POST['ids']) && isSuperAdmin()) {
	$selectedIds = array_map('intval', $_POST['ids']);
	$idList = array_filter($selectedIds, function ($value) {
		return $value > 0;
	});

	if (!empty($idList)) {
		// Load all duplicate profiles (same firstname+lastname) with reference counts.
		$duplicates = DBQuery("
			SELECT pr.profile_id, pr.firstname, pr.lastname,
			       COALESCE(ps.cnt, 0) AS stats_refs,
			       COALESCE(p.cnt, 0) AS player_refs
			FROM uo_player_profile pr
			JOIN (
				SELECT LOWER(firstname) AS fn, LOWER(lastname) AS ln
				FROM uo_player_profile
				WHERE firstname IS NOT NULL AND lastname IS NOT NULL
				GROUP BY fn, ln
				HAVING COUNT(*) > 1
			) dup ON dup.fn = LOWER(pr.firstname) AND dup.ln = LOWER(pr.lastname)
			LEFT JOIN (SELECT profile_id, COUNT(*) AS cnt FROM uo_player_stats GROUP BY profile_id) ps ON ps.profile_id = pr.profile_id
			LEFT JOIN (SELECT profile_id, COUNT(*) AS cnt FROM uo_player GROUP BY profile_id) p ON p.profile_id = pr.profile_id
		");

		$groups = array();
		while ($row = mysqli_fetch_assoc($duplicates)) {
			$key = strtolower($row['firstname']) . '|' . strtolower($row['lastname']);
			$row['total_refs'] = (int)$row['stats_refs'] + (int)$row['player_refs'];

			if (!array_key_exists($key, $groups)) {
				$groups[$key] = array(
					'keep' => null,
					'rows' => array(),
				);
			}

			// Pick the keep-id with the highest total refs (then stats refs, then lowest profile_id for determinism).
			if (
				$groups[$key]['keep'] === null
				|| $row['total_refs'] > $groups[$key]['keep']['total_refs']
				|| ($row['total_refs'] == $groups[$key]['keep']['total_refs'] && $row['stats_refs'] > $groups[$key]['keep']['stats_refs'])
				|| ($row['total_refs'] == $groups[$key]['keep']['total_refs'] && $row['stats_refs'] == $groups[$key]['keep']['stats_refs'] && $row['profile_id'] < $groups[$key]['keep']['profile_id'])
			) {
				$groups[$key]['keep'] = $row;
			}

			$groups[$key]['rows'][] = $row;
		}

		$selectedLookup = array_flip($selectedIds);

		foreach ($groups as $groupKey => $data) {
			$keep = $data['keep'];
			if ($keep === null) {
				continue;
			}

			foreach ($data['rows'] as $row) {
				if (!array_key_exists($row['profile_id'], $selectedLookup)) {
					continue;
				}
				if ($row['profile_id'] == $keep['profile_id']) {
					continue;
				}

				$fromId = (int)$row['profile_id'];
				$keepId = (int)$keep['profile_id'];
				$playersMoved = (int)DBQueryToValue(sprintf("SELECT COUNT(*) FROM uo_player WHERE profile_id=%d", $fromId));
				$statsMoved = (int)DBQueryToValue(sprintf("SELECT COUNT(*) FROM uo_player_stats WHERE profile_id=%d", $fromId));

				DBQuery(sprintf("UPDATE uo_player SET profile_id=%d WHERE profile_id=%d", $keepId, $fromId));
				DBQuery(sprintf("UPDATE uo_player_stats SET profile_id=%d WHERE profile_id=%d", $keepId, $fromId));

				$result .= "<p>" . _("Profile") . " " . utf8entities($fromId) . " --> " . utf8entities($keepId) . " (" . utf8entities($row['firstname']) . " " . utf8entities($row['lastname']) . "): " . sprintf(_("moved %d players, %d stats"), $playersMoved, $statsMoved) . "</p>";
			}
		}
	}

	$result .= "<hr/>";
} elseif (isset($_POST['rename']) && !empty($_POST['ids']) && isSuperAdmin()) {
	$ids = $_POST["ids"];
	$name = $_POST["newname"];
	foreach ($ids as $id) {
		if ($filter == 'teams') {
			$result .= "<p>" . utf8entities(TeamName($id)) . " --> " . utf8entities($name) . "</p>";
			SetTeamName($id, $name);
		} elseif ($filter == 'clubs') {
			if ($id != $name) {
				$result .= "<p>" . utf8entities(ClubName($id)) . " --> " . utf8entities(ClubName($name)) . "</p>";
				$teams = TeamListAll();
				while ($team = mysqli_fetch_assoc($teams)) {
					if ($team['club'] == $id) {
						SetTeamOwner($team['team_id'], $name);
					}
				}
				if (CanDeleteClub($id)) {
					$result .= "<p>" . utf8entities(ClubName($id)) . " " . _("removed") . "</p>";
					RemoveClub($id);
				} else {
					$result .= "<p class='warning'>" . utf8entities(ClubName($id)) . " " . _("cannot delete") . "</p>";
				}
			}
		} elseif ($filter == 'pools') {
			$result .= "<p>" . utf8entities(PoolName($id)) . " --> " . utf8entities($name) . "</p>";
			SetPoolName($id, $name);
		} elseif ($filter == 'series') {
			$result .= "<p>" . utf8entities(SeriesName($id)) . " --> " . utf8entities($name) . "</p>";
			SetSeriesName($id, $name);
		}
	}
	$result .= "<hr/>";
} elseif (isset($_POST['remove']) && !empty($_POST['ids']) && isSuperAdmin()) {
	$ids = $_POST["ids"];
	$type = $_POST["filter"];
	$name = $_POST["newname"];
	foreach ($ids as $id) {
		if ($filter == 'teams') {
			if (CanDeleteTeam($id)) {
				$result .= "<p>" . utf8entities(TeamName($id)) . " " . _("removed") . "</p>";
				DeleteTeam($id);
			} else {
				$result .= "<p class='warning'>" . utf8entities(TeamName($id)) . " " . _("cannot delete") . "</p>";
			}
		} elseif ($filter == 'clubs') {
			if (CanDeleteClub($id)) {
				$result .= "<p>" . utf8entities(ClubName($id)) . " " . _("removed") . "</p>";
				RemoveClub($id);
			} else {
				$result .= "<p class='warning'>" . utf8entities(ClubName($id)) . " " . _("cannot delete") . "</p>";
			}
		} elseif ($filter == 'pools') {
			if (CanDeletePool($id)) {
				$result .= "<p>" . utf8entities(PoolName($id)) . " " . _("removed") . "</p>";
				DeletePool($id);
			} else {
				$result .= "<p class='warning'>" . utf8entities(PoolName($id)) . " " . _("cannot delete") . "</p>";
			}
		} elseif ($filter == 'series') {
			if (CanDeleteSeries($id)) {
				$result .= "<p>" . utf8entities(SeriesName($id)) . " " . _("removed") . "</p>";
				DeletePool($id);
			} else {
				$result .= "<p class='warning'>" . utf8entities(SeriesName($id)) . " " . _("cannot delete") . "</p>";
			}
		}
	}
	$result .= "<hr/>";
}

pageTopHeadOpen($title);
include 'script/common.js.inc';
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
$html .= $result;
$html .=  "<div>\n";
$html .=  _("List") . ": ";
$html .=  "<a href='" . utf8entities($baseurl) . "&amp;filter=teams'>" . _("Teams") . "</a>";
$html .=  "&nbsp;&nbsp;";
$html .=  "<a href='" . utf8entities($baseurl) . "&amp;filter=clubs'>" . _("Clubs") . "</a>";
$html .=  "&nbsp;&nbsp;";
$html .=  "<a href='" . utf8entities($baseurl) . "&amp;filter=pools'>" . _("Pools") . "</a>";
$html .=  "&nbsp;&nbsp;";
$html .=  "<a href='" . utf8entities($baseurl) . "&amp;filter=series'>" . _("Division") . "</a>";
$html .=  "&nbsp;&nbsp;";
$html .=  "<a href='" . utf8entities($baseurl) . "&amp;filter=profiles'>" . _("Profiles") . "</a>";
$html .=  "</div>\n";

$html .=  "<form id='ids' method='post' action='" . utf8entities($baseurl) . "'>\n";

if ($filter == 'clubs') {
	$html .=  "<p>" . _("Club to keep") . ":\n";
	//$html .=  "<input class='input' size='50' name='newname' value=''/></p>";
	$html .=  "<select class='dropdown' name='newname'>";
	$clubs = ClubList();
	foreach ($clubs as $row) {
		$html .= "<option class='dropdown' value='" . utf8entities($row['club_id']) . "'>" . utf8entities($row['name']) . "</option>";
	}
	$html .=  "</select></p>";
	$html .= "<p><input class='button' type='submit' name='rename' value='" . _("Join selected") . "'/>";
} elseif ($filter == 'profiles') {
	$html .=  "<p>" . _("Merge selected duplicate profiles into the most referred (stats + players) profile with the same name.") . "</p>";
	$html .= "<p><input class='button' type='submit' name='mergeprofiles' value='" . _("Merge selected profiles to most referred") . "'/>";
	$html .= "<input class='button' type='submit' name='deleteorphan' value='" . _("Delete orphan profiles") . "'/>";
} else {
	$html .=  "<p>" . _("New name") . ":\n";
	$html .=  "<input class='input' size='50' name='newname' value=''/></p>";
	$html .= "<p><input class='button' type='submit' name='rename' value='" . _("Rename selected") . "'/>";
}
if ($filter != 'profiles') {
	$html .= "<input class='button' type='submit' name='remove' value='" . _("Delete selected") . "'/>";
}
$html .= "<input class='button' type='reset' value='" . _("Clear") . "'/>";
$html .= "<input class='button' type='button' name='takaisin'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/dbadmin'\"/></p>";

$html .= "<table><tr><th><input type='checkbox' onclick='checkAll(\"ids\");'/></th>";
$prevname = "";
$prevseries = "";
$counter = 0;

if ($filter == 'teams') {
	$teams = TeamListAll();
	$html .= "<th>" . _("Team") . "</th><th>" . _("Division") . "</th><th>" . _("Club") . "</th><th>" . _("Event") . "</th></tr>\n";
	while ($team = mysqli_fetch_assoc($teams)) {
		if ($prevname != $team['name'] || $prevseries != $team['seriesname']) {
			$counter++;
			$prevname = $team['name'];
			$prevseries = $team['seriesname'];
		}
		if ($counter % 2) {
			$html .= "<tr class='highlight'>";
		} else {
			$html .= "<tr>";
		}

		$html .= "<td><input type='checkbox' name='ids[]' value='" . utf8entities($team['team_id']) . "'/></td>";
		$html .= "<td><b>" . utf8entities($team['name']) . "</b></td>";
		$html .= "<td>" . utf8entities($team['seriesname']) . "</td>";
		$html .= "<td>" . utf8entities($team['clubname']) . "</td>";
		$html .= "<td>" . utf8entities($team['seasonname']) . "</td>";
		$html .= "</tr>\n";
	}
} elseif ($filter == 'clubs') {
	$clubs = ClubList();
	$html .= "<th>" . _("Name") . "</th><th>" . _("Teams") . "</th></tr>\n";
	foreach ($clubs as $club) {
		if ($prevname != $club['name']) {
			$counter++;
			$prevname = $club['name'];
		}
		if ($counter % 2) {
			$html .= "<tr class='highlight'>";
		} else {
			$html .= "<tr>";
		}

		$html .= "<td><input type='checkbox' name='ids[]' value='" . utf8entities($club['club_id']) . "'/></td>";
		$html .= "<td><b>" . utf8entities($club['name']) . "</b></td>";
		$html .= "<td class='center'>" . ClubNumOfTeams($club['club_id']) . "</td>";
		$html .= "</tr>\n";
	}
} elseif ($filter == 'pools') {
	$pools = PoolListAll();
	$html .= "<th>" . _("Name") . "</th><th>" . _("Division") . "</th><th>" . _("Event") . "</th></tr>\n";
	while ($pool = mysqli_fetch_assoc($pools)) {
		if ($prevname != $pool['name']) {
			$counter++;
			$prevname = $pool['name'];
		}
		if ($counter % 2) {
			$html .= "<tr class='highlight'>";
		} else {
			$html .= "<tr>";
		}

		$html .= "<td><input type='checkbox' name='ids[]' value='" . utf8entities($pool['pool_id']) . "'/></td>";
		$html .= "<td><b>" . utf8entities($pool['name']) . "</b></td>";
		$html .= "<td>" . utf8entities($pool['seriesname']) . "</td>";
		$html .= "<td>" . utf8entities($pool['seasonname']) . "</td>";
		$html .= "</tr>\n";
	}
} elseif ($filter == 'series') {
	$series = Series();
	$html .= "<th>" . _("Name") . "</th><th>" . _("Event") . "</th></tr>\n";
	while ($row = mysqli_fetch_assoc($series)) {
		if ($prevname != $row['name']) {
			$counter++;
			$prevname = $row['name'];
		}
		if ($counter % 2) {
			$html .= "<tr class='highlight'>";
		} else {
			$html .= "<tr>";
		}

		$html .= "<td><input type='checkbox' name='ids[]' value='" . utf8entities($row['series_id']) . "'/></td>";
		$html .= "<td><b>" . utf8entities($row['name']) . "</b></td>";
		$html .= "<td>" . utf8entities($row['seasonname']) . "</td>";
		$html .= "</tr>\n";
	}
} elseif ($filter == 'profiles') {
	$duplicates = DBQuery("
		SELECT pr.profile_id, pr.firstname, pr.lastname,
		       COALESCE(ps.cnt, 0) AS stats_refs,
		       COALESCE(p.cnt, 0) AS player_refs
		FROM uo_player_profile pr
		JOIN (
			SELECT LOWER(firstname) AS fn, LOWER(lastname) AS ln
			FROM uo_player_profile
			WHERE firstname IS NOT NULL AND lastname IS NOT NULL
			GROUP BY fn, ln
			HAVING COUNT(*) > 1
		) dup ON dup.fn = LOWER(pr.firstname) AND dup.ln = LOWER(pr.lastname)
		LEFT JOIN (SELECT profile_id, COUNT(*) AS cnt FROM uo_player_stats GROUP BY profile_id) ps ON ps.profile_id = pr.profile_id
		LEFT JOIN (SELECT profile_id, COUNT(*) AS cnt FROM uo_player GROUP BY profile_id) p ON p.profile_id = pr.profile_id
		ORDER BY pr.firstname, pr.lastname, pr.profile_id
	");

	$groups = array();
	while ($row = mysqli_fetch_assoc($duplicates)) {
		$key = strtolower($row['firstname']) . '|' . strtolower($row['lastname']);
		$row['total_refs'] = (int)$row['stats_refs'] + (int)$row['player_refs'];

		if (!array_key_exists($key, $groups)) {
			$groups[$key] = array(
				'keep' => null,
				'rows' => array(),
			);
		}

		if (
			$groups[$key]['keep'] === null
			|| $row['total_refs'] > $groups[$key]['keep']['total_refs']
			|| ($row['total_refs'] == $groups[$key]['keep']['total_refs'] && $row['stats_refs'] > $groups[$key]['keep']['stats_refs'])
			|| ($row['total_refs'] == $groups[$key]['keep']['total_refs'] && $row['stats_refs'] == $groups[$key]['keep']['stats_refs'] && $row['profile_id'] < $groups[$key]['keep']['profile_id'])
		) {
			$groups[$key]['keep'] = $row;
		}

		$groups[$key]['rows'][] = $row;
	}

	if (empty($groups)) {
		$html .= "<th>" . _("Profile") . "</th><th>" . _("Name") . "</th><th>" . _("Stats refs") . "</th><th>" . _("Player refs") . "</th><th>" . _("Total refs") . "</th><th>" . _("Keep") . "</th></tr>\n";
		$html .= "<tr><td colspan='6'>" . _("No duplicate profiles found") . "</td></tr>";
	} else {
		$html .= "<th>" . _("Profile") . "</th><th>" . _("Name") . "</th><th>" . _("Stats refs") . "</th><th>" . _("Player refs") . "</th><th>" . _("Total refs") . "</th><th>" . _("Keep") . "</th></tr>\n";
		foreach ($groups as $group) {
			$counter++;
			$keepId = $group['keep']['profile_id'];
			foreach ($group['rows'] as $row) {
				if ($counter % 2) {
					$html .= "<tr class='highlight'>";
				} else {
					$html .= "<tr>";
				}
				$html .= "<td><input type='checkbox' name='ids[]' value='" . utf8entities($row['profile_id']) . "'/></td>";
				$html .= "<td><b>" . utf8entities($row['firstname']) . " " . utf8entities($row['lastname']) . "</b></td>";
				$html .= "<td class='center'>" . utf8entities($row['stats_refs']) . "</td>";
				$html .= "<td class='center'>" . utf8entities($row['player_refs']) . "</td>";
				$html .= "<td class='center'>" . utf8entities($row['total_refs']) . "</td>";
				if ($row['profile_id'] == $keepId) {
					$html .= "<td class='center'><b>" . _("Keep (most referred)") . "</b></td>";
				} else {
					$html .= "<td class='center'>" . sprintf(_("-> %d"), $keepId) . "</td>";
				}
				$html .= "</tr>\n";
			}
		}
	}
}

$html .= "</table>\n";
$html .= "<div><input type='hidden' id='filter' name='filter' value='$filter'/></div>\n";
$html .= "</form>\n";
echo $html;
contentEnd();
pageEnd();
