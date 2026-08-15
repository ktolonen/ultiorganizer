# Ultiorganizer

Ultiorganizer is a free, self-hosted web application for organizing and scoring
Ultimate tournaments. It keeps event setup, schedules, live scores, standings,
player statistics, and Spirit of the Game results in one system.

Organizers use the main application to manage the tournament. Mobile-friendly
tools help scorekeepers and teams record game information on the field, while
public pages keep players and spectators up to date.

## Key features

### Plan the event

- Manage events, divisions, teams, player rosters, and team enrollment.
- Create round-robin, playoff, Swiss-draw, and cross-match pools.
- Generate games and move teams between pools based on standings.
- Assign games to fields and times with scheduling conflict checks.

### Run game day

- Record results, goals, assists, timeouts, and game events.
- Use **Scorekeeper** for mobile score entry and live game timing.
- Use **Spiritkeeper** for team Spirit of the Game submissions.
- Use **Timekeeper** for time-limit signals and game timing.

### Publish results

- Show public schedules, live scores, standings, team pages, and statistics.
- Produce printable schedules, scoresheets, player lists, and rosters.
- Export event data through PDF, CSV, XML, RSS, iCalendar, and a JSON API.
- Support multiple languages, user roles, permissions, and custom skins.

## Live! by BULA

Ultiorganizer supports integration with
[Live! by BULA](https://github.com/layoutd/live-by-bula), an optional public
interface. It presents live games, schedules, standings, team information,
Spirit scores, and player statistics in a modern, mobile-friendly view. Live!
is developed and released separately; follow its project instructions to
install a tested version.

## Requirements

- A web server
- PHP 8.3 or newer with cURL, GD, gettext, intl, mbstring, MySQL, and XML support
- MariaDB 10.11 or newer
- At least one non-`C` UTF-8 system locale for translations

## Installation

Use a release package for a production installation:

1. Download an installation ZIP from
   [GitHub Releases](https://github.com/ktolonen/ultiorganizer/releases).
2. Extract the package and upload its contents to your web server.
3. Open `https://your-host/install.php` and follow the installer.
4. After installation, make `conf/` read-only for the web server and remove or
   block access to `install.php`.

Apache installations should enable `mod_rewrite` for API routes under
`/api/v1/`. More deployment information is available in
[docs/deployment.md](docs/deployment.md).

## Local development

The development environment uses Docker Compose. Start the application and
database with:

```bash
docker compose -f docs/dev/compose.yaml up --build app db
```

The application will be available at <http://localhost:8080/> and the installer
at <http://localhost:8080/install.php>. See
[docs/local-development.md](docs/local-development.md) for setup details.

Common checks run in the optional development workspace:

```bash
docker compose -f docs/dev/compose.yaml --profile devtools up --build dev
docker compose -f docs/dev/compose.yaml exec -T dev composer check
docker compose -f docs/dev/compose.yaml exec -T dev eslint script
```

## API

The read-only JSON API is served under `/api/v1/`. OpenAPI metadata is available
at `/api/v1/openapi`, and API tokens are managed in the administration UI. See
[docs/api.md](docs/api.md) for examples and current constraints.

## Project documentation

The complete documentation index is [docs/README.md](docs/README.md). Good
starting points for contributors are:

- [Architecture](docs/architecture.md)
- [Local development](docs/local-development.md)
- [Code style](docs/code-style.md)
- [Routing](docs/routing.md)

The main application starts at `index.php`. Shared application and database
code lives in `lib/`, access-controlled pages in `admin/` and `user/`, and the
standalone game-day tools in `scorekeeper/`, `spiritkeeper/`, and `timekeeper/`.

## History and Credits

Ultiorganizer was first introduced at the 2002 World Championships in Turku, after already being used by the Finnish Flying Disc Association from 1999. Even though the codebase has since been fully rewritten on a modern technology stack, the original vision has remained the same: a free, open, and reliable live-scoring system for Ultimate. This journey has only been possible because of the many people who use the system in real events and continuously improve it through feedback, testing, and patches.

Ultiorganizer's current PHP codebase is mostly authored by **Kari Hulkko** (**ktolonen**), who also maintains the system in the Finnish Flying Disc Association (SLKL) context.

Special thanks to **Pasi Niemi**, whose early and significant contributions helped launch the rewrite of the scoring system in PHP.

Special thanks to **Bruno Gravato** for years of practical development work on a long-running fork used by major Ultimate organizations, including BULA, WFDF, EUF, and national federations. Bruno’s contributions include substantial maintenance and feature work beyond the 2014 upstream baseline.

Thanks as well to **Justin Palmer** and **Patrick** for the [Live by BULA](https://github.com/layoutd/live-by-bula) collaboration.

Contributors:

- Alejandro Molina
- Artsa
- Asmo Soinio
- Bruno Gravato
- cschaffner
- Hartti Suomela
- Jonathan Potts
- Juha Jalovaara
- Kari Hulkko
- Les
- Pasi Niemi
- Plinio Moreno
- Steffen Mecke

## License

Ultiorganizer is released under the [GNU General Public License v3](LICENSE).
