<?php

namespace Drupal\dept_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drupal 7 UUID to NID lookup plugin.
 *
 * Example usage:
 * @code
 * process:
 *   field_name:
 *      -
 *        plugin: uuid_to_nid
 *        source: uuid
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "uuid_to_nid"
 * )
 */
class UuidToNid extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrateUuidLookupManager $lookup_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->lookupManager = $lookup_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dept_migrate.migrate_uuid_lookup_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Cast the $value to be an array, even for single values.
    if (!is_array($value)) {
      $value = (array) $value;
    }

    $node_metadata = $this->lookupManager->lookupBySourceUuId($value);

    if (!empty($node_metadata)) {
      return reset($node_metadata)['d7nid'];
    }
  }

}
