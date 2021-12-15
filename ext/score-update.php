<?php
include_once 'localization.php';

include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/standings.functions.php';
include_once $include_prefix . 'lib/pool.functions.php';
include_once $include_prefix . 'lib/configuration.functions.php';
if (version_compare(PHP_VERSION, '5.0.0', '>')) {
	include_once $include_prefix . 'lib/twitter.functions.php';
}
include_once $include_prefix . 'lib/database.php';
include_once $include_prefix . 'lib/logging.functions.php';

header("Content-type: text/plain; charset=\"UTF-8\"");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");

$_SESSION['uid'] = "anonymous";
if (isset($_GET['q'])) {
	$message = $_GET['q'];
	$splitted = explode(" ", $message);
	$action = $splitted[0];
	$game = $splitted[1];
	$gameId = substr($game, 0, -1);
	if (strtoupper($action) != "U") {
		$home = $splitted[2];
		$away = $splitted[3];
	}
} else {
	$home = $_GET['home'];
	$away = $_GET['guest'];
	$game = $_GET['game'];
	$gameId = substr($game, 0, -1);
	if (isset($_GET['action'])) {
		$action = $_GET['action'];
	} else {
		$action = "P";
	}
}

if (!is_numeric($gameId)) {
	echo _("Game number missing") . ".";
	exit();
} else if (!checkChkNum($game)) {
	echo _("Erroneous game number") . " " . $game . ".";
	exit();
} else if ($action == "P" || $action == "G" || $action == "R") {
	if (!is_numeric($home)) {
		echo _("Home score missing") . ".";
		exit();
	} else if (!is_numeric($away)) {
		echo _("Visitor score missing") . ".";
		exit();
	}
}

$sender = $_GET['sender'];
$result = GameResult($gameId);
if (!$result) {
	echo _("Unknown game number") . ": " . $_GET['game'];
} else {
	if ($action == "P" || $action == "G" || $action == "R") {
		LogGameUpdate($gameId, "result:" . $result['homescore'] . "-" . $result['visitorscore'] . ">" . $home . "-" . $away, "SMS" . $sender);
		$updresult = GameSetResult($gameId, $home, $away);

		header("x-uo-oldscore: " . $result['homescore'] . "-" . $result['visitorscore']);
		header("x-uo-su-status: OK");

		echo $result['hometeamname'] . "-" . $result['visitorteamname'] . "\n";
		echo $home . "-" . $away . " " . _("result saved") . ".\n";
		echo _("You can restore the old result by sending a message:") . " 'U " . $game . "' " . _("to the same number") . ".";
	} else if ($action == "U") {
		if (isset($sender)) {
			$lastEntry = GetLastGameUpdateEntry($gameId, "SMS" . $sender);
			if ($lastEntry) {
				$splitted = explode(">", $lastEntry['description']);
				if (count($splitted) != 2) {
					echo _("Invalid game update entry found. Can not undo");
					exit();
				}
				$oldResultSplit = explode(":", $splitted[0]);
				$oldresult = $oldResultSplit[1];
				$oldResultSplit = explode("-", $oldresult);
				GameSetResult($gameId, $oldResultSplit[0], $oldResultSplit[1]);
				header("x-uo-oldscore: " . $result['homescore'] . "-" . $result['visitorscore']);
				header("x-uo-su-status: OK");
				echo $result['hometeamname'] . "-" . $result['visitorteamname'] . "\n";
				echo $oldResultSplit[0] . "-" . $oldResultSplit[1] . " " . _("result restored") . ".\n";
			} else echo _("Could not find a previous game update entry");
		} else echo _("Missing sender information");
	}
}
CloseConnection();
