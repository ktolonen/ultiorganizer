<?php
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/series.functions.php';
include_once $include_prefix.'lib/club.functions.php';
include_once $include_prefix.'lib/country.functions.php';

$LAYOUT_ID = ENROLLTEAM;
if (empty($_GET['season'])) {
	die(_("Season mandatory"));
}
$season = $_GET['season'];
$seasonInfo = SeasonInfo($season);
$title = _("Enrolled teams").": ".utf8entities($seasonInfo['name']);

$orgarray = "";
$result = ClubList(true);
while($row = @mysqli_fetch_assoc($result)){
	$orgarray .= "\"".$row['name']."\",";
}
$orgarray=trim($orgarray,',');

if (!empty($_POST['add'])) {
	
	$clubname = "";
	$countryname = "";
	$index = $_POST['index'];
	if(!empty($_POST['clubname'])){
		$clubname = $_POST['clubname'];
	}
	if(!empty($_POST['countryname'])){
		$countryname = $_POST['countryname'];
	}
	AddSeriesEnrolledTeam($_POST['series'], $_SESSION['uid'], $_POST['name'.$index], $clubname, $countryname);
}
if (isset($_POST['remenroll_x'])) {
	RemoveSeriesEnrolledTeam($_POST['series'],$_SESSION['uid'], $_POST['deleteEnrollId']);
}
if (!empty($_POST['confirm'])) {
	ConfirmEnrolledTeam($_POST['series'], $_POST['confirmEnrollId']);
}

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "datasource", "autocomplete"));
?>
<script type="text/javascript">

var clubs = new Array(
	<?php
		echo $orgarray;
	?>
	);
</script>
<script type="text/javascript">
<!--
function setId(id, name) 
	{
	var input = document.getElementById(name);
	input.value = id;
	}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
//content
$result = SeasonSeries($season);

if(!count($result)){
	echo "<p>"._("No divisions.")."</p>";
}
$hiddenIndex = 1;

echo "<h2>"._("Enrolling ends")." ".ShortDate($seasonInfo['enroll_deadline'])."</h2>\n";
foreach($result as $row) {
	echo "<form method='post' action='?".utf8entities($_SERVER['QUERY_STRING'])."'>\n";
	echo "<h2>".utf8entities(U_($row['name']))."</h2>\n";
	echo "<table class='yui-skin-sam' style='width:100%' cellpadding='2'><tr><th>"._("Team")."</th>";
	if(!intval($seasonInfo['isnationalteams'])){
		echo "<th>"._("Club")."</th>";
	} 
	if (intval($seasonInfo['isinternational'])){
		echo "<th>"._("Country")."</th>";
	}
	echo "<th>"._("User")."</th><th>"._("Enrolled")."</th><th>"._("State")."</th><th>&nbsp;</th></tr>\n";
	if (hasEditTeamsRight($row['series_id'])) {
		$result2 = SeriesEnrolledTeams($row['series_id']);	
	} else {
		$result2 = SeriesEnrolledTeamsByUser($row['series_id'], $_SESSION['uid']);
	}
	
	while ($row2 = mysqli_fetch_assoc($result2)) {
		
		echo "<tr><td>";
		if(!intval($seasonInfo['isnationalteams'])){
			echo utf8entities(U_($row2['name']));
		} else {
			echo utf8entities(U_($row2['name']));
		}
		echo "</td>";
		if(!intval($seasonInfo['isnationalteams'])){
			echo "<td>".utf8entities($row2['clubname'])."</td>";
		}
		if (intval($seasonInfo['isinternational'])){
			echo "<td>".utf8entities(_($row2['countryname']))."</td>";
		}
		echo "<td>".utf8entities($row2['username'])."</td>";
		echo "<td>".ShortTimeFormat($row2['enroll_time'])."</td>";
		if ($row2['status'] == 0) {
			if (hasEditTeamsRight($row['series_id'])) {
				echo "<td><input type='submit' name='confirm' value='"._("Confirm")."' onclick='setId(".$row2['id'].", \"confirmEnrollId".$hiddenIndex."\");'/></td>";
			}	
			else {
				echo "<td>"._("Unconfirmed")."</td>";
				}
		} else {
			echo "<td>". _("Confirmed")."</td>";
		}
		if (hasEditTeamsRight($row['series_id']) || $row2['status'] == 0) {
			echo "<td><input class='button' type='image' name='remenroll' src='images/remove.png' value='X' alt='X' onclick='setId(".$row2['id'].", \"deleteEnrollId".$hiddenIndex."\");'/></td>";
		}
		
		echo "</tr>\n";
	}
	echo "<tr><td>";
	echo "<input type='hidden' name='index' value='".utf8entities($hiddenIndex)."'/>"; 
	if(!intval($seasonInfo['isnationalteams'])){
		echo "<input class='input' size='15' type='text' name='name".$hiddenIndex."'/></td>\n";
		echo "<td><div id='orgAutocomplete".$hiddenIndex."' style='width:10em;padding-bottom:2em; margin-right:0.5em'><input class='input' size='15' type='text' id='club".$hiddenIndex."' name='clubname'/><div id='orgContainer".$hiddenIndex."'></div></div></td>";
	} else {
		echo TranslatedField("name".$hiddenIndex, "");
		echo "</td>\n";
	}
	if (intval($seasonInfo['isinternational'])){
		echo "<td>".CountryDropList("countryname$hiddenIndex", "countryname")."</td>";
	}
	
	$userinfo = UserInfo($_SESSION['uid']);
	echo "<td>".utf8entities($userinfo['name'])."</td>";
	echo "<td>"._("Unconfirmed")."</td>";
	echo "<td><input type='submit' name='add' value='"._("Add")."'/></td></tr>\n";
	echo "</table>\n";
	echo "<p><input type='hidden' name='series' value='".utf8entities($row['series_id'])."'/>";
	echo "<input type='hidden' id='deleteEnrollId".$hiddenIndex."' name='deleteEnrollId' value='0'/>";
	echo "<input type='hidden' id='confirmEnrollId".$hiddenIndex."' name='confirmEnrollId' value='0'/></p>";
	echo "</form>\n";
	?>
	<script type="text/javascript">
	YAHOO.autocomplete = function() {
		var oDS = new YAHOO.util.LocalDataSource(clubs);
		var oAC = new YAHOO.widget.AutoComplete("club<?php echo $hiddenIndex ?>", "orgContainer<?php echo $hiddenIndex ?>", oDS);
		oAC.prehighlightClassName = "yui-ac-prehighlight";
		oAC.useShadow = true;
		return {
			oDS: oDS,
			oAC: oAC
		};
	}();
	</script>
	<?php
	if(intval($seasonInfo['isnationalteams'])){
		echo TranslationScript("name".$hiddenIndex);
	}
	$hiddenIndex++;
}

//common end
contentEnd();
pageEnd();
?>