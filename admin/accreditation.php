<?php
include_once 'lib/accreditation.functions.php';

$LAYOUT_ID = ACCREDITATION;

$title = _("Accreditation");
$html = "";

if (isset($_GET['season'])) {
  $season = $_GET['season'];
} else {
  $season = CurrentSeason();
}

if (isset($_GET['list'])) {
  $view = $_GET['list'];
} else {
  $view = "acc";
}
$url= "?view=admin/accreditation&amp;season=".$season."&amp;list=".$view;

if (isset($_POST['acknowledge'])) {
  foreach ($_POST['acknowledged'] as $playerGame) {
    $playerGameArr = explode("_", $playerGame);
    AcknowledgeUnaccredited($playerGameArr[0], $playerGameArr[1], "accreditation");
  }
}
if (isset($_POST['remacknowledge']) && isset($_POST['deleteAckId'])) {
  $playerGameArr = explode("_", $_POST['deleteAckId']);
  UnAcknowledgeUnaccredited($playerGameArr[0], $playerGameArr[1], "accreditation");
}

if (isset($_POST['accredit']) && isset($_POST['series'])) {
  $accrIds = explode("\n", $_POST['accrIds']);
  foreach($accrIds as $accrId) {
    AccreditPlayerByAccrId(trim($accrId), $_POST['series'], "accreditation");
  }
}

$unAccredited = SeasonUnaccredited($season);

//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
<!--
function setId(id, name) 
	{
	var input = document.getElementById(name);
	input.value = id;
	}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "[<a href='?view=admin/accreditation&amp;season=".$season."&amp;list=acc'>"._("Accreditation")."</a>]";
$html .= "&nbsp;&nbsp;";	
$html .= "[<a href='?view=admin/accreditation&amp;season=".$season."&amp;list=autoacc'>"._("Automatic Accreditation")."</a>]";
$html .= "&nbsp;&nbsp;";
$html .= "[<a href='?view=admin/accreditation&amp;season=".$season."&amp;list=acclog'>"._("Accreditation log")."</a>]";
$html .= "&nbsp;&nbsp;";
$html .= "[<a href='?view=admin/accreditation&amp;season=".$season."&amp;list=accevents'>"._("Accreditation events")."</a>]";
$html .= "&nbsp;&nbsp;";
$html .= "[<a href='?view=admin/accreditation&amp;season=".$season."&amp;list=accId'>"._("Missing IDs")."</a>]";
$html .= "&nbsp;&nbsp;";

echo $html;


if($view=="acc"){
  echo "<p>";
  echo _("Accreditation can be done manually player by player from team roster or automatically against event organizer's external license database.");
  echo "</p>";
  if (is_file('cust/'.CUSTOMIZATIONS.'/mass-accreditation.php')) {
    include_once 'cust/'.CUSTOMIZATIONS.'/mass-accreditation.php';
  }
}

if($view=="autoacc"){
  if (is_file('cust/'.CUSTOMIZATIONS.'/mass-accreditation.php')) {
    include_once 'cust/'.CUSTOMIZATIONS.'/mass-accreditation.php';
  }
}

if($view=="acclog"){
  echo "<h3>"._("Games played without accreditation")."</h3>";
  echo "<form method='post' action='$url'>\n";
  echo "<table class='infotable'><tr><th>"._("Player")."</th><th>"._("Team")."</th><th>"._("Game")."</th><th>"._("Acknowledged")."</th></tr>\n";
  $acknowledged = array();
  
  while ($row = mysqli_fetch_assoc($unAccredited)) {
    if (hasAccredidationRight($row['team'])) {
      if (!$row['acknowledged']) {
        echo "<tr>";
        echo "<td>".utf8entities($row['firstname'])." ".utf8entities($row['lastname'])."</td>";
        echo "<td>".utf8entities($row['teamname'])."</td>";
        echo "<td>".utf8entities(GameName($row))."</td>";
        echo "<td style='text-align:center'><input type='checkbox' name='acknowledged[]' ";
        echo "value='".utf8entities($row['player_id'])."_".$row['game_id']."'/></td></tr>\n";
      } else {
        $acknowledged[] = $row;
      }
    }
  }
  
  echo "</table>";
  echo "<p><input type='submit' name='acknowledge' value='"._("Acknowledge")."'/></p>\n";
  echo "<h3>"._("Acknowledged")."</h3>";
  echo "<table class='infotable'><tr><th>"._("Player")."</th><th>"._("Team")."</th><th>"._("Game")."</th><th>"._("Acknowledged")."</th></tr>\n";
  foreach ($acknowledged as $row) {
    if (hasAccredidationRight($row['team'])) {
      echo "<tr>";
      echo "<td>".utf8entities($row['firstname'])." ".utf8entities($row['lastname'])."</td>";
      echo "<td>".utf8entities($row['teamname'])."</td>";
      echo "<td>".utf8entities(GameName($row))."</td>";
      echo "<td style='text-align:center'><input class='deletebutton' type='image' src='images/remove.png' name='remacknowledge' ";
      echo "value='X' alt='X' onclick='setId(\"".$row['player_id']."_".$row['game_id']."\", \"deleteAckId\");'/>";
      echo "</td></tr>\n";
    }
  }
  echo "</table>";
  echo "<div><input type='hidden' id='deleteAckId' name='deleteAckId'/></div>\n";
  echo "</form>";
}

if($view=="accevents"){
  echo "<h3>"._("Accreditation events")."</h3>";
  echo "<table class='infotable'><tr><th>"._("Event")."</th><th>"._("Time")."</th><th>"._("Player")."</th>";
  echo "<th>"._("Team")."</th><th>"._("Game")."</th><th>"._("Value")."</th>";
  echo "<th>"._("User")."</th><th>"._("Source")."</th></tr>\n";
  $logResult = SeasonAccreditationLog($season);
  while ($row = mysqli_fetch_assoc($logResult)) {
    if (hasAccredidationRight($row['team'])) {
      if ($row['value']) {
        echo "<tr class='posvalue'>";
      } else {
        echo "<tr class='negvalue'>";
      }
      if (!empty($row['game'])) {
        echo "<td>"._("Game acknowledgement")."</td>";
      } else {
        echo "<td>"._("Accreditation")."</td>";
      }
      echo "<td>".DefBirthdayFormat($row['time'])." ".DefHourFormat($row['time'])."</td>";
      echo "<td>".utf8entities($row['firstname'])." ".utf8entities($row['lastname'])."</td>";
      echo "<td>".utf8entities($row['teamname'])."</td>";
      if (!empty($row['game'])) {
        echo "<td>".utf8entities(GameName($row))."</td>";
      } else {
        echo  "<td>&nbsp;</td>";
      }
      if ($row['value']) {
        echo "<td>+</td>";
      } else {
        echo "<td>-</td>";
      }
      if (!empty($row['email'])) {
        echo "<td><a href='mailto:".$row['email']."'>".utf8entities($row['uname'])."</a></td>";
      } else {
        echo "<td>".utf8entities($row['uname'])."</td>";
      }
      echo "<td>".utf8entities($row['source'])."</td>";
      echo "</tr>\n";
    }
  }
  echo "</table>";
}

if($view=="accId"){
  echo "<h3>"._("Players without membership Id")."</h3>";
  $players = SeasonAllPlayers($season);
  echo "<table class='infotable'>";
  foreach($players as $player) {
    $playerinfo = PlayerInfo($player['player_id']);
    if(empty($playerinfo['accreditation_id'])){
      echo "<tr><td>";
      echo utf8entities($playerinfo['seriesname']);
      echo "</td><td>";
      echo utf8entities($playerinfo['teamname']);
      echo "</td><td>";
      echo utf8entities($playerinfo['firstname']." ".$playerinfo['lastname']);
      echo "</td></tr>";
    }  
  }
  echo "</table>";
  
  echo "<h3>"._("Players not accredited")."</h3>";
  $players = SeasonAllPlayers($season);
  echo "<table class='infotable'>";
  foreach($players as $player) {
    $playerinfo = PlayerInfo($player['player_id']);
    if(empty($playerinfo['accredited'])){
      echo "<tr><td>";
      echo utf8entities($playerinfo['seriesname']);
      echo "</td><td>";
      echo utf8entities($playerinfo['teamname']);
      echo "</td><td>";
      echo utf8entities($playerinfo['firstname']." ".$playerinfo['lastname']);
      echo "</td></tr>";
    }  
  }
  echo "</table>";
}

contentEnd();
pageEnd();

?>