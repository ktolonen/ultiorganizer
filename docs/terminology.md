# Ultiorganizer Terminology Reference

This document is a terminology guide for Ultiorganizer documentation and review.
It standardizes preferred wording for docs, specs, commit messages, and review comments.

This document does not require code, API, database, translation, or UI renames.
Current runtime names and legacy identifiers are documented as aliases where needed.

## Purpose and Scope

- Canonical terms in this document follow WFDF rules spelling where applicable.
- Only terms relevant to Ultiorganizer as it exists today are included.
- Alias mappings cover words reviewers will encounter in current code, docs, API payloads, and UI labels.
- Abbreviations are for narrow table layouts only, not normal prose.

## Canonical Terms

| Preferred term | Meaning in Ultiorganizer | Notes |
| --- | --- | --- |
| Event | User-facing competition scope. | In code and database context, this is usually a `season`. |
| Division | Competition category within an event. | In code and database context, this is usually a `series`. |
| Pool | Group or stage inside a division. | Pools are used for scheduling, standings, and game grouping. |
| Team | Competitive team in a game, pool, or division. | |
| Roster | List of players for a team or for a game-specific played roster. | The detailed scoresheet uses a game-specific played roster. |
| Game | Scheduled contest between two teams. | Main aggregate state lives on `uo_game`. |
| Result | Aggregate game state expressed as the home and visitor score. | Use for current or final game result. |
| Score | Numeric side of a result. | Use for aggregate score values such as home score and visitor score. |
| Point | One scoring sequence in the detailed scoresheet flow. | In practice, detailed point data is stored as goal rows. |
| Goal | Recorded scoring event in a game. | Detailed goal rows live in `uo_goal`. |
| Assist | Final pass credited on a goal. | |
| Scorer | Player credited with a goal. | |
| Callahan | Goal flagged as a Callahan. | |
| Timeout | Ordinary timeout recorded during a game. | |
| Spirit timeout | Spirit-specific timeout recorded separately from ordinary timeouts. | |
| Halftime | Halftime marker or halftime end time for a game. | Prefer `halftime`, not `half-time`. |
| Offence | Starting possession or offence-based gameplay/stat concept. | Prefer `offence`, not `offense`, in new docs. |
| Turnover | Recorded possession change in gameplay data. | |
| Defence | Recorded defensive play/stat tracked through the defence sheet and defence stats. | Prefer `defence`, not `defense`, in new docs. |
| Scoresheet | Detailed game record combining roster, goals, timeouts, note, official, and related metadata. | In Ultiorganizer this is a concept, not a single table. |
| Gameplay | Replay or view of saved game goals and events. | Used by gameplay pages and API responses. |
| Game event | Recorded non-goal gameplay marker. | Includes turnovers, offence markers, timeouts, spirit timeouts, and media-linked events. |
| Game note | Free-text note attached to a game. | Stored through comment helpers. |
| Game official | Official name stored with a game. | |
| Captain | Player marked as captain in game or spirit-related flows. | |
| Spirit score | Spirit scoring submission and total for a game. | Current UI often says `Spirit points`. |
| Spirit of the Game (SOTG) | Spirit scoring context and totals. | |
| Winning score | Goals required to win under pool or format settings. | Repo fields also use `winningscore`. |
| Point cap | Score cap used by pool or format settings. | Repo fields also use `scorecap`. |
| Time cap | Time-cap setting used by pool or format settings. | Repo fields also use `timecap`. |

## Aliases and Legacy Terms

These terms are recognized in the current repository. They are not the preferred wording for new documentation unless there is a reason to mirror existing runtime names exactly.

| Preferred term | Current aliases in repo | Notes |
| --- | --- | --- |
| Event | `season`, `Current event`, `Select event` | Use `event` in user-facing docs; expect `season` in code and DB context. |
| Division | `series`, `series_id`, `seriesname` | This is the main legacy/internal naming mismatch in the repo. |
| Result | `homescore`, `visitorscore`, `final score`, `current score` | Use `result` for the aggregate state of a game. |
| Goal | `done`, `goals`, `ishomegoal` | `done` appears in stat queries and internal result rows. |
| Assist | `fedin`, `pass` | `pass` appears in legacy score-entry forms; `fedin` appears in stats helpers and API normalization. |
| Scorer | `goal`, `scorer` | Legacy score-entry forms use `goal` as the scorer input field name. |
| Callahan | `iscallahan`, `Callahan-goal` | `iscallahan` is the stored flag; replay views also use `Callahan-goal`. |
| Offence | `offense`, `First offense`, `starting on offense`, `offence_points`, `time_on_offence`, `goals_from_offence` | Use WFDF spelling in new docs, but recognize existing alternate spellings and field names. |
| Timeout | `time-out`, `timeouts`, `timeout` | Event rows use `timeout` as the type string. |
| Spirit timeout | `spirit_timeout`, `Spirit timeouts` | `spirit_timeout` is the event type string. |
| Scoresheet | `Game scoresheet`, `fill in scoresheet` | The repo uses both generic and page-specific scoresheet labels. |
| Gameplay | `gameplay.php`, `gameplay` endpoint | Refers to saved game replay, not general game theory. |
| Game event | `uo_gameevent`, `GameEvents()` | Also used indirectly through replay views and API event arrays. |
| Game note | `comment`, `COMMENT_TYPE_GAME`, `Game comment` | Comment storage names are broader than the preferred user-facing term. |
| Game official | `official`, `Game official(s)` | Storage field is `official`; UI labels vary slightly. |
| Spirit score | `Spirit points` | Current UI often says `Spirit points`; use `Spirit score` in new docs when discussing the concept. |
| Defence | `defense`, `Defense`, `uo_defense`, `Defense sheet`, `deftotal` | Use WFDF spelling in new docs, but recognize existing alternate spellings and internal names. |

## Abbreviations for Narrow Tables

Use these only when space is constrained, such as statistics tables, standings tables, exports, or compact scoreboards.

| Full term | Approved short form | Notes |
| --- | --- | --- |
| Total | `Tot.` | Default short form for total columns. |
| Goals | `G` | Use only when the table context is clearly goal-based. |
| Assists | `A` | Use only when the table context is clearly player stats. |
| Callahans | `Call.` | Prefer this over a single-letter shorthand. |
| Games played | `GP` | Prefer `GP` over `G` to avoid confusion with goals. |
| Average | `Avg.` | |
| Timeouts | `TO` | Use in compact stat or summary tables only. |
| Spirit timeouts | `STO` | Use in compact stat or summary tables only. |
| Defence | `D` | Use only when the table is explicitly about defensive stats. |
| Spirit of the Game | `SOTG` | Existing standard abbreviation. |

## Review Rules

- Prefer canonical terms from this document in new documentation, specs, and review comments.
- Recognize listed aliases in existing code and docs without treating them as automatic errors.
- Do not interpret this document as a mandate to rename code, API fields, database columns, translations, or UI strings.
- Distinguish `goal` from `score` carefully:
  - `goal` is a recorded scoring event
  - `score` is the numeric state inside a game result
- Prefer `division` over `series` and `event` over user-facing `season` in new documentation, unless you are describing current code or schema.
- Use abbreviations only in constrained layouts. Avoid inventing new abbreviations in normal prose.
