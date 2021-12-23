#!/usr/bin/env bash
export DRUSH=/app/vendor/bin/drush
export MIGRATIONS=d7_taxonomy_term_chart_type d7_taxonomy_term_global_topics d7_taxonomy_term_indicators \
        d7_taxonomy_term_outcomes users d7_file d7_file_media_image d7_file_media_video node_easychart \
        node_news group_users group_media_image group_media_video group_node_easychart group_node_news

if [ -z ${PLATFORM_BRANCH} ]
then
  # Not running on a platform environment, exit.
  exit 1
fi

# Only execute on the main environment.
if [ "${PLATFORM_BRANCH}" == "main" ]
then
  echo "Resetting all migrations"
  for m in $MIGRATIONS
  do
    $DRUSH migrate:reset $m
  done

  echo "Make sure active config matches that from migrate modules"
  for type in users files nodes taxo
  do
    $DRUSH cim --partial --source=/app/web/modules/custom/dept_migrate/modules/dept_migrate_group_$type/config/install -y
  done

  echo "Migrating D7 taxonomy data"
  $DRUSH migrate:import --group=migrate_drupal_7_taxo --force

  echo "Migrating D7 user and roles"
  $DRUSH migrate:import users --force
  echo "... associate User entities with Group entities"
  $DRUSH migrate:import group_users --force

  echo "Migrating D7 files and media entities"
  $DRUSH migrate:import d7_file --force
  for type in image video

  }do
    echo "Migrating D7 ${type} to corresponding media entities"
    $DRUSH migrate:import d7_file_media_$type --force
    echo "... associate ${type} media entities with Group entities"
    $DRUSH migrate:import group_media_$type --force
  done

  for type in easychart news
  do
    echo "Migrate D7 ${type} nodes"
    $DRUSH migrate:import node_$type --force
    echo "... associate ${type} nodes with Group entities"
    $DRUSH migrate:import group_node_$type --force
  done

  echo ".... DONE"
fi
