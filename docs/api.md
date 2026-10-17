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

- Public API endpoints expose event lists, divisions, teams, games, gameplay, and version metadata.
- Token authentication can be installation, event, or user scoped.
- Event-scoped endpoints require the event to be marked visible in the public API. The same event flag also gates public `ext/` outputs, including generated external links, widgets, RSS feeds, XML helpers, and CSV exports.
- `gameplay` follows the event's public visibility settings: spirit scores are `null` unless the event shows them, and goal, event and half-time times and the time-on-offence statistics are `null` when the event hides scoresheet times.
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
```

Gameplay events include `time`, `team`, and `type`. Halftime-cap and time-cap events use type `half_cap` and `time_cap`, set `team` to `null`, and include the saved cap target in `target`.

`/api/v1/version` returns the Ultiorganizer application compatibility version,
the API route version, the installed database version recorded in the database,
and the active customization id and version. The Ultiorganizer application
version is read from `version.php`. The active customization version is read
from `cust/<customization>/version.php`, which may return a version string or
define `CUSTOMIZATION_VERSION`; missing customization metadata reports `0.0`.

## Scorekeeping app endpoints

A third-party scorekeeping app keeps score for a game by scanning the game's or field's existing scorekeeping QR code (`docs/scorekeeper.md`). Phase 2 serves the offline-upload flow: discovery, the entry context and a complete upload. The live appends (single goals, timeouts, score taps, finalize) come later.

| Route | Purpose |
| --- | --- |
| `GET /api/v1/scorekeeping/games` | the games the link covers now |
| `GET /api/v1/games/{id}/entry-context` | everything needed to keep one game, plus the token |
| `PUT /api/v1/games/{id}/game-data` | replace the whole scoresheet |

**Two credentials.** Effective access is the app token's scope intersected with what the link covers; neither alone is enough.

- The app token (`uo_api_token`, any unrevoked `installation` or event-scoped token) goes in `Authorization: Bearer`. These routes read nothing else: not `?token=`, `X-Api-Token` or `Token `. A `user`-scoped token is refused with `403 invalid_scope`, and an event-scoped token only works for games of its own event (`403 event_scope_mismatch`).
- The scorekeeping link token goes in the `X-Scorekeeping-Token` header, never in the URL or the body. An unknown or replaced link is `401 invalid_scorekeeping_token` and writes a `security` log event without the token.

The QR code encodes `BASEURL/scorekeeper/?t=<32 hex>`. The app takes the part before `/scorekeeper/` as the installation root (the API is at `<root>/api/v1/`) and `t` as the link token. It should accept only `https` URLs (and `http://localhost` for development).

These routes work for private events and ignore the event's `api_public` and `anonymous_scorekeeping` settings: the app token is the gate. Maintenance mode answers `503`, and a read-only event refuses writes with `403 event_readonly`. Responses carry `Cache-Control: no-store`, and the routes bypass the persistent cache. The rate limit is the one in "Expectations", keyed by app token and IP; an installation whose app scores several fields from one address can raise `API_RATE_LIMIT`. Public reads of the same game lag a write by the persistent-cache TTL.

**Discovery.** `GET /scorekeeping/games` returns the link's event, the covered games (id, time, field, pool, home and away team ids and names, result state) and `available_from`. A game link returns one game, a field link the reservation's games. Outside the game day (`ScorekeeperOpenDays()`, with the 4 hour grace) the list is empty and `available_from` is the date of the game or reservation.

**Entry context.** `GET /games/{id}/entry-context` is one consistent read (`START TRANSACTION WITH CONSISTENT SNAPSHOT`, no lock): the game, both teams' eligible players and game rosters, goals in order (`num`, `home_score` and `away_score` are server-derived), timeouts and spirit stoppages per side, result, starting offence, halftime, official, the rules (hidden goal times, timeouts per team, spirit stoppages, accreditation, bounds), and the state an upload keeps (cap events, forfeit, defence counts and referenced players). It has no game notes, contact data or history. `token` is also the strong `ETag`. Players on the game roster who belong to neither team of the fixture are not listed, and an upload drops them.

**Concurrency token.** The token is the SHA-256 of what a complete upload replaces (fixture teams, rosters, goals, timeouts, spirit stoppages, result state, starting offence, halftime, official). Timer fields, defences, other events and notes are left out, so a running clock does not move it. Every successful upload returns the resulting token as `ETag` and in the body, so the app can upload again without refetching.

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
- goal times do not go backwards; when the event hides times they must be `null` and the server numbers them; bounds follow storage (at most 255 goals per side, jersey numbers 0 to 255, goal time up to 32767 seconds, `official` up to 50 characters, timeouts per side up to the pool format or the number already recorded);
- a `null` result requires no goals, and a result with goals equals the goal total;
- spirit stoppages are empty when the event does not use them;
- a player on the defence sheet stays on a roster (`409 retained_defense`). Cap events, defences, forfeit and game notes are kept, and an ongoing upload leaves the game clock alone; a final or cleared result resets it as the desktop does.

A successful upload first force-captures a scoresheet history snapshot (even with `DisableScoresheetHistory` on) so it can be restored from `user/scoresheethistory.php`, then replaces the content through the ordinary mutators, which record their rows with source `api`. The answer holds the new `token` and counts. A game that goes from final back to ongoing has its pool standings recomputed.

**Idempotency.** The first answer is stored in the transaction that made the writes (`uo_api_receipt`). The same key with the same method, path and body returns the stored answer with `Idempotent-Replay: true` and writes nothing, even if the game has moved on; the same key with another body is `409 idempotency_conflict`. Only successes are stored.

**Failures.** The write runs in one transaction under the game row lock (`lib/scorekeepingapi.functions.php`). Data, history and receipt commit or roll back together. A deadlock or lock timeout is retried twice, then answered `503 busy` with `Retry-After`. A request that dies without an answer becomes a JSON `500` and nothing is saved.

**Offline workflow.** Fetch the entry context before the game and keep the token. Record offline, then upload with `If-Match`. On `409 stale_token` fetch the entry context again, merge on the app side and upload again with a new `Idempotency-Key`; on a network error resend the same request with the same key.

```sh
curl -H "Authorization: Bearer APP_TOKEN" -H "X-Scorekeeping-Token: LINK_TOKEN" \
  "https://your-host/api/v1/games/123/entry-context"
curl -X PUT -H "Authorization: Bearer APP_TOKEN" -H "X-Scorekeeping-Token: LINK_TOKEN" \
  -H 'If-Match: "TOKEN"' -H "Idempotency-Key: 5f0c2f4a" -H "Content-Type: application/json" \
  --data-binary @game.json "https://your-host/api/v1/games/123/game-data"
```
