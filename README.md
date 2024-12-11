[![CircleCI](https://circleci.com/gh/dof-dss/nicsdru_dept/tree/development.svg?style=svg)](https://circleci.com/gh/dof-dss/nicsdru_dept/tree/development)

**NOTE: NIGov (northernireland.gov.uk)**
The custom modules automatically grant access to all news press releases, publications and consultations from the NIGov domain.

# Departmental sites codebase

This source code is for the Departmental sites. It is built with Drupal 9 in a single codebase, single database manner using the Domain module to control content and access across the sites:

* [domain](https://www.drupal.org/project/domain)

It is hosted on platform.sh.

Continuous Integration services are provided by [Circle CI](https://github.com/dof-dss/nicsdru_dept/blob/development/.circleci/config.yml).

## Getting started

### Pre-requisites

1. Rancher Desktop [https://docs.rancherdesktop.io/(https://docs.rancherdesktop.io/)]
  - Install notes: https://ddev.readthedocs.io/en/stable/users/install/docker-installation/#rancher-desktop
  - Rancher is an cross-platform Open Source container management tool, as an alternative to Docker Desktop.
  - **Make sure you turn off Kubernetes support or it can block port 80 and 443 causing DDEV to fail to start.**
2. DDEV [https://ddev.com/get-started/](https://ddev.com/get-started/)
  - The project uses a Mutagen (a file sync agent) performance profile for all platforms. It installs itself into each project, as needed.
3. Platform CLI tool and access [https://docs.platform.sh/development/cli.html](https://docs.platform.sh/development/cli.html)
  - You will need to request access to the projects you work on.
  - Once granted, log in with `platform login -f` to renew any local SSH certs.
4. Environment variables
  - You need to populate the sensitive values in `.ddev/.env` as part of the build process.
  - **Ask the team for how best to obtain these.**
5. Platform SH API Token
  - You will need to generate an API Token for DDev. This can be found on platform.sh -> My Profile -> API Tokens
  - Assign the token to the PLATFORMSH_CLI_TOKEN key of your .env file.
6. Install DBeaver or SequelAce (if you haven't done so already)
  - DBeaver (MacOS for Apple Silicon) https://dbeaver.io/download/
  - Sequel Ace https://sequel-ace.com

### Next installation/import steps

- Clone this repo and change directory into it.
- `mv <path-to-dot-env-file> .ddev/.env`
- `ddev start`. This will run composer inside the container for you, avoiding host-level inconsistencies.
- Verify env vars have taken effect with: `ddev exec "env | sort""`
- Run `ddev pull dept` which will import both databases and run config import.
- To inspect the databases you can use either `ddev dbeaver` or `ddev sequelace` depending on the DBMS you have installed.
- Copy your ~/.composer/auth.json to .ddev/homeadditions/.composer to allow git operations inside the container using `ddev composer`

## Troubleshooting

- Can't connect to the database
  - Ensure your .env file values are valid and in effect. See https://ddev.readthedocs.io/en/stable/users/extend/customization-extendibility/#environment-variables-for-containers-and-services for details.
  - Ensure your databases have fully imported.
- Port 443 or 80 is blocked.
  - Make sure Rancher Desktop's Kubernetes option is disabled.
  - Run `lando poweroff` to ensure any legacy applications are not listening on the ports.
  - https://ddev.readthedocs.io/en/stable/users/usage/troubleshooting/#web-server-ports-already-occupied

## Project goals

* Provide non-admin users with an editorial experience that:
  * Masks the complexity around Domain architecture when operating the site for routine content tasks.
  * Is consistent with NIDirect and Unity sites for editorial tasks, with the exception of form elements to share content across multiple sites.
* Permit rolling content migrations from Drupal 7 for sites yet to launch without irregularities, content id clashes or service interruptions to either D7 or D10 applications.
  * We use D7 UUIDs rather than node ids to help with this, the tradeoff is that a D7 node will have a different node id in D10. This won't affect path aliases but migrate lookups will be needed for any migration plugin config, in favour of verbatim node id values from D7.
  * A D7 site will have a D10 domain record from the start. As migrations run, content will be added and updated for all sites. In short: we will get updates for all D7 sites for the migration configurations we have completed, on a rolling basis, until a site is launched on D10.
  * When a site launches to D10, we add the site id to the relevant migration config ignore list.
  * A site launch to D10 will involve (precise steps TBC):
    * Brief content embargo/freeze on D7.
    * DNS record updates, if not already resolving to Platform.sh IPs.
    * Platform.sh application config updates for D7 and D10; routes/domain bindings, for example.
    * Fastly config, as required.
    * Migrate config updates to exclude the new site from rolling D7 content updates.

### Domain hostnames

See `.ddev/config.yaml` and the `additional_hostnames` configuration section for local hostnames to represent the different sites.

## Site/content negotiation and detection

The project serves content for a number of websites. We can split the process of determining which site is being asked for (detection) and how we isolate and present the content (negotiation).

### Site detection

> Which site is being asked for in this request?

We use hostname patterns to determine which site is relevant to a given request. Requests to www.daera-ni.gov.uk and www.executiveoffice-ni.gov.uk both resolve (point) to the same underlying IP address which the services running Drupal are listening to.

By looking at the hostname, we can assess which site we need to use for creating the response. The hostname changes across different application environments, but they are predictable and share a common site id key. See `web/sites/default/settings.domain.php` for how this site id key is detected and used.

#### Production

* Hostname pattern: `https://(www).SITE_ID.gov.uk`
* Configuration files for domain records use this pattern by default.

#### Platform.sh

* Pre-production environments such as: feature branches, edge build, and staging.
* Hostname pattern: `https://SITE_ID.{default}` where `{default}` is the internal, platform.sh specific string. Example: `https://daera-ni.dept-edge-3e7cfpi-dnvkwx4xjhiza.uk-1.platformsh.site`
* See `.platform/routes.yaml` and `web/sites/default/settings.domain.php` for details.

#### Local development

We use DDEV for this, see `.ddev/config.yaml` for the structure and configuration of the services involved.

* Hostname pattern: `https://SITE_ID.ddev.site`
* See `web/sites/default/settings.domain.php` for how the SITE_ID key is extracted.

#### Redis Commander

Redis Commander provides a web based UI to view the Redis keystore which can help with debugging cache issues.

To install, from the root of your project, run: `ddev add-on get ddev/ddev-redis-commander` and `ddev restart`
The url for the Commander UI will be listed in `ddev describe`

If the Https link doesn't work, change to http and set the port to 1358.

### Content negotiation

> What content should be displayed for the current detected site?

Once we have determined the site that a request is being made for, we need to assess how to present content for this. Internally, Drupal uses the Domain module and the node_access table to determine
if the current Domain has been granted view access to that node.

Each entity has 2 entity reference fields, field_domain_access and field_domain_source.

field_domain_access : Multiple references to domains the content can be viewed on.
field_domain_source : Single reference to the domain the content belongs to.

## Migrations

You will need to install modules that start with `Department sites: migration` in order to run migrations to import Drupal 7
content into your Drupal 10 database.

Listing key migrations: `lando drush migrate:status --tag=dept_sites`

NB: migration order is important. The `migrate-scripts/migrate.sh` script outlines the correct sequence of migrations to run and is executed via platform.sh cron every night for a progressive top-up of changes.

> Do NOT use the `--sync` flag on migrate import tasks. This causes a full migration rollback and re-import which can cause confusion for site users, irregularities with other content and can be tricky and time consuming to correct.

There are a number of Drush commands to process migration data.
* dept:updatelinks updates text fields with internal links and converts to LinkIt format links

### Points of interest

* Views and other things that rely on entity API are able to use entity access rules in conjunction with user permissions to determine whether a node can be seen on a given site. Pros: it happens without the need for explicit filters to be added to views config or entity queries. Cons: it can be confusing if viewing the site as a user with an administrative role as it will usually bypass usual access conditions.
* Revisions: these are deemed too complex to track/import on a rolling basis. Access to older content will be available on the D7 application, running on platform.sh on an internal hostname.
* Negative numbers of unprocessed source items: This sometimes occurs when source items (in D7) are removed. The removals are not replicated on the destination (D10) resulting in a natural imbalance to the way unprocessed items are calculated by Migrate API. This is acknowledged/documented here: https://www.drupal.org/project/migrate_tools/issues/2843383. It is possible to re-sync the counts with the `--sync` flag but this isn't recommended, as the process involes a full rollback (removal of prior migrated D10 content) followed by a full import. This can be very time consuming and result in a confusing experience for any site users. It could also lead to inconsistencies in data if executed in an incorrect sequence. **Where possible, irregularities should be investigated on a case-by-case basis and a bulk update or sync operation carried out where there is a clear trend or pattern of inconsistencies to correct.**
* The Domain Access module creates a number of node fields to handle access. Although the values of these fields are saved to the relevant database table if you try retrieve these values (e.g via a preprocess hook) you will always have a null value. This is because these fields use a callback to retrieve the value. To view these callbacks you can look at the field definition and the callback property for that field.

### DepartmentManager helper class

The DepartmentManager class provides a collection of useful methods to load Department entities but does not currently support the creation or deletion of Departments.

##### Loading department(s)

The Department class should not be used to directly load a Department, this is the job of the DepartmentManager class and
can be injected using 'department.manager' or by calling `\Drupal::service('department.manager)`

The Manager has the following methods:

* getCurrentDepartment()
* getAllDepartments()
* getDepartment(id) - id is the machine name e.g. 'economy'

Department objects will be cached and cleared from the cache when

##### Example
```
$dept_manager = \Drupal::service('department.manager);

$finance_dept = $dept_manager->getDepartment('finance');
```

By default the site will inject the current Department into the page preprocess variables.

