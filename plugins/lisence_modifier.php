<?php
ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=updater
format=any
security=superadmin

[DESCRIPTION]
title = "Licenses"
description = "Update details in uo_license"
-->
<?php
ob_end_clean();
if (!isSuperAdmin()){die('Insufficient user rights');}

$html = "";
$title = ("Licenses");
$accId = isset($_GET["accid"]) ? intval($_GET["accid"]):0;

if (isset($_POST['save'])){
  $query = sprintf("UPDATE uo_license SET lastname='%s', firstname='%s', membership='%s',
			birthdate='%s', accreditation_id='%s', ultimate='%s', women='%s', junior='%s', license='%s', external_id='%s', external_type='%s', 
			external_validity='%s' WHERE accreditation_id='%s'",
				DBEscapeString($_POST['lastname']),
				DBEscapeString($_POST['firstname']),
				DBEscapeString($_POST['membership']),
				DBEscapeString($_POST['birthdate']),
				DBEscapeString($_POST['accreditation_id']),
				DBEscapeString($_POST['ultimate']),
				DBEscapeString($_POST['women']),
				DBEscapeString($_POST['junior']),
				DBEscapeString($_POST['license']),
				DBEscapeString($_POST['external_id']),
				DBEscapeString($_POST['external_type']),
				DBEscapeString($_POST['external_validity']),
				$accId);
	DBQuery($query);
	$accId = $_POST['accreditation_id'];
}elseif(isset($_POST['remove_x'])){
  $id = $_POST['hiddenDeleteId'];
  DBQuery("DELETE FROM uo_license WHERE accreditation_id='".$id."'");
}
	

//common page

if($accId>0){
  $html .= "<form method='post' id='tables' action='?view=plugins/lisence_modifier&amp;accid=".$accId."''>\n";
  $licenses = DBQuery("SELECT * FROM uo_license WHERE accreditation_id='".$accId."'");
  $html .= "<table>";
  $lis = mysqli_fetch_assoc($licenses);
  $columns = array_keys($lis);
  $values = array_values($lis);
  $total = count($lis);
  for ($i=0; $i < $total; $i++) {
      $html .= "<tr>";
      $html .="<td>".utf8entities($columns[$i])."</td>";
      $html .="<td><input class='input' name='".$columns[$i]."' value='".utf8entities($values[$i])."'/></td>";
      $html .= "</tr>";
  }
  $html .= "</table>";
  $html .= "<input class='button' type='submit' name='save' value='"._("Save")."' />";
  $html .= "<input class='button' type='button' name='takaisin'  value='"._("Return")."' onclick=\"window.location.href='?view=plugins/lisence_modifier'\"/>";
  
}else{
  $html .= "<form method='post' id='tables' action='?view=plugins/lisence_modifier'>\n";
  $licenses = DBQuery("SELECT * FROM uo_license ORDER BY lastname");
  $html .= "<table style='width:100%'>";
  while($lis = mysqli_fetch_assoc($licenses)){
    $html .= "<tr>";
    $html .="<td>".utf8entities($lis['accreditation_id'])."</td>";    
    $html .="<td>".utf8entities($lis['lastname'])."</td>";
    $html .="<td>".utf8entities($lis['firstname'])."</td>";
    $html .="<td>".utf8entities($lis['membership'])."</td>";
    $html .="<td>".utf8entities($lis['license'])."</td>";
    $html .="<td><a href='?view=plugins/lisence_modifier&amp;accid=".$lis['accreditation_id']."'>"._("edit")."</a></td>";
    $html .="<td><input class='deletebutton' type='image' src='images/remove.png' name='remove' value='X' alt='X' onclick='setId(".$lis['accreditation_id'].");'/></td>";
    //$html .="<td>".utf8entities($lis['accreditation_id'])."</td>";
    $html .= "</tr>";
  }
  $html .= "</table>";
}
$html .= "<div><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></div>";
$html .= "</form>";	  
showPage($title, $html);
?>
