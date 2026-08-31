# Configuration Flags

Use these exact type names when discussing configuration work:

1. `SYSTEM_FLAG`
2. `INSTALLATION_SETTING`
3. `EVENT_SETTING`

## SYSTEM_FLAG

- Scope: installation or environment level.
- Storage: `conf/config.inc.php` and `conf/config.inc.example.php`.
- Use when the value is deployment-specific, security-sensitive, or infrastructure-specific.
- Example: `NO_EMAIL` disables outbound mail at the installation level and makes public self-registration unavailable.
- Example: `MAINTENANCE_RUNTIME_DIR` points to the writable runtime-state directory used for automatic database-upgrade maintenance files and locks.
- Example: `UO_SESSION_NAME` sets the session cookie name. It must be unique per installation, because installations sharing a domain otherwise share one session cookie. Like `MAINTENANCE_RUNTIME_DIR`, it is optional and derived from the installation directory while undefined, so both are shipped commented out; see `docs/deployment.md` for isolating co-hosted installations.

## INSTALLATION_SETTING

- Scope: installation-wide and admin-managed.
- Storage: database-backed server configuration, managed through admin UI such as `admin/serverconf.php`.
- Use when all events in one installation share the same value.
- Example: `DisableGameHistory` turns off scoresheet change-history recording (`uo_game_history`) for the whole installation. It defaults to recording enabled and is not exposed in `install.php`; the default applies on a fresh install, and an admin can change it afterward in `admin/serverconf.php`. See `docs/game-history.md`.

## EVENT_SETTING

- Scope: event, season, or tournament level.
- Storage: event or season records and related admin flows.
- Use when behavior can differ between events in the same installation.

## Selection rule

Prefer the narrowest valid scope. Do not use a broader configuration type than the behavior requires.

## Installation coverage rule

When adding a new `SYSTEM_FLAG` or `INSTALLATION_SETTING`, also cover it in the installation process.
At minimum, check whether `install.php` should expose, persist, or preserve the value during install.

## Migration rule

When moving a setting from one type to another, treat it as a data migration:

- add DB migration logic if needed,
- provide fallback behavior during transition,
- update admin UI and read paths,
- verify the old storage path is no longer required.
