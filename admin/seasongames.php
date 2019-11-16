<?php
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/timetable.functions.php';
include_once 'lib/yui.functions.php';

$LAYOUT_ID = SEASONGAMES;

$html="";
$season = $_GET["season"];
$series = SeasonSeries($season);
$series_id = CurrentSeries($season);
$seasoninfo = SeasonInfo($season);

$title = utf8entities(SeasonName($season)).": "._("Games");

if ($series_id<=0) {
  showPage($title, "<p>"._("No divisions defined. Define at least one division first.")."</p>");
  die;
}

$group = "all";

if(!empty($_GET["group"])) {
  $group  = $_GET["group"];
}

$_SESSION['hide_played_pools'] = !empty($_SESSION['hide_played_pools']) ? $_SESSION['hide_played_pools'] : 0;
$_SESSION['hide_played_games'] = !empty($_SESSION['hide_played_games']) ? $_SESSION['hide_played_games'] : 0;

$showpool = null;
if (!empty($_GET['pool'])) {
  $showpool = $_GET['pool'];
}

if(!empty($_GET["v"])) {
  $visibility = $_GET["v"];

  if($visibility=="pool"){
      $_SESSION['hide_played_pools'] = $_SESSION['hide_played_pools'] ? 0 : 1;
  }elseif($visibility=="game"){
      $_SESSION['hide_played_games'] = $_SESSION['hide_played_games'] ? 0 : 1;
  }
}

if (!empty($_GET["massinput"])) {
  $mass = true;
  $_SESSION['massinput'] = true;
} else {
  $mass = false;
  $_SESSION['massinput'] = false;
}

$feedback = "<p>...</p>";
//process itself on submit
if(!empty($_POST['remove_x'])){
  $id = $_POST['hiddenDeleteId'];
  $ok = true;

  //run some test to for safe deletion
  $goals = GameAllGoals($id);
  if(mysqli_num_rows($goals)){
    $html .= "<p class='warning'>"._("Game has")." ".mysqli_num_rows($goals)." "._("goals").". "._("Goals must be removed before removing the team").".</p>";
    $ok = false;
  }
  if($ok){
    DeleteGame($id);
  }
} elseif (!empty($_POST['save'])) {
  $feedback = GameProcessMassInput($_POST);
}

//common page
pageTopHeadOpen($title);
$html .= yuiLoad(array("utilities"));
?>
<script type="text/javascript">
<!--
function setId(id)
  {
  var input = document.getElementById("hiddenDeleteId");
  input.value = id;
  }
function ChgName(index) {
  YAHOO.util.Dom.get('gamenameEdited' + index).value = 'yes';
  YAHOO.util.Dom.get("save").disabled = false;
}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

function seasongameslink($season, $series, $group, $switchvisible, $mass, $showpool=null) {
  $ret = "?view=admin/seasongames&season=$season" 
    . ($series?"&series=$series":"")
    . "&group=" . utf8entities($group)
    . ($switchvisible?"&v=$switchvisible":"") 
    . ($mass?"&massinput=true":"")
    . ($showpool?"&pool=$showpool":"");
  return $ret;
}

$tab = 0;
foreach($series as $row){
  $menutabs[U_($row['name'])]=seasongameslink($season, $row['series_id'], $group, null, $mass, null);
}
$menutabs[_("...")]="?view=admin/seasonseries&season=".$season;
pageMenu($menutabs, seasongameslink($season, $series_id, $group, null, $mass, $showpool));

$html .= "<table width='100%'><tr><td>";
if (!$showpool) {
  if ($_SESSION['hide_played_pools']) {
    $html .= "<a href='" . seasongameslink($season, $series_id, $group, "pool", $mass, $showpool) . "' tabindex='" .
         ++$tab . "'>" . _("Show played pools") . "</a> ";
  } else {
    $html .= "<a href='" . seasongameslink($season, $series_id, $group, "pool", $mass, $showpool) . "' tabindex='" .
         ++$tab . "'>" . _("Hide played pools") . "</a> ";
  }
}
if ($_SESSION['hide_played_games']) {
  $html .= "<a href='" . seasongameslink($season, $series_id, $group, "game", $mass, $showpool) . "' tabindex='" . ++$tab . "'>" .
       _("Show played games") . "</a> ";
} else {
  $html .= "<a href='" . seasongameslink($season, $series_id, $group, "game", $mass, $showpool) . "' tabindex='" . ++$tab . "'>" .
       _("Hide played games") . "</a> ";
}
$html .= "</td><td style='text-align:right;'>";
if ($mass) {
  $html .= "<a class='button' href='" . seasongameslink($season, $series_id, $group, null, false, $showpool) . "' tabindex='" .
       ++$tab . "'>" . _("Just display values") . "</a></td></tr></table>";
} else {
  $html .= "<a class='button' href='" . seasongameslink($season, $series_id, $group, null, true, $showpool) . "' tabindex='" .
       ++$tab . "'>" . _("Mass input") . "</a></td></tr></table>";
}

$html .= "<form method='post' action='?view=admin/seasongames&amp;season=$season&amp;group=$group'>";

$pools = SeriesPools($series_id);

$html .= "<table class='admintable'>\n";

foreach ($pools as $pool) {
  if ($showpool && $showpool != $pool['pool_id'])
    continue;
  
  $poolinfo = PoolInfo($pool['pool_id']);
  if (!$showpool && $_SESSION['hide_played_pools'] && $poolinfo['played']) {
    continue;
  }

  $games = TimetableGames($pool['pool_id'], "pool", "all", "time", $group);

  $html .= "<tr><th colspan='4'>" . utf8entities(U_($pool['name'])) . "</th>";
  $html .= "<th class='right' colspan='3' ><a class='thlink' href='?view=user/pdfscoresheet&amp;season=$season&amp;pool=" . $pool['pool_id'] . "'>" . _("Print scoresheets") . "</a></th>";
  $html .= "</tr>";

  while ($game = mysqli_fetch_assoc($games)) {
    $i = $game['game_id'];

    if (GameHasStarted($game)) {
      if ($_SESSION['hide_played_games']) {
        continue;
      }
      // $html .= "<tr class='tablelowlight'>";
    }

    $html .= "<tr class='admintablerow'>";

    $html .= "<td style='width:15%'>" . ShortDate($game['starttime']) . " " . DefHourFormat($game['time']) . "<br/>";
    $html .= utf8entities($game['placename']) . " " . utf8entities($game['fieldname']) . "</td>";

    if ($game['hometeam']) {
      $html .= "<td  style='width:20%'>" . utf8entities(TeamName($game['hometeam'])) . "</td>";
    }else {
      $html .= "<td class='lowlight'  style='width:20%'>" . utf8entities(U_($game['phometeamname'])) . "</td>";
    }
    $html .= "<td style='width:1%'>-</td>";
    if ($game['visitorteam']) {
      $html .= "<td  style='width:20%'>" . utf8entities(TeamName($game['visitorteam'])) . "</td>";
    }else {
      $html .= "<td class='lowlight'  style='width:20%'>" . utf8entities(U_($game['pvisitorteamname'])) . "</td>";
    }

    // $html .= "<td class='left' style='white-space: nowrap'>".utf8entities(U_($game['seriesname'])).", ". utf8entities(U_($game['poolname']))."</td>";

    // $html .= "<td class='center'><a href='?view=admin/editgame&amp;season=$season&amp;game=".$game['game_id']."'>"._("edit")."</a></td>";
    if ($_SESSION['massinput']) {
      $html .= "<td colspan='2'><input type='hidden' id='scoreId" . $i . "' name='scoreId[]' value='$i'/>
          <input type='text' size='3' maxlength='4' style='width:5ex' value='" . (is_null($game['homescore'])?"":intval($game['homescore'])) . "' id='homescore$i' name='homescore[]' oninput='confirmLeave(this, true, null);' tabindex='".++$tab."'/>
          - <input type='text' size='3' maxlength='5' style='width:5ex'value='" . (is_null($game['visitorscore'])?"":intval($game['visitorscore'])) . "' id='visitorscore$i' name='visitorscore[]' oninput='confirmLeave(this, true, null);' tabindex='".++$tab."'/></td>";
    }else {
      if (GameHasStarted($game)) {
        if ($game['isongoing'])
          $html .= "<td><em>" . intval($game['homescore']) . "</em> - <em>" . intval($game['visitorscore']) . "</em></td>";
        else
          $html .= "<td>" . intval($game['homescore']) . " - " . intval($game['visitorscore']) . "</td>";
      }else {
        $html .= "<td>? - ?</td>";
      }
      if ($game['hometeam'] && $game['visitorteam']) {
        $html .= "<td class='right'><a href='?view=user/addresult&amp;game=" . $game['game_id'] . "'>" . _("Result") . "</a> | ";
        $html .= "<a href='?view=user/addplayerlists&amp;game=" . $game['game_id'] . "'>" . _("Players") . "</a> | ";
        $html .= "<a href='?view=user/addscoresheet&amp;game=" . $game['game_id'] . "'>" . _("Scoresheet") . "</a>";
        if ($seasoninfo['spiritmode'] > 0) {
          $html .= " | <a href='?view=user/addspirit&amp;game=" . $game['game_id'] . "'>" . _("Spirit") . "</a>";
        }
        if (ShowDefenseStats()) {
          $html .= " | <a href='?view=user/adddefensesheet&amp;game=" . $game['game_id'] . "'>" . _("Defensesheet") . "</a>";
        }
        $html .= "</td>";
      }else {
        $html .= "<td ></td>";
      }
    }
    $html .= "<td>";
    $html .= "<a href='?view=admin/editgame&amp;season=$season&amp;game=" . $game['game_id'] . "'><img class='deletebutton' src='images/settings.png' alt='D' title='" . _("edit details") . "'/></a>";

    if (CanDeleteGame($game['game_id'])) {
      $html .= "<input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"setId(" . $game['game_id'] . ");\"/>";
    }
    $html .= "</td>\n";

    $html .= "</tr>\n";
  }
}
$html .= "</table>";

if ($_SESSION['massinput']) {
  $html .= "<input class='button' name='save' type='submit' value='" . _("Save") . "' tabindex='".++$tab."' onclick='confirmLeave(null, false, null);'/>";
}
$html .= $feedback;


//stores id to delete
//if($i>0){
  $html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/>";
  //$html .= "<input disabled='disabled' id='save' class='button' name='save' type='submit' value='"._("Save game names")."'/></p>";
  $html .= "</form>\n";
  $html .= "<hr/>";
  $html .= "<p><a href='?view=admin/reservations&amp;season=$season'>"._("Reservation management")."</a></p>";
//}
echo $html;
contentEnd();
pageEnd();
?>
