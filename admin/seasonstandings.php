<?php
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = SEASONSTANDINGS;

$season = $_GET["season"];
$series_id = CurrentSeries($season);

$title = utf8entities(SeasonName($season)).": "._("Pool standings");

if ($series_id<=0) {
  showPage($title, "<p>"._("No divisions defined. Define at least one division first.")."</p>");
  die;
}

$series = SeasonSeries($season);
$html = "";

$_SESSION['hide_played_pools'] = !empty($_SESSION['hide_played_pools']) ? $_SESSION['hide_played_pools'] : 0;

if(!empty($_GET["v"])) {
  $visibility = $_GET["v"];
  
  if ($visibility == "pool") {
    $_SESSION['hide_played_pools'] = $_SESSION['hide_played_pools'] ? 0 : 1;
  }
}

//process itself on submit
if(!empty($_POST['remove_x'])){
  $pool = $_POST['PoolId'];
  $team = $_POST['TeamDeleteId'];
  if(CanDeleteTeamFromPool($pool, $team)){
    PoolDeleteTeam($pool, $team);
    $move = PoolGetMoveByTeam($pool, $team);
    if (count($move)) {
      PoolUndoMove($move[0]['frompool'], $move[0]['fromplacing'], $pool);
    }
  }
}

if(!empty($_POST['recalculate'])){
  ResolvePoolStandings($_POST['PoolId']);
}

if (!empty($_POST['editType'])) {
  $editPool = $_POST['PoolId'];
  
  editPoolStandings($_POST['editType'], $editPool, $_POST['startId'], $_POST['editStart'], $_POST['editEnd'], $_POST['seedId'], $_POST['seed'], $_POST['rankId'], $_POST['rank']);
}

if (!empty($_POST['undoFromPlacing'])) {
  $place = -1;
  if ($_POST['undoFromPlacing'] == "from") {
    $moves = PoolMovingsFromPool($_POST['PoolId']);
  } else if ($_POST['undoFromPlacing'] == "to") {
    $moves = PoolMovingsToPool($_POST['PoolId']);
  } else {
    $place = $_POST['undoFromPlacing'];
  }
  
  if ($place == -1) {
    foreach ($moves as $row) {
      if ($row['ismoved']) {
        $team = PoolTeamFromStandings($row['frompool'], $row['fromplacing']);
        if (CanDeleteTeamFromPool($row['topool'], $team['team_id'])) {
          PoolUndoMove($row['frompool'], $row['fromplacing'], $row['topool']);
        }
      }
    }
  } else {
	PoolUndoMove($_POST['PoolId'], $_POST['undoFromPlacing'],$_POST['undoToPool']);
  }
}

if (!empty($_POST['confirmMoves'])) {
  PoolConfirmMoves($_POST['PoolId']);
}

if (!empty($_POST['setVisible'])) {
  SetPoolVisibility($_POST['PoolId'], true);
} else if (!empty($_POST['setInvisible'])) {
  SetPoolVisibility($_POST['PoolId'], false);
} 

//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">

function setAnchor(pool) {
	var form = document.getElementById("theForm");
	form.action = "?view=admin/seasonstandings&season=<?php echo $season?>#P"+pool;
}

function setDeleteId(pool, team){
  var input = document.getElementById("PoolId");
  input.value = pool;
  var input = document.getElementById("TeamDeleteId");
  input.value = team;
  setAnchor(pool);
}

function setPoolId(pool) {
  document.getElementById("PoolId").value = pool;
  setAnchor(pool);
}

function edit(src, prefix, pool){
  var edit = src.value == "<?php echo _("Edit"); ?>";
  var table = document.getElementById("poolstanding_"+pool);
  var displays = table.getElementsByClassName(prefix+"_display");
  var edits = table.getElementsByClassName(prefix+"_edit");
  for (i=0;i<displays.length;i=i+1) {
    YAHOO.util.Dom.setStyle(displays[i], "display", edit?"none":"inline");
    YAHOO.util.Dom.setStyle(edits[i], "display", edit?"inline":"none");
  }
  setAnchor(pool);
}

function setEditId(prefix, pool){
  var input = document.getElementById("PoolId");
  input.value = pool;
  var input = document.getElementById("editType");
  input.value = prefix;
  setAnchor(pool);
}

function setUndoMove(frompool, fromplacing, topool) {
  document.getElementById("PoolId").value = frompool;
  document.getElementById("undoFromPlacing").value = fromplacing;
  document.getElementById("undoToPool").value = topool;
  setAnchor(topool);
}

function setUndoPool(pool, from) {
  document.getElementById("PoolId").value = pool;
  document.getElementById("undoFromPlacing").value = from;
  setAnchor(pool);
}

function setConfirm(pool) {
  document.getElementById("PoolId").value = pool;
  setAnchor(pool);
}

function setCVisible(pool) {
  document.getElementById("PoolId").value = pool;
  setAnchor(pool);
}

</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

foreach($series as $row){
  $menutabs[U_($row['name'])]="?view=admin/seasonstandings&season=".$season."&series=".$row['series_id'];
}
$menutabs[_("...")]="?view=admin/seasonseries&season=".$season;
pageMenu($menutabs,"?view=admin/seasonstandings&season=".$season."&series=".$series_id);

$html .= "<p>";
if($_SESSION['hide_played_pools']){
  $html .= "<a href='?view=admin/seasonstandings&amp;season=$season&amp;v=pool'>"._("Show played pools")."</a> ";
}else{
  $html .= "<a href='?view=admin/seasonstandings&amp;season=$season&amp;v=pool'>"._("Hide played pools")."</a> ";
}
$html .= "</p>";

$html .= "<form method='post' id='theForm' action='?view=admin/seasonstandings&amp;season=$season'>";
$seasoninfo = SeasonInfo($season);
$pools = SeriesPools($series_id);
if(!count($pools)){
  $html .= "<p>"._("Add pools first")."</p>\n";
}

$html .= "<h2><a name='Tasks' id='TasksHeading'>" . _("Tasks") . "</a></h2>";

$firstTask = false;
$missingresults = "";
foreach ($pools as $spool) {
  $poolId = $spool['pool_id'];
  $poolinfo = PoolInfo($poolId);
  if (!$poolinfo['played'] && PoolCountGames($poolId) > 0) {
    if ($missingresults)
      $missingresults .= ", ";
    else
      $missingresults .= " ";
    $missingresults .= poolLink($poolId, $spool['name']);
  }
  
  if (PoolIsMoveFromPoolsPlayed($poolId) && !PoolIsAllMoved($poolId)) {
    if (!$firstTask) {
      $html .= "<ul>";
      $firstTask = true;
    }
    
    $deplist = "";
    $dependees = PoolDependsOn($poolId);
    foreach ($dependees as $dep) {
      if ($deplist) {
        $deplist .= ", ";
      } else {
        $deplist = " ";
      }
      $deplist .=  poolLink($dep['frompool'], $dep['name']);
    }
    
    $confirmtext = poolLink($poolId, sprintf(_("Confirm moves to pool %s."), PoolName($poolId)));
    
    $html .= "<li>" . sprintf(_("Check standings of %s. Then: %s"), $deplist, $confirmtext) . "</li>\n"; 
  }
}
if ($firstTask) {
  $html .= "</ul>\n";
} else if ($missingresults) {
  $html .= "<p>" . _("Games results missing for") . $missingresults . "</p>";  
} else {
  $html .= "<p>" . _("Division completed.") ."</p>";
}

$teamNum = 0;
$poolNum = 0;
foreach ($pools as $spool) {
  $poolId = $spool['pool_id'];
  $start = $teamNum;
  $poolinfo = PoolInfo($poolId);
  
  if ($_SESSION['hide_played_pools'] && $poolinfo['played']) {
    continue;
  }
  
  $standings = PoolTeams($poolId, "rank");
  
  if ($poolNum>0)
    $html .= "<div class='right pagemenu_container'><a href='#Tasks'>" . _("Go to top") . "</a></div>\n";
  $html .= "<h2><a name='P" . $poolId . "'>" . utf8entities(U_($poolinfo['name'])) . "</a>
    <a href='?view=admin/addseasonpools&amp;pool=$poolId'><img class='button' src='images/settings.png' alt='E' title='"._("edit pool")."'/></a></h2>";
  
  $style = "class='admintable'";
  
  if ($poolinfo['played']) {
    $style = "class='playedpool admintable'";
  }
  
  if ($poolinfo['type'] == 3) { // Swissdraw
    $getHeading = 'swissHeading';
    $getRow = 'swissRow';
    $columns = 9;
  } else {
    // regular pool or playoff
    $getHeading = 'regularHeading';
    $getRow = 'regularRow';
    $columns = 10;
  }
  $html .= "<table $style border='0' width='100%' id='poolstanding_" . $poolId . "'>\n";
  $html .= $getHeading($poolId, $poolinfo, count($standings) > 0);
  
  if (count($standings)) {
    foreach ($standings as $row) {
      $html .= $getRow($poolId, $poolinfo, $row, $teamNum);
      $teamNum++;
    }
    $html .= "<tr><th></th><th>";
    if (count(PoolTeams($poolId))) {
      $html .= "<input class='button' type='submit' name='recalculate' value='" . _("Reset") . "' onclick='setPoolId(" . $poolId . ");'/>";
    }
    $html .= "</th>";
    for ($i = 0; $i < $columns - 2; ++$i) {
      $html .= "<th></th>";
    }
    $html .= "</tr>\n";
  } else {
    $html .= "<tr>";
    for ($i=0; $i< $columns-1; ++$i)
      $html .= "<td class='center'>-</td>";
    $html .= "<td></td></tr>\n";
  }
  $html .= "</table>\n";
  $html .= "<input type='hidden' id='startId" . $poolNum . "' name='startId[]' value='" . ($poolId) . "'/>\n";
  $html .= "<input type='hidden' id='editStart" . $poolId . "' name='editStart[]' value='" . ($start) . "'/>\n";
  $html .= "<input type='hidden' id='editEnd" . $poolId . "' name='editEnd[]' value='" . ($teamNum - 1) . "'/>\n";

  if ($poolinfo['played']) {
    $html .= "<p>" . _("Pool is completed:");
  } else {
    $html .= "<p>" . _("Pool games not completed:");
  }
  $html .=" <a href='?view=admin/seasongames&season=".$season."&series=".$series_id."&pool=". $poolId . "'>". _("Games") ."</a></p>\n";

  $fromMoves = PoolMovingsFromPool($poolId);
  $toMoves = PoolMovingsToPool($poolId);
  
  $html .= "<table style='width:100%'><tr><td style='width:50%; vertical-align:top;'>\n";
  
  if (count($toMoves)) {
    $html .= moveTable($toMoves, "to", $poolId, $poolinfo, $season, $series_id);
  }
  
  $html .= "</td><td style='width:50%; vertical-align:top;'>\n";
  
  if (count($fromMoves)) {
    $html .= moveTable($fromMoves, "from", $poolId, $poolinfo, $season, $series_id);
  }
  
  $html .= "</td></tr></table>\n";
 
  ++$poolNum;
}

$html .= "<p>";
$html .= "<input type='hidden' id='PoolId' name='PoolId'/>\n";
$html .= "<input type='hidden' id='TeamDeleteId' name='TeamDeleteId'/>\n";
$html .= "<input type='hidden' id='editType' name='editType'/>\n";

$html .= "<input type='hidden' id='undoFromPlacing' name='undoFromPlacing'/>\n";
$html .= "<input type='hidden' id='undoToPool' name='undoToPool'/>\n";

$html .= "</p>";
$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();

function poolLink($id, $name) {
  return "<a href='#P". intval($id) . "'>". utf8entities($name) ."</a>";
}

function swissHeading($poolId, $poolinfo, $editbuttons) {
  $html = "";
  $html .= "<tr><th>" . _("Seed") . "&nbsp;" . ($editbuttons?editButton("seed", $poolId):"") . "</th>
            <th>" . _("Pos.") . "&nbsp;" . ($editbuttons?editButton("rank", $poolId):"") . "</th>
            <th>" . _("Team") . "</th>";
  $html .= "<th class='center'>" . _("Games") . "</th>";
  $html .= "<th class='center'>" . _("Victory Points") . "</th>";
  $html .= "<th class='center'>" . _("Opponent VPs") . "</th>";
  $html .= "<th class='center'>" . _("Margin") . "</th>";
  $html .= "<th class='center'>" . _("Goals") . "</th>";
  $html .= "<th></th></tr>";
  return $html;
}

function swissRow($poolId, $poolinfo, $row, $teamNum) {
  $html = "";
  $vp = TeamVictoryPointsByPool($poolId, $row['team_id']);
  
  $html .= "<tr>";
  $html .= "<td>" . editField("seed", $teamNum, $row['team_id'], intval($row['Rank'])) . "</td>";
  $html .= "<td>" . editField("rank", $teamNum, $row['team_id'], intval($row['activerank'])) . "</td>";
  $html .= "<td>" . utf8entities($row['name']) . "</td>";
  
  $html .= "<td class='center'>" . intval($vp['games']) . "</td>";
  $html .= "<td class='center'>" . intval($vp['victorypoints']) . "</td>";
  $html .= "<td class='center'>" . intval($vp['oppvp']) . "</td>";
  $html .= "<td class='center'>" . intval($vp['margin']) . "</td>";
  $html .= "<td class='center'>" . intval($vp['score']) . "</td>";
  if (CanDeleteTeamFromPool($poolId, $row['team_id'])) {
    $html .= "<td class='center' style='width:20px;'>
              <input class='deletebutton' type='image' src='images/remove.png' alt='X' title='"._("delete team from pool") ."' name='remove' 
               value='" . _("X") . "' onclick=\"setDeleteId(" . $poolId . "," . $row['team_id'] . ");\"/></td>";
  } else {
    $html .= "<td></td>";
  }
  $html .= "</tr>\n";
  return $html;
}

function regularHeading($poolId, $poolinfo, $editbuttons) {
  $html = "";
  $html .= "<tr><th>"._("Seed")."&nbsp;". ($editbuttons?editButton("seed", $poolId):"") ."</th>
            <th>"._("Pos.")."&nbsp;". ($editbuttons?editButton("rank", $poolId):"") ."</th>
            <th>"._("Team")."</th>";
  $html .= "<th class='center'>" . _("Games") . "</th>";
  $html .= "<th class='center'>" . _("Wins") . "</th>";
  if ($poolinfo['drawsallowed'])
    $html .= "<th class='center'>" . _("Draws") . "</th>";
  $html .= "<th class='center'>" . _("Losses") . "</th>";
  $html .= "<th class='center'>" . _("Goals for") . "</th>";
  $html .= "<th class='center'>" . _("against") . "</th>";
  $html .= "<th class='center'>" . _("diff.") . "</th>";
  $html .= "<th></th></tr>";
  return $html;
}

function regularRow($poolId, $poolinfo, $row, $teamNum) {
  $html = "";
  $stats = TeamStatsByPool($poolId, $row['team_id']);
  $points = TeamPointsByPool($poolId, $row['team_id']);
  
  $html .= "<tr>";
  $html .= "<td>" . editField("seed", $teamNum, $row['team_id'], intval($row['Rank'])) . "</td>";
  $html .= "<td>" . editField("rank", $teamNum, $row['team_id'], intval($row['activerank'])) . "</td>";
  $html .= "<td>" . utf8entities($row['name']) . "</td>";
  $html .= "<td class='center'>" . intval($stats['games']) . "</td>";
  $html .= "<td class='center'>" . intval($stats['wins']) . "</td>";
  if ($poolinfo['drawsallowed']) {
    $html .= "<td class='center'>" . intval($stats['draws']) . "</td>";
  }
  $html .= "<td class='center'>" . intval($stats['losses']) . "</td>";
  $html .= "<td class='center'>" . intval($points['scores']) . "</td>";
  $html .= "<td class='center'>" . intval($points['against']) . "</td>";
  $html .= "<td class='center'>" . ((intval($points['scores']) - intval($points['against']))) . "</td>";
  if (CanDeleteTeamFromPool($poolId, $row['team_id'])) {
    $html .= "<td class='center' style='width:20px;'>
              <input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' title='"._("delete team from pool") ."'
               value='"._("X")."' onclick=\"setDeleteId(".$poolId .",". $row['team_id'].");\"/></td>";
  } else {
    $html .= "<td></td>";
  }
  $html .= "</tr>\n";
  return $html;
}

function moveTable($moves, $type, $poolId, $poolinfo, $seasonId, $seriesId) {
  $html = "<table class='admintable' style='width:100%; margin-left:0pt'>";
  if ($type == "to") {
    $html .= "<tr><th colspan='5'>" . _("Moves to") ." ". $poolinfo['name'] . "</th></tr>\n";
    $html .= "<tr><th>" . _("From pool") . "</th><th>". _("Pos") ."</th><th>" . _("To") . "</th>";
  } else {
    $html .= "<tr><th colspan='5'>" . _("Moves from") ." ". $poolinfo['name'] . "</th></tr>\n";
    $html .= "<tr><th>" . _("From") . "</th><th>" . _("To pool") . "</th><th>". _("Pos") ."</th>";
  }
  $html .= "<th>"._("Team")."</th><th></th></tr>\n";

  $undo = false;
  $allMoved = true;
  foreach ($moves as $row) {
    $topoolinfo = PoolInfo($row['topool']);
    if ($row['ismoved']) {
      $html .= "<tr class='highlight'>";
    } else {
      $html .= "<tr>";
    }
    if ($type == "to") {
      $html .= "<td>" . poolLink($row['frompool'], PoolName($row['frompool'])) . "</a></td>";
      $html .= "<td>" . utf8entities($row['fromplacing']) . "</td>";
      $html .= "<td>" . $row['torank'] . "</td>";
    } else {
      $html .= "<td>" . utf8entities($row['fromplacing']) . "</td>";
      $html .= "<td>" . poolLink($row['topool'], PoolName($row['topool'])) . "</td>";
      $html .= "<td>" . $row['torank'] . "</td>";
    }
    $team = PoolTeamFromStandings($row['frompool'], $row['fromplacing'], $topoolinfo['type']!=2);  // do not count the BYE team if we are moving to a playoff pool
    $html .= "<td>" . $team['name'] . "</td>";

    if ($row['ismoved']) {
      $undo = true;
      $html .= undoButton($row['frompool'], $row['fromplacing'], $row['topool']);
    } else {
      $allMoved = false;
      $html .= "<td></td>";
    }
    $html .= "</tr>\n";
  }
  
  $html .= "<tr><th colspan='4'>";
  if ($type == "to") {
    if (PoolIsMoveFromPoolsPlayed($poolId)) {
      if (!PoolIsAllMoved($poolId)) {
        $html .= "<input class='button' type='submit' name='confirmMoves' value='" . _("Confirm moves") .
             "' onclick='setConfirm(" . $poolId . ")'/>&nbsp;";
      } else {
        if ($poolinfo['visible'])
          $html .= "<input class='button' type='submit' name='setInvisible' value='" . _("Hide pool") .
               "' onclick='setCVisible(" . $poolId . ")'/>&nbsp;";
        else
          $html .= "<input class='button' type='submit' name='setVisible' value='" . _("Show pool") .
               "' onclick='setCVisible(" . $poolId . ")'/>&nbsp;";
      }
    }
    $html .= "<a href='?view=admin/serieteams&amp;season=$seasonId&amp;series=". $seriesId ."&amp;pool=". $poolId ."'>". _("Manage moves") ."</a>";
  }
  $html .= "</th>";
  if ($undo)
    $html .= undoPoolButton($poolId, $type == "from");
  else
    $html .= "<th></th>";
  $html .= "</tr></table>\n";
  return $html;
}

function editButton($prefix, $id) {
  $title = ($prefix == "seed")?_("change initial pool ranking"):_("change final pool ranking"); 
  return "<input class='button " . $prefix . "_display' type='image' src='images/settings.png' alt='D' name='" . $prefix .
       "Display' title='".$title."' value='" . _("Edit") . "' onclick='edit(this,\"" . $prefix . "\", " . $id .
       "); return false;'/>
          <input class='button " .
       $prefix . "_edit' style='display:none' type='image' src='images/save.gif' name='" . $prefix . "Save' title='"._("save ranking")."' value='" .
       _("Save") . "' onclick='setEditId(\"" . $prefix . "\", " . $id . ");'/>";
}

function editField($prefix, $teamNum, $id, $value) {
  return "<input type='hidden' id='".$prefix."Id" . $teamNum . "' name='".$prefix."Id[]' value='$id'/>
          <div class='".$prefix."_display'>".$value."</div>
          <div class='".$prefix."_edit' style='display:none'>
            <input class='input' size='3' maxlength='4' id='".$prefix.$teamNum."' name='".$prefix."[]' value='".$value."' /></div>";
}

function editPoolStandings($type, $pool, $startIds, $editStarts, $editEnds, $seedIds, $seeds, $rankIds, $ranks) {
  foreach ($startIds as $key => $value) {
    if ($value == $pool) {
      $start = $editStarts[$key];
      $end = $editEnds[$key];
      break;
    }
  }
  
  if ($type == "seed") {
    foreach ($seedIds as $key => $value) {
      if (intval($key) >= $start && intval($key) <= $end)
        SetTeamPoolRank($value, $pool, $seeds[$key]);
    }
  } else if ($type == "rank") {
    foreach ($rankIds as $key => $value) {
      if (intval($key) >= $start && intval($key) <= $end){
        SetTeamRank($value, $pool, $ranks[$key]);
      }
    }
  }
}

function undoButton($frompool, $fromplacing, $topool) {
  return "<td class='right'><input class='button' type='submit' name='moveUndo' value='" . _("Undo") .
         "' onclick='setUndoMove(".$frompool.", ".$fromplacing.", ".$topool. ")' /></td>";
}

function undoPoolButton($pool, $from){
  return "<th class='right'><input class='button' type='submit' name='poolUndo' value='" . _("Undo all") . "' onclick='setUndoPool(" . $pool . ", ".($from?"\"from\"":"\"to\"").");'/></th>";
}
?>