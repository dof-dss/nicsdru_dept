<?php

// @codingStandardsIgnoreFile
$local_services_config = $app_root . '/sites/local.development.services.yml';
if (file_exists($local_services_config)) {
  $settings['container_yamls'][] = $local_services_config;
}

$settings['file_private_path'] = getenv('FILE_PRIVATE_PATH');

$databases['default']['default'] = [
  'database'  => getenv('DB_NAME'),
  'username'  => getenv('DB_USER'),
  'password'  => getenv('DB_PASS'),
  'prefix'    => getenv('DB_PREFIX'),
  'host'      => getenv('DB_HOST'),
  'port'      => getenv('DB_PORT'),
  'namespace' => getenv('DB_NAMESPACE'),
  'driver'    => getenv('DB_DRIVER'),
];

$databases['drupal7db']['default'] = array (
  'database' => getenv('MIGRATE_SOURCE_DB_NAME'),
  'username' => getenv('MIGRATE_SOURCE_DB_USER'),
  'password' => getenv('MIGRATE_SOURCE_DB_PASS'),
  'prefix' => getenv('MIGRATE_SOURCE_DB_PREFIX'),
  'host' => getenv('MIGRATE_SOURCE_DB_HOST'),
  'port' => getenv('MIGRATE_SOURCE_DB_PORT'),
  'namespace' => getenv('MIGRATE_SOURCE_DB_NAMESPACE'),
  'driver' => getenv('MIGRATE_SOURCE_DB_DRIVER'),
);

// Redis Cache.
// Due to issues with enabling Redis during install/config import. We cannot enable the cache backend by default.
// Once you have a site/db installed. Enable the Redis module and change the $redis_enabled to true.
$redis_enabled = TRUE;
if ($redis_enabled && !\Drupal\Core\Installer\InstallerKernel::installationAttempted() && extension_loaded('redis') && class_exists('Drupal\redis\ClientFactory')){
    $settings['redis.connection']['interface'] = 'PhpRedis';
    $settings['redis.connection']['host'] = getenv('REDIS_HOSTNAME');
    $settings['redis.connection']['port'] = getenv('REDIS_PORT');
    $settings['cache']['default'] = 'cache.backend.redis';
    $settings['container_yamls'][] = $app_root . '/' . $site_path . '/redis.services.yml';

    // Manually add the classloader path, this is required for the container cache bin definition below
    // and allows to use it without the redis module being enabled.
    $class_loader->addPsr4('Drupal\\redis\\', $app_root . '/' . $site_path . '/modules/contrib/redis/src');

    // Use redis for container cache.
    // The container cache is used to load the container definition itself, and
    // thus any configuration stored in the container itself is not available
    // yet. These lines force the container cache to use Redis rather than the
    // default SQL cache.
    $settings['bootstrap_container_definition'] = [
        'parameters' => [],
        'services' => [
            'redis.factory' => [
                'class' => 'Drupal\redis\ClientFactory',
            ],
            'cache.backend.redis' => [
                'class' => 'Drupal\redis\Cache\CacheBackendFactory',
                'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.phpserialize'],
            ],
            'cache.container' => [
                'class' => '\Drupal\redis\Cache\PhpRedis',
                'factory' => ['@cache.backend.redis', 'get'],
                'arguments' => ['container'],
            ],
            'cache_tags_provider.container' => [
                'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
                'arguments' => ['@redis.factory'],
            ],
            'serialization.phpserialize' => [
                'class' => 'Drupal\Component\Serialization\PhpSerialize',
            ],
        ],
    ];
}

// Prevent SqlBase from moaning.
$databases['migrate']['default'] = $databases['drupal7db']['default'];

// Custom configuration sync directory under web root.
$settings['config_sync_directory'] = getenv('CONFIG_SYNC_DIRECTORY');

// Set config split environment.
$config['config_split.config_split.local']['status'] = TRUE;
$config['config_split.config_split.development']['status'] = FALSE;
$config['config_split.config_split.production']['status'] = FALSE;

// Site hash salt.
$settings['hash_salt'] = getenv('HASH_SALT');

// Configuration that is allowed to be changed in readonly environments.
$settings['config_readonly_whitelist_patterns'] = [
  'system.site',
];

// Environment indicator config.
$settings['simple_environment_indicator'] = sprintf('%s %s', getenv('SIMPLEI_ENV_COLOUR'), getenv('SIMPLEI_ENV_NAME'));

// Geolocation module API key.
$config['geolocation_google_maps.settings']['google_map_api_key'] = getenv('GOOGLE_MAP_API_KEY');
$config['geolocation_google_maps.settings']['google_map_api_server_key'] = getenv('GOOGLE_MAP_API_SERVER_KEY');
// Geocoder module API key.
$config['geocoder.settings']['plugins_options']['googlemaps']['apikey'] = getenv('GOOGLE_MAP_API_SERVER_KEY');

// Google Analytics API config.
$config['google_analytics_counter.settings']['general_settings']['client_id'] = getenv('GA_CLIENT_ID');
$config['google_analytics_counter.settings']['general_settings']['client_secret'] = getenv('GA_CLIENT_SECRET');
$config['google_analytics_counter.settings']['general_settings']['redirect_uri'] = getenv('GA_REDIRECT_URI');
