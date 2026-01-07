<?php
include_once __DIR__ . '/auth.php';
include_once 'menufunctions.php';
include_once 'lib/club.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/player.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/reservation.functions.php';

$LAYOUT_ID = EVENTVIEWER;
$title = _("Event log");
$html = "";

$userfilter = "";
$categoryfilter = array(); //EventCategories();
$resolve = false;
$page = 1;
$pageSize = 100;

if (isset($_POST['update'])) {
	$userfilter = $_POST["userid"];
	if (!empty($_POST["category"])) {
		$categoryfilter = $_POST["category"];
	}
	if (!empty($_POST["resolve"])) {
		$resolve = true;
	}
	$page = 1;
} elseif (isset($_POST['page_nav'])) {
	$page = intval($_POST['page_nav']);
	if ($page < 1) {
		$page = 1;
	}
} elseif (isset($_POST['page_input'])) {
	$page = intval($_POST['page_input']);
	if ($page < 1) {
		$page = 1;
	}
} elseif (isset($_POST['delete']) && !empty($_POST["event_ids"])) {
	$ids = $_POST["event_ids"];
	ClearEventList($ids);
}
if (isset($_POST['userid']) && !isset($_POST['update'])) {
	$userfilter = $_POST["userid"];
}
if (!empty($_POST["category"]) && !isset($_POST['update'])) {
	$categoryfilter = $_POST["category"];
}
if (!empty($_POST["resolve"]) && !isset($_POST['update'])) {
	$resolve = true;
}
if (isset($_POST['page']) && !isset($_POST['page_nav'])) {
	$page = intval($_POST['page']);
	if ($page < 1) {
		$page = 1;
	}
}
if (isset($_GET['page']) && !isset($_POST['page'])) {
	$page = intval($_GET['page']);
	if ($page < 1) {
		$page = 1;
	}
}

//common page
pageTopHeadOpen($title);
include 'script/common.js.inc';
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<form method='post' action='?view=admin/eventviewer'>";

$html .= "<p><a href='?view=admin/visitors'>" . _("Visitor count") . "</a></p>\n";

$categories = EventCategories();

$html .= "<table border='0'>\n";
$html .= "<tr><td colspan='4'><b>" . _("Select type of event") . ":</b></td></tr>\n";
$html .= "<tr>\n";

$i = 0;
foreach ($categories as $category) {
	if ($i > 0 && ($i % 4) == 0)
		$html .= "</tr>\n<tr>";
	if (in_array($category, $categoryfilter))
		$html .= "<td class='center'><input type='checkbox' checked='checked' name='category[]' value='" . utf8entities($category) . "' /></td>";
	else
		$html .= "<td class='center'><input type='checkbox' name='category[]' value='" . utf8entities($category) . "' /></td>";
	$html .= "<td>" . $category . "</td>";
	$i++;
}
$html .= "</tr>\n";
if ($resolve)
	$html .= "<tr><td class='center'><input type='checkbox' name='resolve' checked='checked' value='1'/></td>";
else
	$html .= "<tr><td class='center'><input type='checkbox' name='resolve' value='1'/></td>";

$html .= "<td colspan='3'>" . _("Resolve IDs") . "</td></tr>";
$html .= "</table>\n";
$html .= "<p>" . _("Only user") . ": ";
$html .= "<input class='input' maxlength='50' size='40' name='userid' value='$userfilter'/></p>\n";
$html .= "<p><input class='button' type='submit' name='update' value='" . _("Refresh") . "'/></p>";

$totalEvents = 0;
$totalPages = 1;
if (count($categoryfilter) > 0) {
	$totalEvents = EventCount($categoryfilter, $userfilter);
	$totalPages = max(1, ceil($totalEvents / $pageSize));
	if ($page > $totalPages) {
		$page = $totalPages;
	}
}
$offset = ($page - 1) * $pageSize;

$pagination = "";
if ($totalEvents > 0 && $totalPages > 1) {
	$pagination .= "<p>";
	if ($page > 1) {
		$pagination .= "<button class='button' type='submit' name='page_nav' value='" . ($page - 1) . "'>&laquo; " . _("Previous") . "</button> ";
	}
	$pagination .= sprintf("%s %d/%d (%d %s) ", _("Page"), $page, $totalPages, $totalEvents, _("events"));
	$pagination .= "<label>";
	$pagination .= _("Go to") . ": ";
	$pagination .= "<input class='input' type='number' min='1' max='" . $totalPages . "' name='page_input' size='4' value='" . $page . "'/>";
	$pagination .= "</label> ";
	$pagination .= "<button class='button' type='submit' name='page_nav' value='$page'>" . _("Go") . "</button>";
	if ($page < $totalPages) {
		$pagination .= " <button class='button' type='submit' name='page_nav' value='" . ($page + 1) . "'>" . _("Next") . " &raquo;</button>";
	}
	$pagination .= "</p>\n";
}

$html .= $pagination;
$html .= "<table border='0'>\n";
$html .= "<tr><th>" . _("Time") . "</th><th>" . _("User") . "</th>
	<th>" . _("IP Address") . "</th><th>" . _("Category") . "</th><th>" . _("Type") . "</th>
	<th>" . _("Source") . "</th><th>" . _("Id1") . "</th>
	<th>" . _("Id2") . "</th><th>" . _("Description") . "</th>
	</tr>\n";

$event_ids = "";
if (count($categoryfilter) > 0) {
	$events = EventList($categoryfilter, $userfilter, $pageSize, $offset);

	while ($event = mysqli_fetch_assoc($events)) {

		if ($event['type'] == 'add' || ($event['category'] == 'security' && $event['description'] == 'success')) {
			$html .= "<tr class='posvalue'>";
		} elseif ($event['type'] == 'delete' || ($event['category'] == 'security' && $event['description'] == 'failed')) {
			$html .= "<tr class='negvalue'>";
		} else {
			$html .= "<tr>";
		}

		$html .= "<td>" . $event['time'] . "&nbsp;</td>";
		$html .= "<td>" . $event['user_id'] . "&nbsp;</td>";
		$html .= "<td>" . $event['ip'] . "&nbsp;</td>";
		$html .= "<td>" . $event['category'] . "&nbsp;</td>";
		$html .= "<td>" . $event['type'] . "&nbsp;</td>";
		$html .= "<td>" . $event['source'] . "&nbsp;</td>";
		if ($resolve) {
			if ($event['category'] == 'player') {
				$html .= "<td>" . utf8entities(PlayerName($event['id1'])) . "&nbsp;</td>";
				$html .= "<td>" . utf8entities(TeamName($event['id2'])) . "&nbsp;</td>";
			} elseif ($event['category'] == 'game') {
				$html .= "<td>" . utf8entities(GameNameFromId($event['id1'])) . "&nbsp;</td>";
				$html .= "<td>" . $event['id2'] . "&nbsp;</td>";
			} elseif ($event['category'] == 'club') {
				$html .= "<td>" . utf8entities(ClubName($event['id1'])) . "&nbsp;</td>";
				$html .= "<td>" . $event['id2'] . "&nbsp;</td>";
			} elseif ($event['category'] == 'series') {
				$html .= "<td>" . utf8entities(SeriesName($event['id1'])) . "&nbsp;</td>";
				$html .= "<td>" . $event['id2'] . "&nbsp;</td>";
			} elseif ($event['category'] == 'enrolment') {
				$html .= "<td>" . utf8entities(SeriesName($event['id1'])) . "&nbsp;</td>";
				$html .= "<td>" . utf8entities(TeamName($event['id2'])) . "&nbsp;</td>";
			} elseif ($event['category'] == 'pool') {
				$html .= "<td>" . utf8entities(PoolName($event['id1'])) . "&nbsp;</td>";
				$html .= "<td>" . $event['id2'] . "&nbsp;</td>";
			} elseif ($event['category'] == 'security') {
				if ($event['type'] == 'add' && $event['description'] == 'teamadmin') {
					$html .= "<td>" . utf8entities($event['id1']) . "&nbsp;</td>";
					$html .= "<td>" . utf8entities(TeamName($event['id2'])) . "&nbsp;</td>";
				} else {
					$html .= "<td>" . $event['id1'] . "&nbsp;</td>";
					$html .= "<td>" . $event['id2'] . "&nbsp;</td>";
				}
			} elseif ($event['category'] == 'team') {
				$html .= "<td>" . utf8entities(TeamName($event['id1'])) . "&nbsp;</td>";
				$html .= "<td>" . $event['id2'] . "&nbsp;</td>";
			} else {
				$html .= "<td>" . $event['id1'] . "&nbsp;</td>";
				$html .= "<td>" . $event['id2'] . "&nbsp;</td>";
			}
		} else {
			$html .= "<td>" . $event['id1'] . "&nbsp;</td>";
			$html .= "<td>" . $event['id2'] . "&nbsp;</td>";
		}
		$html .= "<td>" . $event['description'] . "</td>";
		$html .= "</tr>\n";
		$event_ids .= $event['event_id'] . ",";
	}
}
$event_ids = trim($event_ids, ',');
$html .= "</table>";
$html .= $pagination;
$html .= "<p><input type='hidden' name='event_ids' value='$event_ids'/>";
$html .= "<input type='hidden' name='page' value='$page'/>";

if (!empty($event_ids)) {
	$html .= "<input class='button' type='submit' name='delete' value='" . _("Delete events") . "'/></p>\n";
}
$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();
