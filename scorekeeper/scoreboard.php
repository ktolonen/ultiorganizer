<?php
$html = "";

$gameId = intval(iget("game"));
$teamId = intval(iget("team"));
$game_result = GameResult($gameId);
$team_score_board = GameTeamScoreBorad($gameId, $teamId);

$html .= "<div data-role='header'>\n";
$html .= "<h1>". utf8entities(TeamName($teamId)) ." "._("Players of the game")."</h1>\n";
$html .= "</div><!-- /header -->\n\n";
$html .= "<div data-role='content'>\n";

$html .= "<table>\n";
$html .= "<tbody>\n";
while($row = mysqli_fetch_assoc($team_score_board))	{
  $html .= "<tr>\n";
  $html .= "<td style='padding-left:10px'>";
  $html .= "#".$row['num'] ." ";
  $html .= "</td><td style='padding-left:10px'>";
  $html .= utf8entities($row['firstname']) ."&nbsp;". utf8entities($row['lastname']);
  $html .= "</td><td style='padding-left:10px'>";
  $html .= $row['fedin'];
  $html .= "</td><td>";
  $html .= "+";
  $html .= "</td><td>";
  $html .= $row['done'];
  $html .= "</td><td>";
  $html .= "=";
  $html .= "</td><td>";
  $html .= $row['total'];
  $html .= "</td></tr>\n";
}
$html .= "</tbody>\n";		
$html .= "</table>\n";
$html .= "<a href='?view=gameplay&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("Back to game sheet")."</a>";

$html .= "</div><!-- /content -->\n\n";

echo $html;
		
?>
