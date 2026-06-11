# Timekeeper

This page documents the standalone Timekeeper app under `timekeeper/`.

Timekeeper is a public, no-login officiating aid that helps a game official keep the WFDF-style
time limits. It opens directly on the game (timer) view; the official taps an action button and a
timer counts down to zero (turning red at zero), while the screen and an optional beep show what to
signal and when. A continuous game clock runs alongside the action timers. The time limits are
editable, and the interface language is inherited from Ultiorganizer and can be changed from the
footer.

Unlike Scorekeeper and Spiritkeeper, Timekeeper is **not tied to a game or event** and requires
no authentication. It does not read or write any competition data — the session only carries the
inherited interface language.

## Entrypoint

- `timekeeper/index.php`: bootstrap, locale handling, and the full single-page shell (language,
  configuration, and timer screens). It emits the WFDF default offsets (`TIMEKEEPER_DEFAULTS`) and
  the translated labels (`TIMEKEEPER_I18N`) consumed by the client script.
- `script/timekeeper.js`: all client logic (screen switching, configuration persistence, the timer
  engine, the game clock, audio, and optional screen wake lock). Written as ES5 to match the
  project ESLint configuration.

## Screens

1. **Timer** (default first view) — the action buttons (six equally sized buttons in two rows, with
   the most-used Start of point first), the active scenario's large countdown readout plus current
   signal text and colour state, Pause/Resume and Stop, and the always-visible game clock. Both clock
   areas carry a light-grey side panel: the action clock lists the running scenario's signal limits
   (each crossed out as it passes), and the game clock lists marked snapshot times (see below).
2. **Time limits** — numeric inputs (seconds) for every signal offset, seeded with the WFDF
   defaults, a "Reset" button, and persistence in the browser (`localStorage`). Only the offsets are
   editable; signal labels are fixed generic text. Reachable from the footer.
3. **Language** — flag links that reload with `?locale=...` to set the gettext locale server-side.
   The language is inherited from Ultiorganizer; this screen is reachable from the footer "Change
   language" control. The footer button plus this flag block are a self-contained pattern intended
   to be copied into Scorekeeper and Spiritkeeper later (using each app's own path prefix for the
   flag image `src`).

## Timing model

Every button is pressed at the moment its real-world event occurs. The action clock always counts
**down to zero** and turns **red** at zero (the moment play must start); signals fire at the
configured offsets from the press. Elapsed time is computed from a stored `Date.now()` start
timestamp on every render tick — never by accumulating interval counts — so timers stay accurate
when a mobile browser throttles or suspends background timers. An optional Screen Wake Lock keeps the
phone awake while a timer or the game clock runs. At each signal point the display changes
state/colour and, unless muted, a short Web Audio beep sounds (no audio asset files).

### Scenarios and WFDF defaults (seconds)

- **Start of game**: pre-start warnings at 60 and an optional second lead, then the start signal;
  starting it also starts the game clock. (The second-half restart is handled by **Halftime**, which
  rolls into the start-of-point limits.)
- **Start of point**: offence warning 45 / defence warning 60 / play 75.
- **Timeout**: after the pull (WFDF A5.6), timed from the call: 45 (offence 30 s warning) / 60
  (offence 15 s warning) / 75 (defence 15 s warning) / 90 (play). If pressed while the **Start of
  point** timer is running, it is a timeout *before the pull* (A5.5): it adds the configured time
  (75 s) to the ongoing point-start timeline, signals "Timeout over" at that mark measured from the
  start of the point (A5.5.2), and then the start-of-point sequence recommences. (Pressed 30 s into
  the point, the countdown jumps from 0:45 to 2:00 and "Timeout over" fires 45 s later.)
- **Halftime**: a configurable duration (60) with a warning before the end (30), then the
  start-of-point limits apply.
- **Call or discussion**: a first signal (45) and a "play must restart" point (60); after that, the
  clock counts down each repeat interval and the "play must restart" signal repeats every configured
  interval (15) until play resumes (A5.7.3).

A tournament with its own limits (for example SLKL's 30/45/60 start-of-point sequence and a
one-minute halftime) is supported by editing these offsets; there is no fixed WFDF/SLKL selector.

### Game clock

A separate continuous count-up clock shown on the timer screen for any scenario. The primary button
starts the clock, then becomes **Mark** while running. The light-grey side list is an event log of
numbered snapshots (newest first), each showing a label, per-label running number, and the
game-clock time: pressing **Mark** logs a generic "Mark" entry (e.g. a goal time), and starting any
action also logs an entry with that action's name (e.g. "Timeout", "Start of point"). A secondary
button pauses (a spirit stoppage or a long technical/injury stoppage) and logs a "Pause" entry; when
paused the primary button resumes. Reset clears the clock and the log.

## Out of scope

Timekeeper deliberately does not track score, derive halftime from a goal count or time cap, or
count team timeouts — that belongs to Scorekeeper and the scoresheet. It also omits WFDF limits
that carry no timekeeper signal (the 20-second post-pull/out-of-bounds limit and the pre-game toss).

## Release packaging

`timekeeper/` and `script/timekeeper.js` are tracked runtime files, so they are included
automatically by `docs/release/build-release.sh`; `timekeeper` is listed among the package's
required paths.
