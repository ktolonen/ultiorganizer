<?php

include_once __DIR__ . '/auth.php';
include_once 'menufunctions.php';
include_once 'lib/gamehistory.functions.php';
include_once 'lib/game.functions.php';

$LAYOUT_ID = GAMEHISTORYADMIN;
$title = _("Scoresheet history");
$html = "";

$filterKeys = ['season', 'game', 'user', 'from', 'to'];
$filters = array_fill_keys($filterKeys, "");
$page = 1;
$pageSize = 100;

if (isset($_POST['update'])) {
    foreach ($filterKeys as $key) {
        $filters[$key] = trim((string) ($_POST[$key] ?? ""));
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
}
if (!isset($_POST['update'])) {
    foreach ($filterKeys as $key) {
        if (isset($_POST[$key])) {
            $filters[$key] = trim((string) $_POST[$key]);
        }
    }
}
// Every submission carries this hidden field from the previous render, so it
// must be consumed only when nothing else already set $page this request --
// otherwise it overwrites the Go-to value and the update-triggered reset to
// page 1 with a stale number.
if (isset($_POST['page']) && !isset($_POST['page_nav']) && !isset($_POST['update']) && !isset($_POST['page_input'])) {
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

$html .= "<form method='post' action='?view=admin/gamehistory'>";
$html .= "<table border='0'>\n";
$html .= "<tr><td>" . _("Season") . ": <input class='input' maxlength='10' size='10' name='season' value='"
    . utf8entities($filters['season']) . "'/></td>";
$gameValue = intval($filters['game']);
$html .= "<td>" . _("Game") . ": <input class='input' type='number' min='1' name='game' size='8' value='"
    . ($gameValue > 0 ? $gameValue : "") . "'/></td>";
$html .= "<td>" . _("User") . ": <input class='input' maxlength='50' size='20' name='user' value='"
    . utf8entities($filters['user']) . "'/></td></tr>\n";
$html .= "<tr><td>" . _("From") . ": <input class='input' type='date' name='from' value='"
    . utf8entities($filters['from']) . "'/></td>";
$html .= "<td>" . _("To") . ": <input class='input' type='date' name='to' value='"
    . utf8entities($filters['to']) . "'/></td>";
$html .= "<td><input class='button' type='submit' name='update' value='" . _("Refresh") . "'/></td></tr>\n";
$html .= "</table>\n";

$totalRows = GameHistoryAllCount($filters);
$totalPages = max(1, ceil($totalRows / $pageSize));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $pageSize;

$pagination = "";
if ($totalRows > 0 && $totalPages > 1) {
    $pagination .= "<p>";
    if ($page > 1) {
        $pagination .= "<button class='button' type='submit' name='page_nav' value='" . ($page - 1) . "'>&laquo; " . _("Previous") . "</button> ";
    }
    $pagination .= sprintf("%s %d/%d (%d) ", _("Page"), $page, $totalPages, $totalRows);
    $pagination .= "<label>";
    $pagination .= _("Go to") . ": ";
    $pagination .= "<input class='input' type='number' min='1' max='" . $totalPages . "' name='page_input' size='4' value='" . $page . "'/>";
    $pagination .= "</label> ";
    // No name/value here: a named "page_nav" button would submit the OLD
    // $page and shadow the typed page_input value in the elseif chain above.
    $pagination .= "<button class='button' type='submit'>" . _("Go") . "</button>";
    if ($page < $totalPages) {
        $pagination .= " <button class='button' type='submit' name='page_nav' value='" . ($page + 1) . "'>" . _("Next") . " &raquo;</button>";
    }
    $pagination .= "</p>\n";
}

$html .= $pagination;

if ($totalRows === 0) {
    $html .= "<p>" . _("No changes recorded") . ".</p>";
} else {
    $rows = GameHistoryAll($filters, $pageSize, $offset);

    $html .= "<table class='data'>\n<tr>";
    $html .= "<th>" . _("Time") . "</th>";
    $html .= "<th>" . _("Game") . "</th>";
    $html .= "<th>" . _("User") . "</th>";
    $html .= "<th>" . _("IP Address") . "</th>";
    $html .= "<th>" . _("Source") . "</th>";
    $html .= "<th>" . _("Description") . "</th>";
    $html .= "</tr>\n";

    foreach ($rows as $row) {
        $gameId = intval($row['game']);
        $html .= "<tr>";
        $html .= "<td>" . utf8entities(DefTimeFormat($row['time'])) . "</td>";
        $html .= "<td><a href='?view=user/gamehistory&amp;game=" . $gameId . "'>" . $gameId . "</a></td>";
        $html .= "<td>" . utf8entities($row['user_id']) . "</td>";
        $html .= "<td>" . utf8entities($row['ip']) . "</td>";
        $html .= "<td>" . utf8entities($row['source']) . "</td>";
        $html .= "<td>" . utf8entities(GameHistoryFormatDetail($row)) . "</td>";
        $html .= "</tr>\n";
    }
    $html .= "</table>\n";
}

$html .= $pagination;
$html .= "<input type='hidden' name='page' value='" . $page . "'/>";
$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();
