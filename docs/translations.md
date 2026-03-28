# Translations

Translations live under `locale/`.

## Updating translations

- For HTML files, edit the relevant files directly.
- For PHP pages, use gettext tooling.

The simplest workflow is:

```sh
poedit locales/de_DE.utf8/LC_MESSAGES/messages.po
```

Then update the catalog, add translations, and save.
