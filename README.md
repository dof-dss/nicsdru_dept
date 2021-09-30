# Departmental sites codebase

This source code is for the Departmental sites. It is built with Drupal 9 using the domain access module.

It is hosted on platform.sh.

Continuous Integration and Deployment services are provided by Circle CI.

## Getting started

We recommend Lando for local development. To get started, ensure you have the following installed:

1. Lando [https://docs.devwithlando.io/](https://docs.devwithlando.io/)
2. Composer [https://getcomposer.org/](https://getcomposer.org/)
3. Platform CLI tool [https://docs.platform.sh/development/cli.html](https://docs.platform.sh/development/cli.html)

- Clone this repo
- at the command line, 'cd' into your new directory
- `lando start`

Your site should then run locally and should present you with a set of local URLs (thanks to the domain access module).

You should choose one of these URLs ('http://execoffice.lndo.site' for example) and run through the Drupal install,
using the credentials shown for the 'database' service when running the 'lando info' command.

Once this has finished, it is recommended that you download the databases from Platform.sh using the 'platform db:dump'
command.

The 'main' database may be imported into your local Lando site as follows:

  lando db-import <downloaded file name>

The 'drupal7db' database may be imported into your local Lando site as follows:

  lando db-import -h drupal7db <downloaded file name>

## Running migrations

You will need to install modules that start with 'Department sites: migration' in order to run migrations to import Drupal 7
content into your Drupal 9 database.
