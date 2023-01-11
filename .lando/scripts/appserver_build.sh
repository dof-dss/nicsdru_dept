#!/usr/bin/env bash

# Variables to indicate key settings files or directories for Drupal.
DRUPAL_ROOT=/app/web
DRUPAL_SETTINGS_FILE=$DRUPAL_ROOT/sites/default/settings.php
DRUPAL_SERVICES_FILE=$DRUPAL_ROOT/sites/default/services.yml
DRUPAL_CUSTOM_CODE=$DRUPAL_ROOT/modules/custom
DRUPAL_CUSTOM_THEME=$DRUPAL_ROOT/themes/custom/nicsdru_dept_theme

# Semaphore files to control whether we need to trigger an install
# of supporting software or config files.
NODE_INSTALLED=/etc/NODE_INSTALLED

# Create export directories for config and data.
if [ ! -d "/app/.lando/exports" ]; then
  echo "Creating export directories"
  mkdir -p /app/.lando/exports/config && mkdir /app/.lando/exports/data
fi

# If we don't have a Drupal install, download it.
if [ ! -d "/app/web/core" ]; then
  echo "Installing Drupal"
  export COMPOSER_PROCESS_TIMEOUT=600
  composer install
fi

# Create Drupal public files directory and set IO permissions.
if [ ! -d "/app/web/files" ]; then
  echo "Creating public Drupal files directory"
  mkdir -p /app/web/files
  chmod -R 0775 /app/web/files
fi

# Create Drupal private file directory above web root.
if [ ! -d "/app/.lando/private" ]; then
  echo "Creating private Drupal files directory"
  mkdir -p /app/.lando/private
fi

if [ ! -d $DRUPAL_ROOT/sites/default/settings.local.php ]; then
  echo "Creating local Drupal settings and developent services files"
  cp -v /app/.lando/config/drupal.settings.php $DRUPAL_ROOT/sites/default/settings.local.php
  cp -v /app/.lando/config/drupal.services.yml $DRUPAL_ROOT/sites/local.development.services.yml
fi

#echo "Copying Redis service overrides"
#cp -v /app/.lando/config/redis.services.yml $DRUPAL_ROOT/sites/default/redis.services.yml

# Set Simple test variables and put PHPUnit config in place.
if [ ! -f "${DRUPAL_ROOT}/core/phpunit.xml" ]; then
  echo "Adding localised PHPUnit config to Drupal webroot"
  cp $DRUPAL_ROOT/core/phpunit.xml.dist $DRUPAL_ROOT/core/phpunit.xml
  # Fix bootstrap path
  sed -i -e "s|bootstrap=\"tests/bootstrap.php\"|bootstrap=\"${DRUPAL_ROOT}/core/tests/bootstrap.php\"|g" $DRUPAL_ROOT/core/phpunit.xml
  # Inject database params for kernel tests.
  sed -i -e "s|name=\"SIMPLETEST_DB\" value=\"\"|name=\"SIMPLETEST_DB\" value=\"${DB_DRIVER}://${DB_USER}:${DB_PASS}@${DB_HOST}/${DB_NAME}\"|g" $DRUPAL_ROOT/core/phpunit.xml
  # Uncomment option to switch off Symfony deprecatons helper (we use drupal-check for this).
  sed -i -e "s|<!-- <env name=\"SYMFONY_DEPRECATIONS_HELPER\" value=\"disabled\"/> -->|<env name=\"SYMFONY_DEPRECATIONS_HELPER\" value=\"disabled\"/>|g" $DRUPAL_ROOT/core/phpunit.xml
  # Set the base URL for kernel tests.
  sed -i -e "s|name=\"SIMPLETEST_BASE_URL\" value=\"\"|name=\"SIMPLETEST_BASE_URL\" value=\"http:\/\/${LANDO_APP_NAME}.${LANDO_DOMAIN}\"|g" $DRUPAL_ROOT/core/phpunit.xml
fi

# Add yarn/nodejs packages to allow functional testing on this service.
if [ ! -f "${NODE_INSTALLED}" ]; then
  apt update
  # Add and fetch node 14 plus related OS packages to support it.
  curl -sL https://deb.nodesource.com/setup_14.x | bash -
  apt install -y nodejs gcc g++ make

  # Fetch and install node packages if they're not already present.
  if [ ! -d "${DRUPAL_ROOT}/core/node_modules" ]; then
    cd $DRUPAL_ROOT/core
    npm install
  fi

  # Install any known extra npm packges.
#  if [ ! -d "${DRUPAL_CUSTOM_CODE}/node_modules" ]; then
#    cd $DRUPAL_CUSTOM_CODE
#    npm install
#  fi

  # Install any known extra npm packges.
  if [ ! -d "${DRUPAL_CUSTOM_THEME}/node_modules" ]; then
    cd $DRUPAL_CUSTOM_THEME
    npm install
  fi

  touch $NODE_INSTALLED

fi
