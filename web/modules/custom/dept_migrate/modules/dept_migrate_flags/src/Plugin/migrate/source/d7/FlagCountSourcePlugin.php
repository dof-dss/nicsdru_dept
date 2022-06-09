<?php

namespace Drupal\dept_migrate_flags\Plugin\migrate\source\d7;

use Drupal\Core\State\StateInterface;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * D7 flag count source plugin for Flags module.
 *
 * @MigrateSource(
 *   id = "flag_count_source",
 *   source_module = "dept_migrate_flags",
 * )
 */
class FlagCountSourcePlugin extends SqlBase {

  /**
   * Departmental Migration lookup manager.
   *
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, MigrateUuidLookupManager $lookup_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state);
    $this->lookupManager = $lookup_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('state'),
      $container->get('dept_migrate.migrate_uuid_lookup_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {

    $query = $this->select('flag_counts', 'fc')
      ->fields('fc', [
        'fid',
        'entity_id',
        'last_updated',])
      ->condition('fid', [4,5,6], 'IN');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'flag_id' => $this->t('Flag ID'),
      'entity_id' => $this->t('Node/Entity ID'),
      'last_updated' => $this->t('Updated timestamp'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'fid' => [
        'type' => 'integer',
      ],
      'entity_id' => [
        'type' => 'integer',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $flag_id = $row->getSourceProperty('fid');
    $entity_id = $row->getSourceProperty('entity_id');
    $updated = $row->getSourceProperty('last_updated');
    return parent::prepareRow($row);
  }
}


