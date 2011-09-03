<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
$LAYOUT_ID = ADDSEASONSERIES;
$html = "";

$seriesId=0;
$season=0;

if(!empty($_GET["Series"]))
	$seriesId = intval($_GET["Series"]);

if(!empty($_GET["Season"]))
	$season = $_GET["Season"];

$title = _("Edit");
//series parameters
$sp = array(
	"series_id"=>"",
	"name"=>"",
	"type"=>"",
	"ordering"=>"A",
	"season"=>"",
	"valid"=>"1");


//process itself on submit
if(!empty($_POST['add']))
	{
	if(!empty($_POST['name']))
		{
		$sp['name'] = $_POST['name'];
		$sp['type'] = $_POST['type'];
		$sp['ordering'] = $_POST['ordering'];
		$sp['season'] = $season;
		if(!empty($_POST['valid']))
			$sp['valid']=1;
		else
			$sp['valid']=0;
		
		$seriesId = AddSeries($sp);
		session_write_close();
		header("location:?view=admin/seasonseries&Season=$season");
		}
	else
		{
		$html .= "<p class='warning'>"._("Division name is mandatory!")."</p>";
		}
	}
else if(!empty($_POST['save']))
	{
	if(!empty($_POST['name']))
		{
		$sp['series_id'] = $seriesId;
		$sp['name'] = $_POST['name'];
		$sp['type'] = $_POST['type'];
		$sp['ordering'] = $_POST['ordering'];
		$sp['season'] = $season;
		if(!empty($_POST['valid']))
			$sp['valid']=1;
		else
			$sp['valid']=0;
		
		SetSeries($sp);
		session_write_close();
		header("location:?view=admin/seasonseries&Season=$season");
		}
	else
		{
		$html .= "<p class='warning'>"._("Division name is mandatory!")."</p>";
		}
	}
	
//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "datasource", "autocomplete"));
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

//retrieve values if series id known
if($seriesId)
	{
	$info = SeriesInfo($seriesId);
	$sp['series_id']=$info['series_id'];
	$sp['name']=$info['name'];
	$sp['type']=$info['type'];
	$sp['ordering']=$info['ordering'];
	$sp['season']=$info['season'];
	$sp['valid']=$info['valid'];
	}
	
echo $html;

//if seriesid is empty, then add new serie	
if($seriesId)
	echo "<h2>"._("Edit division").":</h2>\n";	
else
	echo "<h2>"._("Add division")."</h2>\n";	
	
	echo "<form method='post' action='?view=admin/addseasonseries&amp;Series=$seriesId&amp;Season=$season'>";
	echo "<table cellpadding='2px'>
			<tr>
			<td class='infocell'>"._("Name").":</td>
			<td>".TranslatedField("name", $sp['name'])."
			</td>
			</tr>\n";
	echo "<tr><td class='infocell'>"._("Order")." (A,B,C,D..):</td>
			<td><input class='input' id='ordering' name='ordering' value='".$sp['ordering']."'/></td></tr>";

	echo "<tr><td class='infocell'>"._("Type").": </td><td><select class='dropdown' name='type'>\n";
	
	$types = SeriesTypes();

	
	foreach($types as $type){
		if($sp['type']==$type)
			echo "<option class='dropdown' selected='selected' value='$type'>".U_($type)."</option>\n";
		else
			echo "<option class='dropdown' value='$type'>".U_($type)."</option>\n";
	}
	
	echo "</select></td></tr>\n";
	
	echo "<tr><td class='infocell'>"._("Valid").":</td>";
	if(intval($sp['valid']))
		echo "<td><input class='input' type='checkbox' id='valid' name='valid' checked='checked'/></td></tr>";
	else
		echo "<td><input class='input' type='checkbox' id='valid' name='valid'/></td></tr>";
		
	echo "</table><p>\n";
if($seriesId)
	echo "<input class='button' name='save' type='submit' value='"._("Save")."'/>";
else
	echo "<input class='button' name='add' type='submit' value='"._("Add")."'/>";
	
	echo "<input class='button' type='button' name='takaisin'  value='"._("Return")."' onclick=\"window.location.href='?view=admin/seasonseries&amp;Season=$season'\"/>
		  </p></form>";

echo TranslationScript("name");
contentEnd();
pageEnd();
?>
