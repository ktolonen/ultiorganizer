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
```

## Configure the stack

Copy the example environment file and adjust credentials or port mappings as needed:

```sh
cp docs/dev/.env.example docs/dev/.env
```

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

## Container layout

The documented local setup bind-mounts the repository root directly into Apache's document root in the `app` container. Because of that layout, non-public directories such as `conf/`, `sql/`, and `docs/` must be access-controlled at the web server level in any non-local deployment.

## Runtime notes

- Run installation through `install.php` only for local setup or controlled first-time installs.
- Restrict write access to `conf/` after installation.
- Stop the stack with `docker compose -f docs/dev/compose.yaml down`.
- Remove local database state with `docker compose -f docs/dev/compose.yaml down -v`.
- Verify changes by running the app and exercising the affected page flow.
