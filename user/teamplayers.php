<?php
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/player.functions.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/accreditation.functions.php';

$LAYOUT_ID = TEAMPLAYERS;
$teamId=0;
$gameId=0;
$title = _("Roster");

if(!empty($_GET["Team"]))
$teamId = intval($_GET["Team"]);
$teaminfo = TeamInfo($teamId);

if(!empty($_POST['remove_x'])){
  $id = $_POST['hiddenDeleteId'];
  $games = PlayerSeasonPlayedGames($id , $teaminfo['season']);
  if($games){
    $playerInfo = PlayerInfo($id );
    echo "<div style='width:100%'>
			<p class='warning'><i>". utf8entities($playerInfo['firstname'] ." ". $playerInfo['lastname']) ."</i> "._("can not be removed from the roster").".
			"._("Played in the team in")." $games "._("game")."</p></div>";
  }else{
    RemovePlayer($id);
  }
}elseif(!empty($_POST['add'])) {
  //add new player when accreditation id is known
  if (isset($_POST["firstname0"]) && isset($_POST["lastname0"])
  && (strlen($_POST["firstname0"]) > 0 || strlen($_POST["lastname0"]) > 0 )
  && !empty($_POST["profileId0"])) {

    $num = -1;
    if(isset($_POST["number0"]) && intval($_POST["number0"])>0){
      $num = intval($_POST["number0"]);
    }
    
    AddPlayer($teamId, trim($_POST["firstname0"]), trim($_POST["lastname0"]), $_POST["profileId0"], $num);
    //add new player when accreditation id is NOT known
  }else{
    if(isset($_POST["number0"])){
      $num = intval($_POST["number0"]);
    }else{
      $num = -1;
    }
    $playerid = AddPlayer($teamId, trim($_POST["firstname0"]), trim($_POST["lastname0"]), 0,$num);
  }
  header("location:?view=user/teamplayers&Team=$teamId");
}elseif(!empty($_POST['save'])) {
  for ($i=0; $i<count($_POST['playerEdited']); $i++) {
    if ($_POST['playerEdited'][$i] == "yes") {
      $id=$_POST['playerId'][$i];
      $playerInfo = PlayerInfo($_POST['playerId'][$i]);
      if (isset($_POST["number$id"])) {
        $playerInfo['num'] = $_POST["number$id"];
      }
      if (isset($_POST["firstname$id"]) && strlen($_POST["firstname$id"]) > 0) {
        $playerInfo['firstname'] = $_POST["firstname$id"];
      }
      if (isset($_POST["lastname$id"]) && strlen($_POST["lastname$id"]) > 0) {
        $playerInfo['lastname'] = $_POST["lastname$id"];
      }
      	
      if (isset($_POST["accrId$id"])) {
        if($playerInfo['accreditation_id'] != $_POST["accrId$id"]){
          DeAccreditPlayer($id, "teamplayers");
        }
        $accId = 0;
        if(!empty($_POST["accrId$id"])){
          $accId = $_POST["accrId$id"];
        }
        
        $playerInfo['accreditation_id'] = $accId;
      }
      if (isset($_POST["profileId$id"])) {
        $playerInfo['profile_id'] = $_POST["profileId$id"];
      }

      SetPlayer($_POST['playerId'][$i], $playerInfo['num'], $playerInfo['firstname'], $playerInfo['lastname'], $playerInfo['accreditation_id'], $playerInfo['profile_id']);

      if (hasAccredidationRight($playerInfo['team'])){
        if (isset($_POST["accredits$id"])) {
          AccreditPlayer($id, "teamplayers");
        }else{
          DeAccreditPlayer($id, "teamplayers");
        }
      }
    }
  }
  header("location:?view=user/teamplayers&Team=$teamId");
}elseif(!empty($_POST['copy'])) {
  TeamCopyRoster($_POST["copyroster"], $teamId);
  header("location:?view=user/teamplayers&Team=$teamId");
}

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
?>
<script type="text/javascript">
<!--
function setId(id) 
	{
	var input = document.getElementById("hiddenDeleteId");
	input.value = id;
	}
	
function ChgPlayer(id) 
	{
	YAHOO.util.Dom.get('playerEdited' + id).value = 'yes';
	YAHOO.util.Dom.get("save").disabled = false;
	YAHOO.util.Dom.get("cancel").disabled = false;
	}

function validNumber(field) 
	{
	field.value=field.value.replace(/[^0-9]/g, '')
	}
//-->
</script>
<?php
//if (is_file('cust/'.CUSTOMIZATIONS.'/teamplayers.functions.php')) {
//  include_once 'cust/'.CUSTOMIZATIONS.'/teamplayers.functions.php';
//}else{
  include_once 'cust/default/teamplayers.functions.php';
//}
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

//help
$help = "<p>"._("Add players into team's roster:")."</p>
	<ul>
		<li> "._("Player's already having profile in ultiorganizer: Enter full or part of the player name and press search-button. Select correct player from opening dialog and press confirm-button.")."</li>
		<li> "._("New players: Enter jersey number, first and last name. Press add-button.")."</li>
	</ul>";

onPageHelpAvailable($help);

//content
echo "<h2>". utf8entities($teaminfo['name']) ." (". utf8entities($teaminfo['seriesname']) .")</h2>\n";

echo "<p><a href='?view=user/teamprofile&amp;Team=". $teamId."'>"._("Edit Team profile")."</a> | ";
echo "<a href='?view=user/clubprofile&amp;Team=". $teamId."'>"._("Edit Club profile")."</a></p>";

echo "<form method='post' action='?view=user/teamplayers&amp;Team=".$teamId;
if (!empty($gameId)) echo "&amp;Game=".$gameId;
echo "'>\n";

echo "<table border='0' cellpadding='2' width='100%'>\n";

echo "<tr>";
echo "<th class='center'>"._("#")."</th>";
echo "<th>"._("First name")."</th>";
echo "<th>"._("Last name")."</th>";
echo "<th>"._("Profile")."</th>";
echo "<th class='center'>". _("Accredited") ."</th>";
if(CUSTOMIZATIONS=="slkl"){
  echo "<th>"._("MemberId")."</th>";
  echo "<th>"._("Membership")."</th>";
  echo "<th>"._("License")."</th>";
  echo "<th></th>";
}else{
  echo "<th>"._("Profile Id")."</th>";
 // echo "<th></th>";
  echo "<th>"._("Delete")."</th>";
}

echo "</tr>\n";


$team_players = TeamPlayerList($teamId);

if (mysql_num_rows($team_players)==0 && (hasAccredidationRight($teamId) || hasEditPlayersRight($teamId))) {
  $teams = TeamGetTeamsByName($teaminfo['name']);
  if(count($teams)){
    echo "<p>". _("Copy team roster from:")." ";
    echo "<select class='dropdown' name='copyroster'>\n";
    foreach($teams as $team){
      $oldteaminfo = TeamInfo($team['team_id']);
      if($oldteaminfo['type']==$teaminfo['type'] && $oldteaminfo['season']!=$teaminfo['season']){
  	      echo "<option class='dropdown' value='". $team['team_id'] . "'>". utf8entities($oldteaminfo['seasonname'] ." ". $oldteaminfo['name']) ."</option>";
      }
    }
    echo "</select>\n";
    echo "<input id='copy' class='button' name='copy' type='submit' value='"._("Copy")."'/>";
    echo "</p>\n";
  }  
}

while($player = mysql_fetch_assoc($team_players)){
  $playerInfo = PlayerInfo($player['player_id']);

  echo "<tr>";
  echo "<td class='center'><input class='input' size='3' maxlength='2' onkeypress=\"ChgPlayer(".$player['player_id'].");\" onkeyup=\"validNumber(this);\" name='number". $player['player_id'] ."' id='number". $player['player_id'] ."' value='". $playerInfo['num'] ."'/></td>";
  echo "<td><input class='input' size='20' maxlength='20' onkeypress=\"ChgPlayer(".$player['player_id'].");\" name='firstname". $player['player_id'] ."' id='firstname". $player['player_id'] ."' value='". utf8entities($playerInfo['firstname']) ."'/></td>";
  echo "<td><input class='input' size='20' maxlength='30' onkeypress=\"ChgPlayer(".$player['player_id'].");\" name='lastname". $player['player_id'] ."' id='lastname". $player['player_id'] ."' value='". utf8entities($playerInfo['lastname']) ."'/></td>";
  echo "<td style='white-space: nowrap'>";
  echo "<input type='hidden' id='profileId".$player['player_id']."' name='profileId".$player['player_id']."' value='". $playerInfo['profile_id'] ."'/>\n";
  echo "<input type='hidden' id='accrId".$player['player_id']."' name='accrId".$player['player_id']."' value='".$player['accreditation_id']."'/>\n";
  echo "<a href='?view=user/playerprofile&amp;Player=".$player['player_id']."'>"._("edit")."</a> | ";
  echo "<a href='?view=playercard&amp;Player=".$player['player_id']."'>"._("view")."</a>";
  echo "</td>\n";

  if (!$playerInfo['accredited']){
    echo "<td  class='center attention'>";
  }else{
    echo "<td  class='center'>";
  }
  if (hasAccredidationRight($teamId)) {
    echo "<input type='checkbox' name='accredits". $player['player_id'] ."' onclick=\"ChgPlayer(".$player['player_id'].");\" value='".$player['player_id']."'";
    if ($playerInfo['accredited']) echo " checked='checked'";
    echo "/>\n";
  } else {
    if ($playerInfo['accredited']) echo _("Yes");
    else echo _("No");
  }
  echo "</td>\n";

if(CUSTOMIZATIONS=="slkl"){    
  if(!empty($playerInfo['accreditation_id'])){
      echo "<td class='center' style='white-space: nowrap'>".$playerInfo['accreditation_id']."</td>";
    }else{
      echo "<td class='center attention'><a id='showAccrId". $player['player_id'] ."' onclick=\"ChgPlayer(".$player['player_id'].");\" href='javascript:checkProfileId(\"". $player['player_id'] ."\");'>". _("N/A")."</a></td>\n";
    }

    $query = sprintf("SELECT membership, license, external_type, external_validity FROM uo_license WHERE accreditation_id=%d",(int)$playerInfo['accreditation_id']);
    $row = DBQueryToRow($query);
    if(!empty($row['membership'])){
      echo "<td class='center' style='white-space: nowrap'>".$row['membership']."</td>";
    }else{
      echo "<td class='center'>-</td>";
    }
    
    if(!empty($row['license'])){
     echo "<td style='white-space: nowrap'>".$row['license']."</td>";
     }else{
     echo "<td>-</td>";
     }
    
}else{
    echo "<td class='center'><a id='showAccrId". $player['player_id'] ."' onclick=\"ChgPlayer(".$player['player_id'].");\" href='javascript:checkProfileId(\"". $player['player_id'] ."\");'>".$player['profile_id']." </a></td>\n";
}
  if(CanDeletePlayer($player['player_id'])){
    echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' name='remove' value='X' alt='X' onclick=\"setId(".$player['player_id'].");\"/></td>";
  }
  echo "</tr>\n";
}

if (hasAccredidationRight($teamId) || hasEditPlayersRight($teamId)) {
  echo "<tr>";
  echo "<td class='center'><input class='input' size='3' maxlength='2' name='number0' id='number0'/></td>";
  echo "<td><input class='input' size='20' maxlength='20' name='firstname0' id='firstname0' value=''/></td>";
  echo "<td><input class='input' size='20' maxlength='30' name='lastname0' id='lastname0' value=''/></td>";
  echo "<td colspan='5'>";
  echo "<input class='button' name='search' type='button' onclick='checkProfileId(0);' value='"._("Search")."'/>";
  echo "<input class='button' name='add' id='add' type='submit' value='"._("Add")."'/>";
  echo "<input type='hidden' id='accrId0' name='accrId0' value=''/>\n";
  echo "<input type='hidden' id='profileId0' name='profileId0' value=''/></td>\n";
  echo "</tr>";
}
echo "</table>\n";


echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/>";
if (hasAccredidationRight($teamId) || hasEditPlayersRight($teamId)) {
  echo "<input disabled='disabled' id='save' class='button' name='save' type='submit' value='"._("Save")."'/>";
  echo "<input disabled='disabled' id='cancel' class='button' name='cancel' type='submit' value='"._("Cancel")."'/>";
  $playerArray = TeamPlayerArray($teamId);
  foreach ($playerArray as $playerId => $name) {
    echo "<input type='hidden' id='playerEdited".$playerId."' name='playerEdited[]' value='no'/>\n";
    echo "<input type='hidden' name='playerId[]' value='".$playerId."'/>\n";
  }
}
echo "</p></form>\n";


if(!empty($gameId)) {
  echo "<p><a href='?view=user/addplayerlists&amp;Game=$gameId'>"._("Back to feeding in player numbers")."</a></p>";
}

//echo "<hr/>\n";

//if (is_file('cust/'.CUSTOMIZATIONS.'/teamplayers.inc.php')) {
//  include_once 'cust/'.CUSTOMIZATIONS.'/teamplayers.inc.php';
//} else {
  include_once 'cust/default/teamplayers.inc.php';
//}
echo "<div><a href='?view=user/pdfscoresheet&amp;team=".$teamId."'>"._("Print roster")."</a></div>";
//common end
contentEnd();
pageEnd();

?>
