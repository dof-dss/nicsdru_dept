#!/usr/bin/env bash
echo ">>> Started at: $(date -u +"%Y-%m-%d %H:%M:%S")"

departments=(daera communities economy education finance health infrastructure justice executiveoffice)
# Note: This is not the full list of content types, review the other imports in this script.
content_types=(topic subtopic actions application article collection consultation contact easychart gallery heritage_site infogram link page profile protected_area ual)

# Check that the $MIGRATE_IGNORE_SITES environment variable is present.
if [ -z "$MIGRATE_IGNORE_SITES" ]
then
  echo "MIGRATE_IGNORE_SITES environment variable is not set"
  exit 1
else
  # Create array of excluded departments from environment variable.
  IFS=', ' read -r -a excluded_departments <<< "$MIGRATE_IGNORE_SITES"
fi

# *****************************************************
# Array of content types that should not be migrated. *
# *****************************************************
if [ -n "$MIGRATE_IGNORE_TYPES" ]
then
  echo "Ignoring the following content types: $MIGRATE_IGNORE_TYPES"
  # Create array of excluded types from environment variable.
  IFS=', ' read -r -a excluded_content_types <<< "$MIGRATE_IGNORE_TYPES"

  for i in "${excluded_content_types[@]}"; do
    content_types=(${content_types[@]//*$i*})
  done
fi

export DRUSH=/app/vendor/bin/drush
# shellcheck disable=SC2089
export MIGRATIONS="\
  d7_taxonomy_term_chart_type \
  d7_taxonomy_term_global_topics
  d7_taxonomy_term_indicators \
  d7_taxonomy_term_outcomes users \
  d7_file \
  d7_file_private \
  d7_file_media_document \
  d7_file_media_secure_file \
  d7_file_media_image \
  d7_file_media_video \
  node_actions \
  node_subtopic \
  node_topic \
  node_application \
  node_article \
  node_consultation \
  node_contact \
  node_easychart \
  node_gallery \
  node_heritage_site \
  node_infogram \
  node_link \
  node_news \
  node_page \
  node_profile \
  node_protected_area \
  node_publication \
  node_ual \
  url_aliases_nodes \
  url_aliases_taxonomy_terms \
  redirects \
  flagging_display_on_rss_feeds \
  flagging_hide_listing \
  flagging_hide_on_topic_subtopic_pages "

if [ -z ${PLATFORM_BRANCH} ] && [ -z ${DDEV_PROJECT} ];
then
  # Not running on a platform environment, or locally, so exit.
  echo "Couldn't detect platform branch or local project variable, exiting."
  exit 1
fi

# Only execute on the main environment.
if [[ "${PLATFORM_BRANCH}" == "main" || "${DDEV_PROJECT}" == "dept" || ${MIGRATE_OVERRIDE} == "override" ]];
then

  echo "Resetting all migrations"
  for m in $MIGRATIONS
  do
    $DRUSH migrate:reset $m
  done

#  echo "Make sure active config matches that from migrate modules"
#  $DRUSH cim --partial --source=/app/web/modules/custom/dept_migrate/modules/dept_migrate_flags/config/install -y
#  $DRUSH cim --partial --source=/app/web/modules/custom/dept_migrate/modules/dept_migrate_taxo/config/install -y
#
#  for type in users files nodes
#  do
#    $DRUSH cim --partial --source=/app/web/modules/custom/dept_migrate/modules/dept_migrate_$type/config/install -y
#  done

  echo "Migrating D7 taxonomy data"
  $DRUSH migrate:import --group=migrate_drupal_7_taxo

  echo "Migrating D7 user and roles"
  $DRUSH migrate:import users --force

  echo "Migrating D7 files and media entities"
  $DRUSH migrate:import d7_file_private --force
  $DRUSH migrate:import d7_file --force

  for type in image video secure_file
  do
    echo "Migrating D7 ${type} to corresponding media entities"
    $DRUSH migrate:import d7_file_media_$type --force
  done

  echo "Migrating D7 file documents to corresponding media entities"
  for i in {1..10}
  do
    $DRUSH migrate:import d7_file_media_document --force --limit=10000
  done

  # Turn off content_lock modules as they interfere with node and redirect entity creation here.
  $DRUSH pmu content_lock,content_lock_timeout
  # NB: module will be re-enabled by config import at end of this script.

  for type in "${content_types[@]}"
  do
    echo "Migrate D7 ${type} nodes"
    $DRUSH migrate:import node_$type --force
  done

  # Handle the larger quantity types independently, in batches, to avoid PHP timeouts.
  echo "Migrate D7 news nodes"
  for i in {1..6}
  do
    $DRUSH migrate:import node_news --force --limit=2500
  done

  echo "Migrate D7 publication nodes"
  for i in {1..12}
  do
    $DRUSH migrate:import node_publication --force --limit=2500
  done

  # Force a refresh on some node types to see if this alleviates
  # occasional missing content issues from the body field on articles.
  $DRUSH migrate:import node_article --update --force
  $DRUSH migrate:import node_page --update --force
  $DRUSH migrate:import node_subtopic --update --force

  echo "Migrate book config"
  $DRUSH migrate:import dept_book --force

  echo "Migrating URL aliases and redirects"
  $DRUSH migrate:import url_aliases_nodes --force
  $DRUSH migrate:import redirects --force

  # ******************************************
  # Execute any non-live department commands *
  #                                          *
  # From this point on the departments array *
  # will only contain those departments not  *
  # present in the $MIGRATE_IGNORE_SITES     *
  # environment variable                     *
  # ******************************************
  echo "Syncing featured content on the department homepages"
  $DRUSH dept:sync-homepage-content

  $DRUSH purge-mig-logs dept_migrate_invalid_links

  # Remove the departments in the $MIGRATE_IGNORE_SITES
  # excluded_departments array from the departments array.
  for i in "${excluded_departments[@]}"; do
    departments=(${departments[@]//*$i*})
  done

  # Grab an array of sites we want to avoid adjusting topics/subtopics content for.
  IFS=',' read -r -a freeze_array <<< "$MIGRATE_TOPIC_FREEZE"
  # Loop through the list of non-live departments.
  for dept in "${departments[@]}"
    do
      # Check if the department is in the MIGRATE_TOPIC_FREEZE list
      if [[ " ${freeze_array[@]} " =~ " ${dept} " ]]; then
        echo "Skipping ${dept} as it is in the MIGRATE_TOPIC_FREEZE list"
        continue
      fi

      echo "Creating Topic/Subtopic content entries for ${dept}"
      $DRUSH dept:topics-child-contents $dept

      echo "Removing Audit Due entries for ${dept}"
      $DRUSH dept:remove-audit-date $dept

      echo "Creating Audit Due entries for ${dept}"
      $DRUSH dept:update-audit-date $dept

      echo "Updating content links for ${dept}"
      $DRUSH dept:updatelinks $dept

    done

  echo ".... DONE"
fi

  echo "Restoring config from config/sync"
  $DRUSH cim -y

echo ">>> Finished at: $(date -u +"%Y-%m-%d %H:%M:%S")"
