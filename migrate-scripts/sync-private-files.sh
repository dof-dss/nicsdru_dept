#!/usr/bin/env bash
D7_PROJECT_ID=$1
D7_PROJECT_ENV=$2

echo "Copying private files from D7 mount..."
platform mount:download -p $D7_PROJECT_ID -e $D7_PROJECT_ENV -A deptinternet -m "/private" --target "/app/imports/files/private" -y
