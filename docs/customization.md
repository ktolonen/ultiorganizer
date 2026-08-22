# Customization

Installation-specific look and feel lives under `cust/<id>/`. `cust/default` is
the base skin; the active skin is selected by the `CUSTOMIZATIONS` constant in
`conf/config.inc.php`. This document currently covers the **CSS color token
system** and how a skin recolors the UI; more customization topics (logos,
layouts, PDF hooks) can be added here over time.

## Maintained and unmaintained skins

`cust/` carries seven skins, and they are not equally alive:

| Skin | Status |
| --- | --- |
| `default` | Base skin. Every other skin cascades on top of it. |
| `slkl`, `wfdf` | Maintained. Verify changes against these. |
| `bula`, `fpudd`, `gummis`, `windmill` | Unmaintained legacy, kept for compatibility. |

Treat the unmaintained skins as frozen. A visual regression that appears only
there is not worth chasing, and reworking them is not part of a change to the
shared stylesheets. When a change touches `cust/default/`, check it against
`default`, `slkl` and `wfdf`; the rest only need to keep loading.

Their age does not show in the file dates — a repo-wide reformat touched every
skin at once — so this list, not `git log`, is what says which is which.

## Skin CSS cascade

`styles()` in `localization.php` emits `cust/default/ultiorganizer.css` first,
then the active skin's `cust/<id>/ultiorganizer.css` on top when it exists and
is not `default`. `mobileStyles()` uses the same cascade with
`ultiorganizer-mobile.css`. A skin file therefore only needs the rules (and
tokens) that differ from default; anything it omits is inherited from default.

Because the skin stylesheet loads **after** default, anything it redefines wins
on equal specificity — including CSS custom properties on `:root`.

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

Both render identically; pick per skin:

- **Overrides only** — list just the tokens that differ from default. Shortest,
  and the skin keeps inheriting any future default value for tokens it does not
  list.
- **Full palette listed** — list every token (default values for the ones the
  skin keeps, overrides for the rest), so the complete palette is visible in one
  place. Trade-off: the skin re-declares the whole palette, so later changes to
  default's base values do **not** propagate to the non-overridden tokens.

The maintained `slkl` and `wfdf` skins list the full palette for easier human
editing and tag their changed values with `/* slkl */` or `/* wfdf */`.
Their non-color rules remain minimal so layout and behavior stay aligned with
`default`.

### Exceptions that are not tokens

Some skin colors do not map to a single default token (a shade used in only one
place, or a value that would collide with another token's role). Keep those as
ordinary per-selector rules below the `:root` block — for example a page-menu
hover color that must stay darker than the skin's accent. Structural overrides
(widths, fonts, logos, custom layout) also remain as normal rules; tokens are
for color only.

### Mobile app palette

`cust/default/ultiorganizer-mobile.css`, used by Scorekeeper, Spiritkeeper, and
Timekeeper, has a separate complete palette because `mobileStyles()` does not
load the desktop stylesheet. Shared concepts use the desktop token names, such
as `--canvas`, `--surface`, `--text-muted`, `--text-inverse`, `--link`,
`--accent`, `--border`, and the `--table-row-*` tokens.
Only the default mobile primary colors (`--link`, `--accent`, and
`--accent-strong`) mirror desktop values; its neutral and semantic colors remain
mobile-specific.

Mobile-only concepts extend that vocabulary with semantic tokens for gameplay,
secondary actions, notices, and Timekeeper states. Keep literal color values in
the `:root` palette and use `var(--token)` in component rules. An installation
can recolor the mobile apps independently by adding
`cust/<id>/ultiorganizer-mobile.css` and redefining the relevant tokens.
The maintained SLKL and WFDF skins include full mobile palettes in their
customization directories. Their changed values are tagged with `/* slkl */`
or `/* wfdf */`, matching the desktop palette convention.

## Dark mode

Because the default skin is fully tokenized, a dark theme is a block that
redefines the token values. The lowest-effort form follows the operating
system, with no markup or server changes:

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

Notes:
- Design the dark relationships deliberately (light text on dark surfaces);
  it is not a value inversion.
- The default desktop and mobile stylesheets are both fully tokenized but load
  independently. A skin that still hardcodes colors, or redefines `:root`
  without a media query, will override these dark values — put a skin's dark
  overrides inside the skin (and inside the media query).
- Non-color assets (logos, icons) do not follow tokens and may need dark
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

The Glasbey lists are Colorcet's complete 256-entry precomputed palettes. They
maximize separation from colors that appear earlier in each list while
constraining lightness for the intended background. Palette selection is not
yet an installation setting: change the final return key in
`cust/default/pool_colors.php`, then use the pool color updater if existing
pools should receive the newly selected colors.

The same stored color is drawn on three surfaces, which is what constrains the
palette:

| Surface | How the color is drawn |
| --- | --- |
| Pool status pages | Tinted to 30% opacity over the white table row, text in `textColor()` |
| PDF schedule and scoresheet | Full-opacity cell fill, text in `textColor()` |
| Pool admin pages | Full-opacity swatch |

The 30% tint pulls every color towards the page background, so a palette that
looks varied at full opacity can still contain pairs that are hard to
distinguish on a pool status page. The precomputed palettes are deliberately a
simple improvement rather than a guarantee: pool names remain the primary cue,
and the updater can repair problematic assignments found in an existing event.

`PoolPickColor()` starts at the palette position matching the pool id and takes
the first color no other pool in the same division uses. A division only starts
repeating a color once it has more pools than the selected palette has entries.
The two 256-color Glasbey options therefore cover the largest divisions seen
in practice; the eight-color Okabe-Ito option deliberately trades capacity for
a smaller, more accessible set.

Two caveats when replacing the list:

- **Order is part of the contract.** Pools created in one request get
  consecutive pool ids, so they get consecutive palette entries. Glasbey's
  greedy order is useful here because each new entry was selected to differ
  from the entries before it.
- **Existing pools keep their stored color.** Changing the palette only affects
  pools created afterwards. Recolor existing ones from the superadmin plugin at
  `?view=plugins/update_pool_colors`, which picks through `PoolPickColor()` as
  well and writes one pool at a time, so recoloring several pools of a division
  in one go cannot give two of them the same color.

No large categorical palette can make dozens of colors reliably identifiable
for every reader. The Okabe-Ito option is available when support for color
vision deficiency matters more than the number of unique pool colors. In every
palette, color stays a secondary cue and pool names remain the primary one.

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
