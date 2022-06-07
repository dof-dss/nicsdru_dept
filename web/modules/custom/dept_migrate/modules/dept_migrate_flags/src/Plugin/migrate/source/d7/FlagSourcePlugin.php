<?php

namespace Drupal\dept_migrate_flags\Plugin\migrate\source\d7;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * D7 source plugin for Flags module.
 *
 * @MigrateSource(
 *   id = "flag_source",
 *   source_module = "dept_migrate_flags",
 * )
 */
class FlagSourcePlugin extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    $query = $this->select('flagging', 'flg')
      ->fields('flg', [
        'flagging_id',
        'fid',
        'entity_id',
        'uid',
      ]);

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
    $flag_id = $row->getSourceProperty('fid');
    $entity_id = $row->getSourceProperty('entity_id');
    $uid = $row->getSourceProperty('uid');

    return parent::prepareRow($row);
  }
}


