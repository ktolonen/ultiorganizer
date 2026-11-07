# API

API structure, constraints and scope.

## Current design

- API lives under `/api`.
- Versioned routes use paths like `/api/v1/...`.
- `api/index.php` is the dedicated entry point.
- Responses are JSON only.

## Expectations

- Use consistent `status`, `data`, and `error` payloads with HTTP status codes.
- Keep normalization and filtering in `/api`.
- Keep SQL and shared data access in `lib/` as the single source of truth.
- Require rate limiting keyed by token and IP, with `429` and `X-RateLimit-*` headers on limit responses.
- The rate-limit IP is the connecting address (`REMOTE_ADDR`); `X-Forwarded-For` is ignored because clients can set it freely. Behind a reverse proxy, all clients of one token share a bucket. Rows from past windows are pruned once per window, by the first request that opens a new rate key; a `prune` sentinel row in `uo_api_rate_limit` tracks this.

## Public v1 scope

- Public API endpoints expose event lists, divisions, teams, games, gameplay, Timekeeper templates, and version metadata.
- Token authentication can be installation, event, or user scoped.
- The token goes in a request header: `Authorization: Bearer` (preferred), `Authorization: Token`, `X-Api-Token` or `X-Api-Key`. A `?token=` query parameter is refused with `401 missing_token`, because URLs end up in access and proxy logs.
- Event-scoped endpoints require the event to be marked visible in the public API.
- Games, pools and divisions follow the public timetable's visibility: a pool hidden from the public schedule (or a follower of a hidden playoff root) and an invalid division are left out of `divisions` and `games`, and `gameplay` answers `404 game_not_found` for their games. The same event flag also gates public `ext/` outputs, including generated external links, widgets, RSS feeds, XML helpers, and CSV exports.
- `gameplay` follows the event's public visibility settings: spirit scores are `null` unless the event shows them, and goal, event, media and half-time times and the time-on-offence statistics are `null` when the event hides scoresheet times.
- Historical data outside those endpoint shapes is not part of the current v1 surface.

## Documentation

OpenAPI documentation lives alongside the API in `api/openapi.json`.

## Access and examples

API documentation is available at:

- `https://your-host/api/v1/openapi` for production with rewrite rules enabled.
- `http://localhost:8080/api/v1/openapi` in the documented Docker development stack.
- `http://localhost:8000/api/index.php/v1/openapi` when using PHP's built-in server without rewrite rules.

Tokens are managed through the admin UI at `?view=admin/apitokens`.

Any valid token also lets a scorekeeping app keep score together with a scorekeeping link; revoking the token cuts that app off. See "Scorekeeping app endpoints" below.

Example requests:

```sh
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/events"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/version"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/teams?event=2025"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/divisions?event=2025"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/games?event=2025"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/gameplay?game=123"
curl -H "Authorization: Bearer YOUR_TOKEN" -H "Accept-Language: fi" "https://your-host/api/v1/timekeeper/templates"
```

Gameplay events include `time`, `team`, and `type`. Halftime-cap and time-cap events use type `half_cap` and `time_cap`, set `team` to `null`, and include the saved cap target in `target`.

`/api/v1/version` returns the Ultiorganizer application compatibility version,
the API route version, the installed database version recorded in the database,
and the active customization id and version. The Ultiorganizer application
version is read from `version.php`. The active customization version is read
from `cust/<customization>/version.php`, which may return a version string or
define `CUSTOMIZATION_VERSION`; missing customization metadata reports `0.0`.

`/api/v1/timekeeper/templates` returns the Timekeeper templates
(`docs/timekeeper.md`) as a list: each template's id, name, halftime and time
caps in minutes, and its signal rows per action, plus `default_template_id`.
Template names and signal texts are database translations in the configured
locale that best matches `Accept-Language`, or the default locale when none
matches; `Content-Language` names the locale and the answer carries
`Vary: Accept-Language`. Any valid token reads it, including a user-scoped one,
because the same data is on the public Timekeeper page.

## Scorekeeping app endpoints

A third-party scorekeeping app keeps score for a game by scanning the game's or field's existing scorekeeping QR code (`docs/scorekeeper.md`). It can keep score live, one write per point, or offline and upload the whole scoresheet afterwards.

| Route | Purpose |
| --- | --- |
| `GET /api/v1/scorekeeping/games` | the games the link covers now |
| `GET /api/v1/games/{id}/entry-context` | everything needed to keep one game, plus the token |
| `PUT /api/v1/games/{id}/game-data` | replace the whole scoresheet |
| `POST /api/v1/games/{id}/goals` | add the next goal |
| `DELETE /api/v1/games/{id}/goals/{num}` | delete the last goal |
| `PATCH /api/v1/games/{id}/goals/{num}` | correct a goal's scorer, assist or Callahan flag |
| `POST /api/v1/games/{id}/timeouts` | add a timeout |
| `DELETE /api/v1/games/{id}/timeouts/{home\|away}/{num}` | delete a team's last timeout |
| `POST /api/v1/games/{id}/spirit-stoppages` | add a spirit stoppage |
| `DELETE /api/v1/games/{id}/spirit-stoppages/{home\|away}/{num}` | delete a team's last spirit stoppage |
| `POST /api/v1/games/{id}/clock` | start, pause, resume, set or reset the game clock |
| `PUT /api/v1/games/{id}/note` | set or clear the game note (user-scoped token) |
| `POST /api/v1/games/{id}/score-taps` | add or take one point (result-only keeping) |
| `PUT /api/v1/games/{id}/rosters/{home\|away}` | replace one team's game roster |
| `PATCH /api/v1/games/{id}/metadata` | set the starting offence, halftime or official |
| `PUT\|DELETE /api/v1/games/{id}/caps/{half_cap\|time_cap}` | set or remove a halftime cap or time cap |
| `POST /api/v1/games/{id}/finalize` | save the final result |

**Two credentials.** Effective access is the app token's scope intersected with what the link covers; neither alone is enough.

- The app token (`uo_api_token`, any unrevoked token) goes in `Authorization: Bearer`. These routes read nothing else: not `?token=`, `X-Api-Token` or `Token `. An event-scoped token only works for games of its own event (`403 event_scope_mismatch`). A `user`-scoped token works like an installation token, so the link alone decides which games are covered, the same as when a logged-in user opens the link in the browser. Its history rows carry the token's user as `user_id`, and it may write the game note as that user, as a logged-in user with the link may in the browser; the request still gets none of that user's other rights.
- The scorekeeping link token goes in the `X-Scorekeeping-Token` header, never in the URL or the body. An unknown or replaced link is `401 invalid_scorekeeping_token` and writes a `security` log event without the token.

The QR code encodes `BASEURL/scorekeeper/?t=<32 hex>`. The app takes the part before `/scorekeeper/` as the installation root (the API is at `<root>/api/v1/`) and `t` as the link token. It should accept only `https` URLs (and `http://localhost` for development).

**Pairing.** An app setup QR code encodes `ultiorganizer-app://pair?root=<BASEURL>&token=<token>`, both URL-encoded (`ScorekeeperAppSetupUrl()`). `root` is the same prefix the scorekeeping link QR codes carry before `/scorekeeper/`, so the app can match a scanned link to the installation it paired with. Anyone holding the code has the token.

- Every logged-in user has one on `?view=user/scorekeepingapp`, linked from the menu. The page shows the user's active `user`-scoped token (`ApiUserToken()`) and when it was last used, or a "Create token" button when there is none. "Replace token" revokes the user's active user-scoped tokens and creates a new one (`ApiUserTokenReplace()`), so a lost phone stops working; "Remove token" only revokes them (`ApiUserTokenRevoke()`). Revoked rows stay, so admins and history rows can still name them. Renaming the login moves the tokens with it; deleting the user deletes them.
- On `?view=admin/apitokens`, each active token has one. Revoked tokens have none.

These routes work for private events and ignore the event's `api_public` and `anonymous_scorekeeping` settings: the app token is the gate. Maintenance mode answers `503`, and a read-only event refuses writes with `403 event_readonly`. Responses carry `Cache-Control: no-store`, and the routes bypass the persistent cache. The rate limit is the one in "Expectations", keyed by app token and IP; an installation whose app scores several fields from one address can raise `API_RATE_LIMIT`. Public reads of the same game lag a write by the persistent-cache TTL.

**Discovery.** `GET /scorekeeping/games` returns the link's event, the covered games (id, time, field, pool, home and away team ids and names, result state) and `available_from`. A game link returns one game, a field link the reservation's games. Outside the game day (`ScorekeeperOpenDays()`, with the 4 hour grace) the list is empty and `available_from` is the date of the game or reservation.

**Entry context.** `GET /games/{id}/entry-context` is one consistent read (`START TRANSACTION WITH CONSISTENT SNAPSHOT`, no lock): the game, both teams' eligible players and game rosters, goals in order (`num`, `home_score` and `away_score` are server-derived), timeouts and spirit stoppages per side, the game clock, the game note, result, starting offence, halftime, official, the rules (hidden goal times, timeouts per team, spirit stoppages, accreditation, bounds), and the state an upload keeps (cap events, forfeit, defence counts and referenced players). It has no contact data or history.

- `clock` is `null` while the event hides times. Otherwise `state` is `not_started`, `running`, `paused` or `stopped` (a game kept without a running clock, or final), and `elapsed` is the reading in seconds when the response was built; while running it advances one per second.
- `note` is the game note for a user-scoped token, the only kind that may write one, and `null` otherwise or when there is none.
- The strong `ETag` is `token`, a dash and 32 hex characters of a hash of the whole context, so it changes with anything in the context, the clock included, but not with a running clock's reading. `If-None-Match` with it answers `304 Not Modified` without a body while nothing changed; the request still counts against the rate limit. The upload's `If-Match` accepts either `token` or this `ETag`.

Players on the game roster who belong to neither team of the fixture are not listed, and an upload drops them.

**Concurrency token.** The token is the SHA-256 of what a complete upload replaces (fixture teams, rosters, goals, timeouts, spirit stoppages, result state, starting offence, halftime, official). Timer fields, defences, other events and notes are left out, so a running clock does not move it. Every successful write, live or upload, returns the resulting token as `ETag` and in the body, so the app can upload later without refetching.

**Complete upload.** `PUT /games/{id}/game-data` takes `Content-Type: application/json` (at most 256 KiB, else `413`; other types `415`), `Idempotency-Key` (1 to 64 printable ASCII characters, else `400`) and `If-Match` with the token (missing is `428 precondition_required`; a mismatch or `*` is `409 stale_token` without the current token). The body is a versioned document, every field required, unknown fields refused with `400 invalid_document` and the field path:

```json
{
  "version": 1,
  "home_team_id": 90300,
  "away_team_id": 90301,
  "rosters": {
    "home": [{"player_id": 90800, "number": 1, "captain": true, "spirit_captain": false}],
    "away": [{"player_id": 90803, "number": 4, "captain": false, "spirit_captain": false}]
  },
  "goals": [
    {"side": "home", "scorer_id": 90800, "assist_id": null, "callahan": true, "time": 180}
  ],
  "timeouts": {"home": [{"time": 100}], "away": []},
  "spirit_stoppages": {"home": [], "away": []},
  "result": {"home": 1, "away": 0, "state": "ongoing"},
  "starting_offence": "home",
  "halftime": null,
  "official": "Name"
}
```

Empty lists clear; `result` is `null`, `ongoing` or `final`. The server derives goal numbers and cumulative scores. Checked before any write, all under the game lock:

- the team ids match the fixture (`409 fixture_changed`) and the game is not a forfeit (`409 forfeit`);
- every player exists, belongs to the side's team and passes accreditation; scorers and assists are on the submitted roster of their side; a Callahan goal has no assist; scorer and assist differ (`400 validation_failed` with paths);
- goal times do not go backwards; when the event hides times they must be `null` and the server numbers them; bounds follow storage (at most 255 goals per side, jersey numbers 0 to 255 or `null` for an unnumbered player (the entry context reports those and the upload keeps them unnumbered), goal time up to 32767 seconds, `official` up to 50 characters, timeouts per side up to the pool format or the number already recorded);
- a `null` result requires no goals, and a result with goals equals the goal total;
- spirit stoppages are empty when the event does not use them;
- a player on the defence sheet stays on a roster (`409 retained_defense`). Cap events, defences, forfeit and game notes are kept, and an ongoing upload leaves the game clock alone; a final or cleared result resets it as the desktop does.

A successful upload first force-captures a scoresheet history snapshot (even with `DisableScoresheetHistory` on) so it can be restored from `user/scoresheethistory.php`, then replaces the content through the ordinary mutators, which record their rows with source `api`. The answer holds the new `token`, the `result` and counts. A game that goes from final back to ongoing has its pool standings recomputed.

**Live writes.** The other write routes take `Idempotency-Key` and, except the bodyless delete, a JSON body with the same size, type and field-path rules (`400 invalid_body`, `400 validation_failed`). They need no `If-Match`: like the browser Scorekeeper, a live write applies to the current state and does not check the token. Each answers with `game_id`, the resulting `token` (also the `ETag`), `result` (`null` before the game starts, else `home`, `away` and `state`) and its own fields. They refuse a forfeited game (`409 forfeit`) and record history rows with source `api` like the upload, but take no forced snapshot.

- `POST goals` takes `{side, scorer_id, assist_id, callahan, time}` and answers `201` with the stored `goal`. The server assigns `num` (last + 1) and the cumulative score, and raises the result to the new score unless a team's stored score is already higher, as the Scorekeeper goal form does. Scorer and assist must be on the side's game roster. `time` must not be earlier than the last goal (the browser form also refuses an equal time); when the event hides times it must be `null` and the server uses the last goal's time plus one second. A final game is `409 game_final`. A browser append that takes the same `num` at the same moment is retried once, then answered `409 goal_changed`.
- `DELETE goals/{num}` deletes goal `num` only while it is still the last goal (`409 goal_changed` otherwise, such as after another device added one), then lowers the result onto the remaining goals when the result was tracking them. A final game is `409 game_final`; correct one with the complete upload.
- `PATCH goals/{num}` takes any of `scorer_id`, `assist_id` and `callahan` and answers with the stored `goal`; the fields not sent keep their value. Side, time and the running score stay, and the complete upload changes those. The players follow the `POST goals` rules for the goal's side. A final game can be corrected, since the result does not change; an unknown `num` is `404 goal_not_found`.
- `POST timeouts` and `POST spirit-stoppages` take `{side, time}` and answer `201` with the stored entry, numbered after the team's last one. The limits are `rules.timeouts_per_team` (`409 timeout_limit`) and 4 spirit stoppages per team (`409 spirit_stoppage_limit`); an event without spirit stoppages answers `409 spirit_stoppages_disabled`.
- `DELETE timeouts/{side}/{num}` and `DELETE spirit-stoppages/{side}/{num}` delete the team's entry `num` only while it is still the team's last one (`409 timeout_changed` or `409 spirit_stoppage_changed` otherwise).
- `POST clock` takes `{action}`, plus `elapsed` in seconds (up to 32767) for `set`, and answers with `clock` as in the entry context. As the Scorekeeper clock controls do: `start` needs a clock that is neither running nor paused (`409 clock_running`) and makes the game ongoing; `pause` needs a running clock (`409 clock_not_running`); `resume` and `set` need a paused one (`409 clock_not_paused`); `reset` needs a 0 - 0 score (`409 score_not_zero`) and returns the game to not started. A final game is `409 game_final`, and while the event hides times the clock is not kept (`409 times_hidden`). Clock changes are not part of the token.
- `PUT note` takes `{text}` (trimmed, at most 4000 bytes) and answers with the stored `note`, `null` after an empty text clears it. It needs a user-scoped app token (`403 user_token_required`), because the browser offers notes only to a logged-in user, and the note and its event log rows are attributed to the token's user. Notes are not part of the token.
- `POST score-taps` takes `{side, delta}` with `delta` 1 or -1 and changes the stored score in one statement, as the Scorekeeper result page's taps do: a game becomes ongoing, and one back at 0-0 before the clock starts returns to not started. A final game is `409 game_final`, a score outside 0 to 1000 `409 score_out_of_range`.
- `PUT rosters/{home|away}` takes `{team_id, players}`, the players as in the upload. `team_id` must be the side's team (`409 fixture_changed`). Removing a player that a goal or the defence sheet refers to is `409 player_referenced` with their ids.
- `PATCH metadata` takes any of `starting_offence`, `halftime` and `official`; only the fields sent change, and `null` clears one.
- `PUT caps/{half_cap|time_cap}` takes `{time, point_cap}` (`time` up to 32767 seconds, as for goals), as the Scorekeeper cap page does: a new cap needs a `point_cap` above the higher score (`409 point_cap_too_low`), while correcting a recorded cap allows any `point_cap` from 1 to 255. A final game can take a cap. `DELETE caps/{half_cap|time_cap}` removes the cap, and removing an absent one succeeds. Both answer `200` with `cap` (`null` after a delete) and are refused with `409 times_hidden` while the event hides times, when the Scorekeeper shows no cap buttons. Caps are not part of the token; the entry context lists them under `retained.cap_events`.
- `POST finalize` takes `{home, away}`: the score the app has been keeping. The stored score must still be exactly that (`409 score_changed`), so a point another device recorded since is not finalized over. A game with no score yet cannot be finalized this way, and a different final score goes through score taps or the complete upload. Finalizing resets the clock and recomputes the pool standings and playoff moves inside the same transaction.

**Idempotency.** The first answer is stored in the transaction that made the writes (`uo_api_receipt`). The same key with the same method, path and body returns the stored answer with `Idempotent-Replay: true` and writes nothing, even if the game has moved on. The stored answer is looked up before the link coverage, maintenance, read-only and rights checks, so a retry still gets it after the game day ends or the event turns read-only or goes into maintenance. The same key with another body is `409 idempotency_conflict`. Only successes are stored. Each receipt also records the request's `User-Agent` (printable ASCII, at most 255 characters, left out while `DisableVisitorLogging` is on), so an admin can tell which app or client made a write; apps should send a descriptive one such as `UltiorganizerApp/1.0`.

**Failures.** Each write runs in one transaction under the game row lock (`lib/scorekeepingapi.functions.php`). Data, history and receipt commit or roll back together. A deadlock or lock timeout is retried twice, then answered `503 busy` with `Retry-After`. A request that dies without an answer becomes a JSON `500` and nothing is saved.

**Offline workflow.** Fetch the entry context before the game and keep the token. Record offline, then upload with `If-Match` before the link stops covering the game (the game day plus the 4 hour grace); a new write after that is `403 game_not_covered`. On `409 stale_token` fetch the entry context again, merge on the app side and upload again with a new `Idempotency-Key`; on a network error resend the same request with the same key.

**Live workflow.** Fetch the entry context, set the rosters, then send one write per event with a new key each. Queue writes while offline and send them in order with their original keys; a replayed write changes nothing. On `409 goal_changed` or `409 score_changed` another device has written to the game: fetch the entry context again before continuing. If the app falls too far behind, it can switch to a complete upload with the last token it received.

```sh
curl -H "Authorization: Bearer APP_TOKEN" -H "X-Scorekeeping-Token: LINK_TOKEN" \
  "https://your-host/api/v1/games/123/entry-context"
curl -X PUT -H "Authorization: Bearer APP_TOKEN" -H "X-Scorekeeping-Token: LINK_TOKEN" \
  -H 'If-Match: "TOKEN"' -H "Idempotency-Key: 5f0c2f4a" -H "Content-Type: application/json" \
  --data-binary @game.json "https://your-host/api/v1/games/123/game-data"
curl -X POST -H "Authorization: Bearer APP_TOKEN" -H "X-Scorekeeping-Token: LINK_TOKEN" \
  -H "Idempotency-Key: 9b1d77e0" -H "Content-Type: application/json" \
  --data '{"side":"home","scorer_id":10,"assist_id":11,"callahan":false,"time":1260}' \
  "https://your-host/api/v1/games/123/goals"
```
