<?php
ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=updater
format=any
security=superadmin
customization=all

[DESCRIPTION]
title = "Pool color updater"
description = "Automatically updates pool colors based on predefined list."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()){die('Insufficient user rights');}
	
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/standings.functions.php';

$html = "";
$title = ("Pool color updater");
$seasonId = "";
$colors = array("F0F8FF","FAEBD7","00FFFF","7FFFD4","F0FFFF","F5F5DC","FFE4C4","0000FF","8A2BE2","DEB887","FFFF00","5F9EA0",
			"7FFF00","D2691E","FF7F50","6495ED","FFF8DC","DC143C","00FFFF","00008B","008B8B","B8860B","A9A9A9","006400",
			"BDB76B","8B008B","FF8C00","9932CC","8B0000","E9967A","8FBC8F","00CED1","9400D3","FF1493","00BFFF","1E90FF",
			"B22222","228B22","FF00FF","DCDCDC","F8F8FF","FFD700","DAA520","008000","ADFF2F","F0FFF0","FF69B4","CD5C5C",
			"FFFFF0","F0E68C","E6E6FA","FFF0F5","7CFC00","FFFACD","ADD8E6","F08080","E0FFFF","FAFAD2","D3D3D3","90EE90",
			"FFB6C1","FFA07A","20B2AA","87CEFA","778899","B0C4DE","FFFFE0","00FF00","32CD32","FAF0E6","FF00FF","800000",
			"66CDAA","0000CD","BA55D3","9370D8","3CB371","7B68EE","00FA9A","48D1CC","C71585","191970","F5FFFA","FFE4E1",
			"FFE4B5","FFDEAD","FDF5E6","808000","6B8E23","FFA500","FF4500","DA70D6","EEE8AA","98FB98","AFEEEE","D87093",
			"FFEFD5","FFDAB9","CD853F","FFC0CB","DDA0DD","B0E0E6","800080","FF0000","BC8F8F","4169E1","FA8072","F4A460",
			"2E8B57","FFF5EE","A0522D","C0C0C0","87CEEB","6A5ACD","708090","FFFAFA","00FF7F","4682B4","D2B48C","D8BFD8",
			"FF6347","40E0D0","EE82EE","F5DEB3","F5F5F5","9ACD32");

if(!empty($_POST['season'])){
	$seasonId = $_POST['season'];
}

if (isset($_POST['simulate']) && !empty($_POST['pools'])) {

	$pools = $_POST["pools"];
	
	foreach($pools as $poolId){
		$color = $colors[rand(0,count($colors)-1)];
		$query = "UPDATE uo_pool SET color='".$color."' WHERE pool_id=".$poolId;
		DBQuery($query);		
	}
}

//season selection
$html .= "<form method='post' id='tables' action='?view=plugins/update_pool_colors'>\n";

if(empty($seasonId)){
	$html .= "<p>".("Select event").": <select class='dropdown' name='season'>\n";

	$seasons = Seasons();
			
	while($row = mysqli_fetch_assoc($seasons)){
		$html .= "<option class='dropdown' value='".utf8entities($row['season_id'])."'>". utf8entities($row['name']) ."</option>";
	}

	$html .= "</select></p>\n";
	$html .= "<p><input class='button' type='submit' name='select' value='".("Select")."'/></p>";
}else{
	
	$html .= "<p>".("Select pools to change color").":</p>\n";
	$html .= "<table>";
	$html .= "<tr><th><input type='checkbox' onclick='checkAll(\"tables\");'/></th>";
	$html .= "<th>".("Pool")."</th>";
	$html .= "<th>".("Series")."</th>";
	$html .= "</tr>\n";
	
	$series = SeasonSeries($seasonId);
	foreach($series as $row){

		$pools = SeriesPools($row['series_id']);
		foreach($pools as $pool){
			$poolinfo = PoolInfo($pool['pool_id']);
			$html .= "<tr style='background-color:#".$poolinfo['color']."'>";
			$html .= "<td class='center'><input type='checkbox' name='pools[]' value='".utf8entities($pool['pool_id'])."' /></td>";
			$html .= "<td>". $pool['name'] ."</td>";
			$html .= "<td>". $row['name'] ."</td>";
			$html .= "</tr>\n";
		}
	}
	$html .= "</table>\n";
	$html .= "<p><input class='button' type='submit' name='simulate' value='".("Update")."'/></p>";
	$html .= "<div>";
	$html .= "<input type='hidden' name='season' value='$seasonId' />\n";
	$html .= "</div>\n";
}

$html .= "</form>";

showPage($title, $html);
?>
