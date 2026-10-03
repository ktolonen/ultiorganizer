# Deployment

Use the release package for production. The install package contains the runtime application, installer, default configuration example, SQL schema, skins, translations and static assets; the update package omits the installer-only files. Neither contains documentation, AI review assets, Docker files, IDE settings, Composer tooling, PHPStan configuration, Git hooks or repository metadata.

## Build a release package

```sh
docs/release/build-release.sh            # install package
docs/release/build-release.sh --update   # update package: no install.php or .sql files, keeps sql/upgrade_db.php
docs/release/build-release.sh --cust wfdf  # only cust/default plus the listed customizations
```

The script prints the source ref, working-tree state, package type, customizations, version, commit and output path, then asks for confirmation (`--yes` skips it). `--cust` can be repeated or comma-separated; `cust/default` is always included.

The package goes to `dist/`, named from `version.php`:

- official release (`HEAD` has a tag matching `version.php`, other tags allowed, and a clean tree): `ultiorganizer-install-4.0.0.zip`
- anything else: the short commit hash is appended, `ultiorganizer-install-4.0.0-abc1234.zip`. Tags that don't match `version.php` produce a warning.
- selected customizations appear in the name: `ultiorganizer-update-cust-default-wfdf-4.0.0-abc1234.zip`

## Publish a GitHub release

Pushing a tag starting with `v` runs `.github/workflows/release.yml`, which checks the tag against `version.php`, builds the install and update packages, and creates a GitHub Release with generated notes. After `master` has passed CI:

```sh
git switch master
git pull --ff-only
git tag -a v.4.0.0 -m "Ultiorganizer 4.0.0"
git push origin v.4.0.0
```

Tags use `v.<version>` (`v<version>` also works); the version must match `version.php` exactly. The install ZIP, update ZIP and `SHA256SUMS` become Release assets and are also kept 30 days as a workflow artifact. Don't create the Release by hand. If the workflow fails before publishing, fix it, delete the tag locally and on GitHub, and push it again.

## Install from a release package

1. Download or build the release ZIP and extract it.
2. Upload the contents to the web server document root or application directory.
3. Open `https://your-host/install.php` and follow the installer.
4. After installation, make sure `conf/` and `conf/config.inc.php` are not writable by the web server user.
5. Remove `install.php` from the server, or block access to it at the web-server level.

`images/uploads/` is not shipped. The installer creates it and the application creates per-entity directories below it, both as the PHP process user (the FPM pool user, or the web server user under mod_php). Give that user the narrowest write access that works: `images/` only so the installer can create `images/uploads/`, or pre-create `images/uploads/` for that user and keep `images/` read-only (the installer waits at the configuration step until it is writable). Never make the application root writable; `conf/` must stay out of reach.

Uploads are stored `0644` in `0775` directories so a web server running as another user can serve them. A pre-created `images/uploads/` keeps its own mode, so make it traversable; owner-only modes cause 403s.

Install packages include `sql/ultiorganizer.sql` and `conf/config.inc.example.php` for the installer; don't expose them for browsing afterwards.

Extracting an update package over an installation does not delete files a release removed. Delete them by hand; for example, the legacy `mobile/` directory is no longer shipped and its leftover pages no longer work.

## PHP upload limits

The event data import (`admin/eventdataimport.php`) and database restore (`admin/dbrestore.php`) can take uploads of tens of megabytes. `post_max_size` and `upload_max_filesize` are `PHP_INI_PERDIR`, so set them on the server, above the largest expected snapshot, with `post_max_size` slightly larger. For example in `php.ini`, an FPM pool or `.user.ini`:

```ini
upload_max_filesize = 64M
post_max_size = 66M
```

Under mod_php, `php_value` in a vhost or `.htaccess` also works, but never ship `php_value` in the package: it causes a 500 under PHP-FPM. Too-low limits are reported by the importer. Local development sets them in `docs/dev/php.dev.ini`.

## Co-hosted installations

Installations on the same server (e.g. test next to production) must not share the maintenance runtime directory or the session cookie name. Normally nothing needs configuring: while `MAINTENANCE_RUNTIME_DIR` and `UO_SESSION_NAME` are undefined, `DBMaintenanceRuntimeDir()` and `sessionCookieName()` derive them from the installation directory. `conf/config.inc.example.php` ships both commented out for that reason. If you define them, keep them unique per installation.

`MAINTENANCE_RUNTIME_DIR` is used verbatim, because admins create `maintenance.flag` there by hand (see [`database-upgrades.md`](database-upgrades.md)). A shared directory means a shared flag and lock: a test-instance upgrade puts production into maintenance, and a failed upgrade leaves both there. Set it only when the system temp directory is unsuitable; `install.php` prefills the derived path.

Cloning an installation together with its `conf/config.inc.php` copies any defined values, and re-running `install.php` keeps them. After cloning, remove or replace `MAINTENANCE_RUNTIME_DIR` and `UO_SESSION_NAME`.

`PERSISTENT_CACHE_DIR` may be shared: the cache already uses a per-database subdirectory (see [`persistent-cache.md`](persistent-cache.md)). Installations on the same database share cache entries unless given separate directories.

### Sessions

Cookie names are scoped per domain, not per path, so each installation needs its own name. Installations upgrading from releases that used a fixed `UO_SESSID` sign their users out once.

A distinct name does not isolate session data: PHP's `files` handler keys sessions by id only, so with a shared `session.save_path` one installation could read another's session by id, and `session.use_strict_mode` does not help. `startSecureSession()` (`lib/session.functions.php`) therefore stamps each session with a fingerprint of `DB_HOST`, `DB_DATABASE`, `BASEURL` and the cookie name, and replaces a foreign session with an empty one. This needs no server configuration. Changing any of those four values (typically `BASEURL` on a move) signs everyone out once.

Where you control PHP, also give each installation its own session directory, e.g. in an FPM pool:

```ini
php_admin_value[session.save_path] = /var/lib/php/sessions/ultiorganizer-prod
```

On Debian and Ubuntu, `session.gc_probability = 0` and a system timer cleans only the default directory, so a custom path needs its own cleanup or `session.gc_probability = 1`.

## Development checkout deployments

Don't upload a full repository checkout to production unless the web server blocks private and development-only paths. The Apache `.htaccess` files are defense in depth that other servers may ignore.
