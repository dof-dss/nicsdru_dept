# Migrations

For services like the UUID Lookup Manager to function correctly migration files should be named using the content type
machine name
e.g.
- D7 content type machine name: application
- D9 migration filename: migrate_plus.migration.node_application

Do not add an 's' (e.g. applications) to the end of the filename.

## Specifying specific items of content to import

The small patch to migrate_tools permits us to use pipe-separated id values, as we sometimes use three unique values from the source db to identify an item of content.

Eg: `drush migrate:import node_publication --update --force --idlist='c4e0d623-d165-45b8-a6c6-82c903a67e81:52834:0,9'`

This idlist specifier uses:

* D7 UUID
* D7 Node ID
* D7 domain IDs (comma separated)

Without the patch, the comma separated domain ids would break the id list builder class. You can see this in `web/modules/contrib/migrate_tools/src/MigrateTools.php`
