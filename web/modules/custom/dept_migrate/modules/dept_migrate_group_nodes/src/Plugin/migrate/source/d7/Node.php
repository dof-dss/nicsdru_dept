<?php

namespace Drupal\dept_migrate_group_nodes\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\node\Plugin\migrate\source\d7\Node as CoreD7Node;

/**
 * Drupal 7 node source from database.
 *
 * Available configuration keys:
 * - node_type: The node_types to get from the source - can be a string or
 *   an array. If not declared then nodes of all types will be retrieved.
 *
 * Examples:
 *
 * @code
 * source:
 *   plugin: d7_node_uuid
 *   node_type: page
 * @endcode
 *
 * In this example nodes of type page are retrieved from the source database.
 *
 * @code
 * source:
 *   plugin: d7_node
 *   node_type: [page, test]
 * @endcode
 *
 * In this example nodes of type page and test are retrieved from the source
 * database.
 *
 * For additional configuration keys, refer to the parent classes.
 *
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 *
 * @MigrateSource(
 *   id = "d7_node_uuid",
 *   source_module = "node"
 * )
 */
class Node extends CoreD7Node {

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return $this->configuration['ids'];
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('Node ID'),
      'type' => $this->t('Type'),
      'title' => $this->t('Title'),
      'node_uid' => $this->t('Node authored by (uid)'),
      'revision_uid' => $this->t('Revision authored by (uid)'),
      'created' => $this->t('Created timestamp'),
      'changed' => $this->t('Modified timestamp'),
      'status' => $this->t('Published'),
      'promote' => $this->t('Promoted to front page'),
      'sticky' => $this->t('Sticky at top of lists'),
      'revision' => $this->t('Create new revision'),
      'language' => $this->t('Language (fr, en, ...)'),
      'tnid' => $this->t('The translation set id for this node'),
      'timestamp' => $this->t('The timestamp the latest revision of this node was created.'),
      'uuid' => $this->t('The unique identifier for this item of content.'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $uuid = $row->getSourceProperty('uuid');
    $row->setSourceProperty('uuid', $row->getSourceProperty('uuid'));

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Select node in its last revision.
    $query = $this->select('node_revision', 'nr')
      ->fields('n', [
        'nid',
        'type',
        'language',
        'status',
        'created',
        'changed',
        'comment',
        'promote',
        'sticky',
        'tnid',
        'translate',
        'uuid',
      ])
      ->fields('nr', [
        'vid',
        'title',
        'log',
        'timestamp',
      ]);
    $query->addField('n', 'uid', 'node_uid');
    $query->addField('nr', 'uid', 'revision_uid');
    $query->innerJoin('node', 'n', static::JOIN);

    // If the content_translation module is enabled, get the source langcode
    // to fill the content_translation_source field.
    if ($this->moduleHandler->moduleExists('content_translation')) {
      $query->leftJoin('node', 'nt', '[n].[tnid] = [nt].[nid]');
      $query->addField('nt', 'language', 'source_langcode');
    }
    $this->handleTranslations($query);

    if (isset($this->configuration['node_type'])) {
      $query->condition('n.type', (array) $this->configuration['node_type'], 'IN');
    }

    return $query;
  }
}
