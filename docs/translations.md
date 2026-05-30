# Translations

Translations live under `locale/`.

Each language uses a directory named after the system locale, such as
`locale/es_ES.utf8/LC_MESSAGES/`. The app only offers a translation when the
matching locale is installed on the server, because gettext needs the OS locale
to be available.

## Updating translations

- For HTML files, edit the relevant files directly.
- For PHP pages, use gettext tooling.

For gettext-backed PHP strings, refresh the tracked catalogs with:

```sh
./docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh
```

This updates:

- all `locale/*/LC_MESSAGES/messages.po` files
- the corresponding `messages.mo` files

If you need to edit translations manually after the catalog refresh, open the relevant `.po` files in Poedit. For example:

```sh
poedit locale/de_DE.utf8/LC_MESSAGES/messages.po
poedit locale/es_ES.utf8/LC_MESSAGES/messages.po
poedit locale/fi_FI.utf8/LC_MESSAGES/messages.po
```

Recommended manual workflow:

1. Update the PHP source strings.
2. Run `./docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh`.
3. Open the changed `.po` files in Poedit and translate new or changed entries.
4. Save the catalogs so the compiled `.mo` files stay in sync.
