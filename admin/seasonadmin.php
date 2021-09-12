<?php
include_once 'lib/season.functions.php';
include_once 'lib/statistical.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/configuration.functions.php';

$LAYOUT_ID = SEASONADMIN;
$info = SeasonInfo($_GET["season"]);

$title = _("Event").": ".utf8entities(U_($info['name']));
$html = "";

//common page
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .=  "<h2>".utf8entities(U_($info['name']))."</h2>\n";
$html .=  "<table style='white-space: nowrap;' border='0'>\n";
$html .=  "<tr><td style='width:300px'>";
$html .=  "<table style='white-space: nowrap;' border='0'>\n";
$club = intval($info['isnationalteams'])?_("National Teams"):_("Club Teams");
$inter = intval($info['isinternational'])?"":_("National");
$tour = intval($info['istournament'])?_("Tournament"):_("Season");

$html .=  "<tr><td  style='width:40%;'><b>"._("Type")."</b></td><td>".U_($info['type'])."/".$tour."/". $inter."/".$club ."</td></tr>\n";
$html .=  "<tr><td><b>"._("Organizer")."</b></td><td>".U_($info['organizer'])."/".U_($info['category'])."</td></tr>\n";

$spirit = _("not given");

if((isset($info['spiritmode']) && $info['spiritmode']>0)) {
  $spiritmode = SpiritMode($info['spiritmode']);
  $spirit = utf8entities(_($spiritmode['name'])) . " / <em>" . (intval($info['showspiritpoints'])?_("visible"):_("not visible")) . "</em>";
}
$html .=  "<tr><td><b>"._("Spirit points")."</b></td><td>".$spirit."</td></tr>\n";

$html .=  "<tr><td><b>"._("Time")."</b></td><td>".ShortDate($info['starttime']);
$html .=  " - ".ShortDate($info['endtime'])."</td></tr>\n";
$enrollment = intval($info['enrollopen'])?_("open"):_("closed");
$html .=  "<tr><td><b>"._("Enrollment")."</b></td><td>".$enrollment." (". ShortDate($info['enroll_deadline']).")</td></tr>\n";

$html .=  "<tr><td><b>"._("Timezone")."</b></td><td>".utf8entities($info['timezone']);
	if(class_exists("DateTime") && !empty($info['timezone'])){
		$dateTime = new DateTime("now", new DateTimeZone($info['timezone']));
		$html .= " (".DefTimeFormat($dateTime->format("Y-m-d H:i:s"))." )";
		}
$html .= "</td></tr>\n";
		
$visible = intval($info['iscurrent'])?_("yes"):_("no");
$html .=  "<tr><td><b>"._("Visible")."</b></td><td>".$visible."</td></tr>\n";
$html .=  "</table>";

$html .=  "</td><td style='width:300px; vertical-align:text-top;'>";

$html .=  "<table style='white-space: nowrap;' border='0'>\n";
$series = SeasonSeries($info['season_id']);
$html .=  "<tr><td style='width:80%;'><a href='?view=admin/seasonseries&amp;season=".$info['season_id']."'>"._("Divisions")."</a></td>";
$html .=  "<td class='right'>".count($series)."</td>";
$html .=  "</tr>\n";

$pools = SeasonPools($info['season_id']);
$html .=  "<tr><td><a href='?view=admin/seasonpools&amp;season=".$info['season_id']."'>"._("Pools")."</a></td>";
$html .=  "<td class='right'>".count($pools)."</td>";
$html .=  "</tr>\n";

$teams = SeasonTeams($info['season_id']);
$html .=  "<tr><td><a href='?view=admin/seasonteams&amp;season=".$info['season_id']."'>"._("Teams")."</a></td>";
$html .=  "<td class='right'>".count($teams)."</td>";
$html .=  "</tr>\n";

$players = SeasonAllPlayers($info['season_id']);
$html .=  "<tr><td><a href='?view=admin/accreditation&amp;season=".$info['season_id']."'>"._("Players")."</a></td>";
$html .=  "<td class='right'>".count($players)."</td>";
$html .=  "</tr>\n";

$reservations = SeasonReservations($info['season_id']);
$html .=  "<tr><td><a href='?view=admin/reservations&amp;season=".$info['season_id']."'>"._("Reservations")."</a></td>";
$html .=  "<td class='right'>".count($reservations)."</td>";
$html .=  "</tr>\n";

$games = SeasonAllGames($info['season_id']);
$html .=  "<tr><td><a href='?view=admin/seasongames&amp;season=".$info['season_id']."'>"._("Games")."</a></td>";
$html .=  "<td class='right'>".count($games)."</td>";
$html .=  "</tr>\n";

$html .=  "</table>";
$html .=  "</td></tr></table>\n\n";

$html .= "<b>"._("Comment").":</b> ".CommentHTML(1, $info['season_id']);

$html .=  "<p>";
$html .=  "<a href='?view=admin/addseasons&amp;season=".$info['season_id']."'>&raquo; "._("Change event properties")."</a><br/>";
$html .=  "<a href='?view=admin/addseasonusers&amp;season=".$info['season_id']."'>&raquo; "._("Edit User access rights")."</a><br/>";
$html .=  "<a href='?view=admin/addseasonlinks&amp;season=".$info['season_id']."'>&raquo; "._("Edit side menu links")."</a><br/>";
if(IsTwitterEnabled()){
	$html .=  "<a href='?view=admin/twitterconf&amp;season=".$info['season_id']."'>&raquo; "._("Configure Twitter")."</a><br/>";
}

if(IsSeasonStatsCalculated($info['season_id'])){
	$html .=  "<a href='?view=admin/stats&amp;season=".$info['season_id']."'>&raquo; "._("Re-archive statistics")."</a><br/>";
}else{
	$html .=  "<a href='?view=admin/stats&amp;season=".$info['season_id']."'>&raquo; "._("Archive statistics")."</a><br/>";
}
$html .= "</p>\n";

$html .= "<hr/>\n";
$html .=  "<p>";
$html .= "<a href='?view=admin/eventdataexport&amp;season=".$info['season_id']."'>"._("Export event data")."</a> | ";
$html .= "<a href='?view=admin/eventdataimport&amp;season=".$info['season_id']."'>"._("Import event data")."</a>";
$html .=  "</p>";

echo $html;

contentEnd();
pageEnd();
?>