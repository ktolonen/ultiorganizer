# Codebase Notes

This page collects implementation details that are useful during coding work but do not need to stay in the root `AGENTS.md`.

## Third-party components

- YUI assets and loader live under `script/yui/` and `lib/yuiloader/`.
- Bundled PHP libraries include `lib/tfpdf/` and `lib/phpqrcode/`.

## PDF generation

- Use `lib/tfpdf/tfpdf.php` and extend `tFPDF` or `tFPDF_CellFit` when `CellFitScale` is needed.
- Register Unicode fonts with `AddFont(..., true)`. DejaVu TTFs are under `lib/tfpdf/font/unifont/`.
- Keep PDF text in UTF-8. Do not use `utf8_decode` or ISO-8859 transcoding helpers.
- After registering DejaVu under family `Arial`, continue using `SetFont('Arial', ...)`.

## Plugins

- `plugins/` are optional and primarily admin-only tools.
- Normal application behavior should not depend on plugins.

## Customizations

- `cust/` contains skins and installation-specific customizations.
- `cust/default` is the default skin.
- `cust/slkl` is actively maintained and used in production at <https://www.ultimate.fi/pelikone>.
- External license database integration is customization-specific. There is no single default external service.
