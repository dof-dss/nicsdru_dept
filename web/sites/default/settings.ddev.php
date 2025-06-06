<?php

$databases['default']['default'] = [
  'database'  => getenv('DB_NAME'),
  'username'  => getenv('DB_USER'),
  'password'  => getenv('DB_PASS'),
  'host'      => getenv('DB_HOST'),
  'port'      => getenv('DB_PORT'),
  'driver'    => getenv('DB_DRIVER'),
];

// Recommended setting for Drupal 10 only
$settings['state_cache'] = TRUE;

// This will prevent Drupal from setting read-only permissions on sites/default.
$settings['skip_permissions_hardening'] = TRUE;

// This will ensure the site can only be accessed through the intended host
// names. Additional host patterns can be added for custom configurations.
$settings['trusted_host_patterns'] = ['.*'];

// Don't use Symfony's APCLoader. ddev includes APCu; Composer's APCu loader has
// better performance.
$settings['class_loader_auto_detect'] = FALSE;

// Set $settings['config_sync_directory'] if not set in settings.php.
if (empty($settings['config_sync_directory'])) {
  $settings['config_sync_directory'] = 'sites/default/files/sync';
}

// Hardcode the settings for the Solr config to match our ddev config.
// The machine name of the server in your Drupal configuration: 'solr_server'.
$config['search_api.server.solr_server']['backend_config']['connector'] = 'basic_auth';
$config['search_api.server.solr_server']['backend_config']['connector_config']['host'] = 'solr';
$config['search_api.server.solr_server']['backend_config']['connector_config']['username'] = getenv('SOLR_AUTH_USER');
$config['search_api.server.solr_server']['backend_config']['connector_config']['password'] = getenv('SOLR_AUTH_PASS');
$config['search_api.server.solr_server']['backend_config']['connector_config']['core'] = 'default';

// Override drupal/symfony_mailer default config to use Mailpit.
$config['symfony_mailer.settings']['default_transport'] = 'sendmail';
$config['symfony_mailer.mailer_transport.sendmail']['plugin'] = 'smtp';
$config['symfony_mailer.mailer_transport.sendmail']['configuration']['user'] = '';
$config['symfony_mailer.mailer_transport.sendmail']['configuration']['pass'] = '';
$config['symfony_mailer.mailer_transport.sendmail']['configuration']['host'] = 'localhost';
$config['symfony_mailer.mailer_transport.sendmail']['configuration']['port'] = '1025';

// Enable verbose logging for errors.
// https://www.drupal.org/forum/support/post-installation/2018-07-18/enable-drupal-8-backend-errorlogdebugging-mode
$config['system.logging']['error_level'] = 'verbose';
