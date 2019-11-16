<?php
include_once 'lib/team.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/player.functions.php';
include_once 'lib/statistical.functions.php';

$teamId = intval(iget("team"));
$teaminfo = TeamInfo($teamId);

$title = _("Roster").": ".utf8entities($teaminfo['name']);
$html = "";

$players = TeamPlayerList($teamId );

$html .= "<h1>"._("Roster")."</h1>\n";
$html .= "<h2>".$teaminfo['name']." (".U_($teaminfo['seriesname']).")</h2>\n";

$html .= "<table style='width:60%' cellpadding='2'>\n";
$html .= "<tr><th>"._("Name")."</th>
	<th class='center'>"._("Events")."</th>
	<th class='center'>"._("Games")."</th>
	<th class='center'>"._("Passes")."</th>
	<th class='center'>"._("Goals")."</th>
	<th class='center'>"._("Tot.")."</th></tr>\n";

$stats = array(array());
$i=0;
while($player = mysqli_fetch_assoc($players)) {
  $playerinfo = PlayerInfo($player['player_id']);
  $stats[$i]['name'] = $playerinfo['firstname'] ." ". $playerinfo['lastname'];
  $stats[$i]['id'] = $player['player_id'];
  $stats[$i]['goals'] = 0;
  $stats[$i]['passes'] = 0;
  $stats[$i]['played'] = 0;
  $stats[$i]['seasons'] = 0;
  $stats[$i]['total'] = 0;
  if(!empty($playerinfo['profile_id'])){
    $player_stats = PlayerStatistics($playerinfo['profile_id']);
  }else{
    $player_stats = array();
  }

  foreach($player_stats as $season){
    $stats[$i]['goals'] += $season['goals'];
    $stats[$i]['passes'] += $season['passes'];
    $stats[$i]['played'] += $season['games'];
    $stats[$i]['total'] = $stats[$i]['passes'] + $stats[$i]['goals'];
    $stats[$i]['seasons']++;
  }
  $i++;
}
mergesort($stats, create_function('$b,$a','return strcmp($b[\'name\'],$a[\'name\']);'));
$teamseasons = 0;
$teamplayed = 0;
$teampasses = 0;
$teamgoal = 0;
$teamtotal = 0;


foreach($stats as $player) {
  if(!empty($player)){
    $playerinfo = PlayerInfo($player['id']);
    $html .= "<tr><td>";
    if(!empty($playerinfo['profile_id'])){
      $html .= "<a href='?view=playercard&amp;series=0&amp;player=". $player['id']."'>".
      utf8entities($player['name']) ."</a>";
    }else{
      $html .= utf8entities($player['name']);
    }
    $html .= "</td>";
    $html .= "<td class='center'>".$player['seasons']."</td>";
    $html .= "<td class='center'>".$player['played']."</td>";
    $html .= "<td class='center'>".$player['passes']."</td>";
    $html .= "<td class='center'>".$player['goals']."</td>";
    $html .= "<td class='center'>".$player['total']."</td></tr>\n";
    $teamseasons += $player['seasons'];
    $teamplayed += $player['played'];
    $teampasses += $player['passes'];
    $teamgoal += $player['goals'];
    $teamtotal += $player['total'];
  }
}
if($teamseasons){
  $html .= "<tr><td>";
  $html .= "</td>";
  $html .= "<td style='border-top-style:solid; border-top-width: 1px;' class='center'>".$teamseasons."</td>";
  $html .= "<td style='border-top-style:solid; border-top-width: 1px;' class='center'>".$teamplayed."</td>";
  $html .= "<td style='border-top-style:solid; border-top-width: 1px;' class='center'>".$teampasses."</td>";
  $html .= "<td style='border-top-style:solid; border-top-width: 1px;' class='center'>".$teamgoal."</td>";
  $html .= "<td style='border-top-style:solid; border-top-width: 1px;' class='center'>".$teamtotal."</td></tr>\n";
  $html .= "</table>\n";
}

showPage($title, $html);

?>
