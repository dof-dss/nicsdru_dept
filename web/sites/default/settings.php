<?php

$databases = [];
$config_directories = [];
$settings['update_free_access'] = FALSE;
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

// Site hash salt.
$settings['hash_salt'] = getenv('HASH_SALT');

// Configuration sync base/default directory.
$settings['config_sync_directory'] = getenv('CONFIG_SYNC_DIRECTORY');

// Temp directory.
$settings["file_temp_path"] = getenv('FILE_TEMP_PATH') ?? '/tmp';
// Private files location.
$settings['file_private_path'] = getenv('FILE_PRIVATE_PATH');

// Set config split environment; environment specific values is set near the end of this file.
$config['config_split.config_split.local']['status'] = FALSE;
$config['config_split.config_split.development']['status'] = FALSE;
$config['config_split.config_split.production']['status'] = FALSE;

// Config readonly settings; should be set to 1 or 0 due to type juggling in PHP unable to correctly interpret strings
// such as 'true' or 'false' from envvars.
$settings['config_readonly'] = (bool) getenv('CONFIG_READONLY');

// Permit changes via command line.
if (PHP_SAPI === 'cli') {
  $settings['config_readonly'] = FALSE;
  // Increase memory for occasional drush tooling that needs it, eg: migration.
  ini_set('memory_limit', '384M');
}

// Configuration that is allowed to be changed in readonly environments.
$settings['config_readonly_whitelist_patterns'] = [
  'system.site',
  'search_api.index.default_content',
];

// Geolocation module API key.
$config['geolocation_google_maps.settings']['google_map_api_key'] = getenv('GOOGLE_MAP_API_KEY');
$config['geolocation_google_maps.settings']['google_map_api_server_key'] = getenv('GOOGLE_MAP_API_SERVER_KEY');
// Geocoder module API key.
$config['geocoder.settings']['plugins_options']['googlemaps']['apikey'] = getenv('GOOGLE_MAP_API_SERVER_KEY');

// Google Map Field config settings.
$config['google_map_field.settings']['google_map_field_apikey'] = getenv('GOOGLE_MAP_API_KEY');

// Environment indicator defaults.
$env_colour = !empty(getenv('SIMPLEI_ENV_COLOUR')) ? getenv('SIMPLEI_ENV_COLOUR') : '#000000';;
$env_name = !empty(getenv('SIMPLEI_ENV_NAME')) ? getenv('SIMPLEI_ENV_NAME') : getenv('PLATFORM_BRANCH');

// Prevents legacy Symfony ApcClassLoader from being used instead of Composer's.
$settings['class_loader_auto_detect'] = FALSE;

// If we're running on platform.sh, check for and load relevant settings.
if (!empty(getenv('PLATFORM_BRANCH'))) {
  if (file_exists($app_root . '/' . $site_path . '/settings.platformsh.php')) {
    include $app_root . '/' . $site_path . '/settings.platformsh.php';
  }

  // Environment specific settings and services.
  switch (getenv('PLATFORM_BRANCH')) {
    case 'main':
    case 'staging':
      $config['config_split.config_split.production']['status'] = TRUE;
      break;

    default:
      // Remap domain records to match PSH environment URLs.
      foreach (['finance', 'communities', 'health', 'infrastructure', 'daera', 'nigov', 'justice', 'economy', 'education', 'executiveoffice'] as $dept) {
        $config["domain.record.{$dept}"]['hostname'] = sprintf("www.%s.%s-%s.uk-1.platformsh.site",
          ($dept != 'nigov') ? $dept . '-ni' : $dept,
          getenv('PLATFORM_ENVIRONMENT'),
          getenv('PLATFORM_PROJECT'));
      }

      // Default to use development settings/services for general platform.sh environments.
      $config['config_split.config_split.development']['status'] = TRUE;
  }
}

$settings['simple_environment_indicator'] = sprintf('%s %s', $env_colour, $env_name);

// Set classic migrate mode to skip revisions on import.
// https://www.drupal.org/node/3105503.
$settings['migrate_node_migrate_type_classic'] = TRUE;


// Automatically generated include for settings managed by ddev.
$ddev_settings = dirname(__FILE__) . '/settings.ddev.php';
if (getenv('IS_DDEV_PROJECT') == 'true' && is_readable($ddev_settings)) {
  require $ddev_settings;
}

// Include settings required for Redis cache.
if ((file_exists(__DIR__ . '/settings.ddev.redis.php') && getenv('IS_DDEV_PROJECT') == 'true')) {
  include __DIR__ . '/settings.ddev.redis.php';
}

// Local settings. These come last so that they can override anything.
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
