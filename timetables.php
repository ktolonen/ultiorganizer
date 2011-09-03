<?php
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/timetable.functions.php';

if (is_file('cust/'.CUSTOMIZATIONS.'/pdfprinter.php')) {
	include_once 'cust/'.CUSTOMIZATIONS.'/pdfprinter.php';
} else {
	include_once 'cust/default/pdfprinter.php';
}

$LAYOUT_ID = TIMETABLES;

$filter = 'series';
$baseurl = "?view=timetables";
$id=0;
$print=0;
$gamefilter="season";
$format = "html";
$group = "";
$groupheader=true;

if(!empty($_GET["Series"])) {
	$id = intval($_GET["Series"]);
	$baseurl .= "&Series=$id";
	$gamefilter="series";
	$title = _("Schedule")." ".utf8entities(U_(SeriesName($id)));
} elseif(!empty($_GET["Pool"])) {
	$id = intval($_GET["Pool"]);
	$baseurl .= "&Pool=$id";
	$gamefilter="pool";
	$title = _("Schedule")." ".utf8entities(U_(PoolSeriesName($id)).", ".U_(PoolName($id)));
} elseif(!empty($_GET["Team"])) {
	$id = intval($_GET["Team"]);
	$baseurl .= "&Team=$id";
	$gamefilter="team";
	$filter = 'places';
	$title = _("Schedule")." ".utf8entities(TeamName($id));
} elseif(!empty($_GET["Season"])) {
	$id = $_GET["Season"];
	$baseurl .= "&Season=$id";
	$gamefilter="season";
	$title = _("Schedule")." ".utf8entities(U_(SeasonName($id)));
} else {
	$id = CurrentSeason();
	$gamefilter="season";
	$title = _("Schedule");
}

if(!empty($_GET["filter"])) {
	$filter  = $_GET["filter"];
}

if(!empty($_GET["group"])) {
	$group  = $_GET["group"];
}

if(!empty($_GET["Print"])) {
	$print = intval($_GET["Print"]);
	$format = "paper";
}

$timefilter="coming";
$order="tournaments";

switch($filter){
	case "today":
		$timefilter="today";
		$order="series";
		break;
	
	case "tomorrow":
		$timefilter="tomorrow";
		$order="series";
		break;

	case "next":
		$order="tournaments";
		$games = NextGameDay($id, $gamefilter, $order);
		break;
		
	case "tournaments":
		$timefilter="coming";
		$order="tournaments";
		break;
	
	case "series":
		$timefilter="coming";
		$order="series";
		break;
	
	case "places":
		$timefilter="coming";
		$order="places";
		break;
	
	case "season":
		$timefilter="coming";
		$order="places";
		$format = "pdf";
		break;
	
	case "onepage":
		$timefilter="coming";
		$order="onepage";
		$format = "pdf";
		break;
}	

$groups = TimetableGrouping($id, $gamefilter, $timefilter);
if(!empty($group)){
	$games = TimetableGames($id, $gamefilter, $timefilter, $order, $group);
}elseif(count($groups)>1){
	$group = $groups[0]['reservationgroup'];
	$games = TimetableGames($id, $gamefilter, $timefilter, $order, $group);
}else{
	$games = TimetableGames($id, $gamefilter, $timefilter, $order);
}

if($format=="pdf"){
	$pdf = new PDF();
	if($filter=="onepage"){
		$pdf->PrintOnePageSchedule($gamefilter, $id, $games);
	}else{
		$pdf->PrintSchedule($gamefilter, $id, $games);
	}
	$pdf->Output();
}else{		
	//common page
	pageTop($title, $print);
	leftMenu($LAYOUT_ID, $print);
	contentStart();

	if(!$print && mysql_num_rows($games)>0){
		
		
		if(count($groups)>1){
			echo "<p>\n";	
			foreach($groups as $grouptmp){
				if($group==$grouptmp['reservationgroup']){
					echo "<a class='groupinglink' href='".utf8entities($baseurl)."&amp;group=".urlencode($grouptmp['reservationgroup'])."'><span class='selgroupinglink'>".U_($grouptmp['reservationgroup'])."</span></a>";
				}else{
					echo "<a class='groupinglink' href='".utf8entities($baseurl)."&amp;group=".urlencode($grouptmp['reservationgroup'])."'>".U_($grouptmp['reservationgroup'])."</a>";
				}
				echo "&nbsp;&nbsp;&nbsp;&nbsp;";
			}
			if($group=="all"){
				echo "<a class='groupinglink' href='".utf8entities($baseurl)."&amp;group=all'><span class='selgroupinglink'>"._("All")."</span></a>";
			}else{
				echo "<a class='groupinglink' href='".utf8entities($baseurl)."&amp;group=all'>"._("All")."</a>";
			}
			echo "</p>\n";	
		}
		echo "<div>\n";	
		//echo _("Upcoming games").": ";
		//if(IsGamesScheduled($id, $gamefilter, "coming")){
		//	echo "<a href='".utf8entities($baseurl)."&amp;filter=next'>"._("Next")."</a>";
		//	echo "&nbsp;|&nbsp;";	
		//}
		echo "<a href='".utf8entities($baseurl)."&amp;group=".urlencode($group)."&amp;filter=tournaments'>"._("By grouping")."</a>";
		echo "&nbsp;|&nbsp;";
		echo "<a href='".utf8entities($baseurl)."&amp;group=".urlencode($group)."&amp;filter=series'>"._("By division")."</a>";
		echo "&nbsp;|&nbsp;";	
		echo "<a href='".utf8entities($baseurl)."&amp;group=".urlencode($group)."&amp;filter=places'>"._("By location")."</a>";
		echo "&nbsp;|&nbsp;";	
		echo "<a href='".utf8entities($baseurl)."&amp;group=".urlencode($group)."&amp;filter=onepage'>"._("Grid (PDF)")."</a>";	
		echo "&nbsp;|&nbsp;";	
		echo "<a href='".utf8entities($baseurl)."&amp;group=".urlencode($group)."&amp;filter=season'>"._("List (PDF)")."</a>";	
		echo "</div>\n";	
	}
	if(!empty($group) && $group!="all"){
		$groupheader=false;
	}

	if(!mysql_num_rows($games)){
		echo "\n<p>"._("No games scheduled").".</p>\n";	
	}else{	
		if($filter == 'tournaments'){
			echo TournamentView($games,$groupheader);
		}elseif($filter == 'series'){
			echo SeriesView($games);
		}elseif($filter == 'today'){
			echo SeriesView($games, false);
		}elseif($filter == 'next'){
			echo TournamentView($games,$groupheader);
		}elseif($filter == 'tomorrow'){
			echo SeriesView($games, false);
		}elseif($filter == 'places'){
			echo PlaceView($games,$groupheader);
		}elseif($filter == 'all'){
			echo SeriesView($games);
		}
	}

	$querystring = $_SERVER['QUERY_STRING'];
	$querystring = preg_replace("/&Print=[0-1]/","",$querystring);
	if($print){
		echo "<hr/><div style='text-align:right'><a href='?".utf8entities($querystring)."'>"._("Return")."</a></div>";
	}elseif(mysql_num_rows($games))
		{
		echo "<hr/><div style='text-align:left;float: left;clear: left'>
			<a href='?view=ical&amp;$gamefilter=$id&amp;time=$timefilter&amp;order=$order'>"._("in iCalendar format")."</a></div>";
		echo "<div style='text-align:right'><a href='?".utf8entities($querystring)."&amp;Print=1'>"._("Printable version")."</a></div>";
		}
	contentEnd();
	pageEnd();
}
?>
