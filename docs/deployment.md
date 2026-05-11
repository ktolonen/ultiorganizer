# Deployment

Ultiorganizer has two different working layouts:

- the full repository checkout for development
- the release package for production installation

Production installations should use the release package. The package contains the runtime application, installer, default configuration example, SQL schema, skins, translations, and static assets. It leaves out development-only files such as AI review assets, Docker development files, IDE settings, Composer tooling, PHPStan configuration, Git hooks, and repository metadata.

## Build a release package

Maintainers can build a package from the repository root:

```sh
docs/release/build-release.sh
```

The package is written to `dist/` with a name like:

```text
ultiorganizer-4.0-abc1234.zip
```

The first part comes from `version.php`; the second part is the current Git commit hash. If the current commit has an exact Git tag and that tag does not match `version.php`, the build prints a warning but still creates the package.

## Install from a release package

To install Ultiorganizer on a server:

1. Download or build the release ZIP.
2. Extract it locally or on the server.
3. Upload the extracted package contents to the web server document root or application directory.
4. Open `https://your-host/install.php` in a browser.
5. Follow the installer steps.
6. After installation, make sure `conf/` and `conf/config.inc.php` are not writable by the web server user.
7. Remove `install.php` from the server, or block access to it at the web-server level.

The installer needs `sql/ultiorganizer.sql` and `conf/config.inc.example.php`, so both files are included in the release package. They should not be exposed for browsing by the web server after installation.

## Development checkout deployments

Developers can continue to run Ultiorganizer directly from the repository checkout. That layout is useful for local work because it includes documentation, development tooling, and review assets.

Do not upload a full checkout to production unless the web server is configured to block private and development-only paths. The repository includes Apache `.htaccess` files as defense in depth, but other web servers may ignore them. The release package is the safer production default because those files are not present.
