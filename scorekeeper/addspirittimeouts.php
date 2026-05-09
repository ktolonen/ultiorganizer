<?php
include_once __DIR__ . '/auth.php';

function ScorekeeperSpiritTimeoutData($gameId, $home, $maxslots)
{
    $values = [];
    foreach (GameSpiritTimeoutsArray($gameId) as $timeout) {
        if ((int) $timeout['ishome'] === (int) $home && count($values) < $maxslots) {
            $time = explode(".", SecToMin($timeout['time']));
            $values[] = [
                "mm" => intval($time[0]),
                "ss" => intval($time[1]),
            ];
        }
    }

    $filled = count($values);
    for ($i = $filled; $i < $maxslots; $i++) {
        $values[] = ["mm" => 0, "ss" => 0];
    }

    return [
        "values" => $values,
        "filled" => $filled,
    ];
}

$html = "";
$maxSpiritTimeouts = 4;

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

$seasoninfo = SeasonInfo(GameSeason($gameId));
if (empty($seasoninfo['spiritmode']) || !empty($seasoninfo['hide_time_on_scoresheet'])) {
    header("location:?view=addtimeouts&game=" . $gameId);
    exit;
}

$useGameClock = !scorekeeperHasManualNoGameClock($gameId);

if ($useGameClock && isset($_POST['pausegame'])) {
    GameTimePause($gameId);
    header("location:?view=addspirittimeouts&game=" . $gameId);
    exit;
}

if ($useGameClock && isset($_POST['resumegame'])) {
    GameTimeResume($gameId);
    header("location:?view=addspirittimeouts&game=" . $gameId);
    exit;
}

$game_result = GameResult($gameId);
$timerState = $useGameClock ? GameTimerState($gameId) : [
    "started" => false,
    "ongoing" => false,
    "paused" => false,
    "mm" => 0,
    "ss" => 0,
    "rss" => 0,
];
$showClock = $useGameClock && ($timerState['ongoing'] || $timerState['mm'] > 0 || $timerState['ss'] > 0);
$homeTimeoutData = ScorekeeperSpiritTimeoutData($gameId, 1, $maxSpiritTimeouts);
$awayTimeoutData = ScorekeeperSpiritTimeoutData($gameId, 0, $maxSpiritTimeouts);

if (isset($_POST['save'])) {
    GameRemoveAllSpiritTimeouts($gameId);

    $j = 0;
    for ($i = 0; $i < $maxSpiritTimeouts; $i++) {
        $timemm = $_POST['htomm' . $i];
        $timess = $_POST['htoss' . $i];
        $time = $timemm . "." . $timess;
        if (($timemm + $timess) > 0) {
            $j++;
            GameAddSpiritTimeout($gameId, $j, TimeToSec($time), 1);
        }
    }

    $j = 0;
    for ($i = 0; $i < $maxSpiritTimeouts; $i++) {
        $timemm = $_POST['atomm' . $i];
        $timess = $_POST['atoss' . $i];
        $time = $timemm . "." . $timess;
        if (($timemm + $timess) > 0) {
            $j++;
            GameAddSpiritTimeout($gameId, $j, TimeToSec($time), 0);
        }
    }

    header("location:?view=addscoresheet&game=" . $gameId);
    exit;
}

$html .= "<div data-role='header'>\n";
if ($showClock) {
    $html .= "<span id='gametime' style='float: left; margin: 0.2em 1.1em 0.25em 0.5ex; padding: 0.15em 0.4em; border-radius: 0.35em; background: #e6eef2; line-height: 1.3; font-size: 1.8em;'>" . sprintf("%02d", $timerState['mm']) . ":" . sprintf("%02d", $timerState['ss']) . "</span>";
}
$html .= "<h1>" . _("Spirit stoppages") . ": " . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
$html .= "<p class='warning'><strong>" . _("Pause the game clock during a spirit stoppage.") . "</strong> " . _("Resume it when play continues.") . "</p>";
$html .= "<form action='?view=addspirittimeouts' method='post' data-ajax='false'>\n";

if ($useGameClock && $timerState['ongoing']) {
    if ($timerState['paused']) {
        $html .= "<input type='submit' name='resumegame' data-ajax='false' value='" . _("Resume game clock") . "'/>";
    } else {
        $html .= "<input type='submit' id='pausegame' name='pausegame' data-ajax='false' value='" . _("Pause game clock") . "'/>";
    }
}

$html .= "<fieldset data-role='controlgroup' id='spirit-timeout-team-selection'>";
$html .= "<legend>" . _("Team taking the spirit stoppage") . "</legend>";
$html .= "<input type='radio' name='spirittimeoutteam' id='spirittimeoutteam-home' value='H' />";
$html .= "<label for='spirittimeoutteam-home'>" . utf8entities($game_result['hometeamname']) . "</label>";
$html .= "<input type='radio' name='spirittimeoutteam' id='spirittimeoutteam-away' value='A' />";
$html .= "<label for='spirittimeoutteam-away'>" . utf8entities($game_result['visitorteamname']) . "</label>";
$html .= "</fieldset>";

$html .= "<label for='htomm0' class='select'><b>" . utf8entities($game_result['hometeamname']) . "</b> " . _("spirit stoppages") . " (" . _("min") . ":" . _("sec") . "):</label>";
$html .= "<div class='timeout-list'>";
foreach ($homeTimeoutData['values'] as $j => $time) {
    $html .= "<div class='timeout-pair'>\n";
    $html .= "<div class='ui-block-a'>\n";
    $html .= "<select id='htomm$j' name='htomm$j' >";
    for ($i = 0; $i <= 180; $i++) {
        if ($i == $time['mm']) {
            $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
        } else {
            $html .= "<option value='" . $i . "'>" . $i . "</option>";
        }
    }
    $html .= "</select>";
    $html .= "</div>";
    $html .= "<span class='timeout-separator'>:</span>";
    $html .= "<div class='ui-block-b'>\n";
    $html .= "<select id='htoss$j' name='htoss$j' >";
    for ($i = 0; $i <= 55; $i = $i + 5) {
        if ($i == $time['ss']) {
            $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
        } else {
            $html .= "<option value='" . $i . "'>" . $i . "</option>";
        }
    }
    $html .= "</select>";
    $html .= "</div>";
    $html .= "</div>";
}
$html .= "</div>";

$html .= "<label for='atomm0' class='select'><b>" . utf8entities($game_result['visitorteamname']) . "</b> " . _("spirit stoppages") . " (" . _("min") . ":" . _("sec") . "):</label>";
$html .= "<div class='timeout-list'>";
foreach ($awayTimeoutData['values'] as $j => $time) {
    $html .= "<div class='timeout-pair'>\n";
    $html .= "<div class='ui-block-a'>\n";
    $html .= "<select id='atomm$j' name='atomm$j' >";
    for ($i = 0; $i <= 180; $i++) {
        if ($i == $time['mm']) {
            $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
        } else {
            $html .= "<option value='" . $i . "'>" . $i . "</option>";
        }
    }
    $html .= "</select>";
    $html .= "</div>";
    $html .= "<span class='timeout-separator'>:</span>";
    $html .= "<div class='ui-block-b'>\n";
    $html .= "<select id='atoss$j' name='atoss$j' >";
    for ($i = 0; $i <= 55; $i = $i + 5) {
        if ($i == $time['ss']) {
            $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
        } else {
            $html .= "<option value='" . $i . "'>" . $i . "</option>";
        }
    }
    $html .= "</select>";
    $html .= "</div>";
    $html .= "</div>";
}
$html .= "</div>";

$html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save") . "'/>";
$html .= "<a class='back-score-button' href='?view=addscoresheet&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Back to scoresheet") . "</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
?>
<script type="text/javascript">
<?php if ($showClock) { ?>
  window.scorekeeperClockMinutes = <?php echo (int) $timerState['mm']; ?>;
  window.scorekeeperClockSeconds = <?php echo (int) $timerState['ss']; ?>;

  (function() {
    var clock = document.getElementById('gametime');
    var pausedSuffix = <?php echo json_encode(" (" . _("Paused") . ")"); ?>;

    function renderClock(paused) {
      if (!clock) {
        return;
      }
      var text = String(window.scorekeeperClockMinutes).padStart(2, '0') + ':' + String(window.scorekeeperClockSeconds).padStart(2, '0');
      if (paused) {
        text += pausedSuffix;
      }
      clock.textContent = text;
    }

    renderClock(<?php echo $timerState['paused'] ? 'true' : 'false'; ?>);

<?php if ($timerState['ongoing'] && !$timerState['paused']) { ?>
    window.setInterval(function() {
      window.scorekeeperClockSeconds++;
      if (window.scorekeeperClockSeconds > 59) {
        window.scorekeeperClockMinutes++;
        window.scorekeeperClockSeconds = 0;
      }
      renderClock(false);
    }, 1000);
<?php } ?>
  })();
<?php } ?>

  (function() {
    var pendingTimeoutSlot = null;
    var homeFilled = <?php echo (int) $homeTimeoutData['filled']; ?>;
    var awayFilled = <?php echo (int) $awayTimeoutData['filled']; ?>;

    function roundedClockTime() {
      if (typeof window.scorekeeperClockMinutes === 'undefined' || typeof window.scorekeeperClockSeconds === 'undefined') {
        return null;
      }

      var minutes = window.scorekeeperClockMinutes;
      var seconds = Math.round(window.scorekeeperClockSeconds / 5) * 5;
      if (seconds === 60) {
        minutes++;
        seconds = 0;
      }

      return { mm: minutes, ss: seconds };
    }

    function selectElements(team, index) {
      var prefix = team === 'H' ? 'hto' : 'ato';
      return {
        mm: document.getElementById(prefix + 'mm' + index),
        ss: document.getElementById(prefix + 'ss' + index)
      };
    }

    function clearPendingTimeoutSlot() {
      if (!pendingTimeoutSlot) {
        return;
      }
      var selects = selectElements(pendingTimeoutSlot.team, pendingTimeoutSlot.index);
      if (selects.mm && selects.ss) {
        selects.mm.value = '0';
        selects.ss.value = '0';
      }
      pendingTimeoutSlot = null;
    }

    function setPendingTimeout(team) {
      var index = team === 'H' ? homeFilled : awayFilled;
      if (index >= <?php echo (int) $maxSpiritTimeouts; ?>) {
        clearPendingTimeoutSlot();
        return;
      }

      var time = roundedClockTime();
      var selects = selectElements(team, index);
      clearPendingTimeoutSlot();
      if (!time || !selects.mm || !selects.ss) {
        return;
      }

      selects.mm.value = String(time.mm);
      selects.ss.value = String(time.ss);
      pendingTimeoutSlot = { team: team, index: index };
    }

    var timeoutTeamRadios = document.querySelectorAll('input[name="spirittimeoutteam"]');
    timeoutTeamRadios.forEach(function(radio) {
      radio.addEventListener('change', function() {
        setPendingTimeout(this.value);
      });
    });

    var pauseButton = document.getElementById('pausegame');
    if (pauseButton) {
      pauseButton.addEventListener('click', function(event) {
        if (!confirm(<?php echo json_encode(_("Pause the game clock for this spirit stoppage?")); ?>)) {
          event.preventDefault();
        }
      });
    }
  })();
</script>
