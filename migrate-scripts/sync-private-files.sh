#!/usr/bin/env bash
D7_PROJECT_ID=$1
D7_PROJECT_ENV=$2

echo "Authenticating API token..."
platform auth:api-token-login

echo "Copying private files from D7 mount..."
platform mount:download -p $D7_PROJECT_ID -e $D7_PROJECT_ENV -A deptinternet -m "/private" --target "/app/imports/files/private" -y

# Get rid of the backup_migrate directory if it exists.
if [ -d /app/imports/files/private/backup_migrate ]; then
  rm -rf /app/imports/files/private/backup_migrate
fi
