# ultiorganizer

This is the **Ultimate Organizer**, a web application for online score keeping of Ultimate tournaments. To find out more, visit project homepage: https://sourceforge.net/apps/trac/ultiorganizer/wiki. To read more about Ultimate sport visit www.wfdf.org .

# What is here?

The files are organized as follows:

  * **php files in main directory** Only index.php is called directly. All other pages are called vi http://hostname.org/?view=pageXYZ. The pages in the main directory are accessible to all users.
  * **user** The pages in this directory are accessible to logged in users. Maintaining user and team info and result reporting.
  * **admin** The pages in this directory are for administrators (including series and event administrators).
  * **lib** Contains utilities used by all pages. SQL statements should only go in here!
  * **script** JavaScript files
  * **conf** Contains config.inc.php, which contains mysql user information and passwort and other server configuration. It should be writable during installation, but later you should restrict access to it as much as possible!
  * **cust** Contains skins for customized Ultiorganizer instances.
  * **locale** Contains translations. To update, simply edit the html files. To update translations in php pages you need the gettext utilities. The simplest way to add translations is by calling `poedit locales/de_DE.utf8/LC_MESSAGES/messages.po`. Then call 'update', add translations and save.
  * **images** Contains icons, flags, and, by default, the image and media upload directory.
  * **mobile** Contains pages for small screens on mobile devices.
  * **scorekeeper** Another take on mobile pages, using jQuery
  * **ext** Contains pages to be embedded in external pages. See ?view=ext/index
  * **plugins** Mainly tools for maintenance, export, import. Some are rather experimental!
  * **sql** database utilities
  * **restful** ???!!!


# Installation

To run Ultiorganizer you need a web server, php 4.4 and a mysql database.

To install Ultiorganizer simply copy the files to your web server, call http://yourpage.com/install.php and follow the instructions.

