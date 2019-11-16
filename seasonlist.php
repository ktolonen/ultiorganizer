<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$title = _("Old events");
$html = "";
$counter = 0;
$maxcols = 3;

$html .= "\n<h1>".$title."</h1>\n";

$seasons = Seasons();

$html .= "<table width='100%' border='0' cellspacing='0' cellpadding='2'>\n";
while($season = mysqli_fetch_assoc($seasons)){
  if(!IsSeasonStatsCalculated($season['season_id'])){
    continue;
  }
  if($counter==0){
    $html .= "<tr>\n";
  }
  $seasonName = SeasonName($season['season_id']);

  $html .= "<td style='vertical-align:text-top;'>";
  $html .= "<h3>".utf8entities($seasonName)."</h3>";

  $html .= "<div><a href='?view=teams&amp;season=".$season['season_id']."'>"._("Teams")."</a><br/>";
  $html .= "<a href='?view=games&amp;season=".$season['season_id']."'>"._("Played games")."</a><br/>";
  $html .= "<a href='?view=teams&amp;season=".$season['season_id']."&amp;list=bystandings'>"._("Final standings")."</a></div>";
  $series = SeasonSeries($season['season_id'], true);

  if(count($series)){
    $html .= "<table cellpadding='0'>";
    foreach($series as $ser){
      $html .= "<tr><td><a href='?view=seriesstatus&amp;series=".$ser['series_id']."
				'>".utf8entities(U_($ser['name']))." "._("division")."</a></td></tr>";
    }
    	
    $html .= "</table>";
  }
  $html .= "</td>";
  $counter++;
  if($counter>=$maxcols){
    $html .= "</tr>\n";
    $counter = 0;
  }
}
if($counter>0 && $counter<=$maxcols){$html .= "</tr>\n";};
$html .= "</table>\n";

showPage($title, $html);
?>
