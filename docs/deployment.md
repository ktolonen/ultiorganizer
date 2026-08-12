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

The package is written to `dist/`. The name depends on whether the build is an official tagged release.

For a development build (any commit that is not an exact release tag, or a tagged commit with an unclean working tree), the name appends the short Git commit hash so the package can be traced to its commit:

```text
ultiorganizer-install-4.0.0-abc1234.zip
```

For an official release — `HEAD` sits on an exact Git tag whose version matches `version.php` and the working tree is clean — the commit hash is omitted, because the tag already identifies the commit:

```text
ultiorganizer-install-4.0.0.zip
```

The version part comes from `version.php`. Any tag on the current commit whose version matches `version.php` marks an official release; a matching tag is recognized even when the commit also carries other tags, such as a pre-release tag. If the commit has tags but none match `version.php`, the build prints a warning, keeps the commit hash in the name, and still creates the package.

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
When customizations are selected, the package filename includes the selected customization set, such as `ultiorganizer-update-cust-default-wfdf-4.0.0-abc1234.zip`.

## Publish a GitHub release

The Release workflow (`.github/workflows/release.yml`) runs when a tag whose name starts with `v` is pushed to GitHub. It checks that the tag version matches `version.php`, calls the release script to build both the install and update packages, and creates a GitHub Release with generated release notes.

To publish a release after the `master` branch has passed CI:

```sh
git switch master
git pull --ff-only
git tag -a v.4.0.0 -m "Ultiorganizer 4.0.0"
git push origin v.4.0.0
```

The repository's existing tags use `v.<version>`, as in `v.4.0.0`; `v4.0.0` is also accepted. The version after either prefix must exactly match the value in `version.php`.

The workflow stores the following files in two places:

- the install ZIP, update ZIP, and `SHA256SUMS` are permanent assets on the GitHub Release
- the same files are retained for 30 days as an artifact of the workflow run

Do not create the GitHub Release manually before pushing the tag because the workflow creates it. If the workflow fails before publishing, fix the problem, delete the tag locally and on GitHub, and then create and push the corrected tag.

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

## Co-hosted installations

Running more than one installation on the same server, such as a test instance next to a production one, needs a few settings to differ between them. `MAINTENANCE_RUNTIME_DIR` and `PERSISTENT_CACHE_DIR` must be distinct so the instances do not share upgrade locks or serve each other's cached pages. `UO_SESSION_NAME` must be distinct so they do not share a session cookie.

### Why the session cookie name is not enough

Session cookie names are scoped per domain, not per directory, so two installations on one domain both receive the cookie named `UO_SESSID`. That is what makes a login on one instance appear on the other. Giving each installation its own `UO_SESSION_NAME` stops that.

It does not, however, isolate the stored session data. PHP's `files` session handler stores each session as `sess_<id>` inside `session.save_path`, and the session name is not part of that key. When two installations share a `save_path`, a session id issued by one can be presented to the other under the other's cookie name, and the second installation reads the first installation's session payload. `session.use_strict_mode` does not prevent this: it only rejects session ids that no session has ever used, and here the id is genuinely present in the shared directory.

Ultiorganizer therefore stamps each session with a fingerprint of the installation that created it, derived from `DB_HOST`, `DB_DATABASE`, `BASEURL` and `UO_SESSION_NAME` (see `startSecureSession()` in `lib/session.functions.php`). A session presented to a different installation is discarded and replaced with a new empty one, so no application code sees the foreign session. This works without any server configuration, which matters on shared hosting where `php.ini` is not available.

Because those four values make up the fingerprint, changing any of them on a running installation invalidates existing sessions and signs everyone out. Moving an installation to a new address by editing `BASEURL` is the usual case. This is a one-time re-login, not an error.

### Separating session storage

Where you do control the PHP configuration, give each installation its own session directory as well. In a PHP-FPM pool:

```ini
php_admin_value[session.save_path] = /var/lib/php/sessions/ultiorganizer-prod
```

One caveat: on Debian and Ubuntu, PHP ships with `session.gc_probability = 0` and expired sessions are removed by a system cron job or systemd timer that only cleans the *default* session directory. A custom `session.save_path` is not swept by it, so session files accumulate indefinitely unless you either add your own cleanup for that directory or re-enable PHP's probabilistic collector with `session.gc_probability = 1`.

## Development checkout deployments

Developers can continue to run Ultiorganizer directly from the repository checkout. That layout is useful for local work because it includes documentation, development tooling, and review assets.

Do not upload a full checkout to production unless the web server is configured to block private and development-only paths. The repository includes Apache `.htaccess` files as defense in depth, but other web servers may ignore them. The release package is the safer production default because documentation and development-only files are not present.
