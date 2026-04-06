# Routing

## Main application

- `index.php` is the main entry point.
- Root-level page scripts are resolved through the `view` query parameter.
- The existing pattern for new pages is `?view=...`, not direct file exposure.
- Root-level routed page scripts are include-only implementation files. They are expected to run through `index.php`, which defines `UO_ROUTED_VIEW` before including the resolved view.
- When a routed root page must not run standalone, use `lib/view.guard.php` and `requireRoutedView(...)` instead of duplicating direct-access checks.

## Sub-app entry points

- `api/index.php`: API entry point.
- `mobile/index.php`: deprecated legacy mobile administration entry point kept for compatibility.
- `scorekeeper/index.php`: touchscreen scorekeeper entry point.
- `spiritkeeper/index.php`: standalone Spiritkeeper entry point.
- `login/index.php` and `ext/index.php`: specialized entry points for those areas.

`mobile/` is no longer the recommended operator surface. Use `scorekeeper/` for scorekeeping workflows and `spiritkeeper/` for spirit-entry workflows.

## Standalone vs include-only files

- Not every `.php` file in the repository is a valid public endpoint.
- Supported standalone entry points are the app index files such as `index.php`, `scorekeeper/index.php`, `spiritkeeper/index.php`, `mobile/index.php`, `login/index.php`, `ext/index.php`, and API entry points under `api/`.
- Many subdirectory files are include-only views or helpers and should not bootstrap themselves when hit directly.
- Include-only PHP files are guarded in code where cross-server portability matters:
  - `lib/` uses `lib/include_only.guard.php`
  - `cust/` uses `cust/include_only.guard.php`
  - routed views use `lib/view.guard.php`
- Apache-specific `.htaccess` files remain as defense in depth, but PHP-side guards are the portable protection for `php -S`, nginx, and similar setups.

## Auth wrappers

- `admin/auth.php`, `user/auth.php`, `mobile/auth.php`, `scorekeeper/auth.php`, `spiritkeeper/auth.php`, and `plugins/auth.php` are thin wrappers around shared auth/session behavior for their areas.
- Use the local wrapper for a sub-app or area instead of including `lib/auth.guard.php` directly from every page.
- `spiritkeeper/auth.php` is slightly different from the others because Spiritkeeper supports both authenticated staff access and token-based team access, depending on the view.

## Include structure

The current application layout expects sibling directories such as `lib/`, `conf/`, and `cust/` next to the main entry points. Any deployment or layout changes should preserve those relative-path assumptions or update them deliberately.
- For subdirectory entry points and external endpoints, prefer `__DIR__`-based includes over bare relative include strings so behavior does not depend on the current working directory.
