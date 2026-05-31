# ultiorganizer

This is the **Ultimate Organizer**, a web application for online score keeping of Ultimate tournaments. To find out more, visit project homepage: <https://github.com/ktolonen/ultiorganizer>. To read more about Ultimate sport visit <http://www.wfdf.org>.

## What is here

The repository is organized as follows:

* **Repo root PHP pages** Public pages are routed through `index.php` with the `view` parameter.
* **user** Logged-in user pages such as teams, results, and related tools.
* **admin** Administrator pages for division and event management.
* **lib** Shared utilities and SQL-backed data access.
* **api** JSON API entry points and versioned routing.
* **cust** Skins and installation-specific customizations.
* **login** Authentication and password reset entry points.
* **mobile**, **scorekeeper**, **spiritkeeper**, **ext** Specialized entry points. `mobile/` is legacy and deprecated; `scorekeeper/` and `spiritkeeper/` are the supported replacements for the old mobile administration UI.
* **images**, **locale**, **plugins** Static assets, translations, and plugin code.
* **script** Client-side JavaScript assets.
* **conf**, **sql** Configuration and database assets that should not be exposed by the web server.

In a repository checkout, additional documentation lives under:

* **docs/** General project documentation and repo-local AI review/fix skills

Release packages include this README and the runtime application files, but leave out
the repository-only `docs/` tree. Current markdown documentation is indexed in
`docs/README.md`; repo-local AI assets and skills live under `docs/ai/`.

## Installation

To run Ultiorganizer you need a web server, PHP 8.3+ and a MariaDB 10.11+ database.

For a local Debian/Ubuntu setup, install the required packages with:

```bash
sudo apt-get update
sudo apt-get install -y \
    apache2 mariadb-server gettext locales \
    php8.3 php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring php8.3-mysql php8.3-xml
```

Ensure the host has native gettext and at least one UTF-8 OS locale available so PHP translations work. On servers you control, generate the locales you expect to serve. For example:

```bash
sudo locale-gen en_US.UTF-8
sudo locale-gen de_DE.UTF-8
sudo locale-gen es_ES.UTF-8
```

On shared hosting without `sudo`, Ultiorganizer can still serve the bundled
German, Spanish, and Finnish translations when PHP gettext is enabled and the
host provides at least one non-`C` UTF-8 locale such as English or Finnish.

For Apache installs, enable `mod_rewrite` so API routes under `/api/v1/...` can use the bundled `.htaccess` rewrite rules:

```bash
sudo a2enmod rewrite
sudo systemctl reload apache2
```

For production installation, use a release package instead of uploading a full repository checkout. Extract the release ZIP, upload the extracted contents to your web server, open <http://yourpage.com/install.php>, and follow the instructions.

Maintainers can build release packages with `docs/release/build-release.sh`. Deployment notes are in `docs/deployment.md`.

## Development

For local development, use the Docker Compose stack documented in
`docs/local-development.md`. The app runs at <http://localhost:8080/> and the
installer at <http://localhost:8080/install.php>.

Common checks are run from the optional `dev` workspace:

```bash
docker compose -f docs/dev/compose.yaml --profile devtools up --build dev
docker compose -f docs/dev/compose.yaml exec -T dev composer check
docker compose -f docs/dev/compose.yaml exec -T dev eslint script
```

The documentation index is `docs/README.md`; coding conventions are in
`docs/code-style.md`.

## HTTP API

The read-only JSON API is served under `/api/v1/...`. OpenAPI metadata is
available at `/api/v1/openapi`, or at
<http://localhost:8080/api/v1/openapi> in the Docker development stack.

API tokens are managed in the admin UI at `?view=admin/apitokens`.
Event-scoped endpoints require the event to be marked visible in the public
API. See `docs/api.md` for endpoint examples and current constraints.

## Credits

Ultiorganizer was first introduced at the 2002 World Championships in Turku, after already being used by the Finnish Flying Disc Association from 1999. Even though the codebase has since been fully rewritten on a modern technology stack, the original vision has remained the same: a free, open, and reliable live-scoring system for Ultimate. This journey has only been possible because of the many people who use the system in real events and continuously improve it through feedback, testing, and patches.

Special thanks to **Pasi Niemi**, whose early and significant contributions helped launch the rewrite of the scoring system in PHP.

Special thanks to **Bruno Gravato** for years of practical development work on a long-running fork used by major Ultimate organizations, including BULA, WFDF, EUF, and national federations. Bruno’s contributions include substantial maintenance and feature work beyond the 2014 upstream baseline.

Thanks as well to **Justin Palmer** and **Patrick** for the [Live by BULA](https://github.com/layoutd/live-by-bula) collaboration.

Contributors:
- Asmo Soinio
- Bruno Gravato
- Hartti Suomela
- Juha Jalovaara
- Kari
- Artsa
- Pasi Niemi
- cschaffner
- Les
- Plinio Moreno
- Jonathan Potts
- Steffen Mecke
