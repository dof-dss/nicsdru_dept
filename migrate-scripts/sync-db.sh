#!/bin/bash
D7_PROJECT_ID=$1
D7_PROJECT_ENV=$2

platform db:dump -p $D7_PROJECT_ID -e $D7_PROJECT_ENV --gzip -f /app/imports/d7.sql.gz --exclude-table={'cache','cache_admin_menu','cache_block','cache_bootstrap','cache_features','cache_field','cache_filter','cache_form','cache_image','cache_libraries','cache_menu','cache_metatag','cache_oembed','cache_page','cache_panels','cache_path','cache_rules','cache_search_api_solr','cache_token','cache_ultimate_cron','cache_update','cache_variable','cache_views','cache_views_data','search_index','search_dataset','search_node_links','search_total','watchdog','history','queue'} -y
