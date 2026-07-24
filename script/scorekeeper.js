/*
 * Scorekeeper: shared client logic for /scorekeeper/. Written as ES5 to match
 * the project ESLint configuration.
 *
 * The game clock is anchored on the elapsed second count rendered by the
 * server and on a Date.now() reading taken at page load, and every value is
 * derived from the difference between those two on demand. Counting seconds in
 * a setInterval drifts badly on phones and tablets, because browsers throttle
 * or suspend timers while the screen is off; deriving from a wall-clock delta
 * survives suspension. Only differences of Date.now() are used, so a device
 * whose absolute clock is wrong still shows the correct game time.
 */
(function () {
  "use strict";

  var anchorElapsed = 0;
  var anchorClientMs = 0;
  var paused = false;
  var ongoing = false;
  var pausedSuffix = "";
  var initialised = false;
  var tickInterval = null;

  function pad(value) {
    return value < 10 ? "0" + value : String(value);
  }

  /*
   * Elapsed game seconds right now. Computed per call so that callers reading
   * the clock to prefill a goal or timeout time get a fresh value even when no
   * render tick has run since the screen woke up.
   */
  function elapsedSeconds() {
    if (!initialised) {
      return 0;
    }
    if (paused) {
      return anchorElapsed;
    }
    var drift = Math.floor((Date.now() - anchorClientMs) / 1000);
    return Math.max(0, anchorElapsed + drift);
  }

  function time() {
    var elapsed = elapsedSeconds();
    return { mm: Math.floor(elapsed / 60), ss: elapsed % 60 };
  }

  /*
   * Goal and timeout times are entered in five second steps. Same rounding and
   * carry rule as GameTimerState() in lib/game.functions.php, so the server
   * side render and this one agree.
   */
  function roundedTime() {
    var current = time();
    var seconds = Math.round(current.ss / 5) * 5;
    var minutes = current.mm;
    if (seconds === 60) {
      minutes++;
      seconds = 0;
    }
    return { mm: minutes, ss: seconds };
  }

  function render() {
    var clock = document.getElementById("gametime");
    if (!clock || !initialised) {
      return;
    }
    var current = time();
    var text = pad(current.mm) + ":" + pad(current.ss);
    if (paused) {
      text += pausedSuffix;
    }
    clock.textContent = text;
  }

  function init(options) {
    var settings = options || {};
    anchorElapsed = Math.max(0, parseInt(settings.elapsed, 10) || 0);
    anchorClientMs = Date.now();
    paused = !!settings.paused;
    ongoing = !!settings.ongoing;
    pausedSuffix = settings.pausedSuffix || "";
    initialised = true;

    render();

    if (tickInterval) {
      window.clearInterval(tickInterval);
      tickInterval = null;
    }
    if (ongoing && !paused) {
      tickInterval = window.setInterval(render, 1000);
      // Redraw as soon as the screen comes back on instead of waiting for the
      // next tick, which may be up to a second away.
      document.addEventListener("visibilitychange", function () {
        if (document.visibilityState === "visible") {
          render();
        }
      });
    }
  }

  /*
   * False until init() runs, which only happens on pages that actually show
   * the clock. Callers that prefill a time field check this so they leave the
   * field alone rather than filling in 00:00.
   */
  function isActive() {
    return initialised;
  }

  window.scorekeeperClock = {
    init: init,
    isActive: isActive,
    elapsedSeconds: elapsedSeconds,
    time: time,
    roundedTime: roundedTime,
    render: render
  };
})();
