<?php
include_once 'lib/database.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = SERIEFORMATS;

$title = _("Pool formats");

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
if(!empty($_POST['remove_x']))
	{
	$id = $_POST['hiddenDeleteId'];
	DeletePoolTemplate($id);
	}
	
echo "<form method='post' action='?view=admin/serieformats'>";

echo "<h2>"._("Pool formats")."</h2>\n";

echo "<table border='0' cellpadding='4px'>\n";

echo "<tr>
	<th>"._("Name")."</th>
	<th>"._("Winning points")."</th>
	<th>"._("Point cap")."</th>
	<th>"._("Time cap")."</th>
	<th>"._("Time-outs")."</th>
	<th>"._("Delete")."</th>
	</tr>\n";

$templates = PoolTemplates();

foreach($templates as $row) {
	echo "<tr>";

	echo "<td><a href='?view=admin/addserieformats&amp;Id=".$row['template_id']."'>".utf8entities(U_($row['name']))."</a></td>";
	echo "<td class='center'>".$row['winningscore']."</td>";
	echo "<td class='center'>".$row['scorecap']."</td>";
	echo "<td class='center'>".$row['timecap']."</td>";
	if(!empty($row['timeouts']))
		echo "<td class='center'>".$row['timeouts']."</td>";
	else
		echo "<td class='center'>-</td>";

	echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId(".$row['template_id'].");\"/></td>";	
	echo "</tr>\n";	
	}

echo "</table><p><input class='button' name='add' type='button' value='"._("Add")."' onclick=\"window.location.href='?view=admin/addserieformats'\"/></p>";

//stores id to delete
echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
echo "</form>\n";

contentEnd();
pageEnd();
?>