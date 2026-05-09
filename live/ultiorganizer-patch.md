# Ultiorganizer compatibility patch notes

Changes made to the Live! skin to maintain compatibility with ultiorganizer after its
database access refactoring (removal of direct `mysqli_*` usage from library functions).

## Baseline

These changes apply on top of **Live! by BULA v1.9.16**, available at:
https://github.com/layoutd/live-by-bula

The patch file `ultiorganizer-compat.patch` in this directory contains all changes
as a standard unified diff and can be applied directly:

```sh
# From the live/ root directory:
patch -p1 < ultiorganizer-compat.patch
```

To verify the patch applies cleanly without making changes:

```sh
patch -p1 --dry-run < ultiorganizer-compat.patch
```

## Compat strategy

All `mysqli_fetch_all(fn(), MYSQLI_ASSOC)` calls have been replaced with
`DBFetchAllAssoc(fn())`. The `DBFetchAllAssoc()` helper accepts both a `mysqli_result`
(returned by older ultiorganizer versions) and a plain PHP array (returned by newer
versions), so the Live! skin works across both.

## Changes by file

### `api/AdminManager.php`

- `getAvailableSeasons()`: replaced `while ($season = DBFetchAssoc(Seasons()))` cursor
  loop with `foreach (DBFetchAllAssoc(Seasons()) as $season)`. `Seasons()` now returns
  an array in current ultiorganizer; `DBFetchAllAssoc` handles both old and new.

### `api/GameManager.php`

- `getGameDetail()`: replaced `mysqli_fetch_all(GameTeamScoreBorad(...), MYSQLI_ASSOC)`
  with `DBFetchAllAssoc(GameTeamScoreBorad(...))` for both home and visitor scoreboards.
- `getGameDetail()`: replaced `mysqli_fetch_all(GameGoals(...), MYSQLI_ASSOC)` with
  `DBFetchAllAssoc(GameGoals(...))`. `GameGoals()` now returns an array in current
  ultiorganizer; `DBFetchAllAssoc` handles both.
- `getGameDetail()`: `ShowSpiritComments()` now requires a `$seasoninfo` argument.
  Changed to `ShowSpiritComments($seasonInfo)`.
- `getGameDetail()`: remapped `GameGetSpiritPoints()` output from integer category-ID
  keys (`{1: val, 2: val, …}`) to the `catN` keys (`{cat1: val, cat2: val, …}`) that
  the live frontend expects. `GameGetSpiritPoints` has always returned
  `[category_id => value]`; the live JS computes totals via
  `D.cat1 + D.cat2 + … + D.cat5` so integer keys silently produce NaN. The remap uses
  `SpiritCategoryRows($seasonInfo['spiritmode'])` to look up each category's `index`
  field and build the `catN` key. An `is_array()` guard provides backward compatibility
  if the return type changes in a future ultiorganizer version. Results with no scored
  categories collapse to `null` so the frontend skips unscored games cleanly.

### `api/ReferenceData.php`

- `getReferenceData()`: `ShowSpiritComments()` now requires a `$seasoninfo` argument.
  Changed to `ShowSpiritComments($seasonInfo)`.

### `api/StandingsManager.php`

- `getSeriesSpiritBoard()`: replaced `mysqli_fetch_all(SeriesSpiritBoardAlt2(...), MYSQLI_ASSOC)`
  with `DBFetchAllAssoc(SeriesSpiritBoardAlt2(...))`. `SeriesSpiritBoardAlt2()` has
  always returned a PHP array; `DBFetchAllAssoc` is used for forward/backward compat.

### `api/TeamManager.php`

- `getTeamDetail()`: replaced `mysqli_fetch_all(TeamSpiritPointsGiven(...), MYSQLI_ASSOC)`
  with `DBFetchAllAssoc(TeamSpiritPointsGiven(...))`.
- `getTeamDetail()`: replaced `mysqli_fetch_all(TeamSpiritPointsReceived(...), MYSQLI_ASSOC)`
  with `DBFetchAllAssoc(TeamSpiritPointsReceived(...))`.
- `getTeamDetail()`: `ShowSpiritComments()` now requires a `$seasoninfo` argument.
  Changed to `ShowSpiritComments($seasonInfo)`.
- `getTeamDetail()`: replaced `mysqli_fetch_all($players, MYSQLI_ASSOC)` with
  `DBFetchAllAssoc($players)`. `$players` may be a `mysqli_result` or an array
  depending on which code path runs; `DBFetchAllAssoc` handles both.

### `admin.php`

- `checkSeasonExists()`: replaced direct `mysqli_prepare` / `mysqli_stmt_bind_param` /
  `mysqli_stmt_execute` / `mysqli_fetch_assoc` block with `DBEscapeString()` +
  `DBQueryToValue()`. Input is already validated to alphanumeric-only by the regex
  guard above, so the escaping is sufficient.

## Changes made in ultiorganizer (your patch notes for the other side)

The following changes were made in the ultiorganizer codebase that triggered these fixes:

- `ShowSpiritComments($seasoninfo)`: the `$seasoninfo` argument is now optional
  (default `null`). Callers that previously passed no argument will receive `false`;
  pass `$seasonInfo` explicitly for correct behaviour.
- `SeasonInfo()`: now includes a `spiritpoints` key (integer 0 or 1) computed from
  `spiritmode > 0`, for backward compatibility with code that used the old column name.
  Use `spiritmode` directly in new code.
- `Seasons()`: now returns a plain PHP array instead of a `mysqli_result`.
- `GameGoals()`: now returns a plain PHP array instead of a `mysqli_result`.
- `TeamSpiritPointsGiven()`, `TeamSpiritPointsReceived()`, `SeriesSpiritBoardAlt2()`:
  these have always returned plain PHP arrays; if your code wrapped them in
  `mysqli_fetch_all()`, remove the wrapper.
