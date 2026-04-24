# Privacy Tools

This document summarizes the current privacy-related admin tools in Ultiorganizer and the database operations they perform.

## Admin entry points

- `?view=admin/privacyplayer`: player privacy tools
- `?view=admin/privacyuser`: registered user privacy tools
- `?view=admin/dbadmin`: links to the privacy tools under the `Privacy` section

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

For registered users, `uo_event_log` coverage includes rows where `user_id`, `id1`, or `id2` matches the selected `userid`.

Current deletion behavior:

- delete matching rows from `uo_event_log`
- delete matching rows from `uo_accreditationlog`
- delete matching rows from `uo_registerrequest`
- delete matching rows from `uo_userproperties`
- delete the row from `uo_users`
- rely on existing foreign-key cascades from `uo_users` for `uo_extraemail`, `uo_extraemailrequest`, and `uo_enrolledteam`

After deletion, the system writes one non-identifying audit entry for the privacy operation itself.
