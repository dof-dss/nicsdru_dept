#!/usr/bin/env bash

. /helpers/log.sh

lando_green "Cloning dof-dss development repositories";

lando_blue "Cloning Origins modules"
rm -rf /app/web/modules/origins
git clone git@github.com:dof-dss/nicsdru_origins_modules.git /app/web/modules/origins

lando_green "Go develop!";
