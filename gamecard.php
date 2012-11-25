<?php
include_once 'lib/team.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/player.functions.php';
include_once 'lib/game.functions.php';

$html = "";

$teamId1=0;
$teamId2=0;
$sorting="series";

if(iget("team1")){
  $teamId1 = intval(iget("team1"));
}
if(iget("team2")){
  $teamId2 = intval(iget("team2"));
}
if(iget("sort")){
  $sorting = iget("sort");
}
$team1 = TeamInfo($teamId1);
$team2 = TeamInfo($teamId2);

$title = _("Game card").": ".utf8entities($team1['name']) ." vs. ". utf8entities($team2['name']);

//$seasons = TeamPlayedSeasons($team1['name'], $serie);

$nGames=0;
$nT1GoalsMade=0;
$nT1GoalsAgainst=0;
$nT1Wins=0;
$nT1Loses=0;
$nT2GoalsMade=0;
$nT2GoalsAgainst=0;
$nT2Wins=0;
$nT2Loses=0;

//ignore spaces from team name
$t1 = preg_replace('/\s*/m','',$team1['name']);
$t2 = preg_replace('/\s*/m','',$team2['name']);

$games = GetAllPlayedGames($t1,$t2, $team1['type'], $sorting);

while($game = mysql_fetch_assoc($games)){
  if(intval($game['homescore']) || intval($game['visitorscore']))  {
    //ignore spaces from team name
    $t1 = preg_replace('/\s*/m','',$team1['name']);
    $t2 = preg_replace('/\s*/m','',$game['hometeamname']);

    if(strcasecmp($t1, $t2)==0)    {
      if (intval($game['homescore']) > intval($game['visitorscore']))      {
        $nT1Wins++;
        $nT2Loses++;
      }else{
        $nT2Wins++;
        $nT1Loses++;
      }
      $nT1GoalsMade += intval($game['homescore']);
      $nT2GoalsAgainst += intval($game['homescore']);
       
      $nT2GoalsMade += intval($game['visitorscore']);
      $nT1GoalsAgainst += intval($game['visitorscore']);
    }else{
      if (intval($game['homescore']) < intval($game['visitorscore'])){
        $nT1Wins++;
        $nT2Loses++;
      }else{
        $nT2Wins++;
        $nT1Loses++;
      }
       
      $nT1GoalsMade += intval($game['visitorscore']);
      $nT2GoalsAgainst += intval($game['visitorscore']);
       
      $nT2GoalsMade += intval($game['homescore']);
      $nT1GoalsAgainst += intval($game['homescore']);
    }

    $nGames++;
  }
}

$html .= "<h2>". utf8entities($team1['name']) ." vs. ". utf8entities($team2['name']) ."</h2>\n";

$html .= "<table border='1' width='100%'><tr>\n
	<th>"._("Team")."</th><th>"._("Games")."</th><th>"._("Wins")."</th><th>"._("Losses")."</th><th>"._("Win-%")."</th><th>"._("Scored")."</th>
	<th>"._("Scored")."/"._("game")."</th><th>"._("Let scores")."</th><th>"._("Let scores")."/"._("game")."</th><th>"._("Goal difference")."</th>
	</tr>\n";

$html .= "<tr>
	 <td><a href='?view=teamcard&amp;team=$teamId1'>". utf8entities($team1['name']) ."</a></td>
	 <td>$nGames</td>
	 <td>$nT1Wins</td>
	 <td>$nT1Loses</td>
	 <td>". number_format((SafeDivide($nT1Wins,$nGames)*100),1) ." %</td>
	 <td>$nT1GoalsMade</td>
	 <td>". number_format(SafeDivide($nT1GoalsMade,$nGames),1) ."</td>
	 <td>$nT1GoalsAgainst</td>
	 <td>". number_format(SafeDivide($nT1GoalsAgainst,$nGames),1) ."</td>
	 <td>". ($nT1GoalsMade-$nT1GoalsAgainst) ."</td></tr>\n";

$html .= "<tr>
	 <td><a href='?view=teamcard&amp;team=$teamId2'>". utf8entities($team2['name']) ."</a></td>
	 <td>$nGames</td>
	 <td>$nT2Wins</td>
	 <td>$nT2Loses</td>
	 <td>". number_format((SafeDivide($nT2Wins,$nGames)*100),1) ." %</td>
	 <td>$nT2GoalsMade</td>
	 <td>". number_format(SafeDivide($nT2GoalsMade,$nGames),1) ."</td>
	 <td>$nT2GoalsAgainst</td>
	 <td>". number_format(SafeDivide($nT2GoalsAgainst,$nGames),1) ."</td>
	 <td>".($nT2GoalsMade-$nT2GoalsAgainst)."</td></tr>\n";

$html .= "</table>\n";

if($nGames){
  $html .= "<h2>"._("Played")." "._("games")."</h2>\n";
  $html .= "<table border='1' cellspacing='2' width='80%'><tr>\n";

  $viewUrl="?view=gamecard&amp;team1=$teamId1&amp;team2=$teamId2&amp;";

  $html .= "<th><a class='thsort' href='".$viewUrl."sort=team'>"._("Game")."</a></th>";
  $html .= "<th><a class='thsort' href='".$viewUrl."sort=result'>"._("Result")."</a></th>";
  $html .= "<th><a class='thsort' href='".$viewUrl."sort=series'>"._("Division")."</a></th></tr>";

  $points=array(array());
  mysql_data_seek($games,0);

  while($game = mysql_fetch_assoc($games)){
    if(intval($game['homescore']) || intval($game['visitorscore'])){
      $arrayYear = strtok($game['season_id'], ".");
      $arraySeason = strtok(".");
       
      if(intval($game['homescore']) > intval($game['visitorscore'])){
        $html .= "<tr><td><b>".utf8entities($game['hometeamname'])."</b>";
      }else{
        $html .= "<tr><td>".utf8entities($game['hometeamname']);
      }

      if(intval($game['homescore']) < intval($game['visitorscore'])){
        $html .= " - <b>".utf8entities($game['visitorteamname'])."</b></td>";
      }else{
        $html .= " - ".utf8entities($game['visitorteamname'])."</td>";
      }
      $html .= "<td><a href='?view=gameplay&amp;Game=".$game['game_id']."'>".$game['homescore']." - ".$game['visitorscore']."</a></td>";

      $html .= "<td>".utf8entities(U_($game['seasonname'])).": <a href='?view=poolstatus&amp;pool=".$game['pool_id']."'>".utf8entities($game['name'])."</a></td></tr>";
       
      $scores = GameScoreBoard($game['game_id']);
      $i=0;
       
      while($row = mysql_fetch_assoc($scores)){
        $bFound=false;
        for ($i=0; ($i < 200) && !empty($points[$i][0]); $i++){
          //ignore spaces from team name
          $t1 = preg_replace('/\s*/m','',$row['teamname']);
          $t2 = preg_replace('/\s*/m','',$points[$i][2]);
          if (($points[$i][0] == $row['profile_id']) && (strcasecmp($t1, $t2)==0)){
            $points[$i][3]++;
            $points[$i][4]+= intval($row['fedin']);
            $points[$i][5]+= intval($row['done']);
            $points[$i][6] = $points[$i][4]+$points[$i][5];
            $bFound=true;
          }
        }
         
        if(!$bFound && $i<200){
          $points[$i][0] = $row['profile_id'];
          $points[$i][1] = $row['firstname'] ." ". $row['lastname'];
          $points[$i][2] = $row['teamname'];
          $points[$i][3] = 1;
          $points[$i][4] = intval($row['fedin']);
          $points[$i][5] = intval($row['done']);
          $points[$i][6] = $points[$i][4]+$points[$i][5];
          $points[$i][7] = $row['player_id'];
        }
      }
    }
  }
  $html .= "</table>\n";

  $html .= "<h2>"._("Scoreboard")."</h2>\n";
  $html .= "<table border='1' width='80%'><tr><th>#</th>";

  $sorted = false;

  if($sorting == "pname"){
    $html .= "<th><b>"._("Player")."</b></th>";
    usort($points, create_function('$b,$a','return strcmp($b[1],$a[1]);'));
    $sorted = true;
  }else{
    $html .= "<th><a class='thsort' href='".$viewUrl."sort=pname'>"._("Player")."</a></th>";
  }

  if($sorting == "pteam"){
    $html .= "<th><b>"._("Team")."</b></th>";
    usort($points, create_function('$b,$a','return strcmp($b[2],$a[2]);'));
    $sorted = true;
  }else{
    $html .= "<th><a class='thsort' href='".$viewUrl."sort=pteam'>"._("Team")."</a></th>";
  }

  if($sorting == "pgames"){
    $html .= "<th><b>"._("Games")."</b></th>";
    usort($points, create_function('$a,$b','return $a[3]==$b[3]?0:($a[3]>$b[3]?-1:1);'));
    $sorted = true;
  }else{
    $html .= "<th><a class='thsort' href='".$viewUrl."sort=pgames'>"._("Games")."</a></th>";
  }

  if($sorting == "ppasses"){
    $html .= "<th><b>"._("Assists")."</b></th>";
    usort($points, create_function('$a,$b','return $a[4]==$b[4]?0:($a[4]>$b[4]?-1:1);'));
    $sorted = true;
  }else{
    $html .= "<th><a class='thsort' href='".$viewUrl."sort=ppasses'>"._("Assists")."</a></th>";
  }

  if($sorting == "pgoals"){
    $html .= "<th><b>"._("Goals")."</b></th>";
    usort($points, create_function('$a,$b','return $a[5]==$b[5]?0:($a[5]>$b[5]?-1:1);'));
    $sorted = true;
  }else{
    $html .= "<th><a class='thsort' href='".$viewUrl."sort=pgoals'>"._("Goals")."</a></th>";
  }
   
  if(($sorting == "ptotal")||(!$sorted)){
    $html .= "<th><b>Yht.</b></th></tr>\n";
    usort($points, create_function('$a,$b','return $a[6]==$b[6]?0:($a[6]>$b[6]?-1:1);'));
  }else{
    $html .= "<th><a class='thsort' href='".$viewUrl."sort=ptotal'>"._("Tot.")."</a></th></tr>\n";
  }
   

  for ($i=0; $i < 200 && !empty($points[$i][0]); $i++){
    $html .= "<tr> <td>".($i+1)."</td>";
     
    if($sorting == "pname"){
      $html .= "<td class='highlight'><a href='?view=playercard&amp;player=".$points[$i][7]."'>".utf8entities($points[$i][1]) ."</a></td>";
    }else{
      $html .= "<td><a href='?view=playercard&amp;player=".$points[$i][7]."'>".utf8entities($points[$i][1]) ."</a></td>";
    }
     
    if($sorting == "pteam"){
      $html .= "<td class='highlight'>".utf8entities($points[$i][2]) ."</td>";
    }else{
      $html .= "<td>".utf8entities($points[$i][2]) ."</td>";
    }
    if($sorting == "pgames"){
      $html .= "<td class='highlight cntr'>".$points[$i][3]."</td>";
    }else{
      $html .= "<td class='cntr'>".$points[$i][3]."</td>";
    }
    if($sorting == "ppasses"){
      $html .= "<td class='highlight cntr'>".$points[$i][4]."</td>";
    }else{
      $html .= "<td class='cntr'>".$points[$i][4]."</td>";
    }
    if($sorting == "pgoals"){
      $html .= "<td class='highlight cntr'>".$points[$i][5]."</td>";
    }else{
      $html .= "<td class='cntr'>".$points[$i][5]."</td>";
    }
    if(($sorting == "ptotal")||(!$sorted)) {
      $html .= "<td class='highlight cntr'>".$points[$i][6]."</td>";
    }else{
      $html .= "<td class='cntr'>".$points[$i][6]."</td>";
    }
    $html .= "</tr>";
  }
  $html .= "</table>\n";
}
showPage($title, $html);

?>
