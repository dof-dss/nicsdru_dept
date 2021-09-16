#!/usr/bin/env bash
D7_PROJECT_ID=$1
D7_PROJECT_ENV=$2

platform mount:download -p $D7_PROJECT_ID -e $D7_PROJECT_ENV -m "/public_html/sites/default/files" --exclude "css*" --exclude "js*" --exclude="status_check*" --exclude="ctools*" --target /app/imports/files/ -y
