# Routing

## Main application

- `index.php` is the main entry point.
- Root-level page scripts are resolved through the `view` query parameter.
- The existing pattern for new pages is `?view=...`, not direct file exposure.

## Sub-app entry points

- `api/index.php`: API entry point.
- `mobile/index.php`: deprecated legacy mobile administration entry point kept for compatibility.
- `scorekeeper/index.php`: touchscreen scorekeeper entry point.
- `spiritkeeper/index.php`: standalone Spiritkeeper entry point.
- `login/index.php` and `ext/index.php`: specialized entry points for those areas.

`mobile/` is no longer the recommended operator surface. Use `scorekeeper/` for scorekeeping workflows and `spiritkeeper/` for spirit-entry workflows.

## Include structure

The current application layout expects sibling directories such as `lib/`, `conf/`, and `cust/` next to the main entry points. Any deployment or layout changes should preserve those relative-path assumptions or update them deliberately.
