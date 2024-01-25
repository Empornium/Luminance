Luminance Installation
=========

Dependencies
------------
 - PHP 8.2 or newer
 - MariaDB 11 or newer
 - Memcached
 - Nginx (recommended)
 - Sphinx 2.0.x
 - Radiance

PHP Modules
-----------
* php-curl
* php-date
* php-igbinary
* php-mbstring
* php-memcache
* php-mysqlnd
* php-xml
* php-zip

Composer
--------
clone the repo into a directory of your choice.

cd into the Luminance directory and follow the brief instructions on https://getcomposer.org/download/ to download composer.

Once downloaded, simply run the following command:
```
php composer.phar install
```

Database
--------
You need to create a database before running the Luminance install:
```
mysql -u root -p
create database luminance;
create user 'luminance'@'localhost' identified by '<DatabasePassword>';
grant all privileges on luminance.* to 'luminance'@'localhost' identified by '<DatabasePassword>';
flush privileges;
```
Ensure the database password is both stong and unique, preferably a random string.

Luminance
---------
To install a brand new instance of Luminance, cd into the Luminance directory and run these commands:
```
php application/entry.php setup configure
php application/entry.php setup install
```

To upgrade an existing instance of Luminance, cd into the Luminance directory and run this command:
```
php application/entry.php setup upgrade
```

To migrate from Gazelle, cd into the Luminance directory and run these commands:
```
php application/entry.php setup configure ../../path/to/gazelle/classes/config.php
php application/entry.php setup upgrade
```

Sphinx
------
An example Sphinxsearch configuration file can be found in the install directory.

Nginx Configuration
-------------------
An example http and https Nginx vhost configuration files can be found in the install directory.

Cronjobs
--------
```
crontab -e
```
Set the following as contents for the file:
```
0,15,30,45  *  *   *    *       /usr/bin/php /var/www/localhost/application/entry.php schedule >> /root/schedule.log
*           *  *   *    *       /usr/bin/indexer -c /etc/sphinx/sphinx.conf --rotate delta
5           *  *   *    *       /usr/bin/indexer -c /etc/sphinx/sphinx.conf --rotate --all
```
