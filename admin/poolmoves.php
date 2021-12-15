<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
$LAYOUT_ID = POOLMOVES;

$backurl = utf8entities($_SERVER['HTTP_REFERER']);
$seriesId = 0;
if (!empty($_GET["pool"]))
  $poolId = intval($_GET["pool"]);

if (!empty($_GET["series"]))
  $seriesId = intval($_GET["series"]);

if (!empty($_GET["season"]))
  $season = $_GET["season"];

$title = _("Continuing pool");

//common page
pageTopHeadOpen($title);
include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "slider", "colorpicker", "datasource", "autocomplete"));
?>
<script type="text/javascript">
  function setId(id1, id2) {
    var input = document.getElementById("hiddenDeleteId");
    input.value = id1 + ":" + id2;
  }

  function setId2(ids) {
    var input = document.getElementById("hiddenDeleteId");
    input.value = ids;
  }

  function toggleField(checkbox, fieldid) {
    var input = document.getElementById(fieldid);
    input.disabled = !checkbox.checked;
  }

  function checkMove(frompool, infield, outfield, pteamname) {
    var frompool = document.getElementById(frompool);
    var input = document.getElementById(infield);
    var output = document.getElementById(outfield);
    var pteamname = document.getElementById(pteamname);

    if (input.value.length > 0) {
      output.disabled = false;
      pteamname.disabled = false;
    } else {
      output.disabled = true;
      pteamname.disabled = true;
    }
    pteamname.value = frompool[frompool.selectedIndex].innerHTML + " " + input.value;
  }

  function checkMove2(frompool, infield, pteamname) {
    var frompool = document.getElementById(frompool);
    var input = document.getElementById(infield);
    var pteamname = document.getElementById(pteamname);

    if (input.value.length > 0) {
      pteamname.disabled = false;
    } else {
      pteamname.disabled = true;
    }
    pteamname.value = frompool[frompool.selectedIndex].innerHTML + " " + input.value;
  }
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
$poolinfo = PoolInfo($poolId);
$basepool = 0;
$err = "";
//process itself on submit
if (!empty($_POST['add'])) {
  $backurl = utf8entities($_POST['backurl']);

  //series pool
  if ($poolinfo['type'] == 1) {
    $total_teams = 10;
    if ($poolinfo['teams'] > 0)
      $total_teams = $poolinfo['teams'];

    for ($i = 0; $i < $total_teams; $i++) {
      if (isset($_POST["frompool$i"]) && isset($_POST["movefrom$i"]) && isset($_POST["moveto$i"])) {
        $frompool = intval($_POST["frompool$i"]);
        $movefrom = intval($_POST["movefrom$i"]);
        $moveto = intval($_POST["moveto$i"]);
        if (!empty($_POST["pteamname$i"])) {
          $pteamname = $_POST["pteamname$i"];
        } else {
          $err .= "<p class='warning'>" . _("No scheduling name given") . ".</p>\n";
        }
        if (PoolMoveExist($frompool, $movefrom)) {
          $err .= "<p class='warning'>" . _("Transfer already exists") . ".</p>\n";
        }

        if (empty($err)) {
          PoolAddMove($frompool, $poolId, $movefrom, $moveto, $pteamname);
        }
      }
    }
  } else {
    //playoff pool
    $total_teams = 8;
    if ($poolinfo['teams'] > 0)
      $total_teams = $poolinfo['teams'];

    for ($i = 0; $i < $total_teams; $i++) {

      if (isset($_POST["frompool$i"]) && !empty($_POST["movefrom$i"])) {
        $frompool = intval($_POST["frompool$i"]);
        $movefrom = intval($_POST["movefrom$i"]);
        $moves = PoolMovingsToPool($poolId);
        $moveto = count($moves) + 1;

        if (!empty($_POST["pteamname$i"])) {
          $pteamname = $_POST["pteamname$i"];
          //$pteamname .= " ($moveto)";
        } else {
          $err .= "<p class='warning'>" . _("No scheduling name given") . ".</p>\n";
        }
        if (PoolMoveExist($frompool, $movefrom)) {
          $err .= "<p class='warning'>" . _("Transfer already exists") . ".</p>\n";
        }

        if (empty($err)) {
          PoolAddMove($frompool, $poolId, $movefrom, $moveto, $pteamname);
        }
      }
    }
  }
} else if (!empty($_POST['remove_x'])) {
  $backurl = utf8entities($_POST['backurl']);

  if ($poolinfo['type'] == 1) {
    $move = preg_split('/:/', $_POST['hiddenDeleteId']);
    if (PoolIsMoved($move[0], $move[1])) {
      $err .= "<p class='warning'>" . _("Team has already moved.") . "</p>\n";
    } else {
      PoolDeleteMove($move[0], $move[1]);
    }
  } else {
    $moves = preg_split('/:/', $_POST['hiddenDeleteId']);

    foreach ($moves as $m) {
      $move = preg_split('/,/', $m);
      if (PoolIsMoved($move[0], $move[1])) {
        $err .= "<p class='warning'>" . _("Team has already moved.") . "</p>\n";
      } else {
        PoolDeleteMove($move[0], $move[1]);
      }
    }
  }
}
echo "<form method='post' action='?view=admin/poolmoves&amp;series=$seriesId&amp;pool=$poolId&amp;season=$season'>";

echo $err;

echo "<h1>" . utf8entities(U_(PoolSeriesName($poolId)) . ", " . U_(PoolName($poolId))) . "</h1>\n";


$poolinfo = PoolInfo($poolId);
$pools = SeriesPools($seriesId);

//round robin or swissdrawn pool
if ($poolinfo['type'] == 1 || $poolinfo['type'] == 3) {

  echo "<table border='0' width='500'><tr>
		<th>" . _("From pool") . "</th>
		<th>" . _("From position") . "</th>
		<th>" . _("To pool") . "</th>
		<th>" . _("To position") . "</th>
		<th>" . _("Move games") . "</th>
		<th>" . _("Name in Schedule") . "</th>
		<th>" . _("Delete") . "</th></tr>";

  $moves = PoolMovingsToPool($poolId);

  foreach ($moves as $row) {
    echo "<tr>";
    echo "<td>" . utf8entities($row['name']) . "</td>";
    echo "<td class='center'>" . intval($row['fromplacing']) . "</td>";
    echo "<td>" . utf8entities(PoolName($poolId)) . "</td>";
    echo "<td class='center'>" . intval($row['torank']) . "</td>";
    if (intval($poolinfo['mvgames']) == 0)
      echo "<td>" . _("all") . "</td>";
    else if (intval($poolinfo['mvgames']) == 1)
      echo "<td>" . _("nothing") . "</td>";
    else if (intval($poolinfo['mvgames']) == 2)
      echo "<td>" . _("mutual") . "</td>";
    echo "<td>" . utf8entities(U_($row['sname'])) . "</td>";
    echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"setId(" . $row['frompool'] . "," . $row['fromplacing'] . ");\"/></td>";
    echo "</tr>\n";
  }
  echo "</table>";
  echo "<hr/>\n";
  echo "<h2>" . _("Make transfer rule") . ":</h2>\n";

  echo "<table>";
  echo "<tr>
		<th>" . _("From pool") . "</th>
		<th>" . _("From position") . "</th>
		<th>" . _("To position") . "</th>	
		<th>" . _("Name in Schedule") . "</th>
		</tr>";

  $total_teams = 10;
  if ($poolinfo['teams'] > 0)
    $total_teams = $poolinfo['teams'];

  for ($i = 0; $i < $total_teams; $i++) {
    echo "<tr>\n";
    echo "<td><select class='dropdown' id='frompool$i' name='frompool$i' onchange=\"checkMove('frompool$i','movefrom$i','moveto$i','pteamname$i');\">";
    foreach ($pools as $pool) {
      if ($pool['pool_id'] != $poolId) {
        echo "<option class='dropdown' value='" . utf8entities($pool['pool_id']) . "'>" . utf8entities(U_($pool['name'])) . "</option>";
      }
    }
    echo "</select></td>\n";
    echo "<td><input class='input' id='movefrom$i' name='movefrom$i' maxlength='3' size='3' value='' onkeyup=\"checkMove('frompool$i','movefrom$i','moveto$i','pteamname$i');\"/></td>\n";
    echo "<td><input class='input' id='moveto$i' name='moveto$i' disabled='disabled' maxlength='3' size='3' value='" . ($i + 1) . "'/></td>\n";
    //echo "<td><input class='input' id='pteamname$i' name='pteamname$i' size='50' maxlength='100' value=''/>\n";
    echo "<td>" . TranslatedField("pteamname$i", "");

    echo TranslationScript("pteamname$i");
    echo "</td>";
    echo "</tr>\n";
  }
  echo "</table>";

  //playoff or crossmatch pool
} else if ($poolinfo['type'] == 2 || $poolinfo['type'] == 4) {

  $moves = PoolMovingsToPool($poolId);

  echo "<table border='0' width='600'><tr>
		<th>" . _("From pool") . "</th>
		<th>" . _("From pos.") . "</th>
		<th class='right'>" . _("Name in Schedule") . "</th>
		<th style='width:50px'></th>
		<th>" . _("Name in Schedule") . "</th>
		<th>" . _("From pos.") . "</th>
		<th>" . _("From pool") . "</th>
		<th>" . _("Delete") . "</th>
		</tr>";

  mergesort($moves, function ($a, $b) {
    return $a['torank'] == $b['torank'] ? 0 : ($a['torank'] < $b['torank'] ? -1 : 1);
  });
  for ($i = 0; $i < count($moves); $i++) {
    $move = $moves[$i];
    echo "<tr>";
    //echo "<td class='right'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId(".$move['frompool'].",".$move['fromplacing'].");\"/></td>";
    $deleteids = $move['frompool'] . "," . $move['fromplacing'];
    echo "<td >" . utf8entities($move['name']) . "</td>";
    echo "<td class='center'>" . intval($move['fromplacing']) . "</td>";
    echo "<td class='right'><i>" . utf8entities(U_($move['sname'])) . "</i></td>";
    echo "<td class='center'><b>" . _("vs.") . "</b></td>";
    $i++;

    if ($i < count($moves)) {
      $move = $moves[$i];
      $deleteids .= ":" . $move['frompool'] . "," . $move['fromplacing'];
      echo "<td><i>" . utf8entities(U_($move['sname'])) . "</i></td>";
      echo "<td class='center'>" . intval($move['fromplacing']) . "</td>";
      echo "<td>" . utf8entities($move['name']) . "</td>";
    }
    echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"setId2('" . $deleteids . "');\"/></td>";
    echo "</tr>";
  }

  echo "</table>";

  echo "<hr/>\n";
  echo "<h2>" . _("Make transfer rule") . ":</h2>\n";

  echo "<table border='0' cellpadding='3' width='250'><tr>
		<th>" . _("From pool") . "</th>
		<th>" . _("From position") . "</th>
		<th>" . _("Name in Schedule") . "</th>
		</tr>";

  $total_teams = 8;
  if ($poolinfo['teams'] > 0)
    $total_teams = $poolinfo['teams'];

  for ($i = 0; $i < $total_teams; $i++) {

    echo "<tr><td><b>" . _("Pair") . " " . ($i / 2 + 1) . "</b></td></tr>\n";
    echo "<tr>\n";
    echo "<td><select class='dropdown' id='frompool$i' name='frompool$i' onchange=\"checkMove2('frompool$i','movefrom$i','pteamname$i');\">";
    foreach ($pools as $pool) {
      if ($pool['pool_id'] != $poolId) {
        echo "<option class='dropdown' ";
        // added convenience when scheduling
        // TODO: retrieve name or id of most likely pool where moves come from
        if ($pool['name'] == 'Round 5 Swissdraw') {
          echo " selected='selected' ";
        }
        echo "value='" . utf8entities($pool['pool_id']) . "'>" . utf8entities(U_($pool['name'])) . "</option>";
      }
    }
    echo "</select></td>\n";
    echo "<td><input class='input' id='movefrom$i' name='movefrom$i' maxlength='3' size='3' value='' onkeyup=\"checkMove2('frompool$i','movefrom$i','pteamname$i');\"/></td>\n";
    echo "<td>" . TranslatedField("pteamname$i", "");
    echo TranslationScript("pteamname$i");
    echo "</td>";
    echo "</tr>\n";
    $i++;
    echo "<tr>\n";
    echo "<td><select class='dropdown' id='frompool$i' name='frompool$i' onchange=\"checkMove2('frompool$i','movefrom$i','pteamname$i');\">";
    foreach ($pools as $pool) {
      if ($pool['pool_id'] != $poolId) {
        echo "<option class='dropdown' ";
        // added convenience when scheduling
        // TODO: retrieve name or id of most likely pool where moves come from
        if ($pool['name'] == 'Round 5 Swissdraw') {
          echo " selected='selected' ";
        }
        echo "value='" . utf8entities($pool['pool_id']) . "'>" . utf8entities(U_($pool['name'])) . "</option>";
      }
    }
    echo "</select></td>\n";
    echo "<td><input class='input' id='movefrom$i' name='movefrom$i' maxlength='3' size='3' value='' onkeyup=\"checkMove2('frompool$i','movefrom$i','pteamname$i');\"/></td>\n";
    echo "<td>" . TranslatedField("pteamname$i", "");
    echo TranslationScript("pteamname$i");
    echo "</td>";
    echo "</tr>\n";
  }
  echo "</table>";
}

echo "<p><input class='button' name='add' type='submit' value='" . _("Add") . "'/>";
echo "<input type='hidden' name='backurl' value='$backurl'/>";
echo "<input class='button' type='button' name='takaisin'  value='" . _("Return") . "' onclick=\"window.location.href='$backurl'\"/></p>";
echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
echo "</form>\n";
contentEnd();
pageEnd();
?>