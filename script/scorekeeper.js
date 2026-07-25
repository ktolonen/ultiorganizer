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

  /*
   * When the server sampled the elapsed count, in client time.
   *
   * The server reads the clock while generating the response, but this script
   * only runs once the response has arrived and parsed. Anchoring on Date.now()
   * here would fold the transfer and parse time into the clock and leave it
   * permanently that far behind -- noticeable on a slow venue connection, where
   * it would stamp goals and timeouts early. responseStart is when the first
   * byte arrived, which is the closest observable moment to the server's
   * reading, since index.php buffers the whole page and flushes it at the end.
   */
  function serverSampleClientMs() {
    var now = Date.now();
    if (!window.performance || !window.performance.getEntriesByType) {
      return now;
    }

    var entries = window.performance.getEntriesByType("navigation");
    var origin = window.performance.timeOrigin;
    if (!entries || !entries.length || !origin || !entries[0].responseStart) {
      return now;
    }

    var anchor = origin + entries[0].responseStart;
    // Ignore nonsense values: a restored or prerendered page can report timings
    // unrelated to this render, and a mid-session clock change breaks the sum.
    if (anchor > now || now - anchor > 300000) {
      return now;
    }

    return anchor;
  }

  function init(options) {
    var settings = options || {};
    anchorElapsed = Math.max(0, parseInt(settings.elapsed, 10) || 0);
    anchorClientMs = serverSampleClientMs();
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

/*
 * Double-submit guard. On a slow network a scorekeeper who taps Save twice can
 * post the same goal or timeout twice, so block the second submit and disable
 * the buttons for the duration of the request.
 */
(function () {
  "use strict";

  var BUSY_ATTR = "data-scorekeeper-submitting";

  function submitControls(form) {
    return form.querySelectorAll("input[type=submit], button[type=submit], button:not([type])");
  }

  function disableSubmitControls(form, disabled) {
    var controls = submitControls(form);
    for (var i = 0; i < controls.length; i++) {
      controls[i].disabled = disabled;
    }
  }

  document.addEventListener("submit", function (event) {
    var form = event.target;
    if (!form || form.nodeName !== "FORM") {
      return;
    }

    if (form.getAttribute(BUSY_ATTR)) {
      event.preventDefault();
      return;
    }

    // Mark synchronously, so a second submit arriving before the deferred
    // disable runs is still rejected by the check above.
    form.setAttribute(BUSY_ATTR, "1");

    /*
     * Disable on the next tick, never synchronously: a disabled submit button
     * is left out of the POST body, and the scorekeeper pages branch on which
     * button was pressed (add vs forceadd, startgame vs pausegame, ...).
     */
    window.setTimeout(function () {
      disableSubmitControls(form, true);
    }, 0);
  });

  /*
   * Restoring from the back/forward cache reuses the old DOM, which would
   * otherwise come back with a dead Save button.
   */
  window.addEventListener("pageshow", function (event) {
    if (!event.persisted) {
      return;
    }
    var forms = document.querySelectorAll("form[" + BUSY_ATTR + "]");
    for (var i = 0; i < forms.length; i++) {
      forms[i].removeAttribute(BUSY_ATTR);
      disableSubmitControls(forms[i], false);
    }
  });
})();
