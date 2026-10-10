# Local Development

Docker Compose setup for local work. The stack lives in `docs/dev/` (`compose.yaml`, `Dockerfile.app`, `Dockerfile.dev`, `.env.example`, `php.dev.ini`) and needs [Docker](https://docs.docker.com/get-docker/) with the Compose plugin. The images run PHP 8.5 and MariaDB 12.3 (newer than the minimum versions in the root `README.md`) and generate the `en_US`, `de_DE`, `es_ES` and `fi_FI` UTF-8 locales for translations.

## Configure the stack

Copy the example environment and adjust credentials or ports:

```sh
cp docs/dev/.env.example docs/dev/.env
```

Change `MYSQL_ROOT_PASSWORD` before the first `up`. The defaults create database and user `ultiorganizer`.

## Start the app and database

```sh
docker compose -f docs/dev/compose.yaml up --build app db   # first start or after Dockerfile changes
docker compose -f docs/dev/compose.yaml up app db           # normal restarts
```

The repository is bind-mounted, so code edits need no rebuild. `app` serves <http://localhost:8080/>; `db` keeps its data in a named volume. Open <http://localhost:8080/install.php> and use database host `db`, name and user `ultiorganizer`, and the `MYSQL_PASSWORD` from `docs/dev/.env`.

## Allow the installer to write `conf/`

PHP runs as `www-data` against the bind-mounted checkout, so allow writes before installing:

```sh
chmod 777 conf
```

and tighten them afterwards:

```sh
chmod 775 conf
chmod 664 conf/config.inc.php
```

## Allow uploads to write `images/uploads/`

`images/uploads/` is untracked and created by the installer as `www-data`. If that fails, or uploads fail with a generic processing error, create it:

```sh
mkdir -p images/uploads
chmod 777 images/uploads
```

Production permissions are covered in `docs/deployment.md`.

## Optional developer workspace

The optional `dev` service (for agents, shell work and repo tooling, including `git`, `curl`, `mariadb-client`, `ripgrep`, ESLint and Stylelint) shares the source tree but serves no traffic:

```sh
docker compose -f docs/dev/compose.yaml --profile devtools up --build dev
```

```sh
docker compose -f docs/dev/compose.yaml exec dev bash
```

## Test harness

The test suite lives in [`ktolonen/ultiorganizer-tests`](https://github.com/ktolonen/ultiorganizer-tests) and runs against a copy of this tree. Clone it as a sibling checkout:

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

`--sut-path <path>` tests another checkout. Keep the harness on an up-to-date `main`, as CI uses. See its README for suites and reports.

## Host database clients

MariaDB is published on `127.0.0.1:${DB_PORT}` (default `3306`) for HeidiSQL, DBeaver and similar, with the same credentials as above, or `root` with `MYSQL_ROOT_PASSWORD`.

## Compiled PHP is cached for two seconds

`opcache.revalidate_freq=2` means a changed file can serve its old compile for up to two seconds. This matters when swapping a file to compare behaviour (`git checkout <ref> -- <file>`, request, restore, request): the second run can silently use the old code. Wait between swap and request:

```sh
docker compose -f docs/dev/compose.yaml exec -T app sh -c 'sleep 4'
```

and confirm the swap took before trusting the comparison.

## PHP error logging

`docs/dev/php.dev.ini` displays PHP errors in the browser and writes them to `/tmp/ultiorganizer-php-error.log` in `app`:

```sh
docker compose -f docs/dev/compose.yaml exec app tail -f /tmp/ultiorganizer-php-error.log
```

and `docker compose -f docs/dev/compose.yaml logs -f app` shows the combined container log.

## Xdebug

The `app` and `dev` images include Xdebug, configured in `docs/dev/.env` (`XDEBUG_MODE=off` disables it):

```sh
XDEBUG_MODE=debug
XDEBUG_START_WITH_REQUEST=yes
XDEBUG_CLIENT_HOST=host.docker.internal
XDEBUG_CLIENT_PORT=9003
XDEBUG_IDEKEY=VSCODE
```

Restart the services after changing it. `XDEBUG_START_WITH_REQUEST=trigger` connects only on an explicit trigger. In the IDE use `localhost:9003` and map the project root to `/var/www/html`; Compose adds `host.docker.internal` on every OS.

## Stopping

`docker compose -f docs/dev/compose.yaml down` stops the stack; add `-v` to remove the database volume.

The repository root is the document root, so a deployment built this way must block `conf/`, `sql/` and `docs/` at the web server.
