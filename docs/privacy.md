# Privacy Tools

The privacy admin tools and the database operations they perform.

## Admin entry points

- `?view=admin/privacyplayer`: player privacy tools
- `?view=admin/privacyuser`: registered user privacy tools
- `?view=admin/dbadmin`: links to the privacy tools under the `Privacy` section

## Event snapshots

JSON event snapshots are portable competition packages, not privacy exports or backups. They include event-local competition data plus the `uo_player_profile` fields needed to link players (name, number, accreditation ID, birthdate, gender, public display fields), and exclude user accounts, roles, registration ownership, emails, API tokens, profile URLs, uploaded media and private identifiers such as `national_id`. Import links matching profiles without overwriting them.

## Player privacy tools

Export one player's data as a text report, or anonymize them while keeping competition history. Operations are logged to `uo_event_log` with `source='privacy'`, with the internal `player_id` or `profile_id` as target.

Selection is by name, but anchored to `uo_player_profile`: every `uo_player` row sharing the `profile_id` is the same person, even after a name change, and is in scope.

### Player data export

The player export currently includes rows from:

- `uo_player`
- `uo_player_profile`
- `uo_player_stats`
- `uo_played`
- `uo_goal`
- `uo_defense`
- `uo_license`
- `uo_accreditationlog`
- `uo_event_log` for player-targeted rows where `category='player'` and `id1` matches the linked `uo_player` rows
- `uo_event_log` privacy audit rows where `source='privacy'` and `id1` matches the selected internal `player:<id>` or `profile:<id>` target
- `uo_urls` for player profile links
- player profile image metadata from `uo_player_profile` and `uo_image`
- `uo_scoresheet_history` snapshots: the player's own `played[]`, `goals[]` and `defenses[]` entries, projected by player id and tagged with game and snapshot time. Roster entries carry name, team, number, captaincies and accreditation flags; goal entries carry only the player's own side (with the recorded jersey and name), point number, time, score and flags; defence entries carry sequence, time and flags. Old snapshots are the only record of earlier names, numbers and captaincies.
- `uo_scoresheet_history` change rows whose `detail` names the player (`played.player`, `played.players`, `goal.scorer`, `goal.assist`, `defense.player`): history id, game, time, target, action, matched field, and the row's own context (`num` as jersey on roster rows, `sequence` as the point or defence ordinal on scoring rows, times, scores, flags, and a captaincy's `role` and `team`). Keys naming other people are withheld.

`user_id` and `userid` are hidden in log-derived sections. Player log rows use `uo_event_log.id2` for the team, so the tools never match `id2`. Report downloads are logged.

### Player anonymization

Keeps competition history, removes personal data and direct identifiers:

| Table | Action |
|---|---|
| `uo_player` | keep; names set to `-`; clear `num`, `accreditation_id`, `reg_id`; `accredited = 0` |
| `uo_player_profile` | keep; names set to `-`; clear `email`, `num`, `nickname`, `birthdate`, `birthplace`, `nationality`, `throwing_hand`, `height`, `weight`, `position`, `gender`, `info`, `national_id`, `accreditation_id`, `story`, `achievements`, `image`, `profile_image`, `ffindr_id`; empty `public` |
| `uo_license` | delete rows matching the accreditation IDs |
| `uo_urls` | delete `owner='player'` rows for the `profile_id` |
| `uo_image` and files | delete the profile image row and `images/uploads/players/<profile_id>/` files, including thumbnails |
| `uo_accreditationlog` | delete rows for the player IDs |
| `uo_event_log` | delete player-category rows by `id1` (not `id2`, the team); then write one non-identifying audit entry |
| `uo_player_stats`, `uo_played`, `uo_goal`, `uo_defense` | unchanged; history stays linked to the kept rows |
| `uo_scoresheet_history` | rows kept; `detail` player ids left as references; snapshot `assist_name`, `scorer_name` and `played[].name` rewritten to `- -` by player id, so names recorded before a correction are reached too |

The snapshot scrub runs inside the anonymization transaction, whose read view is fixed at start, so a snapshot written concurrently keeps the old name. Prefer running outside live scoring, or run it again: the tool is idempotent and resolves its subject by ids anonymization does not clear.

## Free-text fields naming other people

Anonymization clears free text on the subject's own rows (`story` and `achievements` on `uo_player_profile`). Free text on other entities' rows can also name a person, and no per-subject query can find it:

- `uo_team_profile`: `coach`, `captain`, `story`, `achievements`
- `uo_club`: `contacts`, `story`, `achievements`
- `uo_comment`: the body
- `uo_scoresheet_history.snapshot`: `game.official`, `comment`, `events[].info` (the embedded player names are rewritten, see above)
- `uo_scoresheet_history.detail`: the `name` of an `official`/`update` row (the scorekeeper name), the only free-text name in `detail`

Removing these is a manual admin edit; a request about a coach, captain or club contact should include a check of them.

## Visitor counter

`uo_visitor_counter` stores one raw IP per unique visitor, read only as aggregates by `LogGetVisitorCount()`. It has no link to a person, so the per-subject tools cannot reach it. Set `DisableVisitorLogging` to stop recording IPs, or purge all rows from the visitor admin page (`LogResetVisitorCounter()`, superadmin).

## Registered user privacy tools

Export one registered user's data as a text report, or delete it including matching logs. Operations are logged with `source='privacy'`; downloads use the internal account row id as target, and deletion logs no identifier.

Report scope: `uo_users`, `uo_userproperties`, `uo_extraemail`, `uo_extraemailrequest`, `uo_enrolledteam`, `uo_registerrequest`, `uo_accreditationlog`, `uo_event_log` (rows where `user_id`, `id1` or `id2` matches), and `uo_scoresheet_history` rows with the user's `user_id`, without the `snapshot` column or the scorekeeper `name` in `official` rows, which describe other people. `UserUpdateInfo()` rewrites `user_id` in `uo_scoresheet_history` and `uo_event_log` when a login is renamed, so history follows the account and cannot be inherited by the next holder of the name.

Deletion:

- delete matching rows from `uo_event_log`, `uo_accreditationlog`, `uo_registerrequest`, `uo_passwordresetrequest` and `uo_userproperties`, then the `uo_users` row; `uo_extraemail`, `uo_extraemailrequest` and `uo_enrolledteam` cascade
- anonymize `uo_scoresheet_history` rows (`user_id` set to `-`, `ip` cleared); they belong to the game's history and go only with the game
- write one non-identifying audit entry

`uo_passwordresetrequest` has no foreign key, hence the explicit delete. It is left out of the report because a pending row holds a live reset token.
