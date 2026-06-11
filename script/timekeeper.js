/*
 * Timekeeper: a configurable WFDF time-limit assistant for game officials.
 * Standalone client logic for /timekeeper/. Written as ES5 to match the
 * project ESLint configuration. Reads TIMEKEEPER_DEFAULTS and TIMEKEEPER_I18N
 * emitted by timekeeper/index.php.
 */
(function () {
  "use strict";

  var DEFAULTS = window.TIMEKEEPER_DEFAULTS || {};
  var I18N = window.TIMEKEEPER_I18N || {};
  var CONFIG_KEY = "timekeeper.config";
  var SOUND_KEY = "timekeeper.sound";

  var config = {};
  var soundOn = true;
  var audioCtx = null;
  var wakeLock = null;

  // Active scenario timer state.
  var scenario = null;
  var scenarioInterval = null;

  // Match clock state.
  var clock = {
    running: false,
    startTs: 0,
    accumMs: 0,
    marks: []
  };
  var clockInterval = null;

  function el(id) {
    return document.getElementById(id);
  }

  function t(key) {
    return Object.prototype.hasOwnProperty.call(I18N, key) ? I18N[key] : key;
  }

  function num(value) {
    var n = parseInt(value, 10);
    return isNaN(n) || n < 0 ? 0 : n;
  }

  function formatTime(totalSeconds) {
    var sign = totalSeconds < 0 ? "-" : "";
    var s = Math.abs(Math.floor(totalSeconds));
    var m = Math.floor(s / 60);
    var sec = s % 60;
    return sign + m + ":" + (sec < 10 ? "0" + sec : sec);
  }

  // localStorage may be unavailable (private mode); fall back silently.
  function storageGet(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (e) {
      void e;
      return null;
    }
  }

  function storageSet(key, value) {
    try {
      window.localStorage.setItem(key, value);
    } catch (e) {
      void e;
    }
  }

  /* ------------------------------------------------------------------ */
  /* Configuration                                                       */
  /* ------------------------------------------------------------------ */

  function loadConfig() {
    config = {};
    var field;
    for (field in DEFAULTS) {
      if (Object.prototype.hasOwnProperty.call(DEFAULTS, field)) {
        config[field] = DEFAULTS[field];
      }
    }
    var stored = storageGet(CONFIG_KEY);
    if (stored) {
      try {
        var parsed = JSON.parse(stored);
        for (field in parsed) {
          if (Object.prototype.hasOwnProperty.call(config, field)) {
            config[field] = num(parsed[field]);
          }
        }
      } catch (e) {
        void e;
      }
    }
  }

  function saveConfig() {
    storageSet(CONFIG_KEY, JSON.stringify(config));
  }

  function populateInputs() {
    var field;
    for (field in config) {
      if (Object.prototype.hasOwnProperty.call(config, field)) {
        var input = el("cfg_" + field);
        if (input) {
          input.value = config[field];
        }
      }
    }
  }

  function readInputs() {
    var inputs = document.querySelectorAll("#tk-config-form input[data-field]");
    var i;
    for (i = 0; i < inputs.length; i++) {
      var field = inputs[i].getAttribute("data-field");
      config[field] = num(inputs[i].value);
    }
    saveConfig();
  }

  function resetConfig() {
    var field;
    for (field in DEFAULTS) {
      if (Object.prototype.hasOwnProperty.call(DEFAULTS, field)) {
        config[field] = DEFAULTS[field];
      }
    }
    populateInputs();
    saveConfig();
  }

  /* ------------------------------------------------------------------ */
  /* Scenario definitions                                                */
  /* ------------------------------------------------------------------ */

  // Each builder returns { total, signals, repeat }. The display counts down
  // from total to zero; signals fire by elapsed time from the button press.
  // A signal is { at, key, kind: "warn"|"go", final?, startsClock? }.
  function buildScenario(id) {
    var c = config;
    if (id === "halfstart") {
      var lead1 = num(c.hs_lead1);
      var lead2 = num(c.hs_lead2);
      var signals = [{ at: 0, key: "sig_start_warn", kind: "warn" }];
      if (lead2 > 0 && lead2 < lead1) {
        signals.push({ at: lead1 - lead2, key: "sig_start_warn", kind: "warn" });
      }
      signals.push({ at: lead1, key: "sig_start_go", kind: "go", final: true, startsClock: true });
      return { total: lead1, signals: signals };
    }
    if (id === "betweenpoints") {
      return {
        total: num(c.bp_play),
        signals: [
          { at: num(c.bp_off), key: "sig_off_warn", kind: "warn" },
          { at: num(c.bp_def), key: "sig_def_warn", kind: "warn" },
          { at: num(c.bp_play), key: "sig_play", kind: "go", final: true }
        ]
      };
    }
    if (id === "timeout") {
      // Before the pull (A5.5): pressed while the between-points timer runs, the
      // timeout adds to_add seconds to the ongoing point-start timeline. It
      // signals "Timeout over" at to_add seconds from the start of the point
      // (A5.5.2), then the A5.4 between-points sequence commences. The "anchor"
      // flag tells startScenario to keep the point's elapsed time.
      if (scenario && scenario.id === "betweenpoints") {
        var add = num(c.to_add);
        return {
          total: add + num(c.bp_play),
          anchor: "point",
          signals: [
            { at: add, key: "sig_end_timeout", kind: "warn" },
            { at: add + num(c.bp_off), key: "sig_off_warn", kind: "warn" },
            { at: add + num(c.bp_def), key: "sig_def_warn", kind: "warn" },
            { at: add + num(c.bp_play), key: "sig_play", kind: "go", final: true }
          ]
        };
      }
      // Normal in-game timeout after the pull (A5.6), timed from the call.
      return {
        total: num(c.to_play),
        signals: [
          { at: num(c.to_off1), key: "sig_off_warn", kind: "warn" },
          { at: num(c.to_off2), key: "sig_off_warn", kind: "warn" },
          { at: num(c.to_def), key: "sig_def_warn", kind: "warn" },
          { at: num(c.to_play), key: "sig_play", kind: "go", final: true }
        ]
      };
    }
    if (id === "halftime") {
      var htLen = num(c.ht_len);
      var warnAt = htLen - num(c.ht_warn);
      if (warnAt < 0) {
        warnAt = 0;
      }
      return {
        total: htLen + num(c.bp_play),
        signals: [
          { at: warnAt, key: "sig_half_warn", kind: "warn" },
          { at: htLen, key: "sig_half_end", kind: "warn" },
          { at: htLen + num(c.bp_off), key: "sig_off_warn", kind: "warn" },
          { at: htLen + num(c.bp_def), key: "sig_def_warn", kind: "warn" },
          { at: htLen + num(c.bp_play), key: "sig_play", kind: "go", final: true }
        ]
      };
    }
    if (id === "dispute") {
      var first = num(c.dp_first);
      var restart = num(c.dp_restart);
      var rep = num(c.dp_repeat);
      var built = {
        total: restart,
        signals: [
          { at: first, key: "sig_dispute", kind: "warn" },
          { at: restart, key: "sig_restart", kind: "go", final: true }
        ]
      };
      // After play must restart, repeat that signal every dp_repeat seconds
      // until play resumes (A5.7.3).
      if (rep > 0) {
        built.repeat = { from: restart, every: rep, key: "sig_restart", kind: "go" };
      }
      return built;
    }
    return null;
  }

  /* ------------------------------------------------------------------ */
  /* Audio + wake lock                                                   */
  /* ------------------------------------------------------------------ */

  function ensureAudio() {
    if (audioCtx) {
      return;
    }
    var Ctx = window.AudioContext || window.webkitAudioContext;
    if (Ctx) {
      try {
        audioCtx = new Ctx();
      } catch (e) {
        void e;
        audioCtx = null;
      }
    }
  }

  function tone(frequency, durationMs, delayMs) {
    if (!audioCtx) {
      return;
    }
    var start = audioCtx.currentTime + delayMs / 1000;
    var osc = audioCtx.createOscillator();
    var gain = audioCtx.createGain();
    osc.type = "square";
    osc.frequency.value = frequency;
    gain.gain.value = 0.001;
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    gain.gain.setValueAtTime(0.25, start);
    gain.gain.setTargetAtTime(0.0001, start + durationMs / 1000, 0.03);
    osc.start(start);
    osc.stop(start + durationMs / 1000 + 0.1);
  }

  function beep(kind) {
    if (!soundOn) {
      return;
    }
    ensureAudio();
    if (audioCtx && audioCtx.state === "suspended" && audioCtx.resume) {
      audioCtx.resume();
    }
    if (kind === "go") {
      tone(1320, 140, 0);
      tone(1320, 140, 200);
    } else {
      tone(880, 160, 0);
    }
  }

  function requestWakeLock() {
    if (!navigator.wakeLock || wakeLock) {
      return;
    }
    navigator.wakeLock.request("screen").then(function (lock) {
      wakeLock = lock;
      wakeLock.addEventListener("release", function () {
        wakeLock = null;
      });
    }).catch(function () {
      wakeLock = null;
    });
  }

  function releaseWakeLock() {
    if (clock.running || scenario) {
      return;
    }
    if (wakeLock && wakeLock.release) {
      var released = wakeLock.release();
      if (released && released.catch) {
        released.catch(function () {});
      }
      wakeLock = null;
    }
  }

  /* ------------------------------------------------------------------ */
  /* Scenario timer engine                                               */
  /* ------------------------------------------------------------------ */

  function scenarioElapsed() {
    if (!scenario) {
      return 0;
    }
    var endTs = scenario.paused ? scenario.pauseTs : Date.now();
    return (endTs - scenario.startTs - scenario.accumMs) / 1000;
  }

  function setDisplayState(state) {
    var display = el("tk-display");
    display.className = "card tk-display tk-state-" + state;
  }

  // List the scenario's signal limits on the side, in light grey. The clock
  // counts down, so each limit is shown as the remaining time when it fires.
  function renderActionLimits(built) {
    var box = el("tk-action-limits");
    box.innerHTML = "";
    var i;
    for (i = 0; i < built.signals.length; i++) {
      var sig = built.signals[i];
      var row = document.createElement("div");
      row.className = "tk-side-row";
      var label = document.createElement("span");
      label.textContent = t(sig.key);
      var time = document.createElement("span");
      time.className = "tk-side-time";
      time.textContent = formatTime(built.total - sig.at);
      row.appendChild(label);
      row.appendChild(time);
      box.appendChild(row);
      sig.row = row;
    }
  }

  function renderScenario() {
    if (!scenario) {
      return;
    }
    var elapsed = scenarioElapsed();
    var i;

    for (i = 0; i < scenario.signals.length; i++) {
      var sig = scenario.signals[i];
      if (!sig.fired && elapsed >= sig.at) {
        sig.fired = true;
        el("tk-display-signal").textContent = t(sig.key);
        setDisplayState(sig.kind === "go" ? "zero" : "warn");
        beep(sig.kind);
        if (sig.row) {
          sig.row.className = "tk-side-row tk-side-row--done";
        }
        if (sig.startsClock && !clock.running) {
          startClock();
        }
      }
    }

    if (scenario.repeat && elapsed >= scenario.nextRepeat) {
      while (elapsed >= scenario.nextRepeat) {
        el("tk-display-signal").textContent = t(scenario.repeat.key);
        setDisplayState(scenario.repeat.kind === "go" ? "zero" : "warn");
        beep(scenario.repeat.kind);
        scenario.nextRepeat += scenario.repeat.every;
      }
    }

    // The action clock always counts down to zero; at zero it turns red.
    var remaining = scenario.total - elapsed;
    if (remaining <= 0) {
      remaining = 0;
      setDisplayState("zero");
    }
    el("tk-display-time").textContent = formatTime(remaining);
  }

  function startScenario(id) {
    var prev = scenario;
    var built = buildScenario(id);
    if (!built) {
      return;
    }
    ensureAudio();
    var i;
    for (i = 0; i < built.signals.length; i++) {
      built.signals[i].fired = false;
    }
    built.signals.sort(function (a, b) {
      return a.at - b.at;
    });
    scenario = built;
    scenario.id = id;
    if (built.anchor === "point" && prev) {
      // Before-pull timeout: keep the previous (between-points) elapsed time so
      // signals are measured from the start of the point, not the timeout call.
      var prevEnd = prev.paused ? prev.pauseTs : Date.now();
      scenario.startTs = Date.now() - (prevEnd - prev.startTs - prev.accumMs);
    } else {
      scenario.startTs = Date.now();
    }
    scenario.accumMs = 0;
    scenario.paused = false;
    scenario.pauseTs = 0;
    if (scenario.repeat) {
      scenario.nextRepeat = scenario.repeat.from + scenario.repeat.every;
    }

    el("tk-display-scenario").textContent = t("sc_" + id);
    el("tk-display-signal").innerHTML = "&nbsp;";
    setDisplayState("running");
    el("tk-timer-pause").disabled = false;
    setPauseLabel(false);
    el("tk-timer-stop").disabled = false;
    renderActionLimits(built);

    requestWakeLock();
    if (scenarioInterval) {
      window.clearInterval(scenarioInterval);
    }
    scenarioInterval = window.setInterval(renderScenario, 200);
    renderScenario();
  }

  function setPauseLabel(paused) {
    el("tk-timer-pause").textContent = paused ? t("ui_resume") : t("ui_pause");
  }

  function togglePause() {
    if (!scenario) {
      return;
    }
    if (scenario.paused) {
      scenario.accumMs += Date.now() - scenario.pauseTs;
      scenario.paused = false;
      setPauseLabel(false);
    } else {
      scenario.pauseTs = Date.now();
      scenario.paused = true;
      setPauseLabel(true);
    }
  }

  function stopScenario() {
    if (scenarioInterval) {
      window.clearInterval(scenarioInterval);
      scenarioInterval = null;
    }
    scenario = null;
    el("tk-display-scenario").innerHTML = "&nbsp;";
    el("tk-display-time").textContent = "0:00";
    el("tk-display-signal").innerHTML = "&nbsp;";
    el("tk-action-limits").innerHTML = "";
    setDisplayState("ready");
    el("tk-timer-pause").disabled = true;
    setPauseLabel(false);
    el("tk-timer-stop").disabled = true;
    releaseWakeLock();
  }

  /* ------------------------------------------------------------------ */
  /* Match (game) clock                                                  */
  /* ------------------------------------------------------------------ */

  function clockElapsed() {
    if (!clock.running) {
      return clock.accumMs / 1000;
    }
    return (Date.now() - clock.startTs + clock.accumMs) / 1000;
  }

  function renderClock() {
    el("tk-matchclock-time").textContent = formatTime(clockElapsed());
  }

  // Once running, the primary button marks the current time; the secondary
  // button pauses. Paused -> primary resumes. Stopped -> primary starts.
  function setClockButton() {
    var primary = el("tk-clock-primary");
    var pause = el("tk-clock-pause");
    if (clock.running) {
      primary.textContent = t("ui_mark");
      pause.disabled = false;
    } else if (clock.accumMs > 0) {
      primary.textContent = t("ui_resume_clock");
      pause.disabled = true;
    } else {
      primary.textContent = t("ui_start_clock");
      pause.disabled = true;
    }
  }

  // Newest mark on top, keeping each mark's chronological number.
  function renderMarks() {
    var box = el("tk-clock-marks");
    box.innerHTML = "";
    var i;
    for (i = clock.marks.length - 1; i >= 0; i--) {
      var row = document.createElement("div");
      row.className = "tk-side-row";
      var label = document.createElement("span");
      label.textContent = (i + 1) + ".";
      var time = document.createElement("span");
      time.className = "tk-side-time";
      time.textContent = clock.marks[i];
      row.appendChild(label);
      row.appendChild(time);
      box.appendChild(row);
    }
  }

  function markClock() {
    if (!clock.running) {
      return;
    }
    clock.marks.push(formatTime(clockElapsed()));
    renderMarks();
  }

  function startClock() {
    if (clock.running) {
      return;
    }
    clock.running = true;
    clock.startTs = Date.now();
    requestWakeLock();
    if (!clockInterval) {
      clockInterval = window.setInterval(renderClock, 250);
    }
    setClockButton();
  }

  function pauseClock() {
    if (!clock.running) {
      return;
    }
    clock.accumMs += Date.now() - clock.startTs;
    clock.running = false;
    setClockButton();
    renderClock();
    releaseWakeLock();
  }

  function primaryClock() {
    if (clock.running) {
      markClock();
    } else {
      startClock();
    }
  }

  function resetClock() {
    clock.running = false;
    clock.startTs = 0;
    clock.accumMs = 0;
    clock.marks = [];
    if (clockInterval) {
      window.clearInterval(clockInterval);
      clockInterval = null;
    }
    renderClock();
    renderMarks();
    setClockButton();
    releaseWakeLock();
  }

  /* ------------------------------------------------------------------ */
  /* Sound toggle                                                        */
  /* ------------------------------------------------------------------ */

  function loadSound() {
    soundOn = storageGet(SOUND_KEY) !== "0";
    updateSoundButton();
  }

  function updateSoundButton() {
    el("tk-sound-toggle").textContent = soundOn ? t("ui_sound_on") : t("ui_sound_off");
  }

  function toggleSound() {
    soundOn = !soundOn;
    storageSet(SOUND_KEY, soundOn ? "1" : "0");
    updateSoundButton();
    if (soundOn) {
      ensureAudio();
      beep("warn");
    }
  }

  /* ------------------------------------------------------------------ */
  /* Screen navigation                                                   */
  /* ------------------------------------------------------------------ */

  function showScreen(name) {
    var screens = ["language", "config", "timer"];
    var i;
    for (i = 0; i < screens.length; i++) {
      var node = el("tk-screen-" + screens[i]);
      if (node) {
        if (screens[i] === name) {
          node.className = "tk-screen";
        } else {
          node.className = "tk-screen tk-hidden";
        }
      }
    }
  }

  /* ------------------------------------------------------------------ */
  /* Init                                                                */
  /* ------------------------------------------------------------------ */

  function bind() {
    var actions = document.querySelectorAll(".tk-action");
    var i;
    for (i = 0; i < actions.length; i++) {
      actions[i].addEventListener("click", function () {
        startScenario(this.getAttribute("data-scenario"));
      });
    }

    el("tk-timer-pause").addEventListener("click", togglePause);
    el("tk-timer-stop").addEventListener("click", stopScenario);
    el("tk-clock-primary").addEventListener("click", primaryClock);
    el("tk-clock-pause").addEventListener("click", pauseClock);
    el("tk-clock-reset").addEventListener("click", resetClock);

    el("tk-config-reset").addEventListener("click", resetConfig);
    el("tk-config-done").addEventListener("click", function () {
      readInputs();
      showScreen("timer");
    });

    el("tk-nav-language").addEventListener("click", function () {
      showScreen("language");
    });
    el("tk-nav-config").addEventListener("click", function () {
      populateInputs();
      showScreen("config");
    });
    el("tk-sound-toggle").addEventListener("click", toggleSound);
  }

  function init() {
    loadConfig();
    populateInputs();
    loadSound();
    bind();
    renderClock();
    setClockButton();

    // The game (timer) view is the default. The interface language is inherited
    // from Ultiorganizer and changed from the footer "Change language" control,
    // whose links reload the page with ?locale=... and return here.
    showScreen("timer");
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
