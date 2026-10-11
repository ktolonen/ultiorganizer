# Codebase Notes

Implementation notes for third-party code, PDFs, plugins, customizations, guards and auth wrappers.

## Third-party components

- YUI assets and loader live under `script/yui/` and `lib/yuiloader/`. No page loads them any more.
- Calendar fields use flatpickr 4.6.13 under `script/flatpickr/`, with shared setup in `script/calendar.js`. Dates keep the `day.month.year` form format; the picker writes only a picked date and never rewrites typed text. Calendar weeks start on Monday. The bundled locale files cover German, Spanish, and Finnish; English is built in.
- Drag-and-drop lists use SortableJS 1.15.7 under `script/sortablejs/`, with shared setup and the save request in `script/sortable-lists.js`. Each `makeSortableLists()` call forms its own group, so items move only between the lists passed together. On touch screens a drag starts after a 200 ms press. An item dropped outside its group's lists goes back where it was.
- The roster page's player profile search (`cust/default/teamplayers.functions.php`) opens a native `<dialog>`, reads the `players.php` XML with `fetch`, and sorts the result columns with Tablesort 5.7.1 under `script/tablesort/` (number sort plugin included). Picking a row fills the roster fields.
- Local club and team name suggestions use native `datalist` controls through `script/local-autocomplete.js`. Inputs retain free-text entry and their original form field names. Translated fields load suggestions from the existing translation endpoint through `script/translation-autocomplete.js`; selecting a suggestion retains the translation key. Reservation-location suggestions use `script/location-autocomplete.js`, with address context, keyboard selection, and a separate selected location ID that clears when the name is edited.
- Bundled PHP libraries include `lib/hsvclass/`, `lib/tfpdf/`, and `lib/phpqrcode/`.

## PDF generation

- Use `lib/tfpdf/tfpdf.php` and extend `tFPDF` or `tFPDF_CellFit` when `CellFitScale` is needed.
- Register Unicode fonts with `AddFont(..., true)`. DejaVu TTFs are under `lib/tfpdf/font/unifont/`.
- Keep PDF text in UTF-8. Do not use `utf8_decode` or ISO-8859 transcoding helpers.
- After registering DejaVu under family `Arial`, continue using `SetFont('Arial', ...)`.
- Local tFPDF modification: the Unicode-font metrics cache (`*.mtx.php`, `*.cw.dat`, `*.cw127.php` under `lib/tfpdf/font/unifont/`) is disabled in `tfpdf.php` (`AddFont()` and `_putTTfontwidths()`). The upstream cache stored absolute paths and broke after moving an installation; metrics are computed in memory instead, so no writable font directory is needed. `_parsegif()` also drops the `imagedestroy()` call, which is deprecated in PHP 8.5. Re-apply after any tFPDF upgrade; the sections are marked `Ultiorganizer local modification`.

## Plugins

- `plugins/` are optional and primarily admin-only tools.
- Normal application behavior should not depend on plugins.
- `plugins/*.php` are routed admin views, not standalone scripts. They should run through `?view=plugins/...` and use `plugins/auth.php` for direct-hit redirect/auth behavior.

## Customizations

- `cust/` contains skins and installation-specific customizations.
- `cust/default` is the base skin; `cust/slkl` is used in production at <https://www.ultimate.fi/pelikone>.
- Skins cascade over default and recolor through CSS tokens; see `docs/customization.md`. A skin cannot disable a default rule by omitting it; override the property explicitly (e.g. `border-radius: 0`).
- External license database integration is customization-specific. There is no single default external service.
- Most `cust/*.php` files are include-only fragments. They are blocked by `cust/.htaccess` on Apache and by `cust/include_only.guard.php` in PHP for cross-server portability.
- The only allowed customization HTTP endpoint is `players.php`; if a new public endpoint is added under `cust/`, update `cust/.htaccess` at the same time.
- Files such as `head.php`, `pdfschedule.php`, `pdfscoresheet.php`, `mass-accreditation.php`, `teamplayers.functions.php`, `teamplayers.inc.php`, and `pool_colors.php` are customization hooks loaded by the main app, not public entry points.

## Standalone Rendering And Guards

- `lib/*.php` are shared include-only modules. They must not try to self-bootstrap on direct access; use `lib/include_only.guard.php` to fail fast instead.
- Root routed pages under the repository root are expected to run via `index.php?view=...`; use `lib/view.guard.php` when protecting a routed page from direct access.
- Helper files such as `localization.php`, `menufunctions.php`, and `sql/upgrade_db.php` are include-only and guarded accordingly.
- When adding a new include-only PHP file in a portable execution path, prefer a PHP-side direct-access guard in addition to any web-server rules.

## Auth Conventions

- Area-specific wrappers are preferred over repeating raw `lib/auth.guard.php` includes:
  - `admin/auth.php`
  - `user/auth.php`
  - `scorekeeper/auth.php`
  - `spiritkeeper/auth.php`
  - `plugins/auth.php`
- Keep the wrapper aligned with the area's real access model. For example, Spiritkeeper supports both login-based access and token-based access, so its wrapper is not equivalent to the standard login-only wrappers.
