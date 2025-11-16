# ultiorganizer

This is the **Ultimate Organizer**, a web application for online score keeping of Ultimate tournaments. To find out more, visit project homepage: <https://sourceforge.net/apps/trac/ultiorganizer/wiki>. To read more about Ultimate sport visit <http://www.wfdf.org>.

## What is here

The files are organized as follows:

* **PHP files in main directory** Only index.php is called directly. All other pages are called using the view parameter e.g. <http://hostname.org/?view=pageXYZ>. The pages in the main directory are accessible to all users.
* **user** Pages in this directory are accessible to logged in users. Maintaining user and team info and result reporting.
* **admin** Pages in this directory are for administrators (including series and event administrators).
* **lib** Contains utilities used by all pages. SQL statements should only go in here!
* **script** JavaScript files
* **conf** Contains config.inc.php, which contains MySQL user information, password and other server configuration. It should be writable during installation, but later you should restrict access to it as much as possible!
* **cust** Contains skins for customized Ultiorganizer instances.
* **locale** Contains translations. To update, simply edit the html files. To update translations in PHP pages you need the gettext utilities. The simplest way to add translations is by calling `poedit locales/de_DE.utf8/LC_MESSAGES/messages.po`. Then call 'update', add translations and save.
* **images** Contains icons, flags, and, by default, the image and media upload directory.
* **mobile** Contains pages for small screens on mobile devices.
* **scorekeeper** Another take on mobile pages, using jQuery
* **ext** Contains pages to be embedded in external pages. See ?view=ext/index
* **plugins** Mainly tools for maintenance, export, import. Some are rather experimental!
* **sql** database utilities
* **restful** ???!!!

## Installation

To run Ultiorganizer you need a web server, PHP 8.x and a MySQL/MariaDB database.

Ensure the host has native gettext and locales available so PHP translations work (for Debian/Ubuntu: `sudo apt-get install gettext locales` and generate the locales you need with `sudo locale-gen`).

To install Ultiorganizer simply copy the files to your web server, call <http://yourpage.com/install.php> and follow the instructions.

## Development

To enable fast start to Ultiorganizer development follow the instructions below to set up a development environment using Docker containers.

In order to install Docker follow the instructions on <https://docs.docker.com/get-docker/>

### Create a network

Adding a Docker network allows you to refer to the database with the containers name instead of using an IP-address in addition to isolating your development environment from your other containers. This step is optional but recommended.

```sh
docker network create ultiorganizer-net
```

### Create the DB
MariaDB 10.x is used for development to stay compatible with the codebase while avoiding MySQL 8 defaults that conflict with older query patterns (MySQL 5.7 works too if you prefer it).

```sh
export MYSQL_ROOT_PASSWORD='<root password>'

docker run --detach --name=ultiorganizer-db --network ultiorganizer-net --env "MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD" mariadb:10.11

```

MySQL 5.7.5 and up implements detection of functional dependence. As there are queries in Ultiorganizer that refer to columns that are not listed in the `GROUP BY` section errors occur. These can be circumvented by disabling the new functionality.

```sh
docker exec ultiorganizer-db mysql --user=root --password="$MYSQL_ROOT_PASSWORD" --execute="CREATE DATABASE ultiorganizer;SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));"
```

More details in: <https://dev.mysql.com/doc/refman/5.7/en/group-by-handling.html>

#### Create user and grant accesses

```sh
docker exec ultiorganizer-db mysql --user=root --password="$MYSQL_ROOT_PASSWORD" --execute="CREATE USER ultiorganizer IDENTIFIED BY 'ultiorganizer'"

docker exec ultiorganizer-db mysql --user=root --password="$MYSQL_ROOT_PASSWORD" --execute="GRANT ALL PRIVILEGES ON ultiorganizer.* TO ultiorganizer"
```

### Create the web server

The command below should be run in the folder where you have cloned your Ultiorganizer Git repo. If not, then substitute `$PWD` with a path to the code or copy the code to the container.

```sh
docker run --network ultiorganizer-net --name=ultiorganizer --publish 8080:80 --volume "$PWD":/var/www/html --detach php:8.3-apache
```

The base PHP apache image is missing some libraries and extensions that need to be installed.

```sh
docker exec ultiorganizer sh -c 'apt-get --assume-yes update && apt-get --assume-yes install zlib1g-dev libpng-dev gettext locales && locale-gen en_US.UTF-8'

docker exec ultiorganizer sh -c 'docker-php-ext-install mysqli gettext gd mbstring && apachectl restart'
```

Now you should be able to connect to your development Ultiorganizer by opening your browser to <http://localhost:8080/>
