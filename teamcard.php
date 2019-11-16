<?php
include_once 'lib/team.functions.php';
include_once 'lib/player.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/statistical.functions.php';
include_once 'lib/timetable.functions.php';

$html = "";

$teamId = intval(iget("team"));
$teaminfo = TeamInfo($teamId);
$profile = TeamProfile($teamId);

$title = utf8entities($teaminfo['name']);

$html .= "<h1>";
$html .= utf8entities($teaminfo['name'])." (".U_($teaminfo['type']).")</h1>";

if(intval($teaminfo['country'])>0){
  $html .= "<p>";
  $html .= "<img height='10' src='images/flags/tiny/".$teaminfo['flagfile']."' alt=''/>&nbsp;";
  $html .= "<a class='headerlink' href='?view=countrycard&amp;country=". $teaminfo['country']."'>".utf8entities($teaminfo['countryname'])."</a></p>";
}
if(intval($teaminfo['club'])>0){
  $html .= "<p>". _("Club").": <a class='headerlink' href='?view=clubcard&amp;club=". $teaminfo['club']."'>".utf8entities($teaminfo['clubname'])."</a></p>";
}
if($profile){
  $html .= "<table style='width:100%'>";

  if(!empty($profile['profile_image'])){
    $html .= "<tr><td colspan='2'><a href='".UPLOAD_DIR."teams/$teamId/".$profile['profile_image']."'>";
    $html .= "<img src='".UPLOAD_DIR."teams/$teamId/thumbs/".$profile['profile_image']."' alt='"._("Profile image")."'/></a></td></tr>\n";
  }else{
    $html .= "<tr><td colspan='2'></td></tr>";
  }

  if(!empty($profile['coach'])){
    $html .= "<tr><td class='profileheader' style='width:100px'>"._("Coach").":</td>";
    $html .= "<td>".utf8entities($profile['coach'])."</td></tr>\n";
  }
  if(!empty($profile['captain'])){
    $html .= "<tr><td class='profileheader' style='width:100px'>"._("Captain").":</td>";
    $html .= "<td>".utf8entities($profile['captain'])."</td></tr>\n";
  }

  if(!empty($profile['story'])){
    $html .= "<tr><td colspan='2'>&nbsp;</td></tr>\n";
    $story = utf8entities($profile['story']);
    $story = str_replace("\n",'<br/>',$story);
    $html .= "<tr><td colspan='2'>".$story."</td></tr>\n";
  }

  if(!empty($profile['achievements'])){
    $html .= "<tr><td colspan='2'>&nbsp;</td></tr>\n";
    $html .= "<tr><td class='profileheader' colspan='2'>"._("Achievements").":</td></tr>\n";
    $html .= "<tr><td colspan='2'></td></tr>\n";
    $achievements = utf8entities($profile['achievements']);
    $achievements = str_replace("\n",'<br/>',$achievements);
    $html .= "<tr><td colspan='2'>".$achievements."</td></tr>\n";
  }
  $html .= "</table>";
}

$urls = GetUrlList("team", $teamId);
if(count($urls)){
  $html .= "<table style='width:100%'>";
  $html .= "<tr><td colspan='2' class='profileheader' style='vertical-align:top'>"._("Team pages").":</td></tr>";
  foreach($urls as $url){
    $html .= "<tr>";
    $html .= "<td style='width:18px'><img width='16' height='16' src='images/linkicons/".$url['type'].".png' alt='".$url['type']."'/> ";
    $html .= "</td><td>";
    if(!empty($url['name'])){
      $html .="<a href='". $url['url']."'>". $url['name']."</a>";
    }else{
      $html .="<a href='". $url['url']."'>". $url['url']."</a>";
    }
    $html .= "</td>";
    $html .= "</tr>";
  }
  $html .= "</table>";
}

$urls = GetMediaUrlList("team", $teamId);
if(count($urls)){
  $html .= "<table style='width:100%'>";
  $html .= "<tr><td colspan='2' class='profileheader' style='vertical-align:top'>"._("Photos and Videos").":</td></tr>";
  foreach($urls as $url){
    $html .= "<tr>";
    $html .= "<td style='width:18px'><img width='16' height='16' src='images/linkicons/".$url['type'].".png' alt='".$url['type']."'/> ";
    $html .= "</td><td>";
    if(!empty($url['name'])){
      $html .="<a href='". $url['url']."'>". $url['name']."</a>";
    }else{
      $html .="<a href='". $url['url']."'>". $url['url']."</a>";
    }
    if(!empty($url['mediaowner'])){
      $html .=" "._("from")." ". $url['mediaowner'];
    }
    $html .= "</td>";
    $html .= "</tr>";
  }
  $html .= "</table>";
}

if(ShowDefenseStats())
{
  $playerswihtdef = TeamScoreBoardWithDefenses($teamId,0,"name",0);
  if(mysqli_num_rows($playerswihtdef)){
    $html .= "<p><span class='profileheader'>".utf8entities(U_(SeasonName($teaminfo['season'])))." ". _("roster").":</span></p>\n";

    $html .= "<table style='width:80%'>\n";
    $html .= "<tr><th style='width:40%'>"._("Name")."</th>
		<th class='center' style='width:15%'>"._("Games")."</th>
		<th class='center' style='width:15%'>"._("Passes")."</th>
		<th class='center' style='width:15%'>"._("Goals")."</th>
		<th class='center' style='width:15%'>"._("Tot.")."</th>
		<th class='center' style='width:15%'>"._("Defenses")."</th></tr>\n";

    while($player = mysqli_fetch_assoc($playerswihtdef)) {
      $playerinfo = PlayerInfo($player['player_id']);
      $html .= "<tr><td>";
      if(!empty($playerinfo['profile_id'])){
        if ($playerinfo['num']>-1){
          $html .= "<a href='?view=playercard&amp;series=0&amp;player=". $player['player_id']."'>".
								"#".$playerinfo['num']." ". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</a>";
        }else{
          $html .= "<a href='?view=playercard&amp;series=0&amp;player=". $player['player_id']."'>".
          utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</a>";
        }
        if(!empty($playerinfo['profile_image'])){
          $html .= "&nbsp;<img width='10' height='10' src='images/linkicons/image.png' alt='"._("Photo")."'/>";
        }
      }else{
        if($playerinfo['num']>-1){
          $html .= "#".$playerinfo['num']." ". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']);
        }else{
          $html .= utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']);
        }
      }
      $html .= "</td>";
      $html .= "<td class='center'>".$player['games']."</td>";
      $html .= "<td class='center'>".$player['fedin']."</td>";
      $html .= "<td class='center'>".$player['done']."</td>";
      $html .= "<td class='center'>".$player['total']."</td>";
      $html .= "<td class='center'>".$player['deftotal']."</td></tr>\n";
    }
    $html .= "</table>\n";
  }
}
else
{

  $players = TeamScoreBoard($teamId,0,"name",0);
  if(mysqli_num_rows($players)){
    $html .= "<p><span class='profileheader'>".utf8entities(U_(SeasonName($teaminfo['season'])))." ". _("roster").":</span></p>\n";

    $html .= "<table style='width:80%'>\n";
    $html .= "<tr><th style='width:40%'>"._("Name")."</th>
		<th class='center' style='width:15%'>"._("Games")."</th>
		<th class='center' style='width:15%'>"._("Passes")."</th>
		<th class='center' style='width:15%'>"._("Goals")."</th>
		<th class='center' style='width:15%'>"._("Tot.")."</th></tr>\n";

    while($player = mysqli_fetch_assoc($players)) {
      $playerinfo = PlayerInfo($player['player_id']);
      $html .= "<tr><td>";
      if(!empty($playerinfo['profile_id'])){
        if ($playerinfo['num']>-1){
          $html .= "<a href='?view=playercard&amp;series=0&amp;player=". $player['player_id']."'>".
								"#".$playerinfo['num']." ". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</a>";
        }else{
          $html .= "<a href='?view=playercard&amp;series=0&amp;player=". $player['player_id']."'>".
          utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</a>";
        }
        if(!empty($playerinfo['profile_image'])){
          $html .= "&nbsp;<img width='10' height='10' src='images/linkicons/image.png' alt='"._("Photo")."'/>";
        }
      }else{
        if($playerinfo['num']>-1){
          $html .= "#".$playerinfo['num']." ". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']);
        }else{
          $html .= utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']);
        }
      }
      $html .= "</td>";
      $html .= "<td class='center'>".$player['games']."</td>";
      $html .= "<td class='center'>".$player['fedin']."</td>";
      $html .= "<td class='center'>".$player['done']."</td>";
      $html .= "<td class='center'>".$player['total']."</td></tr>\n";
    }
    $html .= "</table>\n";
  }

}
$allgames = TimetableGames($teamId, "team", "all", "time");
if(mysqli_num_rows($allgames)){
  $html .= "<h2>".U_(SeasonName($teaminfo['season'])).":</h2>\n";
  $html .=  "<p>"._("Division").": <a href='?view=poolstatus&amp;series=". $teaminfo['series'] ."'>".utf8entities(U_($teaminfo['seriesname']))."</a></p>";
  $html .= "<table style='width:80%'>\n";
  while($game = mysqli_fetch_assoc($allgames)){
    //function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
    $html .= GameRow($game, false, false, false, false, false, true);
  }

  $html .= "</table>\n";
}

$seasons = TeamStatisticsByName($teaminfo['name'], $teaminfo['type']);
if(ShowDefenseStats())
{
  if(count($seasons)){
    $html .= "<h2>"._("History").":</h2>\n";

    $tmphtml = "";

    $tmphtml .= "<table style='white-space: nowrap;' border='1' cellspacing='0' width='100%'><tr>
		<th>"._("Event")."</th>
		<th>"._("Division")."</th>
		<th>"._("Pos.")."</th>
		<th>"._("Games")."</th>
		<th>"._("Wins")."</th>
		<th>"._("Losses")."</th>
		<th>"._("Win-%")."</th>
		<th>"._("Goals for")."</th>
		<th>"._("GF/game")."</th>
		<th>"._("against")."</th>
		<th>"._("GA/game")."</th>
		<th>"._("diff.")."</th>
		<th>"._("Defenses")."</th>
		</tr>";


    $prevseason="";
    $seasoncounter=0;

    $nCurSer=0;

    $stats = array();

    foreach($seasons as $season){

      //played games
      $pg = array(
			"season_type"=>"",
			"games"=>0,
			"wins"=>0,
			"losses"=>0,
			"goals_made"=>0,
			"goals_against"=>0,
			"defenses"=>0
      );

      $pg['season_type'] = $season['seasontype'];
      if($season['season'] != $prevseason){
        $seasoncounter++;
        $prevseason = $season['season'];
      }
      	
      if($seasoncounter%2){
        $tmphtml .= "<tr class='highlight'>";
      }else{
        $tmphtml .= "<tr>";
      }
      $tmphtml .= "<td>".utf8entities(U_($season['seasonname']))."</td>";
      $tmphtml .= "<td>".utf8entities(U_($season['seriesname']))."</td>";
      $tmphtml .= "<td>".$season['standing']."</td>";
      $pg['goals_made'] = $season['goals_made'];
      $pg['goals_against'] = $season['goals_against'];
      $pg['wins'] = $season['wins'];
      $pg['losses'] = $season['losses'];
      $pg['games'] = $season['wins']+$season['losses'];
      $pg['defenses'] = $season['defenses_total'];

      $tmphtml .= "<td>".$pg['games']."</td>";
      $tmphtml .= "<td>".$pg['wins']."</td>";
      $tmphtml .= "<td>".$pg['losses']."</td>";
      $tmphtml .= "<td>". number_format((SafeDivide($pg['wins'],$pg['games'])*100),1) ."%</td>";
      $tmphtml .= "<td>".$pg['goals_made']."</td>";
      $tmphtml .= "<td>". number_format(SafeDivide($pg['goals_made'],$pg['games']),1) ."</td>";
      $tmphtml .= "<td>".$pg['goals_against']."</td>";
      $tmphtml .= "<td>". number_format(SafeDivide($pg['goals_against'],$pg['games']),1) ."</td>";
      $tmphtml .= "<td>". ($pg['goals_made']-$pg['goals_against']) ."</td>";
      $tmphtml .= "<td>".$pg['defenses']."</td>";
      $tmphtml .= "</tr>";

      $stats[] = $pg;
    }
    $tmphtml .= "</table><p></p>";

    mergesort($stats, create_function('$b,$a','return strcmp($b[\'season_type\'],$a[\'season_type\']);'));

    $html .= "<table border='1' width='100%'><tr>
	<th>"._("Event type")."</th>
	<th>"._("Games")."</th>
	<th>"._("Wins")."</th>
	<th>"._("Losses")."</th>
	<th>"._("Win-%")."</th>
	<th>"._("Goals for")."</th>
	<th>"._("GF/game")."</th>
	<th>"._("against")."</th>
	<th>"._("GA/game")."</th>
	<th>"._("diff.")."</th>
	<th>"._("Defenses")."</th>
	</tr>";

    $total_games=0;
    $total_wins=0;
    $total_losses=0;
    $total_goals_made=0;
    $total_goals_against=0;
    $total_defenses=0;

    for($i=0;$i<count($stats);){
      $season_type = $stats[$i]['season_type'];
      $games = $stats[$i]['games'];
      $wins = $stats[$i]['wins'];
      $losses = $stats[$i]['losses'];
      $goals_made = $stats[$i]['goals_made'];
      $goals_against = $stats[$i]['goals_against'];
      $defenses = $stats[$i]['defenses'];

      for($i=$i+1;$i<count($stats)&& $season_type==$stats[$i]['season_type'];$i++){
        $games += $stats[$i]['games'];
        $wins += $stats[$i]['wins'];
        $losses += $stats[$i]['losses'];
        $goals_made += $stats[$i]['goals_made'];
        $goals_against += $stats[$i]['goals_against'];
        $defenses += $stats[$i]['defenses'];
      }
      $total_games += $games;
      $total_wins += $wins;
      $total_losses += $losses;
      $total_goals_made += $goals_made;
      $total_goals_against += $goals_against;
      $total_defenses += $defenses;

      $html .= "<tr>";
      $html .= "<td>".U_($season_type)."</td>";
      $html .= "<td>$games</td>";
      $html .= "<td>$wins</td>";
      $html .= "<td>$losses</td>";
      $html .= "<td>".(number_format((SafeDivide($wins,$games)*100),1))." %</td>";
      $html .= "<td>$goals_made</td>";
      $html .= "<td>".(number_format(SafeDivide($goals_made,$games),1))."</td>";
      $html .= "<td>$goals_against</td>";
      $html .= "<td>".(number_format(SafeDivide($goals_against,$games),1))."</td>";
      $html .= "<td>".($goals_made-$goals_against)."</td>";
      $html .= "<td>$defenses</td>";
      $html .= "</tr>";
    }

    $html .= "<tr class='highlight'>";
    $html .= "<td>"._("Total")."</td>";
    $html .= "<td>$total_games</td>";
    $html .= "<td>$total_wins</td>";
    $html .= "<td>$total_losses</td>";
    $html .= "<td>".(number_format((SafeDivide($total_wins,$total_games)*100),1))." %</td>";
    $html .= "<td>$total_goals_made</td>";
    $html .= "<td>".(number_format(SafeDivide($total_goals_made,$total_games),1))."</td>";
    $html .= "<td>$total_goals_against</td>";
    $html .= "<td>".(number_format(SafeDivide($total_goals_against,$total_games),1))."</td>";
    $html .= "<td>".($total_goals_made-$total_goals_against)."</td>";
    $html .= "<td>$total_defenses</td>";
    $html .= "</tr>";


    $html .= "</table>";

    $html .= $tmphtml;
  }
}
else
{
  if(count($seasons)){
    $html .= "<h2>"._("History").":</h2>\n";

    $tmphtml = "";

    $tmphtml .= "<table style='white-space: nowrap;' border='1' cellspacing='0' width='100%'><tr>
		<th>"._("Event")."</th>
		<th>"._("Division")."</th>
		<th>"._("Pos.")."</th>
		<th>"._("Games")."</th>
		<th>"._("Wins")."</th>
		<th>"._("Losses")."</th>
		<th>"._("Win-%")."</th>
		<th>"._("Goals for")."</th>
		<th>"._("GF/game")."</th>
		<th>"._("against")."</th>
		<th>"._("GA/game")."</th>
		<th>"._("diff.")."</th>
		</tr>";


    $prevseason="";
    $seasoncounter=0;

    $nCurSer=0;

    $stats = array();

    foreach($seasons as $season){

      //played games
      $pg = array(
			"season_type"=>"",
			"games"=>0,
			"wins"=>0,
			"losses"=>0,
			"goals_made"=>0,
			"goals_against"=>0
      );

      $pg['season_type'] = $season['seasontype'];
      if($season['season'] != $prevseason){
        $seasoncounter++;
        $prevseason = $season['season'];
      }
      	
      if($seasoncounter%2){
        $tmphtml .= "<tr class='highlight'>";
      }else{
        $tmphtml .= "<tr>";
      }
      $tmphtml .= "<td>".utf8entities(U_($season['seasonname']))."</td>";
      $tmphtml .= "<td>".utf8entities(U_($season['seriesname']))."</td>";
      $tmphtml .= "<td>".$season['standing']."</td>";
      $pg['goals_made'] = $season['goals_made'];
      $pg['goals_against'] = $season['goals_against'];
      $pg['wins'] = $season['wins'];
      $pg['losses'] = $season['losses'];
      $pg['games'] = $season['wins']+$season['losses'];

      $tmphtml .= "<td>".$pg['games']."</td>";
      $tmphtml .= "<td>".$pg['wins']."</td>";
      $tmphtml .= "<td>".$pg['losses']."</td>";
      $tmphtml .= "<td>". number_format((SafeDivide($pg['wins'],$pg['games'])*100),1) ."%</td>";
      $tmphtml .= "<td>".$pg['goals_made']."</td>";
      $tmphtml .= "<td>". number_format(SafeDivide($pg['goals_made'],$pg['games']),1) ."</td>";
      $tmphtml .= "<td>".$pg['goals_against']."</td>";
      $tmphtml .= "<td>". number_format(SafeDivide($pg['goals_against'],$pg['games']),1) ."</td>";
      $tmphtml .= "<td>". ($pg['goals_made']-$pg['goals_against']) ."</td>";
      $tmphtml .= "</tr>";

      $stats[] = $pg;
    }
    $tmphtml .= "</table><p></p>";

    mergesort($stats, create_function('$b,$a','return strcmp($b[\'season_type\'],$a[\'season_type\']);'));

    $html .= "<table border='1' width='100%'><tr>
	<th>"._("Event type")."</th>
	<th>"._("Games")."</th>
	<th>"._("Wins")."</th>
	<th>"._("Losses")."</th>
	<th>"._("Win-%")."</th>
	<th>"._("Goals for")."</th>
	<th>"._("GF/game")."</th>
	<th>"._("against")."</th>
	<th>"._("GA/game")."</th>
	<th>"._("diff.")."</th>
	</tr>";

    $total_games=0;
    $total_wins=0;
    $total_losses=0;
    $total_goals_made=0;
    $total_goals_against=0;

    for($i=0;$i<count($stats);){
      $season_type = $stats[$i]['season_type'];
      $games = $stats[$i]['games'];
      $wins = $stats[$i]['wins'];
      $losses = $stats[$i]['losses'];
      $goals_made = $stats[$i]['goals_made'];
      $goals_against = $stats[$i]['goals_against'];

      for($i=$i+1;$i<count($stats)&& $season_type==$stats[$i]['season_type'];$i++){
        $games += $stats[$i]['games'];
        $wins += $stats[$i]['wins'];
        $losses += $stats[$i]['losses'];
        $goals_made += $stats[$i]['goals_made'];
        $goals_against += $stats[$i]['goals_against'];
      }
      $total_games += $games;
      $total_wins += $wins;
      $total_losses += $losses;
      $total_goals_made += $goals_made;
      $total_goals_against += $goals_against;

      $html .= "<tr>";
      $html .= "<td>".U_($season_type)."</td>";
      $html .= "<td>$games</td>";
      $html .= "<td>$wins</td>";
      $html .= "<td>$losses</td>";
      $html .= "<td>".(number_format((SafeDivide($wins,$games)*100),1))." %</td>";
      $html .= "<td>$goals_made</td>";
      $html .= "<td>".(number_format(SafeDivide($goals_made,$games),1))."</td>";
      $html .= "<td>$goals_against</td>";
      $html .= "<td>".(number_format(SafeDivide($goals_against,$games),1))."</td>";
      $html .= "<td>".($goals_made-$goals_against)."</td>";
      $html .= "</tr>";
    }

    $html .= "<tr class='highlight'>";
    $html .= "<td>"._("Total")."</td>";
    $html .= "<td>$total_games</td>";
    $html .= "<td>$total_wins</td>";
    $html .= "<td>$total_losses</td>";
    $html .= "<td>".(number_format((SafeDivide($total_wins,$total_games)*100),1))." %</td>";
    $html .= "<td>$total_goals_made</td>";
    $html .= "<td>".(number_format(SafeDivide($total_goals_made,$total_games),1))."</td>";
    $html .= "<td>$total_goals_against</td>";
    $html .= "<td>".(number_format(SafeDivide($total_goals_against,$total_games),1))."</td>";
    $html .= "<td>".($total_goals_made-$total_goals_against)."</td>";
    $html .= "</tr>";


    $html .= "</table>";

    $html .= $tmphtml;
  }
}


$sort = iget("sort");

if(empty($sort)){
  $sort="serie";
}

$played = TeamPlayedGames($teaminfo['name'], $teaminfo['type'], $sort);
if(mysqli_num_rows($played)){
  $html .= "<h2>"._("Game history")."</h2>";

  $viewUrl="?view=teamcard&amp;team=$teamId&amp;";

  $html .= "<table border='1' cellspacing='2' width='100%'><tr>";

  $html .= "<th><a class='thsort' href=\"".$viewUrl."sort=team\">"._("Team")."</a></th>";
  $html .= "<th><a class='thsort' href=\"".$viewUrl."sort=result\">"._("Result")."</a></th>";
  $html .= "<th><a class='thsort' href=\"".$viewUrl."sort=serie\">"._("Division")."</a></th></tr>";
  $curSeason = Currentseason();

  while($row = mysqli_fetch_assoc($played))
  {
    if($row['season_id'] == $curSeason){ continue;}
    if (GameHasStarted($row))
    {
      $seasonName = SeasonName($row['season_id']);

      if($row['homescore'] > $row['visitorscore'])
      $html .= "<tr><td><b>".utf8entities($row['hometeamname'])."</b>";
      else
      $html .= "<tr><td>".utf8entities($row['hometeamname']);
      	
      	
      if($row['homescore'] < $row['visitorscore'])
      $html .= " - <b>".utf8entities($row['visitorteamname'])."</b></td>";
      else
      $html .= " - ".utf8entities($row['visitorteamname'])."</td>";
      	
      	
      $html .= "<td><a href=\"?view=gameplay&amp;game=" .$row['game_id']."\">".$row['homescore']." - " .$row['visitorscore']. "</a></td>";

      $html .= "<td>".utf8entities(U_($seasonName)).": <a href=\"?view=poolstatus&amp;pool=" .$row['pool_id']. "\">".utf8entities(U_($row['name']))."</a></td></tr>";
    }
  }

  $html .= "</table>";
}
if ($_SESSION['uid'] != 'anonymous') {
  $html .= "<div style='float:left;'><hr/><a href='?view=user/addmedialink&amp;team=$teamId'>"._("Add media")."</a></div>";
}
showPage($title, $html);

?>
