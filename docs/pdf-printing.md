# PDF Printing

Ultiorganizer PDF output is split by purpose so installations can customize the pages that usually need local layout changes without copying unrelated schedule code.

## Entrypoints

- `games.php` generates schedule PDFs and loads `cust/<customization>/pdfschedule.php`.
- `user/pdfscoresheet.php` generates scoresheets, game player lists, and team roster PDFs, and loads `cust/<customization>/pdfscoresheet.php`.

Each purpose file defines a `PDF` class. The class name is shared intentionally because each request loads only one PDF purpose file.

## Customization Fallbacks

Schedule PDFs use this lookup order:

1. `cust/<customization>/pdfschedule.php`
2. `cust/default/pdfschedule.php`

Scoresheet and player-list PDFs use this lookup order:

1. `cust/<customization>/pdfscoresheet.php`
2. `cust/default/pdfscoresheet.php`

There is no `pdfprinter.php` fallback. A customization can omit `pdfschedule.php` when the default schedule layout is enough, and can omit `pdfscoresheet.php` when the default scoresheet and roster layouts are enough.

## Schedule File

`pdfschedule.php` owns schedule-oriented output from `games.php`.

Methods that belong here:

- `PrintSchedule()`
- `PrintOnePageSchedule()`
- schedule-only helpers used by those methods, including one-page schedule helpers
- optional schedule pool helpers such as `PrintSeasonPools()`, `PrintSeriesPools()`, `PrintPools()`, and schedule `PrintError()` behavior

Schedule PDFs should be customized only for schedule layout, grouping, field display, or event schedule branding.

## Scoresheet File

`pdfscoresheet.php` owns printable field-use PDFs from `user/pdfscoresheet.php`.

Methods that belong here:

- `PrintScoreSheet()`
- `PrintDefenseSheet()`
- `PrintPlayerList()`
- `PrintRoster()`
- scoresheet and player-list helpers shared by those methods

`PrintPlayerList()` is the game player list printed with, or alongside, scoresheets for both teams in a scheduled game. `PrintRoster()` is the one-page team roster PDF called from roster/team flows. Both remain in `pdfscoresheet.php` because they share the same team and player layout concerns as scoresheets.

## Libraries And Fonts

PDF purpose files normally include `lib/tfpdf/tfpdf.php` and extend `tFPDF`. Use `tFPDF_CellFit` only when a layout needs cell fitting helpers such as `CellFitScale()`.

Use UTF-8 PDF text and register Unicode fonts with `AddFont(..., true)`. The bundled DejaVu fonts live under `lib/tfpdf/font/unifont/`; existing PDF files register them under the `Arial` family and then continue using `SetFont('Arial', ...)`.

Customization files under `cust/` are include-only files. Keep the `cust/include_only.guard.php` guard at the top of each PDF purpose file so they are loaded through the application entrypoints rather than invoked directly.
