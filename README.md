# ultiorganizer

This is the **Ultimate Organizer**, a web application for online score keeping of Ultimate tournaments. To find out more, visit project homepage: <https://github.com/ktolonen/ultiorganizer>. To read more about Ultimate sport visit <http://www.wfdf.org>.

## What is here

The repository is organized as follows:

* **Repo root PHP pages** Public pages are routed through `index.php` with the `view` parameter.
* **user** Logged-in user pages such as teams, results, and related tools.
* **admin** Administrator pages for series and event management.
* **lib** Shared utilities and SQL-backed data access.
* **api** JSON API entry points and versioned routing.
* **cust** Skins and installation-specific customizations.
* **mobile**, **scorekeeper**, **ext** Specialized entry points for those use cases.
* **conf**, **sql** Configuration and database assets that should not be exposed by the web server.

Additional documentation lives under:

* **docs/** General project documentation

The `docs/ai/` directory is reserved for future AI assets or automation files. Current markdown documentation lives under `docs/`.

## Installation

To run Ultiorganizer you need a web server, PHP 8.3+ and a MariaDB 10.11+ database.

Ensure the host has native gettext and locales available so PHP translations work (for Debian/Ubuntu: `sudo apt-get install gettext locales` and generate the locales you need with `sudo locale-gen`).

To install Ultiorganizer simply copy the files to your web server, call <http://yourpage.com/install.php> and follow the instructions.

## Development

For local development setup, see `docs/local-development.md`.

Useful follow-up pages:

* `docs/local-development.md`
* `docs/translations.md`
* `docs/database-upgrades.md`
* `docs/configuration-flags.md`

## HTTP API

For API structure, documentation locations, and example requests, see `docs/api.md`.

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
