---
name: query-database
description: Run read-only, ad-hoc SQL queries against the local Ultiorganizer dev database to investigate data-driven behavior — for example, why a specific page shows or hides a group, pool, or game for a given season, or what flags a row actually has. Use when the question depends on live data state and reading `lib/` code alone cannot answer it. Reads connection details from `conf/config.inc.php` and queries the `db` Docker Compose service. SELECT only; never use this skill to mutate data.
metadata:
  short-description: Run ad-hoc read-only SQL queries against the local dev database
---

# Query Database

Investigate live data state in the local Ultiorganizer database when a question can't be answered from code alone — e.g. a page's grouping tabs, visibility, or a specific record's flags depend on what's actually stored, not just on the query logic in `lib/`.

This skill is for **reading**, not fixing. If the investigation turns up a data problem that needs correcting, do that through the app's normal admin flows (or a proper `sql/upgrade_db.php` migration for schema/systemic fixes — see `docs/database-upgrades.md`), not by running `UPDATE`/`DELETE` through this skill.

## Prerequisites

The local stack must be running:

```sh
docker compose -f docs/dev/compose.yaml up -d app db
```

## Getting connection details

You don't need to read or type the password. The `db` container already exposes the credentials as its own environment variables — `MYSQL_USER`, `MYSQL_PASSWORD`, and `MYSQL_DATABASE` — and by default these match `DB_USER`, `DB_PASSWORD`, and `DB_DATABASE` in `conf/config.inc.php` (both are seeded from `docs/dev/.env`; see `docs/local-development.md`). The commands below expand those variables *inside* the container, so the secret is never placed on the host command line.

`DB_HOST` is `db`, the Compose service name — reachable only from inside the Compose network, so run queries through `docker compose exec`, not a host-installed client.

## Running a query

Run the query inside the `db` service (which bundles the `mariadb` client) through `sh -lc`, and pass the password via `MYSQL_PWD` from the container's environment rather than a `-p` argument. This keeps the credential out of the process argument list and avoids shell-quoting breakage from special characters:

```sh
docker compose -f docs/dev/compose.yaml exec -T db sh -lc \
'MYSQL_PWD="$MYSQL_PASSWORD" mariadb -u"$MYSQL_USER" "$MYSQL_DATABASE" -e "SELECT ...;"'
```

The same form handles multi-line or quote-heavy SQL:

```sh
docker compose -f docs/dev/compose.yaml exec -T db sh -lc \
'MYSQL_PWD="$MYSQL_PASSWORD" mariadb -u"$MYSQL_USER" "$MYSQL_DATABASE" -e "
SELECT ...
FROM ...
WHERE ...;
"'
```

Add `--table` for aligned column output on wide result sets, or `-E`/`--vertical` when a single row has many columns and horizontal output would wrap unreadably.

If you need root (e.g. toggling `general_log`, cross-database introspection), use `MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mariadb -uroot ...` — same pattern, the container's root password variable. See the query-logging workflow in `docs/runtime-cache.md`.

## Example: why doesn't a schedule group/game show up publicly?

The public schedule page (`games.php`) and admin data views often read the same tables through different filters (visibility, validity, current pool ownership). When a group, pool, or game is missing from a public view despite existing in the admin UI, resolve the underlying flags directly. Because playoff visibility follows the bracket's *root* (see below), the query walks each pool up to its playoff root and reports the root's `visible` alongside the pool's own:

```sql
WITH RECURSIVE pool_root_visibility AS (
    SELECT pool_id, follower, visible AS root_visible
    FROM uo_pool
    WHERE NOT EXISTS (SELECT 1 FROM uo_pool anc WHERE anc.follower = uo_pool.pool_id)
    UNION ALL
    SELECT child.pool_id, child.follower, parent.root_visible
    FROM uo_pool child
    INNER JOIN pool_root_visibility parent ON parent.follower = child.pool_id
)
SELECT g.game_id, g.valid, gp.pool, gp.timetable, p.name AS pool_name,
       p.visible AS own_visible, rv.root_visible,
       ser.name AS series_name, ser.valid AS series_valid, ser.season
FROM uo_game g
LEFT JOIN uo_game_pool gp ON gp.game = g.game_id
LEFT JOIN uo_pool p ON p.pool_id = gp.pool
LEFT JOIN pool_root_visibility rv ON rv.pool_id = gp.pool
LEFT JOIN uo_series ser ON ser.series_id = p.series
WHERE g.reservation = <reservation id>;
```

Public schedule queries (see `TimetableGrouping()` and `TimetableGames()` in `lib/timetable.functions.php`) require `g.valid=1`, a `uo_game_pool` row with `timetable=1` (the game's *current* owning pool — playoff progression can leave stale `timetable=0` carryover rows), `ser.valid=1` for the requested season, and the pool's **playoff-root visibility** (`root_visible=1`), not the pool's own `visible`. Playoff follower pools (Quarterfinals, Semifinals, Finals) are created hidden (`visible=0`) and stay out of the pool menus by a structural check, but their games follow the root at read time via `TimetablePublicVisibilityCte()`/`TimetablePublicVisibilityCondition()`. So a follower with `own_visible=0` still shows publicly when `root_visible=1` — check `root_visible`, not `own_visible`, when diagnosing a missing playoff game or group. Admin list queries (e.g. `ReservationInfo()` in `lib/reservation.functions.php`) are often much looser, which is why the two views can disagree.

## Safety

- SELECT only, unconditionally. This skill never runs `INSERT`/`UPDATE`/`DELETE`/`ALTER`/`DROP` — not even when asked. Raw writes bypass the permission checks and cache invalidation that the app's write paths enforce (see `docs/database-access.md`), so a data change belongs in an admin flow or an `sql/upgrade_db.php` migration (`docs/database-upgrades.md`), never here. If a mutation is genuinely needed, stop and hand it to one of those paths.
- Only target the local dev database reached through `docs/dev/compose.yaml`. Never point this workflow at a production connection string.
