# Codebase Notes

This page collects implementation details that are useful during coding work but do not need to stay in the root `AGENTS.md`.

## Third-party components

- YUI assets and loader live under `script/yui/` and `lib/yuiloader/`.
- Bundled PHP libraries include `lib/hsvclass/`, `lib/tfpdf/`, and `lib/phpqrcode/`.

## PDF generation

- Use `lib/tfpdf/tfpdf.php` and extend `tFPDF` or `tFPDF_CellFit` when `CellFitScale` is needed.
- Register Unicode fonts with `AddFont(..., true)`. DejaVu TTFs are under `lib/tfpdf/font/unifont/`.
- Keep PDF text in UTF-8. Do not use `utf8_decode` or ISO-8859 transcoding helpers.
- After registering DejaVu under family `Arial`, continue using `SetFont('Arial', ...)`.

## Plugins

- `plugins/` are optional and primarily admin-only tools.
- Normal application behavior should not depend on plugins.
- `plugins/*.php` are routed admin views, not standalone scripts. They should run through `?view=plugins/...` and use `plugins/auth.php` for direct-hit redirect/auth behavior.

## Customizations

- `cust/` contains skins and installation-specific customizations.
- `cust/default` is the default skin.
- `cust/slkl` is actively maintained and used in production at <https://www.ultimate.fi/pelikone>.
- External license database integration is customization-specific. There is no single default external service.
- Most `cust/*.php` files are include-only fragments. They are blocked by `cust/.htaccess` on Apache and by `cust/include_only.guard.php` in PHP for cross-server portability.
- The current allowed customization HTTP endpoints are `players.php` and `jasenet.php`; if a new public endpoint is added under `cust/`, update `cust/.htaccess` at the same time.
- Files such as `head.php`, `pdfprinter.php`, `mass-accreditation.php`, `teamplayers.functions.php`, `teamplayers.inc.php`, and `pool_colors.php` are customization hooks loaded by the main app, not public entry points.

## Standalone Rendering And Guards

- `lib/*.php` are shared include-only modules. They must not try to self-bootstrap on direct access; use `lib/include_only.guard.php` to fail fast instead.
- Root routed pages under the repository root are expected to run via `index.php?view=...`; use `lib/view.guard.php` when protecting a routed page from direct access.
- Helper files such as `localization.php`, `menufunctions.php`, and `sql/upgrade_db.php` are include-only and guarded accordingly.
- When adding a new include-only PHP file in a portable execution path, prefer a PHP-side direct-access guard in addition to any web-server rules.

## Auth Conventions

- Area-specific wrappers are preferred over repeating raw `lib/auth.guard.php` includes:
  - `admin/auth.php`
  - `user/auth.php`
  - `mobile/auth.php`
  - `scorekeeper/auth.php`
  - `spiritkeeper/auth.php`
  - `plugins/auth.php`
- Keep the wrapper aligned with the area's real access model. For example, Spiritkeeper supports both login-based access and token-based access, so its wrapper is not equivalent to the standard login-only wrappers.
