<?php
include_once 'view_ids.inc.php';
include_once '../lib/database.php';
include_once '../lib/place.functions.php';
include_once 'lib/place.functions.php';
include_once 'builder.php';
$LAYOUT_ID = PLACES;



//common page
pageTopHeadOpen();
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
pageTopHeadClose();
leftMenu($LAYOUT_ID);
contentStart();
OpenConnection();

//process itself on submit
if(!empty($_POST['remove']))
	{
	$id = $_POST['hiddenDeleteId'];
	DeletePlaceTemplate($id);
	}

echo "<form method='post' action='places.php'>";

echo "<h2>"._("Pelipaikat")."</h2>\n";

echo "<table border='0' cellpadding='4px'>\n";

echo "<tr><th>"._("Nimi")."</th>
	<th></th><th></th></tr>\n";

$placetemplates = PlaceTemplates();

while($row = mysql_fetch_assoc($placetemplates))
	{
	echo "<tr>";
	echo "<td>".$row['paikka']."</td>";
	echo "<td class='center'><input class='button' type='button' name='edit'  value='"._("Muokkaa")."' onclick=\"window.location.href='addplaces.php?Id=".$row['paikka_id']."'\"/></td>";
	echo "<td class='center'><input class='button' type='submit' name='remove' value='"._("Poista")."' onclick=\"setId(".$row['paikka_id'].");\"/></td>";
	echo "</tr>\n";	
	}

echo "</table><p><input class='button' name='add' type='button' value='"._("Lis&auml;&auml;")."' onclick=\"window.location.href='addplaces.php'\"/></p>";

//stores id to delete
echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
echo "</form>\n";

CloseConnection();

contentEnd();
pageEnd();
?>
