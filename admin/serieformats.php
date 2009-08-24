<?php
include_once 'view_ids.inc.php';
include_once '../lib/database.php';
include_once '../lib/serie.functions.php';
include_once 'lib/serie.functions.php';
include_once 'builder.php';
$LAYOUT_ID = SERIEFORMATS;



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
	DeleteSerie($id);
	}
	
echo "<form method='post' action='serieformats.php'>";

echo "<h2>"._("Sarjaformaatit")."</h2>\n";

echo "<table border='0' cellpadding='4px'>\n";

echo "<tr><th>"._("Nimi")."</th>
	<th>"._("Voittopiste")."</th>
	<th>"._("Pistekatto")."</th>
	<th>"._("Aikakatto")."</th>
	<th>"._("Aikalisi&auml;")."</th>
	<th></th><th></th></tr>\n";

$serietemplates = SerieTemplates();

while($row = mysql_fetch_assoc($serietemplates))
	{
	echo "<tr>";
	echo "<td>".$row['nimi']."</td>";
	echo "<td class='center'>".$row['pelipist']."</td>";
	echo "<td class='center'>".$row['pistekatto']."</td>";
	echo "<td class='center'>".$row['aikakatto']."</td>";
	if(!empty($row['aikalisia']))
		echo "<td class='center'>".$row['aikalisia']."</td>";
	else
		echo "<td class='center'>-</td>";
	echo "<td class='center'><input class='button' type='button' name='edit'  value='"._("Muokkaa")."' onclick=\"window.location.href='addserieformats.php?Id=".$row['sarja_id']."'\"/></td>";
	echo "<td class='center'><input class='button' type='submit' name='remove' value='"._("Poista")."' onclick=\"setId(".$row['sarja_id'].");\"/></td>";
	echo "</tr>\n";	
	}

echo "</table><p><input class='button' name='add' type='button' value='"._("Lis&auml;&auml;")."' onclick=\"window.location.href='addserieformats.php'\"/></p>";

//stores id to delete
echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
echo "</form>\n";

CloseConnection();

contentEnd();
pageEnd();
?>
