<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = SEASONSERIES;

$season = $_GET["Season"];

$title = utf8entities(U_(SeasonName($season))).": "._("Divisions");

//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
<!--
function setId(id) 
	{
	var input = document.getElementById("hiddenDeleteId");
	input.value = id;
	}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

	
//process itself on submit
if(!empty($_POST['remove_x'])){
	$id = $_POST['hiddenDeleteId'];
	if(CanDeleteSeries($id)){
		DeleteSeries($id);
	}
}

echo "<form method='post' action='?view=admin/seasonseries&amp;Season=$season'>";

echo "<h2>"._("Divisions")."</h2>\n";

$series = SeasonSeries($season);

if(count($series))
	{
	echo "<table border='0' cellpadding='4px'>\n";

	echo "<tr><th>"._("Name")."</th>
		<th>"._("Order")."</th>
		<th>"._("Type")."</th>		
		<th>"._("Valid")."</th>
		<th>"._("Operations")."</th>
		<th></th></tr>\n";

	foreach($series as $row)
		{
		$valid = intval($row['valid'])?_("yes"):_("no");
		
		if(!empty($row['name'])){
				$name = utf8entities(U_($row['name']));
			}else{
				$name = _("No name");
			}
			
		echo "<tr>";
		echo "<td><a href='?view=admin/addseasonseries&amp;Season=$season&amp;Series=".$row['series_id']."'>".$name."</a></td>";
		echo "<td class='center'>".$row['ordering']."</td>";
		echo "<td>".U_($row['type'])."</td>";
		echo "<td class='center'>$valid</td>";
		echo "<td><a href='?view=admin/seriesseeding&amp;Series=".$row['series_id']."'>"._("Seeding")."</a></td>";
		if(CanDeleteSeries($row['series_id'])){
			echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId(".$row['series_id'].");\"/></td>";		
		}
		echo "</tr>\n";	
		}

	echo "</table>";
	}
echo "<p><input class='button' name='add' type='button' value='"._("Add")."' onclick=\"window.location.href='?view=admin/addseasonseries&amp;Season=$season'\"/></p>";

//stores id to delete
echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
echo "</form>\n";

contentEnd();
pageEnd();
?>