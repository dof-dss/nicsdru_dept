<?php

namespace Drupal\dept_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use PHPStan\DependencyInjection\Container;

/**
 * Drupal 7 UUID to NID lookup plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "uuid_to_nid"
 * )
 */
class UuidToNid extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Cast the $value to be an array, even for single values.
    if (!is_array($value)) {
      $value = (array) $value;
    }

    $node_metadata = \Drupal::service('dept_migrate.migrate_uuid_lookup_manager')
      ->lookupBySourceUuId($value);

    if (!empty($node_metadata)) {
      return reset($node_metadata)['d7nid'];
    }
  }

}
