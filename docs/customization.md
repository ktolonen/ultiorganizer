# Customization

Installation-specific look and feel lives under `cust/<id>/`. `cust/default` is
the base skin; the active skin is selected by the `CUSTOMIZATIONS` constant in
`conf/config.inc.php`. This document covers the CSS color tokens, dark mode,
and pool colors.

## Maintained and unmaintained skins

| Skin | Status |
| --- | --- |
| `default` | Base skin. Every other skin cascades on top of it. |
| `slkl`, `wfdf` | Maintained. Verify changes against these. |
| `bula`, `fpudd`, `gummis`, `windmill` | Unmaintained legacy, kept for compatibility. |

Treat the unmaintained skins as frozen: check `cust/default/` changes against
`default`, `slkl` and `wfdf`, and the rest only need to keep loading. File dates
don't show their age (a repo-wide reformat touched every skin), so rely on this
list.

## Skin CSS cascade

`styles()` in `localization.php` emits `cust/default/ultiorganizer.css` first,
then the active skin's `cust/<id>/ultiorganizer.css` on top when it exists and
is not `default`. `mobileStyles()` uses the same cascade with
`ultiorganizer-mobile.css`. A skin only needs the rules and tokens that differ;
loading after default, it wins on equal specificity, including `:root` tokens.

## Color tokens

`cust/default/ultiorganizer.css` defines the palette as CSS custom properties
(design tokens) in a `:root` block, and every color in the default skin is
written as `var(--token)`. Changing a token value re-colors every rule that uses
it. The tokens, grouped by role:

| Group | Token | Controls |
|---|---|---|
| Surfaces | `--canvas` | Page backdrop behind the content card |
| | `--surface` | Card / content / table background (white) |
| | `--surface-muted` | Subtle grey fill: top header bar, left-menu boxes, page-menu tabs |
| Text | `--text` | Primary body text |
| | `--text-muted` | Secondary / meta text |
| | `--text-faint` | Low-emphasis / disabled text |
| | `--text-inverse` | Text on dark backgrounds |
| Links | `--link` | Link text |
| | `--link-hover` | Link hover / focus |
| Accent | `--accent` | Primary accent: active nav, header title, calendar header |
| | `--accent-strong` | Stronger accent: selected grouping link, delete-button hover |
| | `--accent-deep` | Darkest accent: active page-menu tab text |
| Borders | `--menu-border` | Left-menu box border |
| | `--border-strong` | Dark borders / dividers |
| | `--border` | Light borders (table cell lines) |
| Tables | `--table-header-bg` | Table header background |
| | `--table-header-text` | Table header text |
| | `--table-row-odd` | Odd row background |
| | `--table-row-even` | Even (zebra) row background |
| | `--table-row-hover` | Row hover background |
| | `--admin-row-alt` | Admin-table alternate row tint |
| Menu & nav | `--menu-section-bg` | Menu section heading background |
| | `--menu-highlight` | Nav hover background |
| | `--menu-dropdown-bg` | Season dropdown background |
| | `--menu-dropdown-border` | Season dropdown border |
| Page-menu tabs | `--tab-border` | Tab border |
| | `--tab-hover-bg` | Tab hover background |
| | `--tab-hover-border` | Tab hover border |
| | `--tab-active-bg` | Active tab background |
| | `--tab-active-border` | Active tab border |
| Teams | `--team-home` | Home team color (row background + font color) |
| | `--team-away` | Away / guest team color |
| Status | `--status-warning` | Warning / error text |
| | `--status-positive` | Positive value text |
| | `--status-negative` | Negative value text |
| | `--status-warning-bg` | Attention / warning highlight background |
| | `--highlight` | Row / selection highlight background |
| | `--played-bg` | Played / disabled grey background |
| | `--halftime-bg` | Half-time row background |

Several tokens intentionally share a value in default (for example `--text`,
`--link`, and `--table-header-bg` are all the same near-black) but stay distinct
names so a skin can diverge them.

## Recoloring a skin with tokens

A skin recolors the whole UI by **redefining tokens in its own `:root`** — no
per-selector overrides needed, because default's rules already read the tokens:

```css
/* cust/<id>/ultiorganizer.css */
:root {
	--accent: #0bc5e0;
	--team-home: #0bc5e0;
	--team-away: #ff7f02;
	/* ...only the tokens this skin changes... */
}
```

### Two declaration styles

- **Overrides only**: list just the changed tokens; the skin inherits future
  default values for the rest.
- **Full palette**: list every token so the palette is visible in one place, at
  the cost of not inheriting later default changes.

`slkl` and `wfdf` list the full palette, tag changed values with `/* slkl */` or
`/* wfdf */`, and keep non-color rules minimal.

### Exceptions that are not tokens

A color that fits no single token (a one-off shade, or one that must stay darker
than the skin's accent) stays an ordinary rule below `:root`. Structural
overrides (widths, fonts, logos, layout) are normal rules too; tokens are for
color only.

### Mobile app palette

`cust/default/ultiorganizer-mobile.css` (Scorekeeper, Spiritkeeper,
Timekeeper) has its own complete palette, since `mobileStyles()` does not load
the desktop stylesheet. Shared concepts reuse desktop token names (`--canvas`,
`--surface`, `--text-muted`, `--link`, `--accent`, `--border`,
`--table-row-*`, ...); only `--link`, `--accent` and `--accent-strong` share
desktop values. Mobile-only tokens cover gameplay, secondary actions, notices
and Timekeeper states. Keep literals in `:root` and use `var(--token)` in rules.
A skin recolors the mobile apps with `cust/<id>/ultiorganizer-mobile.css`;
`slkl` and `wfdf` ship full tagged mobile palettes.

## Dark mode

A dark theme is a block redefining the tokens, e.g. following the OS setting:

```css
@media (prefers-color-scheme: dark) {
	:root {
		--canvas: #1a1a1a;
		--surface: #242424;
		--text: #e6e6e6;
		--table-header-bg: #333333;
		/* ...dark values for the remaining tokens... */
	}
}
```

Design the dark relationships deliberately rather than inverting values. Desktop
and mobile stylesheets need separate dark blocks. A skin that hardcodes colors
or redefines `:root` outside a media query overrides them, so a skin's dark
values belong in the skin, inside the media query. Logos and icons may need dark
variants.

## Pool colors

Pool colors are data, not CSS. `cust/default/pool_colors.php` contains several
named lists and returns one of them through a hardcoded key at the bottom of the
file. An installation can instead add `cust/<CUSTOMIZATIONS>/pool_colors.php`
that returns an array of 6-digit hex colors. `PoolColors()` in
`lib/pool.functions.php` loads the returned list and drops entries that are not
6-digit hex.

| Shipped palette | Entries | Intended use |
| --- | ---: | --- |
| `ultiorganizer` | 48 | The original replacement palette, tuned for the 30% tint over white |
| `glasbey-light-background` | 256 | Light page backgrounds |
| `glasbey-dark-background` | 256 | Dark page backgrounds |
| `okabe-ito` | 8 | A compact color-vision-deficiency-aware option |

The Glasbey lists are Colorcet's 256-entry palettes, each entry chosen to differ
from those before it at a lightness suited to the background. Palette selection
is not an installation setting: change the return key in
`cust/default/pool_colors.php`.

A stored color is drawn tinted to 30% over white on pool status pages, as a
full fill on PDF schedules and scoresheets, and as a swatch on pool admin pages,
both with text in `textColor()`. The tint makes some pairs hard to tell apart,
so pool names remain the primary cue and color a secondary one; Okabe-Ito
trades capacity for color-vision-deficiency support.

`PoolPickColor()` starts at the palette position matching the pool id and takes
the first color unused in the division, so colors repeat only once a division
has more pools than the palette. Order therefore matters: pools created together
get consecutive ids and consecutive entries.

Changing the palette affects only new pools. Recolor existing ones with the
superadmin plugin `?view=plugins/update_pool_colors`, which uses
`PoolPickColor()` one pool at a time so a division gets no duplicates.

## Verification

The CSS lint/review skill is `docs/ai/css-style-and-lint/SKILL.md`. Run
Stylelint on changed files via the dev container:

```sh
docker compose -f docs/dev/compose.yaml exec -T dev stylelint "cust/**/*.css"
```

To preview a skin, set `CUSTOMIZATIONS` in `conf/config.inc.php` and reload. The
app caches rendered HTML (which embeds the skin's stylesheet link), so restart
the app container after switching skins mid-session:

```sh
docker compose -f docs/dev/compose.yaml restart app
```
