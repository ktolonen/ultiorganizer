# Local Development

This page contains the current Docker Compose-based local setup guidance. The canonical Docker assets live under `docs/dev/`.

## Requirements

- Docker with the Compose plugin
- PHP 8.3 compatibility
- MariaDB 10.11 compatibility
- Host locales and gettext support for translations

The bundled Docker images generate the `en_US.UTF-8`, `de_DE.UTF-8`,
`es_ES.UTF-8`, and `fi_FI.UTF-8` locales so those translations can be selected
in the local app.

## Install Docker

Install Docker by following Docker's general guide at <https://docs.docker.com/get-docker/>.

## Stack files

The local stack is defined in:

```sh
docs/dev/compose.yaml
docs/dev/Dockerfile.app
docs/dev/Dockerfile.dev
docs/dev/.env.example
docs/dev/php.dev.ini
```

## Configure the stack

Copy the example environment file and adjust credentials or port mappings as needed:

```sh
cp docs/dev/.env.example docs/dev/.env
```

Change `MYSQL_ROOT_PASSWORD` in `docs/dev/.env` before the first `docker compose up` so the database is not initialized with the default root password.

Xdebug is enabled by default in the local PHP images. If you want to turn it off temporarily, set `XDEBUG_MODE=off` in `docs/dev/.env`.

The defaults create a local MariaDB database named `ultiorganizer` with user `ultiorganizer`.

The database container is also exposed to the host on `127.0.0.1:${DB_PORT}` for local database tools. The default host port is `3306`.

## Start the app and database

On the first start, or after changing `docs/dev/Dockerfile.app` or other build-time dependencies, run:

```sh
docker compose -f docs/dev/compose.yaml up --build app db
```

For normal restarts while working on the PHP codebase, start the same services without rebuilding:

```sh
docker compose -f docs/dev/compose.yaml up app db
```

Because the repository is bind-mounted into the `app` container, normal PHP, template, and static-asset edits do not require an image rebuild.

This starts:

- `app`: Apache + PHP 8.3 serving Ultiorganizer on <http://localhost:8080/>
- `db`: MariaDB 10.11 with a named Docker volume for persistent local data

You should then be able to open <http://localhost:8080/install.php>.

When `install.php` asks for database connection details, use the Docker Compose service name as the database host:

- Database host: `db`
- Database name: `ultiorganizer` by default
- Database user: `ultiorganizer` by default
- Database password: the value of `MYSQL_PASSWORD` in `docs/dev/.env`

## Allow the installer to write `conf/`

The `app` container runs Apache/PHP as `www-data`, but the repository is bind-mounted from the host. On a typical local checkout, the installer may not be able to write `conf/` until you temporarily relax the host directory permissions.

Before running the installer, allow writes to `conf/`:

```sh
chmod 777 conf
```

After installation has created `conf/config.inc.php`, tighten permissions again:

```sh
chmod 775 conf
chmod 664 conf/config.inc.php
```

For non-local deployments, do not leave `conf/` world-writable.

## Allow uploads to write `images/uploads/`

The upload directory is not tracked in git, so a fresh checkout does not have it. The installer checks that it is writable and disables its Continue button while it is missing, and afterwards team, club and player image uploads fail with a generic processing error.

Create it and let `www-data` write to it:

```sh
mkdir -p images/uploads
chmod 777 images/uploads
```

For non-local deployments, give the directory to the user the PHP process runs as — the pool user under PHP-FPM, or the web server user under mod_php — instead of making it world-writable.

## Optional developer workspace

The stack also includes an optional `dev` service for AI agents, shell work, and repo tooling. It shares the same source tree as the running app but does not serve web traffic.

Start it only when needed:

```sh
docker compose -f docs/dev/compose.yaml --profile devtools up --build dev
```

Open a shell inside the workspace container:

```sh
docker compose -f docs/dev/compose.yaml exec dev bash
```

The `dev` image includes CLI tools useful for local development work such as `git`, `curl`, `less`, `mariadb-client`, and `ripgrep`.

## Test harness

The project's test suite is not part of this repository. It lives in the public [`ktolonen/ultiorganizer-tests`](https://github.com/ktolonen/ultiorganizer-tests) repository and runs against a copy of this source tree, so it never modifies your checkout.

Clone it once as a sibling of this checkout — the layout both CI and the harness default to:

```sh
git clone https://github.com/ktolonen/ultiorganizer-tests.git ../ultiorganizer-tests
```

Then run it from that directory:

```sh
cd ../ultiorganizer-tests
./doctor       # check Docker and the environment
./test:quick   # day-to-day run
./test:matrix  # the full matrix CI runs
```

The harness defaults to the sibling source checkout at `../ultiorganizer`; pass `--sut-path <path>` to test a different checkout or worktree. Keep it on `main`, which is the ref CI uses. See the harness README for the suite definitions and reporting commands.

## Connect with HeidiSQL or another host database client

The local MariaDB container is published to the host so you can connect with HeidiSQL, DBeaver, or another desktop client.

Use these connection settings:

- Hostname: `127.0.0.1`
- Port: `3306` by default, or the value of `DB_PORT` in `docs/dev/.env`
- User: `ultiorganizer` by default
- Password: the value of `MYSQL_PASSWORD` in `docs/dev/.env`
- Database: `ultiorganizer` by default

If you want full administrative access for local development, you can also connect as `root` with the password from `MYSQL_ROOT_PASSWORD`.

## Compiled PHP is cached for two seconds

The `app` image runs with `opcache.enable=On` and `opcache.revalidate_freq=2`,
so a changed file can keep serving its previous compile for up to two seconds.
Editing normally, this is invisible. It matters when a file is swapped under a
live request to compare behaviour — `git checkout <ref> -- <file>`, request,
restore, request — because the second request can silently answer from the code
you just replaced, and the two runs then look identical when they are not.

Wait it out between the swap and the request:

```sh
docker compose -f docs/dev/compose.yaml exec -T app sh -c 'sleep 4'
```

Then confirm the swap took, for instance by grepping the file for a line only
one version contains, before trusting the comparison.

## PHP error logging

The local Docker setup enables development-oriented PHP error reporting through `docs/dev/php.dev.ini`.

- PHP errors are displayed in the browser
- PHP errors are also written to `/tmp/ultiorganizer-php-error.log` inside the `app` container
- Apache and PHP stderr output remains visible through `docker compose` logs

To inspect the PHP error log directly:

```sh
docker compose -f docs/dev/compose.yaml exec app tail -f /tmp/ultiorganizer-php-error.log
```

To inspect the combined container log stream:

```sh
docker compose -f docs/dev/compose.yaml logs -f app
```

## Xdebug

The local `app` and `dev` images include Xdebug for browser and CLI debugging.

Xdebug is controlled through `docs/dev/.env`:

```sh
XDEBUG_MODE=debug
XDEBUG_START_WITH_REQUEST=yes
XDEBUG_CLIENT_HOST=host.docker.internal
XDEBUG_CLIENT_PORT=9003
XDEBUG_IDEKEY=VSCODE
```

After changing Xdebug-related values in `docs/dev/.env`, restart the services:

```sh
docker compose -f docs/dev/compose.yaml up app db
```

If you changed the Dockerfiles or are starting the stack for the first time, rebuild instead:

```sh
docker compose -f docs/dev/compose.yaml up --build app db
```

With `XDEBUG_START_WITH_REQUEST=yes`, Xdebug tries to connect to your IDE on every request. If you want to turn that off temporarily, set `XDEBUG_START_WITH_REQUEST=trigger` and use an explicit trigger only when needed.

Typical IDE settings:

- Host: `localhost`
- Port: `9003`
- Path mapping: project root to `/var/www/html`

The Compose file adds `host.docker.internal` for the local containers, so Xdebug can connect back to the IDE on the host machine on Linux, macOS, and Windows.

## Container layout

The documented local setup bind-mounts the repository root directly into Apache's document root in the `app` container. Because of that layout, non-public directories such as `conf/`, `sql/`, and `docs/` must be access-controlled at the web server level in any non-local deployment.

## Runtime notes

- Run installation through `install.php` only for local setup or controlled first-time installs.
- Restrict write access to `conf/` after installation.
- Stop the stack with `docker compose -f docs/dev/compose.yaml down`.
- Remove local database state with `docker compose -f docs/dev/compose.yaml down -v`.
- Verify changes by running the app and exercising the affected page flow.
