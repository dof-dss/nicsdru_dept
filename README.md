# Departmental sites codebase

This source code is for the Departmental sites. It is built with Drupal 9 in a single codebase, single database manner using these key modules to control content and access across the sites:

* group (for entity access control and management)
* domain (for hostname negotiation and mapping to group entities)

It is hosted on platform.sh.

Continuous Integration services are provided by Circle CI.

## Getting started

We recommend Lando for local development. To get started, ensure you have the following installed:

1. Lando [https://docs.devwithlando.io/](https://docs.devwithlando.io/)
2. Composer [https://getcomposer.org/](https://getcomposer.org/)
3. Platform CLI tool [https://docs.platform.sh/development/cli.html](https://docs.platform.sh/development/cli.html)

- Clone this repo
- at the command line, 'cd' into your new directory
- `lando start`

Once this has finished, it is recommended that you download the databases from Platform.sh using the 'platform db:dump'
command.

The 'main' database may be imported into your local Lando site as follows:

  `lando db-import <downloaded file name>`

The 'drupal7db' database may be imported into your local Lando site as follows:

  `lando db-import -h drupal7db <downloaded file name>`

### Domain hostnames

See `.lando.yml` and the `proxy` configuration section for local hostnames to represent the different sites.

* For administrators, we recommend using `https://dept.lndo.site` for general site administration.
* All other authenticated users should sign in to the site that they are managing content for, eg: `https://finance.lndo.site`

## Running migrations

You will need to install modules that start with 'Department sites: migration' in order to run migrations to import Drupal 7
content into your Drupal 9 database.

Listing key migrations: `lando drush migrate:status --tag=dept_sites`

NB: migration order is important. A script will be written to be either run as-is, or executed step-by-step as needed.

Migrations will be progressive and incremental over time as sites move across from Drupal 7 to this codebase. ID clashes are expected as content is added at both sides. Migrate API will track source IDs to destination IDs by itself. This does *not* extend to revisions which is why revisions are not included in the scope of migrations.
