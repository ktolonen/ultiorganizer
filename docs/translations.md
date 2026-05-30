# Translations

Ultiorganizer has three translation mechanisms:

- gettext catalogs for PHP interface strings
- locale-specific static files for long-form HTML and email text
- database-backed translations for installation-defined names and labels

Available languages come from `getAvailableLocalizations()` in
`lib/configuration.functions.php`. A locale must be configured in `$locales`,
which is normally defined in `conf/config.inc.php` from
`conf/config.inc.example.php`, have a matching directory under `locale/`, and
be available to PHP through `setlocale()`. The OS locale also has to be
generated or installed on the server, for example with `locale-gen` on
Debian-based systems. English is kept as a fallback even when the OS locale is
not installed.

The active request locale is selected in `localization.php`. `setSessionLocale()`
stores the locale in the session, binds gettext to `locale/`, calls
`setlocale(LC_MESSAGES, ...)`, and reloads database translations for the active
locale.

## Gettext Interface Strings

PHP interface strings use gettext:

```php
_("Save")
```

The source strings are extracted into
`locale/<locale>/LC_MESSAGES/messages.po`, and runtime gettext reads the
compiled `messages.mo` file for the active locale.

After adding or changing gettext-backed PHP strings, refresh the tracked
catalogs with:

```sh
./docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh
```

This updates all tracked `messages.po` files and their corresponding
`messages.mo` files. Edit the `.po` files in Poedit or another gettext editor
when manual translation work is needed, then save so the `.mo` files stay in
sync.

## Static Localized Files

Long-form text is stored as full localized files instead of gettext strings.
Each locale keeps these under `locale/<locale>/LC_MESSAGES/`.

Current static page files:

- `welcome.html`: front page content, loaded by `frontpage.php`
- `user_guide.html`: user guide content, loaded by `user_guide.php`
- `privacy.html`: privacy policy content, loaded by `privacy.php`
- `help.html`: admin help content, loaded by `admin/help.php`

Current email template files:

- `register.txt`: account confirmation email, loaded by
  `AddRegisterRequest()`
- `verify_email.txt`: extra email confirmation email, loaded by
  `AddExtraEmailRequest()`
- `pwd_reset.txt`: password reset email, loaded by the password reset flow
- `playeradmin_register.txt`: player-admin registration template kept with the
  locale assets

The static HTML page loaders look for a customization override first:

```text
cust/<CUSTOMIZATIONS>/locale/<locale>/LC_MESSAGES/<file>
```

If no customization file exists, they fall back to:

```text
locale/<locale>/LC_MESSAGES/<file>
```

The email template loaders currently read directly from `locale/<locale>/`.
Templates may contain placeholders replaced by the caller. For example,
registration and extra-email confirmation templates use `$url` and
`$ultiorganizer`, while password reset uses `$url` and `$username`.

When adding a new locale, copy every required static `.html` and `.txt` file
for that locale. When adding a new static localized file, update every locale
directory and add the read path in code with a clear fallback strategy.

## Database-Backed User Strings

Installation-defined names and labels are translated through `uo_translation`.
The table stores:

- `translation_key`: the source key, up to 50 characters
- `locale`: the locale id with dots normalized to underscores, such as
  `fi_FI_utf8`
- `translation`: the localized value, up to 100 characters

Use `U_($name)` from `lib/translation.functions.php` when rendering
installation-defined values such as event, division, pool, location, field,
reservation group, menu link, and other database-provided names. `U_()` looks
up the lowercase key from the session translation cache for the active locale.
If a full key is missing, `translate()` attempts word-by-word replacement and
otherwise leaves the input unchanged.

Use `TranslatedField()` for admin inputs that should participate in the
database-backed translation/autocomplete flow. The autocomplete endpoint uses
the same translation helper functions to suggest stored keys and translated
values.

`loadDBTranslations($locale)` populates `$_SESSION['dbtranslations']` from
`uo_translation`. It is called when the session locale changes and lazily by
`U_()` if the cache is missing.

Admin management for these records lives in `admin/translations.php`, backed by
`SetTranslation()`, `AddTranslation()`, and `RemoveTranslation()` in
`lib/translation.functions.php`. Keep SQL for this mechanism inside
`lib/translation.functions.php`; routed pages should call these helpers rather
than accessing `uo_translation` directly.
