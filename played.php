<?php
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/timetable.functions.php';

if (is_file('cust/'.CUSTOMIZATIONS.'/pdfprinter.php')) {
	include_once 'cust/'.CUSTOMIZATIONS.'/pdfprinter.php';
} else {
	include_once 'cust/default/pdfprinter.php';
}

$LAYOUT_ID = PLAYED;

$filter = 'series';
$baseurl = "?view=played";
$id=0;
$gamefilter="season";
$format = "html";
$group = "";
$groupheader=true;

if(!empty($_GET["Series"])) {
	$id = intval($_GET["Series"]);
	$baseurl .= "&Series=$id";
	$gamefilter="series";
	$title = _("Played games")." ".utf8entities(U_(SeriesName($id)));
} elseif(!empty($_GET["Pool"])) {
	$id = intval($_GET["Pool"]);
	$baseurl .= "&Pool=$id";
	$gamefilter="pool";
	$title = _("Played games")." ".utf8entities(U_(PoolSeriesName($id)).", ".U_(PoolName($id)));
} elseif(!empty($_GET["Team"])) {
	$id = intval($_GET["Team"]);
	$baseurl .= "&Team=$id";
	$gamefilter="team";
	$title = _("Played games")." ".utf8entities(TeamName($id));
} elseif(!empty($_GET["Season"])) {
	$id = $_GET["Season"];
	$baseurl .= "&Season=$id";
	$gamefilter="season";
	$title = _("Played games")." ".utf8entities(U_(SeasonName($id)));
} else {
	$id = CurrentSeason();
	$gamefilter="season";
	$title = _("Played games");
}

if(!empty($_GET["filter"])) {
	$filter  = $_GET["filter"];
}

if(!empty($_GET["group"])) {
	$group  = $_GET["group"];
}

$timefilter="past";
$order="series";

switch($filter){
	case "today":
		$timefilter="today";
		$order="series";
		$games = TimetableGames($id, $gamefilter, $timefilter, $order);
		break;
	
	case "yesterday":
		$timefilter="yesterday";
		$order="series";
		break;

	case "prev":
		$order="tournaments";
		$games = PrevGameDay($id, $gamefilter, $order);
		break;

	case "tournaments":
		$timefilter="past";
		$order="tournamentsdesc";
		break;
	
	case "series":
		$timefilter="past";
		$order="series";
		break;
	
	case "places":
		$timefilter="past";
		$order="placesdesc";
		break;
		
	case "season":
		$timefilter="past";
		$order="placesdesc";
		$format = "pdf";
		break;
}	

$groups = TimetableGrouping($id, $gamefilter, $timefilter);
if(!empty($group)){
	$games = TimetableGames($id, $gamefilter, $timefilter, $order, $group);
}elseif(count($groups)>1){
	$group = $groups[count($groups)-1]['reservationgroup'];
	$games = TimetableGames($id, $gamefilter, $timefilter, $order, $group);
}else{
	$games = TimetableGames($id, $gamefilter, $timefilter, $order);
}

if($format=="pdf"){
	$pdf = new PDF();
	$pdf->PrintSchedule($gamefilter, $id, $games);
	$pdf->Output();
}else{		
	//common page
	pageTop($title);
	leftMenu($LAYOUT_ID);
	contentStart();
	if(count($groups)>1){
		echo "<div><p>\n";	
		foreach($groups as $grouptmp){
			if($group==$grouptmp['reservationgroup']){
				echo "<a class='groupinglink' href='".utf8entities($baseurl)."&amp;group=".urlencode($grouptmp['reservationgroup'])."'><span class='selgroupinglink'>".U_($grouptmp['reservationgroup'])."</span></a>";
			}else{
				echo "<a class='groupinglink' href='".utf8entities($baseurl)."&amp;group=".urlencode($grouptmp['reservationgroup'])."'>".U_($grouptmp['reservationgroup'])."</a>";
			}
			echo "&nbsp;&nbsp;&nbsp; ";
		}
		if($group=="all"){
			echo "<a class='groupinglink' href='".utf8entities($baseurl)."&amp;group=all'><span class='selgroupinglink'>"._("All")."</span></a>";
		}else{
			echo "<a class='groupinglink' href='".utf8entities($baseurl)."&amp;group=all'>"._("All")."</a>";
		}
		echo "</p></div>\n";	
	}
	echo "<div>\n";	
	//echo _("Played games").": ";
	//if(IsGamesScheduled($id, $gamefilter, "past")){
	//	echo "<a href='".utf8entities($baseurl)."&amp;filter=prev'>"._("Previous")."</a>";
	//	echo "&nbsp;|&nbsp;";	
	//}
	echo "<a href='".utf8entities($baseurl)."&amp;group=".urlencode($group)."&amp;filter=tournaments'>"._("By grouping")."</a>";
	echo "&nbsp;|&nbsp;";	
	echo "<a href='".utf8entities($baseurl)."&amp;group=".urlencode($group)."&amp;filter=series'>"._("By division")."</a>";
	echo "&nbsp;|&nbsp;";	
	echo "<a href='".utf8entities($baseurl)."&amp;group=".urlencode($group)."&amp;filter=places'>"._("By location")."</a>";
	echo "&nbsp;|&nbsp;";	
	echo "<a href='".utf8entities($baseurl)."&amp;group=".urlencode($group)."&amp;filter=season'>"._("PDF")."</a>";

	echo "</div>\n";	

	if(!empty($group) && $group!="all"){
		$groupheader=false;
	}
	
	if(!mysql_num_rows($games)){
		echo "\n<p>"._("No games").".</p>\n";	
	}else{
		if($filter == 'tournaments'){
			echo TournamentView($games,$groupheader);
		}elseif($filter == 'series'){
			echo SeriesView($games);
		}elseif($filter == 'today'){
			echo SeriesView($games, false);
		}elseif($filter == 'yesterday'){
			echo SeriesView($games, false);
		}elseif($filter == 'prev'){
			echo TournamentView($games,$groupheader);
		}elseif($filter == 'places'){
			echo PlaceView($games,$groupheader);
		}
	}
}
contentEnd();
pageEnd();
?>
