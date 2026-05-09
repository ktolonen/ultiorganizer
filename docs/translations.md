# Translations

Translations live under `locale/`.

## Updating translations

- For HTML files, edit the relevant files directly.
- For PHP pages, use gettext tooling.

For gettext-backed PHP strings, refresh the tracked catalogs with:

```sh
./docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh
```

This updates:

- `locale/de_DE.utf8/LC_MESSAGES/messages.po`
- `locale/fi_FI.utf8/LC_MESSAGES/messages.po`
- the corresponding `messages.mo` files when `msgfmt` is available

If you need to edit the translations manually after the catalog refresh, open the `.po` files in Poedit:

```sh
poedit locale/de_DE.utf8/LC_MESSAGES/messages.po
poedit locale/fi_FI.utf8/LC_MESSAGES/messages.po
```

Recommended manual workflow:

1. Update the PHP source strings.
2. Run `./docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh`.
3. Open the changed `.po` files in Poedit and translate new or changed entries.
4. Save the catalogs so the compiled `.mo` files stay in sync.
