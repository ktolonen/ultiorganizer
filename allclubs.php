<?php
include_once 'lib/club.functions.php';

$LAYOUT_ID = ALLCLUBS;
$title = _("All clubs");
$html = "";

$filter = "A";

if(!empty($_GET["list"])) {
	$filter = strtoupper($_GET["list"]);
}

$validletters = array("#","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
$maxcols = 3;
/*
$clubs = ClubList(true);
if(mysql_num_rows($clubs)){
	$html .= "<h1>"._("Clubs")."</h1>\n";

	$html .= "<table style='white-space: nowrap;' border='0' cellspacing='0' cellpadding='2' width='300px'>\n";
	$html .= "<tr><th>"._("Club")."</th><th>"._("Teams")."</th></tr>\n";
	while ($club = mysql_fetch_assoc($clubs)) {
		$html .= "<tr>";
		$html .= "<td>";
		if(intval($club['country'])){
			$html .= "<img height='10' src='images/flags/tiny/".$club['flagfile']."' alt=''/>&nbsp;";
		}
		$html .= "<a href='?view=clubcard&amp;Club=". $club['club_id']."'>".utf8entities($club['name'])."</a></td>";
		$html .= "<td class='center'>".ClubNumOfTeams($club['club_id'])."</td>";
		$html .= "</tr>\n";
	}
	
	$html .= "</table>\n";
}*/
$html .= "<h1>$title</h1>\n";
$html .= "<table style='white-space: nowrap;'><tr>\n";
foreach($validletters as $let){
	if($let==$filter){
		$html .= "<td class='selgroupinglink'>&nbsp;".utf8entities($let)."&nbsp;</td>";
	}else{
		$html .= "<td>&nbsp;<a class='groupinglink' href='?view=allclubs&amp;list=".urlencode($let)."'>".utf8entities($let)."</a>&nbsp;</td>";
	}
}
if($filter=="ALL"){
	$html .= "<td class='selgroupinglink'>&nbsp;"._("ALL")."</td>";
}else{
	$html .= "<td>&nbsp;<a class='groupinglink' href='?view=allclubs&amp;list=all'>"._("ALL")."</a></td>";
}
$html .= "</tr></table>\n";

$html .= "<table style='white-space: nowrap;width:100%;'>\n";
$clubs = ClubList(true,$filter);

$firstchar = " ";
$listletter = " ";
$counter = 0;

while($club = mysql_fetch_assoc($clubs)){
	
	if($filter == "ALL"){
		$firstchar = strtoupper(substr(utf8_decode($club['name']),0,1));
		if($listletter != $firstchar && in_array($firstchar,$validletters)){
			$listletter = $firstchar;
			if($counter>0 && $counter<=$maxcols){$html .= "</tr>\n";}
			$html .= "<tr><td></td></tr>\n";
			$html .= "<tr><td class='list_letter' colspan='$maxcols'>".utf8_encode("$listletter")."</td></tr>\n";
			$counter = 0;
		}
	}
	if($counter==0){
		$html .= "<tr>\n";
		}
	
	$html .= "<td style='width:33%'>";
	if(intval($club['country'])){
		$html .= "<img height='10' src='images/flags/tiny/".$club['flagfile']."' alt=''/>&nbsp;";
	}
	$html .= "<a href='?view=clubcard&amp;Club=".$club['club_id']."'>".utf8entities($club['name'])."</a>";
	$html .= "</td>";
	$counter++;			
	
	if($counter>=$maxcols){
		$html .= "</tr>\n";
		$counter = 0;
	}
}
if($counter>0 && $counter<=$maxcols){$html .= "</tr>\n";};
$html .= "</table>\n";

showPage($LAYOUT_ID, $title, $html);

?>
