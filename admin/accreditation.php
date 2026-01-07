<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/accreditation.functions.php';
include_once 'lib/player.functions.php';

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
$url = "?view=admin/accreditation&amp;season=" . $season . "&amp;list=" . $view;

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
  foreach ($accrIds as $accrId) {
    AccreditPlayerByAccrId(trim($accrId), $_POST['series'], "accreditation");
  }
}

$unAccredited = SeasonUnaccredited($season);

//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
  function setId(id, name) {
    var input = document.getElementById(name);
    input.value = id;
  }
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "[<a href='?view=admin/accreditation&amp;season=" . $season . "&amp;list=acc'>" . _("Accreditation") . "</a>]";
$html .= "&nbsp;&nbsp;";
$html .= "[<a href='?view=admin/accreditation&amp;season=" . $season . "&amp;list=autoacc'>" . _("Automatic Accreditation") . "</a>]";
$html .= "&nbsp;&nbsp;";
$html .= "[<a href='?view=admin/accreditation&amp;season=" . $season . "&amp;list=acclog'>" . _("Accreditation log") . "</a>]";
$html .= "&nbsp;&nbsp;";
$html .= "[<a href='?view=admin/accreditation&amp;season=" . $season . "&amp;list=accevents'>" . _("Accreditation events") . "</a>]";
$html .= "&nbsp;&nbsp;";
$html .= "[<a href='?view=admin/accreditation&amp;season=" . $season . "&amp;list=accId'>" . _("Missing IDs") . "</a>]";
$html .= "&nbsp;&nbsp;";

echo $html;


if ($view == "acc") {
  echo "<p>";
  echo _("Accreditation can be done manually player by player from team roster or automatically against event organizer's external license database.");
  echo "</p>";
  if (is_file('cust/' . CUSTOMIZATIONS . '/mass-accreditation.php')) {
    include_once 'cust/' . CUSTOMIZATIONS . '/mass-accreditation.php';
  }
}

if ($view == "autoacc") {
  if (is_file('cust/' . CUSTOMIZATIONS . '/mass-accreditation.php')) {
    include_once 'cust/' . CUSTOMIZATIONS . '/mass-accreditation.php';
  }
}

if ($view == "acclog") {
  echo "<h3>" . _("Games played without accreditation") . "</h3>";
  echo "<form method='post' action='$url'>\n";
  echo "<table class='infotable'><tr><th>" . _("Player") . "</th><th>" . _("Team") . "</th><th>" . _("Game") . "</th><th>" . _("Acknowledged") . "</th></tr>\n";
  $acknowledged = array();

  while ($row = mysqli_fetch_assoc($unAccredited)) {
    if (hasAccredidationRight($row['team'])) {
      if (!$row['acknowledged']) {
        echo "<tr>";
        echo "<td>" . utf8entities($row['firstname']) . " " . utf8entities($row['lastname']) . "</td>";
        echo "<td>" . utf8entities($row['teamname']) . "</td>";
        echo "<td>" . utf8entities(GameName($row)) . "</td>";
        echo "<td style='text-align:center'><input type='checkbox' name='acknowledged[]' ";
        echo "value='" . utf8entities($row['player_id']) . "_" . $row['game_id'] . "'/></td></tr>\n";
      } else {
        $acknowledged[] = $row;
      }
    }
  }

  echo "</table>";
  echo "<p><input type='submit' name='acknowledge' value='" . _("Acknowledge") . "'/></p>\n";
  echo "<h3>" . _("Acknowledged") . "</h3>";
  echo "<table class='infotable'><tr><th>" . _("Player") . "</th><th>" . _("Team") . "</th><th>" . _("Game") . "</th><th>" . _("Acknowledged") . "</th></tr>\n";
  foreach ($acknowledged as $row) {
    if (hasAccredidationRight($row['team'])) {
      echo "<tr>";
      echo "<td>" . utf8entities($row['firstname']) . " " . utf8entities($row['lastname']) . "</td>";
      echo "<td>" . utf8entities($row['teamname']) . "</td>";
      echo "<td>" . utf8entities(GameName($row)) . "</td>";
      echo "<td style='text-align:center'><input class='deletebutton' type='image' src='images/remove.png' name='remacknowledge' ";
      echo "value='X' alt='X' onclick='setId(\"" . $row['player_id'] . "_" . $row['game_id'] . "\", \"deleteAckId\");'/>";
      echo "</td></tr>\n";
    }
  }
  echo "</table>";
  echo "<div><input type='hidden' id='deleteAckId' name='deleteAckId'/></div>\n";
  echo "</form>";
}

if ($view == "accevents") {
  echo "<h3>" . _("Accreditation events") . "</h3>";
  echo "<table class='infotable'><tr><th>" . _("Event") . "</th><th>" . _("Time") . "</th><th>" . _("Player") . "</th>";
  echo "<th>" . _("Team") . "</th><th>" . _("Game") . "</th><th>" . _("Value") . "</th>";
  echo "<th>" . _("User") . "</th><th>" . _("Source") . "</th></tr>\n";
  $logResult = SeasonAccreditationLog($season);
  while ($row = mysqli_fetch_assoc($logResult)) {
    if (hasAccredidationRight($row['team'])) {
      if ($row['value']) {
        echo "<tr class='posvalue'>";
      } else {
        echo "<tr class='negvalue'>";
      }
      if (!empty($row['game'])) {
        echo "<td>" . _("Game acknowledgement") . "</td>";
      } else {
        echo "<td>" . _("Accreditation") . "</td>";
      }
      echo "<td>" . DefBirthdayFormat($row['time']) . " " . DefHourFormat($row['time']) . "</td>";
      echo "<td>" . utf8entities($row['firstname']) . " " . utf8entities($row['lastname']) . "</td>";
      echo "<td>" . utf8entities($row['teamname']) . "</td>";
      if (!empty($row['game'])) {
        echo "<td>" . utf8entities(GameName($row)) . "</td>";
      } else {
        echo  "<td>&nbsp;</td>";
      }
      if ($row['value']) {
        echo "<td>+</td>";
      } else {
        echo "<td>-</td>";
      }
      if (!empty($row['email'])) {
        echo "<td><a href='mailto:" . $row['email'] . "'>" . utf8entities($row['uname']) . "</a></td>";
      } else {
        echo "<td>" . utf8entities($row['uname']) . "</td>";
      }
      echo "<td>" . utf8entities($row['source']) . "</td>";
      echo "</tr>\n";
    }
  }
  echo "</table>";
}

if ($view == "accId") {
  $hideNoGames = !empty($_GET['hide_no_games']);
  $sort = isset($_GET['sort']) ? $_GET['sort'] : "";
  $accIdBaseUrl = "?view=admin/accreditation&amp;season=" . $season . "&amp;list=accId";
  $accIdSortUrl = $accIdBaseUrl;
  if ($hideNoGames) {
    $accIdSortUrl .= "&amp;hide_no_games=1";
  }
  if ($hideNoGames) {
    echo "<p><a href='" . $accIdBaseUrl . "'>" . _("Show players without games") . "</a></p>";
  } else {
    echo "<p><a href='" . $accIdBaseUrl . "&amp;hide_no_games=1'>" . _("Hide players without games") . "</a></p>";
  }

  echo "<h3>" . _("Players without membership Id") . "</h3>";
  $players = SeasonAllPlayers($season);
  echo "<table class='infotable'><tr><th>" . _("Series") . "</th><th>" . _("Team") . "</th><th>" . _("Player") . "</th><th>" . _("Games") . "</th></tr>";
  foreach ($players as $player) {
    $playerinfo = PlayerInfo($player['player_id']);
    if (empty($playerinfo['accreditation_id'])) {
      $gamesPlayed = PlayerSeasonTeamPlayedGames($player['player_id'], $playerinfo['team'], $season);
      if ($hideNoGames && empty($gamesPlayed)) {
        continue;
      }
      echo "<tr><td>";
      echo utf8entities($playerinfo['seriesname']);
      echo "</td><td>";
      echo "<a href='?view=user/teamplayers&amp;team=" . $playerinfo['team'] . "'>" . utf8entities($playerinfo['teamname']) . "</a>";
      echo "</td><td>";
      echo utf8entities($playerinfo['lastname'] . " " . $playerinfo['firstname']);
      echo "</td><td class='center'>";
      echo $gamesPlayed;
      echo "</td></tr>";
    }
  }
  echo "</table>";

  echo "<h3>" . _("Players not accredited") . "</h3>";
  $players = SeasonAllPlayers($season);
  $accRows = array();
  foreach ($players as $player) {
    $playerinfo = PlayerInfo($player['player_id']);
    if (empty($playerinfo['accredited'])) {
      $gamesPlayed = PlayerSeasonTeamPlayedGames($player['player_id'], $playerinfo['team'], $season);
      if ($hideNoGames && empty($gamesPlayed)) {
        continue;
      }
      $row = array(
        'playerinfo' => $playerinfo,
        'games' => $gamesPlayed,
        'membership' => '',
        'external_type' => '',
      );
      if (CUSTOMIZATIONS == "slkl") {
        $query = sprintf("SELECT membership, license, external_type, external_validity FROM uo_license WHERE accreditation_id=%d", (int)$playerinfo['accreditation_id']);
        $licenseRow = DBQueryToRow($query);
        $row['membership'] = !empty($licenseRow['membership']) ? $licenseRow['membership'] : '';
        $row['external_type'] = !empty($licenseRow['external_type']) ? $licenseRow['external_type'] : '';
      }
      $accRows[] = $row;
    }
  }
  if ($sort == "external" && CUSTOMIZATIONS == "slkl") {
    usort($accRows, function ($a, $b) {
      $aType = strtolower(trim($a['external_type']));
      $bType = strtolower(trim($b['external_type']));
      if ($aType === $bType) {
        $aName = strtolower($a['playerinfo']['lastname'] . " " . $a['playerinfo']['firstname']);
        $bName = strtolower($b['playerinfo']['lastname'] . " " . $b['playerinfo']['firstname']);
        return $aName <=> $bName;
      }
      if ($aType === '') {
        return 1;
      }
      if ($bType === '') {
        return -1;
      }
      return $aType <=> $bType;
    });
  }
  echo "<table class='infotable'><tr><th>" . _("Series") . "</th><th>" . _("Team") . "</th><th>" . _("Player") . "</th><th>" . _("Games") . "</th>";
  if (CUSTOMIZATIONS == "slkl") {
    if ($sort == "external") {
      echo "<th>" . _("Membership") . "</th><th>" . _("External accreditation") . "</th>";
    } else {
      echo "<th>" . _("Membership") . "</th><th><a class='thsort' href='" . $accIdSortUrl . "&amp;sort=external'>" . _("External accreditation") . "</a></th>";
    }
  }
  echo "</tr>";
  foreach ($accRows as $row) {
    $playerinfo = $row['playerinfo'];
    echo "<tr><td>";
    echo utf8entities($playerinfo['seriesname']);
    echo "</td><td>";
    echo "<a href='?view=user/teamplayers&amp;team=" . $playerinfo['team'] . "'>" . utf8entities($playerinfo['teamname']) . "</a>";
    echo "</td><td>";
    echo utf8entities($playerinfo['lastname'] . " " . $playerinfo['firstname']);
    echo "</td><td class='center'>";
    echo $row['games'];
    echo "</td>";
    if (CUSTOMIZATIONS == "slkl") {
      if (!empty($row['membership'])) {
        echo "<td class='center' style='white-space: nowrap'>" . $row['membership'] . "</td>";
      } else {
        echo "<td class='center'>-</td>";
      }
      if (!empty($row['external_type'])) {
        echo "<td style='white-space: nowrap'>" . U_($row['external_type']) . "</td>";
      } else {
        echo "<td>-</td>";
      }
    }
    echo "</tr>";
  }
  echo "</table>";
}

contentEnd();
pageEnd();

?>
