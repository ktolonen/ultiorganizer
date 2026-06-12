# Timekeeper

This page documents the standalone Timekeeper app under `timekeeper/`.

Timekeeper is a public, no-login officiating aid that helps a game official keep the WFDF-style
time limits. It opens directly on the game (timer) view; the official taps an action button and a
timer counts down to zero (turning red at zero), while the screen and an optional beep show what to
signal and when. A continuous game clock runs alongside the action timers. The time limits come from
installation-wide templates, remain editable locally in the browser, and the interface language is
inherited from Ultiorganizer and can be changed from the footer.

Unlike Scorekeeper and Spiritkeeper, Timekeeper is **not tied to a game or event** and requires
no authentication. It reads installation-level Timekeeper templates but does not read or write game
or event data; the session only carries the inherited interface language.

## Entrypoint

- `timekeeper/index.php`: bootstrap, locale handling, and the full single-page shell (language,
  configuration, and timer screens). It emits the available templates (`TIMEKEEPER_TEMPLATES`), the
  default template id, and translated labels consumed by the client script.
- `script/timekeeper.js`: all client logic (screen switching, configuration persistence, the timer
  engine, the game clock, audio, and optional screen wake lock). Written as ES5 to match the
  project ESLint configuration.
- `admin/timekeepertemplates.php` and `admin/addtimekeepertemplate.php`: superadmin template list and
  edit screens. Template data access lives in `lib/timekeeper.functions.php`.

## Screens

1. **Timer** (default first view) — the action buttons (six equally sized buttons in rows, with
   the most-used Start of point first), the active scenario's large countdown readout plus current
   signal text and colour state, Pause/Resume and Stop, and the always-visible game clock. Both clock
   areas carry a light-grey side panel: the action clock lists the running scenario's signal limits
   (each crossed out as it passes), and the game clock lists marked snapshot times (see below).
2. **Time limits** — template selector plus numeric inputs for every template signal time and the
   game-clock cap times. The selected template and unsaved local edits are persisted in the browser
   (`localStorage`). Reset returns the current template's signal times to its database defaults.
   Reachable from the footer.
3. **Language** — flag links that reload with `?locale=...` to set the gettext locale server-side.
   The language is inherited from Ultiorganizer; this screen is reachable from the footer "Change
   language" control. The footer button plus this flag block are a self-contained pattern intended
   to be copied into Scorekeeper and Spiritkeeper later (using each app's own path prefix for the
   flag image `src`).

## Timing model

Every button is pressed at the moment its real-world event occurs. The selected template provides
signal rows (`action`, `signal`, and elapsed `time`); the action clock starts from the highest
configured signal time, counts **down to zero**, and turns **red** at zero. Signals fire at the
configured elapsed times from the press. Elapsed time is computed from a stored `Date.now()` start
timestamp on every render tick — never by accumulating interval counts — so timers stay accurate
when a mobile browser throttles or suspends background timers. An optional Screen Wake Lock keeps the
phone awake while a timer or the game clock runs. At each signal point the display changes
state/colour and, unless muted, a short Web Audio beep sounds (no audio asset files).

### Scenarios and WFDF defaults

- **Start of game**: pre-start warning and start signal; starting it also starts the game clock.
- **Start of point**: offence warning 45 / defence warning 60 / play 75.
- **Timeout**: after the pull (WFDF A5.6), timed from the call: 45 (offence 30 s warning) / 60
  (offence 15 s warning) / 75 (defence 15 s warning) / 90 (play). A template-only **Timeout before
  pull** group stores the "Timeout over" signal at 75 seconds. If Timeout is pressed while the
  **Start of point** timer is running, that value is added to the current point-start timeline:
  Timeout over fires after the added duration, and any remaining Start of point signals are shifted
  by that duration.
- **Halftime**: a configurable duration (420 seconds) with a warning before the end (30), then the
  halftime-over signal.
- **Call or discussion**: a first signal (45) and a "play must restart" point (60); after that, the
  clock counts down each repeat interval and the "play must restart" signal repeats every configured
  interval (15) until play resumes (A5.7.3).
- **Disc retrieval**: after the pull once the disc comes to rest, or after an out-of-bounds turnover
  once the disc comes to rest, signals play after 20 seconds (A5.8).
- **Game clock caps**: halftime cap defaults to 55 minutes and time cap defaults to 100 minutes.
  Score-dependent target handling remains outside Timekeeper.

Superadmins can add installation-specific templates with their own signal schedules and editable
signal texts. Action labels remain static gettext-backed interface strings. Signal texts are stored
with the template and rendered through the database translation mechanism. Public users can choose a
template and make local, unsaved timing adjustments for their browser.

### Game clock

A separate continuous count-up clock shown on the timer screen for any scenario. The primary button
starts the clock, then becomes **Mark** while running. The light-grey side list is an event log of
numbered snapshots (newest first), each showing a label, per-label running number, and the
game-clock time: pressing **Mark** logs a generic "Mark" entry (e.g. a goal time), and starting any
action also logs an entry with that action's name (e.g. "Timeout", "Start of point"). A secondary
button pauses (a spirit stoppage or a long technical/injury stoppage) and logs a "Pause" entry; when
paused the primary button resumes. Reset clears the clock and the log. If the clock reaches the
configured halftime cap before the Halftime action has been started, the game clock area turns
yellow and shows a dismissible cap notice. If it reaches time cap, the game clock area turns red and
shows a dismissible time-cap notice. Each cap is also logged once in the game-clock event log. Cap
timing uses the running game-clock time, excluding pauses.

If any action other than Start of game is started while the game clock is stopped, the game-clock
area turns red until the clock is started or resumed. This highlights a likely missed game-clock
start without interrupting the action timer.

## Out of scope

Timekeeper deliberately does not track score, derive the cap target, or count team timeouts — that
belongs to Scorekeeper and the scoresheet. It also omits WFDF limits that carry no timekeeper signal
(such as the pre-game toss).

## Release packaging

`timekeeper/` and `script/timekeeper.js` are tracked runtime files, so they are included
automatically by `docs/release/build-release.sh`; `timekeeper` is listed among the package's
required paths.
