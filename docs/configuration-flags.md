# Configuration Flags

Use these exact type names when discussing configuration work:

1. `SYSTEM_FLAG`
2. `INSTALLATION_SETTING`
3. `EVENT_SETTING`

## SYSTEM_FLAG

- Scope: installation or environment level.
- Storage: `conf/config.inc.php` and `conf/config.inc.example.php`.
- Use when the value is deployment-specific, security-sensitive, or infrastructure-specific.

## INSTALLATION_SETTING

- Scope: installation-wide and admin-managed.
- Storage: database-backed server configuration, managed through admin UI such as `admin/serverconf.php`.
- Use when all events in one installation share the same value.

## EVENT_SETTING

- Scope: event, season, or tournament level.
- Storage: event or season records and related admin flows.
- Use when behavior can differ between events in the same installation.

## Selection rule

Prefer the narrowest valid scope. Do not use a broader configuration type than the behavior requires.

## Migration rule

When moving a setting from one type to another, treat it as a data migration:

- add DB migration logic if needed,
- provide fallback behavior during transition,
- update admin UI and read paths,
- verify the old storage path is no longer required.
