<?php

// These are needed for the menu structure
$LAYOUT_ID = EXECUTESQL;
$title = _("Run SQL");
$query = "";
$html = "";

// process itself on submit
// Fetch querydata to be presented later
if (!defined('ENABLE_ADMIN_DB_ACCESS') || constant('ENABLE_ADMIN_DB_ACCESS') != "enabled") {
	$html = "<h2>" . _("Run SQL") . "</h2>\n";
	$html = "<p>" . _("Direct database access is disabled. To enable it, define(ENABLE_ADMIN_DB_ACCESS,'enabled') in the config.inc.php file") . "</p>";
} else {

	if (!empty($_POST['sql']) || !empty($_GET['sql'])) {
		if (!empty($_GET['sql'])) {
			$query = urldecode($_GET['sql']);
		} else {
			$query = $_POST['sql'];
		}
		$isSelect = (strpos(strtolower($query), "select") === 0);
		$isShow = (strpos(strtolower($query), "show") === 0);
		$isUpdate = (strpos(strtolower($query), "update") === 0);
		$isSet = (strpos(strtolower($query), "set") === 0);
		$isDelete = (strpos(strtolower($query), "delete") === 0);
		$arraycolumnsname = array();
		if (isSuperAdmin()) {
			$result = DBQuery($query);
		}

		if ($isSelect || $isShow) {
			$i = 0;
			while ($i < mysqli_num_fields($result)) {
				$meta = mysqli_fetch_field($result);
				$arraycolumnsname[$i] = $meta->name;
				$arraycolumnstype[$i] = $meta->type;

				$arraycolumnstable[$i] = $meta->table;
				$arraycolumnsdefault[$i] = $meta->def;
				$arraycolumnsmaxlength[$i] = $meta->max_length;
				$arraycolumnsnotnull[$i] = $meta->flags & 1;
				$arraycolumnsprimarykey[$i] = $meta->flags & 2;
				$arraycolumnsmultiplekey[$i] = $meta->flags & 8;
				$arraycolumnsuniquekey[$i] = $meta->flags & 4;
				$arraycolumnsnumeric[$i] = $meta->flags & 128;
				$arraycolumnsblob[$i] = $meta->flags & 16;
				$arraycolumnsunsigned[$i] = $meta->flags & 32;
				$arraycolumnszerofill[$i] = $meta->flags & 64;

				$i++;
			}
		}
	}



	$html .= "<form method='post' action='?view=admin/executesql'>";

	if (!empty($result)) {
		$html .= "<table>";
		$html .= "<tr>";
		foreach ($arraycolumnsname as $i => $columnname) {
			$html .= "<th>";
			// extracolumninfo is set if checkbox is checked
			if (!empty($_POST['extracolumninfo'])) {
				$html .= strtoupper($arraycolumnstable[$i]) . ".";
			}
			$html .= strtoupper($columnname);
			$html .= "</th>\n";
		}
		$html .= "</tr><tr>\n";
		foreach ($arraycolumnsname as $i => $columnname) {
			if (!empty($_POST['extracolumninfo'])) {
				$html .= "<td><table>";
				$html .= "<tr><td>type</td><td>" . $arraycolumnstype[$i] . "</td></tr>";
				$html .= "<tr><td>default</td><td>" . $arraycolumnsdefault[$i] . "</td></tr>";
				$html .= "<tr><td>maxlen</td><td>" . $arraycolumnsmaxlength[$i] . "</td></tr>";
				$html .= "<tr><td>notnull</td><td>" . $arraycolumnsnotnull[$i] . "</td></tr>";
				$html .= "<tr><td>pk</td><td>" . $arraycolumnsprimarykey[$i] . "</td></tr>";
				$html .= "<tr><td>mk</td><td>" . $arraycolumnsmultiplekey[$i] . "</td></tr>";
				$html .= "<tr><td>uk</td><td>" . $arraycolumnsuniquekey[$i] . "</td></tr>";
				$html .= "<tr><td>num</td><td>" . $arraycolumnsnumeric[$i] . "</td></tr>";
				$html .= "<tr><td>blob</td><td>" . $arraycolumnsblob[$i] . "</td></tr>";
				$html .= "<tr><td>unsig</td><td>" . $arraycolumnsunsigned[$i] . "</td></tr>";
				$html .= "<tr><td>zerofill</td><td>" . $arraycolumnszerofill[$i] . "</td></tr>";
				$html .= "</table></td>\n";
			}
		}
		$html .= "</tr>\n";
		// Print contents of the query
		if ($isSelect || $isShow) {
			while ($row = mysqli_fetch_assoc($result)) {
				$html .= "<tr>";
				foreach ($arraycolumnsname as $i => $columnname) {
					if (mysqli_fetch_field_direct($result, $i)->type != 'blob') {
						$html .= "<td  class='dbrow'>" . utf8entities($row[$columnname]) . "</td>";
					} else {
						$html .= "<td  class='dbrow'>BINARY</td>";
					}
				}
				$html .= "</tr>";
			}
		} else {
			$html .= "<tr>";
			$html .= "<td>" . $result . "</td>";
			$html .= "</tr>";
		}

		$html .= "</table>";
	}

	if (!empty($query))
		$html .= "<p><textarea rows='10' cols='80' name='sql'>" . $query . "</textarea></p>\n";
	else
		$html .= "<p><textarea rows='10' cols='80' name='sql'></textarea></p>\n";

	if (!empty($_POST['extracolumninfo'])) {
		$html .= "<p>" . _("Column information") . "<input type='checkbox' name='extracolumninfo' checked='checked'/> <input class='button' type='submit' name='save' value='" . _("Run") . "' />";
	} else {
		$html .= "<p>" . _("Column information") . "<input type='checkbox' name='extracolumninfo'/> <input class='button' type='submit' name='save' value='" . _("Run") . "' />";
	}
	$html .= "<input class='button' type='button' name='takaisin'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/dbadmin'\"/></p>";
	$html .= "</form>\n";
}

//common page
pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();

echo $html;

contentEnd();
pageEnd();
