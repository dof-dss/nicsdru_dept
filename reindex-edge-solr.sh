#!/usr/bin/env bash
PLATFORM_SOLR_HOST=solr.internal:8080

if [ ${PLATFORM_BRANCH} != "DEPT-edge" ];
then
  # Not running on the edge environment, exit.
  exit 0
fi

echo "Force purge of Solr service contents..."
curl http://${PLATFORM_SOLR_HOST}/solr/default/update --data '<delete><query>*:*</query></delete>' -H 'Content-type:text/xml; charset=utf-8'
curl http://${PLATFORM_SOLR_HOST}/solr/default/update --data '<commit/>' -H 'Content-type:text/xml; charset=utf-8'

echo "Reset Solr tracker index"
drush sapi-rt
echo "Clear Solr indexes"
drush sapi-c
echo "Mark all content for re-indexing"
drush sapi-r
echo "Reindex all active indices"
drush sapi-i --batch-size=250

echo "DONE"
