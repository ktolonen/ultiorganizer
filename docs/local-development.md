# Local Development

This page contains the current Docker-based local setup guidance.

## Requirements

- Docker
- PHP 8.3 compatibility
- MariaDB 10.11 compatibility
- Host locales and gettext support for translations

## Install Docker

Follow the Docker installation guide at <https://docs.docker.com/get-docker/>.

## Create a network

Adding a Docker network allows you to refer to the database by container name instead of IP address and keeps the development environment isolated from other containers. This step is optional but recommended.

```sh
docker network create ultiorganizer-net
```

## Create the database

MariaDB 10.11+ is used for development to match the current production compatibility.

```sh
export MYSQL_ROOT_PASSWORD='<root password>'

docker run --detach --name=ultiorganizer-db --network ultiorganizer-net --env "MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD" mariadb:10.11
```

### Create user and grant access

```sh
docker exec ultiorganizer-db mysql --user=root --password="$MYSQL_ROOT_PASSWORD" --execute="CREATE USER ultiorganizer IDENTIFIED BY 'ultiorganizer'"

docker exec ultiorganizer-db mysql --user=root --password="$MYSQL_ROOT_PASSWORD" --execute="GRANT ALL PRIVILEGES ON ultiorganizer.* TO ultiorganizer"
```

## Create the web server

Run the following command in the directory where you cloned the repository. If needed, replace `$PWD` with the path to the repository.

```sh
docker run --network ultiorganizer-net --name=ultiorganizer --publish 8080:80 --volume "$PWD":/var/www/html --detach php:8.3-apache
```

The base `php:8.3-apache` image is missing some libraries and extensions needed by the application.

```sh
docker exec ultiorganizer sh -c 'apt-get --assume-yes update && apt-get --assume-yes install zlib1g-dev libpng-dev gettext locales && locale-gen en_US.UTF-8'

docker exec ultiorganizer sh -c 'docker-php-ext-install mysqli gettext gd mbstring && apachectl restart'
```

You should then be able to open <http://localhost:8080/>.

## Container layout

The documented local setup mounts the repository root directly into Apache's document root. Because of that layout, non-public directories such as `conf/`, `sql/`, and `docs/` should be access-controlled at the web server level.

## Runtime notes

- Run installation through `install.php` only for local setup or controlled first-time installs.
- Restrict write access to `conf/` after installation.
- Verify changes by running the app and exercising the affected page flow.
