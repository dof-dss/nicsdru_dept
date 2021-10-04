#!/usr/bin/env bash
export DRUSH=/app/vendor/bin/drush

echo "Resetting all migrations"
for m in d7_user_role d7_user d7_taxonomy_term_chart_type d7_taxonomy_term_global_topics d7_taxonomy_term_indicators d7_taxonomy_term_outcomes d7_file d7_file_media_image group_media_image node_news group_node_news group_users
do
  $DRUSH migrate:reset $m
done

echo "Migrating D7 user and roles"
$DRUSH migrate:import d7_users --execute-dependencies --force
echo "... associate User entities with Group entities"
$DRUSH migrate:import group_users --force

echo "Migrating D7 taxonomy data"
$DRUSH migrate:import --group=migrate_drupal_7_taxo --force

echo "Migrating D7 files"
$DRUSH migrate:import d7_file --force
echo "Migrating D7 images to Image media entities"
$DRUSH migrate:import d7_file_media_image --force
echo "... associate Image media entities with Group entities"
$DRUSH migrate:import group_media_image --force

echo "Migrate D7 news nodes"
$DRUSH migrate:import node_news --force
echo "... associate News nodes with Group entities"
$DRUSH migrate:import group_node_news --force

echo ".... DONE"
