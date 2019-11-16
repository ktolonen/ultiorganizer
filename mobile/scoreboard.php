<?php
include_once 'lib/common.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
$html = "";

$gameId = intval(iget("game"));
$teamId = intval(iget("team"));
$game_result = GameResult($gameId);
$team_score_board = GameTeamScoreBorad($gameId, $teamId);

mobilePageTop(_("Players of the game"));

$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= "<b>". utf8entities(TeamName($teamId)) ."</b>";
$html .= "</td></tr><tr><td>\n";
while($row = mysqli_fetch_assoc($team_score_board))	{
	$html .= $row['num'] ." ";
	$html .= utf8entities($row['firstname']) ."&nbsp;". utf8entities($row['lastname']) ." ";
	$html .= $row['fedin'] ."+". $row['done'] ."=". $row['total'];
	$html .= "</td></tr><tr><td>\n";
}
		
$html .= "<a href='?view=mobile/gameplay&amp;game=".$gameId."'>"._("Back to game sheet")."</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";

echo $html;
		
pageEnd();
?>
