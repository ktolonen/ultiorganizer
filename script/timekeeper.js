/*
 * Timekeeper: a configurable WFDF time-limit assistant for game officials.
 * Standalone client logic for /timekeeper/. Written as ES5 to match the
 * project ESLint configuration. Reads TIMEKEEPER_TEMPLATES and TIMEKEEPER_I18N
 * emitted by timekeeper/index.php.
 */
(function () {
  "use strict";

  var DEFAULTS = window.TIMEKEEPER_CAP_DEFAULTS || {};
  var TEMPLATES = window.TIMEKEEPER_TEMPLATES || {};
  var DEFAULT_TEMPLATE_ID = window.TIMEKEEPER_DEFAULT_TEMPLATE_ID || "";
  var I18N = window.TIMEKEEPER_I18N || {};
  var CONFIG_KEY = "timekeeper.config";
  var CONFIGS_KEY = "timekeeper.configs";
  var TEMPLATE_KEY = "timekeeper.template";
  var SOUND_KEY = "timekeeper.sound";

  var config = {};
  var signalConfig = {};
  var activeTemplateId = "";
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
    markCounts: {},
    marks: [],
    capConfig: null,
    halfTimeStarted: false,
    dismissedCap: "",
    activeCap: "",
    loggedCaps: {},
    startWarning: false
  };
  var clockInterval = null;

  function el(id) {
    return document.getElementById(id);
  }

  function t(key, overrides) {
    if (overrides && Object.prototype.hasOwnProperty.call(overrides, key)) {
      return overrides[key];
    }
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

  function objectKeys(obj) {
    var keys = [];
    var key;
    for (key in obj) {
      if (Object.prototype.hasOwnProperty.call(obj, key)) {
        keys.push(key);
      }
    }
    return keys;
  }

  function copyObject(obj) {
    var copy = {};
    var key;
    for (key in obj) {
      if (Object.prototype.hasOwnProperty.call(obj, key)) {
        copy[key] = obj[key];
      }
    }
    return copy;
  }

  /* ------------------------------------------------------------------ */
  /* Configuration                                                       */
  /* ------------------------------------------------------------------ */

  function templateExists(id) {
    return Object.prototype.hasOwnProperty.call(TEMPLATES, id);
  }

  function firstTemplateId() {
    var keys = objectKeys(TEMPLATES);
    return keys.length ? keys[0] : "";
  }

  function currentTemplate() {
    if (templateExists(activeTemplateId)) {
      return TEMPLATES[activeTemplateId];
    }
    return null;
  }

  function currentDefaults() {
    var template = currentTemplate();
    if (template && template.caps) {
      var defaults = copyObject(DEFAULTS);
      var field;
      for (field in template.caps) {
        if (Object.prototype.hasOwnProperty.call(template.caps, field)) {
          defaults[field] = num(template.caps[field]);
        }
      }
      return defaults;
    }
    return DEFAULTS;
  }

  function currentTemplateSignals() {
    var template = currentTemplate();
    if (template && template.signals) {
      return template.signals;
    }
    return {};
  }

  function loadStoredConfigs() {
    var stored = storageGet(CONFIGS_KEY);
    if (stored) {
      try {
        return JSON.parse(stored) || {};
      } catch (e) {
        void e;
      }
    }
    return {};
  }

  function migrateLegacyConfig() {
    if (storageGet(CONFIGS_KEY)) {
      return;
    }
    var stored = storageGet(CONFIG_KEY);
    if (!stored) {
      return;
    }
    try {
      var parsed = JSON.parse(stored);
      var configs = {};
      configs[String(DEFAULT_TEMPLATE_ID)] = { caps: {} };
      if (Object.prototype.hasOwnProperty.call(parsed, "half_time_cap")) {
        configs[String(DEFAULT_TEMPLATE_ID)].caps.half_time_cap = parsed.half_time_cap;
      }
      if (Object.prototype.hasOwnProperty.call(parsed, "time_cap")) {
        configs[String(DEFAULT_TEMPLATE_ID)].caps.time_cap = parsed.time_cap;
      }
      storageSet(CONFIGS_KEY, JSON.stringify(configs));
    } catch (e) {
      void e;
    }
  }

  function resolveTemplateId(id) {
    id = String(id || "");
    if (templateExists(id)) {
      return id;
    }
    id = String(DEFAULT_TEMPLATE_ID || "");
    if (templateExists(id)) {
      return id;
    }
    return firstTemplateId();
  }

  function setActiveTemplate(id) {
    activeTemplateId = resolveTemplateId(id);
    if (activeTemplateId) {
      storageSet(TEMPLATE_KEY, activeTemplateId);
    }
    loadConfig();
    populateTemplateSelector();
    populateInputs();
  }

  function loadConfig() {
    config = {};
    var defaults = currentDefaults();
    var field;
    for (field in defaults) {
      if (Object.prototype.hasOwnProperty.call(defaults, field)) {
        config[field] = defaults[field];
      }
    }
    var configs = loadStoredConfigs();
    var stored = configs[activeTemplateId] || {};
    var parsed = stored.caps || stored || {};
    for (field in parsed) {
      if (Object.prototype.hasOwnProperty.call(config, field)) {
        config[field] = num(parsed[field]);
      }
    }
    signalConfig = stored.signals || {};
  }

  function populateTemplateSelector() {
    var select = el("tk-template-select");
    if (select) {
      select.value = activeTemplateId;
    }
  }

  function saveConfig() {
    var configs = loadStoredConfigs();
    configs[activeTemplateId] = { caps: {}, signals: {} };
    var field;
    for (field in config) {
      if (Object.prototype.hasOwnProperty.call(config, field)) {
        configs[activeTemplateId].caps[field] = config[field];
      }
    }
    for (field in signalConfig) {
      if (Object.prototype.hasOwnProperty.call(signalConfig, field)) {
        configs[activeTemplateId].signals[field] = signalConfig[field];
      }
    }
    storageSet(CONFIGS_KEY, JSON.stringify(configs));
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
    renderSignalInputs();
  }

  function readInputs() {
    var inputs = document.querySelectorAll("#tk-config-form input[data-field]");
    var i;
    for (i = 0; i < inputs.length; i++) {
      var field = inputs[i].getAttribute("data-field");
      config[field] = num(inputs[i].value);
    }
    inputs = document.querySelectorAll("#tk-config-form input[data-signal-id]");
    for (i = 0; i < inputs.length; i++) {
      var signalId = inputs[i].getAttribute("data-signal-id");
      signalConfig[signalId] = num(inputs[i].value);
    }
    saveConfig();
  }

  function resetConfig() {
    var defaults = currentDefaults();
    var field;
    for (field in defaults) {
      if (Object.prototype.hasOwnProperty.call(defaults, field)) {
        config[field] = defaults[field];
      }
    }
    signalConfig = {};
    populateInputs();
    saveConfig();
  }

  function rowTime(row) {
    var id = String(row.id);
    if (Object.prototype.hasOwnProperty.call(signalConfig, id)) {
      return num(signalConfig[id]);
    }
    return num(row.time);
  }

  function signalRowsForAction(actionId) {
    var signals = currentTemplateSignals();
    var rows = signals[actionId] || [];
    var copy = [];
    var i;
    for (i = 0; i < rows.length; i++) {
      copy.push({
        id: rows[i].id,
        time: rowTime(rows[i]),
        text: rows[i].text
      });
    }
    copy.sort(function (a, b) {
      if (a.time === b.time) {
        return a.id - b.id;
      }
      return a.time - b.time;
    });
    return copy;
  }

  function renderSignalInputs() {
    var box = el("tk-signal-config");
    if (!box) {
      return;
    }
    box.innerHTML = "";
    var actionOrder = ["betweenpoints", "timeout", "timeoutbeforepull", "halfstart", "halftime", "dispute", "discretrieval"];
    var actionIndex;
    for (actionIndex = 0; actionIndex < actionOrder.length; actionIndex++) {
      var actionId = actionOrder[actionIndex];
      var rows = signalRowsForAction(actionId);
      if (!rows.length) {
        continue;
      }
      var fieldset = document.createElement("fieldset");
      fieldset.className = "tk-config-group";
      var legend = document.createElement("legend");
      legend.textContent = t("sc_" + actionId);
      fieldset.appendChild(legend);
      var i;
      for (i = 0; i < rows.length; i++) {
        var row = document.createElement("div");
        row.className = "tk-config-row";
        var label = document.createElement("label");
        label.setAttribute("for", "cfg_signal_" + rows[i].id);
        label.textContent = rows[i].text;
        var input = document.createElement("input");
        input.type = "number";
        input.inputMode = "numeric";
        input.min = "0";
        input.step = "1";
        input.id = "cfg_signal_" + rows[i].id;
        input.setAttribute("data-signal-id", rows[i].id);
        input.value = rows[i].time;
        var unit = document.createElement("span");
        unit.className = "tk-unit";
        unit.textContent = t("ui_seconds");
        row.appendChild(label);
        row.appendChild(input);
        row.appendChild(unit);
        fieldset.appendChild(row);
      }
      box.appendChild(fieldset);
    }
  }

  /* ------------------------------------------------------------------ */
  /* Scenario definitions                                                */
  /* ------------------------------------------------------------------ */

  // A signal is just a time and a textual instruction. The highest-time signal
  // is the "play" the countdown ends on (red); earlier ones are warnings. Each
  // builder returns { total, signals, repeat }. Special behaviour is keyed off
  // the action: "halfstart" starts the game clock on its final signal, and
  // "dispute" repeats its final signal.
  function buildScenario(id) {
    if (id === "timeout" && scenario && scenario.id === "betweenpoints") {
      return buildTimeoutBeforePull();
    }
    var rows = signalRowsForAction(id);
    if (!rows.length) {
      return null;
    }
    var last = rows.length - 1;
    var total = rows[last].time;
    var built = {
      total: total,
      signals: []
    };
    var i;
    for (i = 0; i < rows.length; i++) {
      var isFinal = i === last;
      built.signals.push({
        at: rows[i].time,
        text: rows[i].text,
        kind: isFinal ? "go" : "warn",
        final: isFinal,
        startsClock: id === "halfstart" && isFinal
      });
    }
    // Only "Call or discussion" repeats its final signal -- every gap between the
    // last two signals -- until the operator stops. Behaviour is fixed to the
    // action and does not depend on the signal text.
    if (id === "dispute" && rows.length >= 2) {
      var every = total - rows[last - 1].time;
      if (every > 0) {
        built.repeat = {
          from: total,
          every: every,
          text: rows[last].text,
          kind: "go"
        };
      }
    }
    return built;
  }

  function timeoutEndSignal() {
    var rows = signalRowsForAction("timeoutbeforepull");
    if (rows.length) {
      return rows[rows.length - 1];
    }
    return { time: 75, text: t("sig_end_timeout") };
  }

  function buildTimeoutBeforePull() {
    var timeoutEnd = timeoutEndSignal();
    var addedTime = num(timeoutEnd.time);
    var rows = signalRowsForAction("betweenpoints");
    var pullTime = rows.length ? rows[rows.length - 1].time : 0;

    // A5.5.2: the end-of-timeout is signalled `addedTime` seconds from the START
    // of the point (not from the call), then a fresh A5.4 sequence commences.
    // The timer keeps the point-start timeline (anchor), so every offset below
    // is measured from the start of the point.
    var built = {
      total: addedTime + pullTime,
      anchor: "point",
      signals: [
        { at: addedTime, text: timeoutEnd.text, kind: "warn" }
      ]
    };

    var i;
    for (i = 0; i < rows.length; i++) {
      built.signals.push({
        at: addedTime + rows[i].time,
        text: rows[i].text,
        kind: i === rows.length - 1 ? "go" : "warn",
        final: i === rows.length - 1
      });
    }
    return built;
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
      label.textContent = sig.text;
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
        el("tk-display-signal").textContent = sig.text;
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

    if (scenario.repeat && elapsed >= scenario.repeat.from + scenario.repeat.every) {
      var repeatCount = Math.floor((elapsed - scenario.repeat.from) / scenario.repeat.every);
      if (repeatCount > scenario.repeat.lastCount) {
        scenario.repeat.lastCount = repeatCount;
        el("tk-display-signal").textContent = scenario.repeat.text;
        setDisplayState(scenario.repeat.kind === "go" ? "zero" : "warn");
        beep(scenario.repeat.kind);
      }
    }

    // Repeat-enabled scenarios keep showing the countdown to the next repeat
    // signal after the first "play must restart" point has passed.
    var remaining = scenario.total - elapsed;
    if (remaining <= 0 && scenario.repeat) {
      var repeatElapsed = elapsed - scenario.repeat.from;
      if (repeatElapsed > 0) {
        var repeatRemainder = repeatElapsed % scenario.repeat.every;
        remaining = repeatRemainder === 0 ? 0 : scenario.repeat.every - repeatRemainder;
      }
      setDisplayState("zero");
    }

    // Non-repeating action clocks count down to zero and stay red there.
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
      scenario.repeat.lastCount = 0;
    }
    if (id === "halftime") {
      clock.halfTimeStarted = true;
      if (clock.activeCap === "half") {
        clock.dismissedCap = "half";
        renderCapAlert();
      }
    }
    if (id !== "halfstart" && !clock.running) {
      clock.startWarning = true;
      renderCapAlert();
    }

    el("tk-display-scenario").textContent = t("sc_" + id);
    el("tk-display-signal").innerHTML = "&nbsp;";
    setDisplayState("running");
    el("tk-timer-pause").disabled = false;
    setPauseLabel(false);
    el("tk-timer-stop").disabled = false;
    renderActionLimits(built);

    // Log this action in the game-clock mark list with its generic label.
    addMark("sc_" + id, t("sc_" + id));

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

  function capConfig() {
    return clock.capConfig || config;
  }

  function capMinutesToSeconds(value) {
    return num(value) * 60;
  }

  function renderCapAlert() {
    var body = el("tk-matchclock-body");
    var alert = el("tk-cap-alert");
    var text = el("tk-cap-text");
    if (!body || !alert || !text) {
      return;
    }

    var c = capConfig();
    var elapsed = clockElapsed();
    var active = "";
    var timeCap = capMinutesToSeconds(c.time_cap);
    var halfTimeCap = capMinutesToSeconds(c.half_time_cap);
    var timeCapReached = timeCap > 0 && elapsed >= timeCap;
    var halfTimeCapReached = !clock.halfTimeStarted && halfTimeCap > 0 && elapsed >= halfTimeCap;

    if (halfTimeCapReached && !clock.loggedCaps.half) {
      clock.loggedCaps.half = true;
      addMark("cap_half_time", t("cap_half_time_mark"));
    }
    if (timeCapReached && !clock.loggedCaps.time) {
      clock.loggedCaps.time = true;
      addMark("cap_time", t("cap_time_mark"));
    }

    if (timeCapReached && clock.dismissedCap !== "time") {
      active = "time";
    } else if (!timeCapReached && halfTimeCapReached && clock.dismissedCap !== "half") {
      active = "half";
    }

    clock.activeCap = active;
    body.className = "tk-matchclock-body tk-cap-" + (active || "none")
      + (clock.startWarning ? " tk-clock-start-warning" : "");
    if (active === "time") {
      alert.className = "tk-cap-alert";
      text.textContent = t("cap_time");
    } else if (active === "half") {
      alert.className = "tk-cap-alert";
      text.textContent = t("cap_half_time");
    } else {
      alert.className = "tk-cap-alert tk-hidden";
      text.innerHTML = "&nbsp;";
    }
  }

  function renderClock() {
    el("tk-matchclock-time").textContent = formatTime(clockElapsed());
    renderCapAlert();
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

  // Newest mark on top. Each mark is { label, labelNumber, time }.
  function renderMarks() {
    var box = el("tk-clock-marks");
    box.innerHTML = "";
    var i;
    for (i = clock.marks.length - 1; i >= 0; i--) {
      var row = document.createElement("div");
      row.className = "tk-side-row";
      var label = document.createElement("span");
      label.textContent = clock.marks[i].label + " " + clock.marks[i].labelNumber;
      var time = document.createElement("span");
      time.className = "tk-side-time";
      time.textContent = clock.marks[i].time;
      row.appendChild(label);
      row.appendChild(time);
      box.appendChild(row);
    }
  }

  // Log a labelled snapshot of the current game-clock time. Used by the manual
  // Mark button (generic label) and by every action when it starts.
  function addMark(key, label) {
    if (!Object.prototype.hasOwnProperty.call(clock.markCounts, key)) {
      clock.markCounts[key] = 0;
    }
    clock.markCounts[key]++;
    clock.marks.push({
      label: label,
      labelNumber: clock.markCounts[key],
      time: formatTime(clockElapsed())
    });
    renderMarks();
  }

  function markClock() {
    if (!clock.running) {
      return;
    }
    addMark("ui_mark", t("ui_mark"));
  }

  function startClock() {
    if (clock.running) {
      return;
    }
    if (!clock.capConfig) {
      clock.capConfig = copyObject(config);
    }
    clock.startWarning = false;
    clock.running = true;
    clock.startTs = Date.now();
    requestWakeLock();
    if (!clockInterval) {
      clockInterval = window.setInterval(renderClock, 250);
    }
    setClockButton();
    renderClock();
  }

  function pauseClock() {
    if (!clock.running) {
      return;
    }
    addMark("ui_pause", t("ui_pause"));
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
    clock.markCounts = {};
    clock.marks = [];
    clock.capConfig = null;
    clock.halfTimeStarted = false;
    clock.dismissedCap = "";
    clock.activeCap = "";
    clock.loggedCaps = {};
    clock.startWarning = false;
    if (clockInterval) {
      window.clearInterval(clockInterval);
      clockInterval = null;
    }
    renderClock();
    renderMarks();
    setClockButton();
    releaseWakeLock();
  }

  function dismissCapAlert() {
    if (clock.activeCap) {
      clock.dismissedCap = clock.activeCap;
      renderCapAlert();
    }
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
    el("tk-cap-dismiss").addEventListener("click", dismissCapAlert);

    el("tk-config-reset").addEventListener("click", resetConfig);
    el("tk-config-done").addEventListener("click", function () {
      readInputs();
      showScreen("timer");
    });
    el("tk-template-select").addEventListener("change", function () {
      setActiveTemplate(this.value);
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
    migrateLegacyConfig();
    activeTemplateId = resolveTemplateId(storageGet(TEMPLATE_KEY));
    loadConfig();
    populateTemplateSelector();
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
