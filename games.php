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

$LAYOUT_ID = GAMES;

$filter = 'tournaments';
$baseurl = "?view=games";
$id=0;
$print=0;
$gamefilter="season";
$format = "html";

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
} elseif(!empty($_GET["Pools"])) {
	$id = $_GET["Pools"];
	$baseurl .= "&Pools=$id";
	$gamefilter="poolgroup";
	$title = _("Schedule")." ".utf8entities(U_(PoolSeriesName($id)).", ".U_(PoolName($id)));
} elseif(!empty($_GET["Team"])) {
	$id = intval($_GET["Team"]);
	$baseurl .= "&Team=$id";
	$gamefilter="team";
	$filter = 'tournaments';
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

if(!empty($_GET["Print"])) {
	$print = intval($_GET["Print"]);
	$format = "paper";
}

$timefilter="coming";
$order="tournaments";

switch($filter){
	case "today":
		$timefilter="all";
		$order="series";
		$games = TimetableGames($id, $gamefilter, $timefilter, $order);
		break;
	
	case "tomorrow":
		$timefilter="all";
		$order="series";
		$games = TimetableGames($id, $gamefilter, $timefilter, $order);
		break;

	case "next":
		$order="tournaments";
		$games = NextGameDay($id, $gamefilter, $order);
		break;
		
	case "tournaments":
		$timefilter="all";
		$order="tournaments";
		$games = TimetableGames($id, $gamefilter, $timefilter, $order);
		break;
	
	case "series":
		$timefilter="all";
		$order="series";
		$games = TimetableGames($id, $gamefilter, $timefilter, $order);
		break;
	
	case "places":
		$timefilter="all";
		$order="places";
		$games = TimetableGames($id, $gamefilter, $timefilter, $order);
		break;
	
	case "season":
		$timefilter="all";
		$order="places";
		$format = "pdf";
		$games = TimetableGames($id, $gamefilter, $timefilter, $order);
		break;
}	

if($format=="pdf"){
	$pdf = new PDF();
	$pdf->PrintSchedule($gamefilter, $id, $games);
	$pdf->Output();
}else{		
	//common page
	pageTop($title, $print);
	leftMenu($LAYOUT_ID, $print);
	contentStart();

	if(!$print){
		echo "<div>\n";	
		echo "<a href='".utf8entities($baseurl)."&amp;filter=tournaments'>"._("By grouping")."</a>";
		echo "&nbsp;|&nbsp;";	
		echo "<a href='".utf8entities($baseurl)."&amp;filter=series'>"._("By division")."</a>";
		echo "&nbsp;|&nbsp;";	
		echo "<a href='".utf8entities($baseurl)."&amp;filter=places'>"._("By location")."</a>";
		echo "&nbsp;|&nbsp;";	
		echo "<a href='".utf8entities($baseurl)."&amp;filter=season'>"._("PDF")."</a>";	
		echo "</div>\n";	
	}

	if(!mysql_num_rows($games)){
		echo "\n<p>"._("No games").".</p>\n";	
	}else{	
		if($filter == 'tournaments'){
			echo TournamentView($games);
		}elseif($filter == 'series'){
			echo SeriesView($games);
		}elseif($filter == 'today'){
			echo SeriesView($games, false);
		}elseif($filter == 'next'){
			echo TournamentView($games);
		}elseif($filter == 'tomorrow'){
			echo SeriesView($games, false);
		}elseif($filter == 'places'){
			echo PlaceView($games);
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
