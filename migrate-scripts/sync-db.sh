#!/usr/bin/env bash
D7_PROJECT_ID=$1
D7_PROJECT_ENV=$2

echo "Authenticating API token..."
platform auth:api-token-login

echo "Fetching D7 db from legacy project..."
platform db:dump -p $D7_PROJECT_ID -e $D7_PROJECT_ENV -A deptinternet --gzip -f /app/imports/d7.sql.gz --exclude-table={'cache','cache_admin_menu','cache_block','cache_bootstrap','cache_features',,'cache_filter','cache_form','cache_image','cache_libraries','cache_menu','cache_metatag','cache_oembed','cache_page','cache_panels','cache_path','cache_rules','cache_search_api_solr','cache_token','cache_ultimate_cron','cache_update','cache_variable','cache_views','cache_views_data','search_index','search_dataset','search_node_links','search_total','watchdog','history','queue'} -y

echo "Restore d7.sql.gz into drupal7db database..."
# sqlq with --file option permits us to stream the unzipped output
# from the large (~3GB) db dump file into the underlying mysql client
# yielding large disk space savings in the process.
vendor/bin/drush sqlq --database=drupal7db --file=/app/imports/d7.sql.gz

echo ".... DONE"
