<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/team.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/club.functions.php';
include_once 'lib/country.functions.php';

$LAYOUT_ID = ADDSEASONTEAMS;
$html = "";
$teamId = 0;
$season = 0;
$seriesId = 0;

if (!empty($_GET["team"]))
	$teamId = intval($_GET["team"]);

$team_info = TeamInfo($teamId);
$seriesId = $team_info['series'];
$season = $team_info['season'];

$tp = array(
	"team_id" => "",
	"name" => "",
	"club" => "",
	"country" => "",
	"abbreviation" => "",
	"series" => "",
	"pool" => "", //pool
	"rank" => "",
	"valid" => "1",
	"bye" => "0"
);

//process itself on submit
if (!empty($_POST['save']) || !empty($_POST['add'])) {
	if (empty($_POST['name'])) {
		$html .= "<p>" . _("Name is mandatory!") . "</p><hr/>";
	} else {
		$tp['team_id'] = $teamId;
		$tp['name'] = trim($_POST['name']);
		$tp['abbreviation'] = trim($_POST['abbreviation']);

		$tp['pool'] = $team_info['pool'];
		$tp['rank'] = intval($_POST['rank']);
		$tp['series'] = $seriesId;

		if (!empty($_POST['club'])) {
			$clubId = ClubId($_POST['club']);

			//slot owner club not found
			if ($clubId == -1) {
				$clubId = AddClub($seriesId, $_POST['club']);
			}
			$tp['club'] = $clubId;
		}

		if (!empty($_POST['country'])) {
			$tp['country'] = $_POST['country'];
		}

		if (!empty($_POST['teamvalid'])) {
			$tp['valid'] = 1;
		} else {
			$tp['valid'] = 0;
		}
		/*
		if(!empty($_POST['teambye'])){
			$tp['valid']=2;
		}		
		*/
		if ($teamId) {
			SetTeam($tp);
			if (intval($tp['pool']))
				PoolAddTeam($tp['pool'], $teamId, $tp['rank']);
			$html .= "<p>" . _("Changes saved") . "</p><hr/>";
		} else {
			$teamId = AddTeam($tp);
			if (intval($tp['pool']))
				PoolAddTeam($tp['pool'], $teamId, $tp['rank']);

			$html .= "<p>" . $tp['name'] . " " . _("added") . ".</p><hr/>";
			$teamId = 0;
			$tp['name'] = "";
			$tp['club'] = "";
		}
		session_write_close();
		header("location:?view=admin/seasonteams&season=$season&series=$seriesId");
	}
}

$orgarray = "";
$result = ClubList(true);
foreach ($result as $row) {
	$orgarray .= "\"" . $row['name'] . "\",";
}
$orgarray = trim($orgarray, ',');

//common page
$title = _("Edit");
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "datasource", "autocomplete"));
?>
<style type="text/css">
	#orgAutoComplete {
		width: 26em;
		padding-bottom: 2em;
	}
</style>
<script type="text/javascript">
	var clubs = new Array(
		<?php
		echo $orgarray;
		?>
	);
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
$seasonInfo = SeasonInfo($season);

if ($teamId) {
	$info = TeamFullInfo($teamId);

	$tp['name'] = $info['name'];
	$tp['abbreviation'] = $info['abbreviation'];
	$tp['club'] = $info['club'];
	$tp['country'] = $info['country'];
	$tp['pool'] = $info['pool'];
	$tp['valid'] = $info['valid'];
	$tp['rank'] = $info['rank'];
	$tp['series'] = $info['series'];

	$html .= "<h2>" . _("Edit team") . "</h2>\n";
	$html .= "<form method='post' action='?view=admin/addseasonteams&amp;season=$season&amp;series=$seriesId&amp;team=$teamId'>";
} else {
	$html .= "<h2>" . _("Add team") . "</h2>\n";
	$html .= "<form method='post' action='?view=admin/addseasonteams&amp;season=$season&amp;series=$seriesId'>";
}

$html .= "<table cellpadding='2px' class='yui-skin-sam'><tr><td class='infocell'>" . _("Name") . ":</td><td>";
if (!intval($seasonInfo['isnationalteams'])) {
	$html .= "<input class='input' id='name' name='name' size='50' value='" . utf8entities($tp['name']) . "'/></td></tr>";
	$html .= "<tr><td class='infocell'>" . _("Club") . ":</td>\n";
	$html .= "<td><div id='orgAutoComplete'><input class='input' type='text' id='club' name='club' size='50' value='" . utf8entities(ClubName($tp['club'])) . "'/>";
	$html .= "<div id='orgContainer'></div>";
	$html .= "</div>";
} else {
	$html .= TranslatedField("name", $tp['name']);
}
$html .= "</td></tr>";

if (intval($seasonInfo['isinternational'])) {
	$html .= "<tr><td class='infocell'>" . _("Country") . ":</td>\n";
	$html .= "<td>" . CountryDropListWithValues("country", "country", $tp['country']) . "</td></tr>";
}
$html .= "<tr><td class='infocell'>" . _("Abbreviation") . ":</td>";
$html .= "<td><input class='input' id='abbreviation' name='abbreviation' maxlength='15' size='16' value='" . utf8entities($tp['abbreviation']) . "'/></td></tr>";

$seriesname = SeriesName($seriesId);
$html .= "<tr><td class='infocell'>" . _("Division") . ":</td>
		<td><input class='input' id='series' name='series' disabled='disabled' size='50' value='" . utf8entities($seriesname) . "'/></td></tr>";

$html .= "<tr><td class='infocell'>" . _("Starting pool") . ":</td>";
//$html .= "<td><input class='input' id='pool' name='pool' disabled='disabled' size='50' value='".utf8entities($team_info['poolname'])."'/></td></tr>";


$html .= "<td><select class='dropdown' name='pool'>";

$pools = SeriesPools($seriesId, false, true, true);

//empty selection
if (intval($tp['pool']))
	$html .= "<option class='dropdown' value='0'></option>";
else
	$html .= "<option class='dropdown' selected='selected' value='0'></option>";

foreach ($pools as $row) {
	if ($row['pool_id'] == $tp['pool'])
		$html .= "<option class='dropdown' selected='selected' value='" . utf8entities($row['pool_id']) . "'>" . utf8entities(U_($row['name'])) . "</option>";
	else
		$html .= "<option class='dropdown' value='" . utf8entities($row['pool_id']) . "'>" . utf8entities(U_($row['name'])) . "</option>";
}

$html .= "</select></td></tr>";

$html .= "<tr><td class='infocell'>" . _("Seed") . ":</td>
		<td><input class='input' id='rank' size='4' name='rank' value='" . utf8entities($tp['rank']) . "'/></td></tr>";

$html .= "<tr><td class='infocell'>" . _("Valid") . ":</td>";
if (intval($tp['valid']) || !$teamId)
	$html .= "<td><input class='input' type='checkbox' id='teamvalid' name='teamvalid' checked='checked'/></td>";
else
	$html .= "<td><input class='input' type='checkbox' id='teamvalid' name='teamvalid' /></td>";

/* BYE functionality TBD
$html .= "<tr><td class='infocell'>"._("BYE").":</td>";		
$html .= "<td><input class='input' type='checkbox' id='teambye' name='teambye'/></td>";
$html .= "</tr>";
*/
$html .= "</table>";

$html .= "<p><a href='?view=admin/users'>" . _("Select contact person") . "</a></p>\n";

if ($teamId)
	$html .= "<p><input class='button' name='save' type='submit' value='" . _("Save") . "'/>";
else
	$html .= "<p><input class='button' name='add' type='submit' value='" . _("Add") . "'/>";



$html .= "<input class='button' type='button' name='back'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/seasonteams&amp;season=$season'\"/></p>";
$html .= "</form>\n";

echo $html;
?>
<script type="text/javascript">
	YAHOO.autocomplete = function() {
		var oDS = new YAHOO.util.LocalDataSource(clubs);
		var oAC = new YAHOO.widget.AutoComplete("club", "orgContainer", oDS);
		oAC.prehighlightClassName = "yui-ac-prehighlight";
		oAC.useShadow = true;
		return {
			oDS: oDS,
			oAC: oAC
		};
	}();
</script>
<?php
if (intval($seasonInfo['isnationalteams'])) {
	echo TranslationScript("name");
}
contentEnd();
pageEnd();
?>