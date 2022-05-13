<?php

namespace Drupal\dept_migrate_group_files\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Provides a 'VideoUrl' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "video_url"
 * )
 */
class VideoUrl extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // This is a text field in D8 and we don't need the 'oembed://' prefix.
    $url = preg_replace('|^oembed:\/\/|', '', $value);
    // Remove HTML special chars to give a user readable string.
    $url = urldecode($url);

    return $url;
  }

}
