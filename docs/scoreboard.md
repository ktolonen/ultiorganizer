# Scoreboard

`scoreboard/` is a public, no-login, full-screen display of one game's score with team names, meant for a screen or tablet at the field. It reads game data only and writes nothing; the session carries only the interface language.

## Entrypoint

- `scoreboard/index.php`: bootstrap, the game picker and board shell, and the JSON feed
  (`?json=list`, `?json=game&game=ID`).
- `scoreboard/scoreboard.css`: black background, white names, yellow scores for daylight readability.
  Sizes use `vmin`, so the board scales to any screen; portrait screens stack the teams. Each score
  fills the space its team name leaves (container query units on `.sb-score-box`), and the script
  gives both scores the smaller of the two sizes.
- `script/scoreboard.js`: ES5 client. Picker, polling, name and score fitting, fullscreen, and screen wake lock.
- `scoreboard/` is a required path in `docs/release/build-release.sh`.

## Behavior

- The picker lists ongoing games first, then games still to start today in the event's timezone,
  in time order. Only games of public events (`public_event=1`) are listed or served.
- The selected game is kept in the URL hash (`#game=ID`), so a reload or a bookmarked link returns
  to the same board.
- The board polls the feed every 3 seconds (the picker every 15) and flashes the side whose score
  changed. A finished game stays on the board showing its final score.
- While a scorekeeper runs the live game clock (see `docs/scorekeeper.md`), the board shows the
  elapsed time above the teams; a paused clock pulses. The feed's `clock` is `null` when no clock
  runs: before the start, with `No game clock` or `hide_time_on_scoresheet`, and after the game
  ends, which clears the timer. Between polls the client ticks from the last reading, re-anchoring
  only on a pause change or more than a second of drift. The game feed bypasses the persistent
  cache so a pause shows up on the next poll.
- Data access lives in `ScoreboardGames()` and `ScoreboardGame()` in `lib/game.functions.php`.
