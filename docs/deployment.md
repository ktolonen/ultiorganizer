# Deployment

Ultiorganizer has two different working layouts:

- the full repository checkout for development
- the release package for production installation

Production installations should use the release package. The install package contains the runtime application, installer, default configuration example, SQL schema, skins, translations, and static assets. Update packages omit installer-only files such as the default configuration example. Release packages leave out documentation, AI review assets, Docker development files, IDE settings, Composer tooling, PHPStan configuration, Git hooks, and repository metadata.

## Build a release package

Maintainers can build a package from the repository root:

```sh
docs/release/build-release.sh
```

The package is written to `dist/` with a name like:

```text
ultiorganizer-install-4.0-abc1234.zip
```

The first part comes from `version.php`; the second part is the current Git commit hash. If the current commit has an exact Git tag and that tag does not match `version.php`, the build prints a warning but still creates the package.

Before building, the script prints the source branch or ref, clean/dirty working tree state, package type, selected customizations, version, commit, and output archive path, then asks for confirmation. For automated builds, pass `--yes` to accept this confirmation.

The default package type is `install`. To build an update package for an existing installation, use:

```sh
docs/release/build-release.sh --update
```

Update packages leave out `install.php` and `.sql` files. They keep runtime upgrade code such as `sql/upgrade_db.php`.

By default, release packages include every customization under `cust/`. To build a package with only one customization plus the required default customization, use:

```sh
docs/release/build-release.sh --cust wfdf
```

`cust/default` is always included. Repeat `--cust` or pass a comma-separated list to include more than one non-default customization.
When customizations are selected, the package filename includes the selected customization set, such as `ultiorganizer-update-cust-default-wfdf-4.0-abc1234.zip`.

## Install from a release package

To install Ultiorganizer on a server:

1. Download or build the release ZIP.
2. Extract it locally or on the server.
3. Upload the extracted package contents to the web server document root or application directory.
4. Open `https://your-host/install.php` in a browser.
5. Follow the installer steps.
6. After installation, make sure `conf/` and `conf/config.inc.php` are not writable by the web server user.
7. Remove `install.php` from the server, or block access to it at the web-server level.

The installer needs `sql/ultiorganizer.sql` and `conf/config.inc.example.php`, so both files are included in install packages. Update packages omit both files. They should not be exposed for browsing by the web server after installation.

## PHP upload limits

The event data import (`admin/eventdataimport.php`) and database restore (`admin/dbrestore.php`) accept file uploads that can exceed PHP's defaults. A JSON event snapshot for a large event can be tens of megabytes. PHP's `post_max_size` and `upload_max_filesize` are `PHP_INI_PERDIR` directives, so they cannot be raised from application code at runtime — they must be configured on the server.

Set both limits comfortably above the largest snapshot you expect to import, keeping `post_max_size` above `upload_max_filesize` because the POST body wraps the file plus the form fields. For example, in `php.ini` (or an FPM pool / `.user.ini`):

```ini
upload_max_filesize = 64M
post_max_size = 66M
```

With Apache mod_php you may instead set `php_value upload_max_filesize 64M` in a vhost or directory `.htaccess`, but do not ship `php_value` directives in the release package: they cause a 500 error under PHP-FPM. If the limits are too low, the importer reports that the uploaded file is too large instead of failing silently. The local development environment configures these limits in `docs/dev/php.dev.ini`.

## Development checkout deployments

Developers can continue to run Ultiorganizer directly from the repository checkout. That layout is useful for local work because it includes documentation, development tooling, and review assets.

Do not upload a full checkout to production unless the web server is configured to block private and development-only paths. The repository includes Apache `.htaccess` files as defense in depth, but other web servers may ignore them. The release package is the safer production default because documentation and development-only files are not present.
