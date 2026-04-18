# Local Development

This page contains the current Docker Compose-based local setup guidance. The canonical Docker assets live under `docs/dev/`.

## Requirements

- Docker with the Compose plugin
- PHP 8.3 compatibility
- MariaDB 10.11 compatibility
- Host locales and gettext support for translations

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

The defaults create a local MariaDB database named `ultiorganizer` with user `ultiorganizer`.

## Start the app and database

Run the following command from the repository root:

```sh
docker compose -f docs/dev/compose.yaml up --build app db
```

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

## Container layout

The documented local setup bind-mounts the repository root directly into Apache's document root in the `app` container. Because of that layout, non-public directories such as `conf/`, `sql/`, and `docs/` must be access-controlled at the web server level in any non-local deployment.

## Runtime notes

- Run installation through `install.php` only for local setup or controlled first-time installs.
- Restrict write access to `conf/` after installation.
- Stop the stack with `docker compose -f docs/dev/compose.yaml down`.
- Remove local database state with `docker compose -f docs/dev/compose.yaml down -v`.
- Verify changes by running the app and exercising the affected page flow.
