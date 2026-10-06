# Scoreboard

`scoreboard/` is a public, no-login, full-screen display of one game's score with team names, meant for a screen or tablet at the field. It reads game data only and writes nothing; the session carries only the interface language.

## Entrypoint

- `scoreboard/index.php`: bootstrap, the game picker and board shell, and the JSON feed
  (`?json=list`, `?json=game&game=ID`).
- `scoreboard/scoreboard.css`: black background, white names, yellow scores for daylight readability.
  Sizes use `vmin`, so the board scales to any screen; portrait screens stack the teams.
- `script/scoreboard.js`: ES5 client. Picker, polling, name fitting, fullscreen, and screen wake lock.
- `scoreboard/` is a required path in `docs/release/build-release.sh`.

## Behavior

- The picker lists ongoing games first, then games still to start today in the event's timezone,
  in time order. Only games of public events (`public_event=1`) are listed or served.
- The selected game is kept in the URL hash (`#game=ID`), so a reload or a bookmarked link returns
  to the same board.
- The board polls the feed every 3 seconds (the picker every 15) and flashes the side whose score
  changed. A finished game stays on the board showing its final score.
- Data access lives in `ScoreboardGames()` and `ScoreboardGame()` in `lib/game.functions.php`.
