<?php

// These are needed for the menu structure
$LAYOUT_ID = EXECUTESQL;
$title = _("Run SQL");
$query = "";
// process itself on submit
// Fetch querydata to be presented later
if(!empty($_POST['sql']) || !empty($_GET['sql']))
	{
	if(!empty($_GET['sql'])){
		$query = urldecode($_GET['sql']);
	}else{
		$query = $_POST['sql'];
	}
	$isSelect = (strpos(strtolower($query), "select") === 0);
	$isShow = (strpos(strtolower($query), "show") === 0);
	$isUpdate = (strpos(strtolower($query), "update") === 0);
	$isSet = (strpos(strtolower($query), "set") === 0);
	$isDelete = (strpos(strtolower($query), "delete") === 0);
	$arraycolumnsname = array();
	if(isSuperAdmin()){
		$result = mysql_query($query);
		}

	if (!$result) { die('Invalid query: ' . mysql_error()); }

	if ($isSelect || $isShow){
		$i=0;
		while ($i < mysql_num_fields($result)) 
			{
			$meta = mysql_fetch_field($result, $i);
			$arraycolumnsname[$i] = $meta->name;
			$arraycolumnstype[$i] = $meta->type;

			$arraycolumnstable[$i] = $meta->table;
			$arraycolumnsdefault[$i] = $meta->def;
			$arraycolumnsmaxlength[$i] = $meta->max_length;
			$arraycolumnsnotnull[$i] = $meta->not_null;
			$arraycolumnsprimarykey[$i] = $meta->primary_key;
			$arraycolumnsmultiplekey[$i] = $meta->multiple_key;
			$arraycolumnsuniquekey[$i] = $meta->unique_key;
			$arraycolumnsnumeric[$i] = $meta->numeric;
			$arraycolumnsblob[$i] = $meta->blob;
			$arraycolumnsunsigned[$i] = $meta->unsigned;
			$arraycolumnszerofill[$i] = $meta->zerofill;

			$i++;
			}
		}
	}

//common page
pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();

echo "<form method='post' action='?view=admin/executesql'>";

echo "<h2>"._("Run SQL")."</h2>\n";
if(!empty($result))
        {
	  echo "<table>";
	  echo "<tr>";
	  foreach ($arraycolumnsname as $i => $columnname)
		{
		  echo "<th>";
		  // extracolumninfo is set if checkbox is checked
		  if (!empty($_POST['extracolumninfo']))
				{
				echo strtoupper($arraycolumnstable[$i]) .".";
				}
		  echo strtoupper($columnname);
		  if (!empty($_POST['extracolumninfo']))
		    {
			echo "<table>";
			echo "<tr><td>type</td><td>" . $arraycolumnstype[$i] . "</td></tr>";
			echo "<tr><td>default</td><td>" . $arraycolumnsdefault[$i] . "</td></tr>";
			echo "<tr><td>maxlen</td><td>" . $arraycolumnsmaxlength[$i] . "</td></tr>";
			echo "<tr><td>notnull</td><td>" . $arraycolumnsnotnull[$i] . "</td></tr>";
			echo "<tr><td>pk</td><td>" . $arraycolumnsprimarykey[$i] . "</td></tr>";
			echo "<tr><td>mk</td><td>" . $arraycolumnsmultiplekey[$i] . "</td></tr>";
			echo "<tr><td>uk</td><td>" . $arraycolumnsuniquekey[$i] . "</td></tr>";
			echo "<tr><td>num</td><td>" . $arraycolumnsnumeric[$i] . "</td></tr>";
			echo "<tr><td>blob</td><td>" . $arraycolumnsblob[$i] . "</td></tr>";
			echo "<tr><td>unsig</td><td>" . $arraycolumnsunsigned[$i] . "</td></tr>";
			echo "<tr><td>zerofill</td><td>" . $arraycolumnszerofill[$i] . "</td></tr>";
			echo "</table>";
			}
		  echo "</th>";
		  }
	  echo "</tr>";
	  // Print contents of the query
	  if ($isSelect || $isShow){
		  while ($row = mysql_fetch_assoc($result))
				{
				echo "<tr>";
				foreach ($arraycolumnsname as $i => $columnname)
					{
					if(mysql_field_type($result,$i)!='blob'){
						echo "<td  class='dbrow'>" . utf8entities($row[$columnname]) . "</td>";
					}else{
						echo "<td  class='dbrow'>BINARY</td>";
					}
					}
			  echo "</tr>";
			  }
		}else{
		  echo "<tr>";
		  echo "<td>" . $result . "</td>";
		  echo "</tr>";
		}
		
	  echo "</table>";
	}

if(!empty($query))	
	echo "<p><textarea rows='10' cols='80' name='sql'>".$query."</textarea></p>\n";
else
	echo "<p><textarea rows='10' cols='80' name='sql'></textarea></p>\n";
	
if (!empty($_POST['extracolumninfo'])){
	echo "<p>"._("Column information")."<input type='checkbox' name='extracolumninfo' checked='checked'/> <input class='button' type='submit' name='save' value='"._("Run")."' />";
}else{
	echo "<p>"._("Column information")."<input type='checkbox' name='extracolumninfo'/> <input class='button' type='submit' name='save' value='"._("Run")."' />";
}
echo "<input class='button' type='button' name='takaisin'  value='"._("Return")."' onclick=\"window.location.href='?view=admin/dbadmin'\"/></p>";
echo "</form>\n";

contentEnd();
pageEnd();
?>
