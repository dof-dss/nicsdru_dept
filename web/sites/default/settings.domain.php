<?php

// @codingStandardsIgnoreFile

// This file overrides the hostname values held in config
// for certain environments so a site can be identified.

// TODO: uncomment when live to avoid unecessary config overrides,
// and rely on raw values from .yml files.
//if (empty(getenv('LANDO')) || getenv('PLATFORM_ENVIRONMENT') === 'production') {
//  // Stop early if we don't need to change anything.
//  return;
//}

foreach (glob(getenv('CONFIG_SYNC_DIRECTORY') . '/domain.record.*') as $domain_config_file) {
  $config_id = pathinfo($domain_config_file, PATHINFO_BASENAME);

  // Pull the domain id from the hostname prefix
  // eg: finance-ni from financi-ni.env-hash-projectid.region.platformsh.site
  // or finance-ni.lndo.site.
  $matches = [];
  $file = DRUPAL_ROOT . '/' . $domain_config_file;
  $content = file_get_contents($file);
  preg_match('|^hostname: (.+).gov.uk|mi', $content, $matches);
  $site_id = $matches[1];

  if (empty($site_id)) {
    var_dump('No match with ' . $domain_config_file);
    // Skip if we can't detect a site id/prefix.
    continue;
  }

  $host = '.lndo.site';

  if (getenv('PLATFORM_ENVIRONMENT')) {
    // Fixed value (for now). Replace with env var if/when it becomes available.
    $region = 'uk-1.platformsh.site';
    $host = sprintf('%s-%s.%s', getenv('PLATFORM_ENVIRONMENT'), getenv('PLATFORM_PROJECT'), $region);
  }

  var_dump($config_id . ' :: ' . sprintf('%s.%s', $site_id, $host));

  $config[$config_id]['hostname'] = sprintf('%s.%s', $site_id, $host);
}
