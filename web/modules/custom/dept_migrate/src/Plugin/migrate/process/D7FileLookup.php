<?php

namespace Drupal\dept_migrate\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\media\MediaInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drupal 7 file lookup plugin.
 *
 * Allows us to find the D9 file id
 * from a D7 file id, when using UUIDs
 * as a migration id.
 *
 * Example usage:
 * @code
 * process:
 *   field_name:
 *      - plugin: d7_file_lookup
 *        source: fid
 *        entity_type: file OR media
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "d7_file_lookup"
 * )
 */
class D7FileLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrateUuidLookupManager $lookup_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->lookupManager = $lookup_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dept_migrate.migrate_uuid_lookup_manager'),
      $container->get('entity_type.manager')
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

    $entity_type = $this->configuration['entity_type'] ?? 'file';

    if ($entity_type === 'file') {
      $entity_metadata = $this->lookupManager->lookupBySourceFileId($value);
    }
    else {
      $entity_metadata = $this->lookupManager->lookupMediaBySourceFileId($value);
    }

    if (!empty($entity_metadata)) {
      $value = reset($entity_metadata)['id'];
    }

    return $value ?? [];
  }

}
