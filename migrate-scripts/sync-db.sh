#!/usr/bin/env bash
D7_PROJECT_ID=$1
D7_PROJECT_ENV=$2

echo "Authenticating API token..."
platform auth:api-token-login

echo "Fetching D7 db from legacy project..."
platform db:dump -p $D7_PROJECT_ID -e $D7_PROJECT_ENV -A deptinternet --gzip -f /app/imports/d7.sql.gz --exclude-table={'cache','cache_admin_menu','cache_block','cache_bootstrap','cache_features',,'cache_filter','cache_form','cache_image','cache_libraries','cache_menu','cache_metatag','cache_oembed','cache_page','cache_panels','cache_path','cache_rules','cache_search_api_solr','cache_token','cache_ultimate_cron','cache_update','cache_variable','cache_views','cache_views_data','search_index','search_dataset','search_node_links','search_total','watchdog','history','queue'} -y

echo "Restore d7.sql.gz into drupal7db database..."
if [ -f /app/imports/d7.sql.gz ]; then
  gunzip -f /app/imports/d7.sql.gz
fi
vendor/bin/drush sqlc --database=drupal7db < /app/imports/d7.sql
gzip -f /app/imports/d7.sql
mv /app/imports/d7.sql.gz /app/imports/d7.last.sql.gz
echo ".... DONE"
