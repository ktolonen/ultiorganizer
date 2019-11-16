<?php
include_once 'lib/season.functions.php';
include_once 'lib/statistical.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/configuration.functions.php';

$LAYOUT_ID = SEASONS;

$title = _("Events");
$html = "";

//season parameters
$sp = array(
	"season_id"=>"",
	"name"=>"",
	"type"=>"",
	"starttime"=>"",
	"endtime"=>"",
	"iscurrent"=>0
	);
	
if(!empty($_GET["season"]))
	{
	$info = SeasonInfo($_GET["season"]);
	$sp['season_id'] = $info['season_id'];
	$sp['name'] = $info['name'];
	$sp['type'] = $info['type'];
	$sp['starttime'] = $info['starttime'];
	$sp['endtime'] = $info['endtime'];
	$sp['iscurrent'] = $info['iscurrent'];
	}

//process itself on submit
if(!empty($_POST['remove_x']) && !empty($_POST['hiddenDeleteId']))
	{
	$id = $_POST['hiddenDeleteId'];
	$ok = true;
	//run some test to for safe deletion
	$series = SeasonSeries($id);
	if(count($series)){
		$html .= "<p class='warning'>"._("Event has")." ".mysqli_num_rows($series)." "._("Division(s)").". "._("Divisions must be removed before removing the event").".</p>";
		$ok = false;
	}
	$cur = CurrentSeason();

	if($cur == $id)
		{
		$html .= "<p class='warning'>"._("You can not remove a current event").".</p>";
		$ok = false;
		}
	if($ok)
		{
		DeleteSeason($id);
		//remove rights from deleted season
		$propId = getPropId($_SESSION['uid'], 'editseason', $id);
		RemoveEditSeason($_SESSION['uid'],$propId);
		$propId = getPropId($_SESSION['uid'], 'userrole', 'seasonadmin:'.$id);
		RemoveUserRole($_SESSION['uid'], $propId);
		}
	}
//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
<!--
function setId(id) {
	var input = document.getElementById("hiddenDeleteId");
	
	var answer = confirm('<?php echo _("Are you sure you want to delete the event?");?>');
	if (answer){
		input.value = id;	
	}else{
		input.value = "";
	}
}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .=  "<form method='post' action='?view=admin/seasons'>";

$html .=  "<h2>"._("Seasons/Tournaments")."</h2>\n";

$html .=  "<table style='white-space: nowrap;width:90%' border='0' cellpadding='4px'>\n";

$html .=  "<tr>
	<th>"._("Name")."</th>
	<th>"._("Type")."</th>
	<th>"._("Starts")."</th>
	<th>"._("Ends")."</th>
	<th>"._("Enrollment")."</th>
	<th>"._("Visible")."</th>
	<th>"._("Operations")."</th>
	<th></th>
	</tr>\n";

$seasons = Seasons();

while($row = mysqli_fetch_assoc($seasons))
	{
	$info = SeasonInfo($row['season_id']);
	
	$html .=  "<tr>";
	$html .=  "<td><a href='?view=admin/addseasons&amp;season=".$info['season_id']."'>".utf8entities(U_($info['name']))."</a></td>";

	if(!empty($info['type']))
		$html .=  "<td>".U_($info['type'])."</td>";
	else
		$html .=  "<td>?</td>";

	if(!empty($info['starttime']))
		$html .=  "<td>".ShortDate($info['starttime'])."</td>";
	else
		$html .=  "<td>-</td>";
	
	if(!empty($info['endtime']))
		$html .=  "<td>".ShortDate($info['endtime'])."</td>";
	else
		$html .=  "<td>-</td>";

	$enrollment = intval($info['enrollopen'])?_("open"):_("closed");
	$html .=  "<td>".$enrollment."</td>";
	
	$visible = intval($info['iscurrent'])?_("yes"):_("no");
	$html .=  "<td>".$visible."</td>";
	
	$html .=  "<td>";
	if(IsTwitterEnabled()){
		$html .=  "<a href='?view=admin/twitterconf&amp;season=".$info['season_id']."'>"._("Conf. Twitter")."</a> | ";
	}
	if(!CanDeleteSeason($row['season_id'])){
		if(IsSeasonStatsCalculated($row['season_id'])){
			$html .=  "<a href='?view=admin/stats&amp;season=".$info['season_id']."'>"._("Re-calc. stats")."</a>";
		}else{
			$html .=  "<a href='?view=admin/stats&amp;season=".$info['season_id']."'><b>"._("Calc. stats")."</b></a>";
		}
	}
	$html .= " | <a href='?view=admin/eventdataexport&amp;season=".$info['season_id']."'>"._("Export")."</a>";
	$html .=  "</td>";
	
	if(CanDeleteSeason($row['season_id'])){
		$html .=  "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId('".$row['season_id']."');\"/></td>";
	}
	$html .=  "</tr>\n";
	}

$html .=  "</table>";
$html .=  "<div>";
$html .= "<a href='?view=admin/eventdataimport'>"._("Import event")."</a> | ";
$html .= "<a href='?view=admin/seasonstats'>"._("All event statistics")."</a></div>";
$html .=  "<p><input class='button' name='add' type='button' value='"._("Add")."' onclick=\"window.location.href='?view=admin/addseasons'\"/></p>";
$html .=  "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
$html .=  "</form>\n";

echo $html;

contentEnd();
pageEnd();
?>