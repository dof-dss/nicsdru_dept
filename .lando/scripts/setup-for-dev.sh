#!/usr/bin/env bash

. /helpers/log.sh

lando_green "Cloning dof-dss development repositories";

if [ -d "/app/web/themes/custom/nicsdru_dept_theme" ]; then
  lando_pink "Dept theme folder already present..."
  mv /app/web/themes/custom/nicsdru_dept_theme /app/.lando/exports/
  lando_pink "FYI: I moved it to /app/.lando/exports/nicsdru_dept_theme for reference, if you need it."
fi

lando_blue "Cloning DEPT theme"
rm -rf /app/web/themes/custom/nicsdru_dept_theme
git clone git@github.com:dof-dss/nicsdru_dept_theme.git /app/web/themes/custom/nicsdru_dept_theme

lando_blue "Cloning Origins modules"
rm -rf /app/web/modules/origins
git clone git@github.com:dof-dss/nicsdru_origins_modules.git /app/web/modules/origins

lando_green "Go develop!";
