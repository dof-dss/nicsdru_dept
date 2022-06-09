<?php

namespace Drupal\dept_migrate_flags\Plugin\migrate\source\d7;

use Drupal\Core\State\StateInterface;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * D7 flagging source plugin for Flags module.
 *
 * @MigrateSource(
 *   id = "flagging_source",
 *   source_module = "dept_migrate_flags",
 * )
 */
class FlaggingSourcePlugin extends SqlBase {

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

    $query = $this->select('flagging', 'flg')
      ->fields('flg', [
        'flagging_id',
        'fid',
        'entity_id',
        'uid',])
      ->condition('fid', '5', '=');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'flagging_id' => $this->t('Flagging ID'),
      'fid' => $this->t('Flag ID'),
      'entity_id' => $this->t('Node/Entity ID'),
      'uid' => $this->t('User ID'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'flagging_id' => [
        'type' => 'integer',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $flagging_id = $row->getSourceProperty('flagging_id');
    $flag_id = $row->getSourceProperty('fid');
    $uid = $row->getSourceProperty('uid');
    $entity_id = $row->getSourceProperty('entity_id');

//    $nids = $this->lookupManager->lookupBySourceNodeId([$row->getSourceProperty('entity_id')]);

//    $entity_id = $nids[$row->getSourceProperty('entity_id')]['nid'];
    return parent::prepareRow($row);
  }
}


