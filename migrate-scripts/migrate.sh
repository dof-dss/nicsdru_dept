#!/usr/bin/env bash
export DRUSH=/app/vendor/bin/drush
export MIGRATIONS="d7_taxonomy_term_chart_type d7_taxonomy_term_global_topics d7_taxonomy_term_indicators \
        d7_taxonomy_term_outcomes users d7_file d7_file_private d7_file_media_image d7_file_media_video \
          node_easychart node_news node_publication"

if [ -z ${PLATFORM_BRANCH} ] && [ -z ${LANDO} ];
then
  # Not running on a platform environment, or Lando, so exit.
  echo "Couldn't detect platform branch or Lando variable, exiting."
  exit 1
fi

# Only execute on the main environment.
if [[ "${PLATFORM_BRANCH}" == "main" || "${LANDO}" == "ON" ]];
then
  echo "Resetting all migrations"
  for m in $MIGRATIONS
  do
    $DRUSH migrate:reset $m
  done

  echo "Make sure active config matches that from migrate modules"
  $DRUSH cim --partial --source=/app/web/modules/custom/dept_migrate/modules/dept_migrate_taxo/config/install -y

  for type in users files nodes
  do
    $DRUSH cim --partial --source=/app/web/modules/custom/dept_migrate/modules/dept_migrate_group_$type/config/install -y
  done

#  echo "Migrating D7 taxonomy data"
#  $DRUSH migrate:import --group=migrate_drupal_7_taxo --force

  echo "Migrating D7 user and roles"
  $DRUSH migrate:import users --force

  echo "Migrating D7 files and media entities"
  $DRUSH migrate:import d7_file --force
  $DRUSH migrate:import d7_file_private --force

  for type in image video
  do
    echo "Migrating D7 ${type} to corresponding media entities"
    $DRUSH migrate:import d7_file_media_$type --force
  done

  for type in easychart news publication
  do
    echo "Migrate D7 ${type} nodes"
    $DRUSH migrate:import node_$type --force
  done

  echo ".... DONE"
fi
