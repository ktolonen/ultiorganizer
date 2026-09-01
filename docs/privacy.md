# Privacy Tools

This document summarizes the current privacy-related admin tools in Ultiorganizer and the database operations they perform.

## Admin entry points

- `?view=admin/privacyplayer`: player privacy tools
- `?view=admin/privacyuser`: registered user privacy tools
- `?view=admin/dbadmin`: links to the privacy tools under the `Privacy` section

## Event snapshots

Admin event snapshots are portable competition packages, not full privacy exports or database backups.
The JSON event snapshot export includes event-local competition data and the limited `uo_player_profile` fields needed to keep imported players linked to profiles: name, number, accreditation ID, birthdate, gender, and public competition display fields.
It excludes registered user accounts, user roles, registration ownership records, emails, API tokens, player profile URLs, uploaded profile media, and private profile identifiers such as `national_id`.
On import, matching existing player profiles are linked but not overwritten.

## Player privacy tools

The player privacy tools support two operations:

- export one player's data as a text report
- anonymize one player while keeping historical competition records

All player privacy operations are written to `uo_event_log` with `source='privacy'`.
Player export and anonymization logs may record the internal `player_id` or `profile_id` as the audit target.

Player selection is name-based in the admin UI, but matching is anchored to `uo_player_profile` when a profile exists.
If multiple `uo_player` rows share the same `profile_id`, they are treated as the same person even if the stored name changed later.
After selection, the export and anonymization scope covers all linked historical `uo_player` rows for that profile.

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
- `uo_game_history` name values: not the raw rows or the `snapshot` column, but an ID-filtered
  projection of this player's own `played[].name`, `goals[].scorer_name`, and `goals[].assist_name`
  entries out of every `has_snapshot=1` snapshot, tagged with the game and snapshot time. This
  reuses the same decode-and-match-by-player-id traversal `PrivacyAnonymizePlayer()` uses below,
  and is why a prior spelling of the player's name retained in an old snapshot, but no longer
  present in `uo_player`, still reaches their report -- without exporting the rest of that
  snapshot, which would also expose other players' names.

To avoid exposing other members' account identifiers in the player export, `user_id` and `userid` values are hidden in log-derived sections.
Current player log writers use `uo_event_log.id2` for the team reference, not for player identity, so player privacy tools do not match `id2` in order to avoid deleting unrelated team-linked history.
Successful player privacy report downloads are logged to `uo_event_log`.

### Player anonymization

Player anonymization keeps the competition history structure intact but removes personal data and direct identifiers.

Current table-level behavior:

- `uo_player`
  Keep rows.
  Set `firstname` and `lastname` to `-`.
  Clear `num`, `accreditation_id`, and `reg_id`.
  Set `accredited` to `0`.

- `uo_player_profile`
  Keep the linked profile row.
  Set `firstname` and `lastname` to `-`.
  Clear `email`, `num`, `nickname`, `birthdate`, `birthplace`, `nationality`, `throwing_hand`, `height`, `weight`, `position`, `gender`, `info`, `national_id`, `accreditation_id`, `story`, `achievements`, `image`, `profile_image`, and `ffindr_id`.
  Reset `public` to an empty value.

- `uo_license`
  Delete rows whose `accreditation_id` matches the anonymized player/profile accreditation IDs.

- `uo_urls`
  Delete player profile URL rows where `owner='player'` and `owner_id` matches the anonymized `profile_id`.

- `uo_image`
  Delete the referenced profile image row when `uo_player_profile.image` is set.

- uploaded player image files
  Remove the stored profile image files under `images/uploads/players/<profile_id>/`, including thumbnails, when present.

- `uo_accreditationlog`
  Delete rows linked by `player` to the anonymized player IDs.

- `uo_event_log`
  Delete player-category rows linked by `id1` to the anonymized player IDs.
  The current implementation does not match `id2` here, because `id2` is used as the team reference in player event rows.
  After anonymization, write one new non-identifying audit entry for the privacy operation itself.

- `uo_player_stats`
  No row deletion or scrubbing is done.
  Historical statistics remain linked to the kept player/profile rows.

- `uo_played`
  No row deletion or scrubbing is done.
  Historical played-roster links remain.

- `uo_goal`
  No row deletion or scrubbing is done.
  Historical scorer and assist links remain.

- `uo_defense`
  No row deletion or scrubbing is done.
  Historical defense links remain.

- `uo_game_history`
  No row deletion is done; rows are removed only by the foreign-key cascade when the linked game is deleted.
  `assist_name`, `scorer_name`, and `played[].name` inside the `snapshot` column are embedded free
  text, not foreign keys, so anonymizing `uo_player` does not reach them. Every `has_snapshot=1`
  row is decoded, and any `played[].player`, `goals[].scorer`, or `goals[].assist` entry that
  matches one of the anonymized player's linked `player_id` values has its paired name field
  rewritten to `- -`; the row is then re-encoded and saved. Matching is by player id, not by the
  name text stored at capture time, so a name recorded before a later correction in `uo_player`
  is still reached.

## Free-text fields naming other people

Anonymization clears free text on the data subject's own row: `PrivacyAnonymizePlayer()`
nulls `story` and `achievements` on the player's `uo_player_profile` row.

Free text stored on another entity's row is not reachable that way. A person can be named in:

- `uo_team_profile` — `coach`, `captain`, `story`, `achievements`
- `uo_club` — `contacts`, `story`, `achievements`
- `uo_comment` — the comment body
- `uo_game_history.snapshot` — `game.official`, `comment`, and `events[].info`; the embedded `assist_name`, `scorer_name`, and `played[].name` fields are the one exception, rewritten by player anonymization by player id as described above

No per-subject query can find those mentions, because the row belongs to a team, club, or game
rather than to the person. Removing them is a manual admin edit, and a privacy request that
concerns a coach, captain, or club contact should include a check of these fields.

## Visitor counter

`uo_visitor_counter` stores one raw IP address per unique visitor, used only to count
visitors: `LogGetVisitorCount()` reads aggregates, and no page displays an individual row.

The table has no link to a player or registered user, so the per-subject privacy tools cannot
reach it. Two controls apply instead:

- set the `DisableVisitorLogging` setting to stop recording IPs entirely
- a super admin can purge every row from the visitor admin page, which calls `LogResetVisitorCounter()`

## Registered user privacy tools

The registered user privacy tools support two operations:

- export one registered user's data as a text report
- delete one registered user's data, including matching logs

All registered user privacy operations are written to `uo_event_log` with `source='privacy'`.
Successful privacy report downloads are logged with the internal account row id as the audit target.
Deletion is also logged, but the deletion log does not include the deleted user's identifier.

Current report scope includes:

- `uo_users`
- `uo_userproperties`
- `uo_extraemail`
- `uo_extraemailrequest`
- `uo_enrolledteam`
- `uo_registerrequest`
- `uo_event_log`
- `uo_accreditationlog`
- `uo_game_history` for rows where `user_id` matches the selected `userid`, excluding the `snapshot` column, which is game data rather than that user's data

For registered users, `uo_event_log` coverage includes rows where `user_id`, `id1`, or `id2` matches the selected `userid`.

Current deletion behavior:

- delete matching rows from `uo_event_log`
- delete matching rows from `uo_accreditationlog`
- delete matching rows from `uo_registerrequest`
- delete matching rows from `uo_passwordresetrequest`
- delete matching rows from `uo_userproperties`
- delete the row from `uo_users`
- rely on existing foreign-key cascades from `uo_users` for `uo_extraemail`, `uo_extraemailrequest`, and `uo_enrolledteam`
- anonymize matching rows in `uo_game_history`: set `user_id` to `-` and clear `ip`, keeping the row, because it is the linked game's change history and not solely this user's data; rows are removed only by the foreign-key cascade when the game itself is deleted

`uo_passwordresetrequest` has no foreign key to `uo_users`, so it needs an explicit delete.
It is deliberately left out of the report scope: a pending row holds a live reset token, and the
export is a plain text file.

After deletion, the system writes one non-identifying audit entry for the privacy operation itself.
