<?php 
include '../../lib/database.php';

OpenConnection();
include_once $include_prefix.'localization.php';
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/user.functions.php';
include_once $include_prefix.'lib/restful.functions.php';

function printSeasons($seasons) {
	restHeader(_("Ultiorganizer")." "._("Seasons"));
	echo "	<div class=\"list\">\n";
	printList($seasons, '', 'season_id', true);
	echo "	</div>\n";
	
	$actions = standardActions(true);
	$actions["filter=active"] = _("Current event");
	restFooter($actions);
}

//if ($_SERVER['REQUEST_METHOD'] == "GET") {
if (count($_GET) === 0) {
	$seasons = Seasons();
	printSeasons($seasons);
} else {
	$elements = explode("/", $_SERVER['QUERY_STRING']);
	$season = $elements[0];
	$seasonInfo = SeasonInfo($season);
	if (count($elements) == 1) {
		if (isset($_GET['filter'])) {
			$filter = $_GET['filter'];
			if ($filter == "active") {
				$seasons = CurrentSeasons();
				printSeasons($seasons);
			}
		} else {
			$id = $_SERVER['QUERY_STRING'];
			restHeader(_("Season").": ".U_($seasonInfo['name']));
			printItem($seasonInfo);
			echo "				<ul>
					<li><a href='?".urlencode($id)."/series'>"._("Division")."</a></li>
				</ul>\n";
			$actions = standardActions(false);
			restFooter($actions);
			
		}
	} else {
		include $elements[1].'.php';
	}
}
CloseConnection();
?>