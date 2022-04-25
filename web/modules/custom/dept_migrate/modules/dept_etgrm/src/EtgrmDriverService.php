<?php

namespace Drupal\dept_etgrm;

use Drupal\Core\Database\Database;
use Drupal\Core\Logger\LoggerChannelFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity To Group Relationship Manager service.
 */
class EtgrmDriverService {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;

  /**
   * Drupal\Core\Logger\LoggerChannelFactory definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')
    );
  }

  /**
   * Constructs a new instance of this object.
   */
  public function __construct(LoggerChannelFactory $logger) {
    $this->logger = $logger->get('dept_migrate');
    $this->dbconn = Database::getConnection('default', 'default');
  }

  public function rebuildRelationsByType(string $type) {
    $type_map = [
      'publication' => 'group_content_type_d91f8322473a4',
    ];
    $map_table = 'migrate_map_node_' . $type;

    // Delete any data we already have in use by joining on the map table.
    // NB: Multi-table delete is a MySQL specific feature used for convenience.
    $delete_query = $this->dbconn->query("
      DELETE group_content, group_content_field_data
      FROM group_content
      JOIN group_content_field_data ON group_content.id = group_content_field_data.id
      JOIN $map_table ON $map_table.destid1 = group_content_field_data.entity_id
    ")->execute();

    // Array to represent content to add into the group_content table.
    $map_group_content = [];

    $results = $this->dbconn->query("SELECT
      :gc_type as type,
      n.langcode
      FROM
      {node} n
      JOIN $map_table mt ON mt.destid1 = n.nid
      WHERE n.type = :type
    ", [
      ':gc_type' => $type_map['publication'],
      ':type' => $type
    ]);
    foreach ($results as $row) {
      $gc_row = [];
      $gc_row['type'] = $type_map['publication'];
      $gc_row['uuid'] = \Drupal::service('uuid')->generate();
      $gc_row['langcode'] = $row->langcode;

      $map_group_content[] = $gc_row;
    }

    // Insert to group_content table.
    $gc_insert_query = $this->dbconn->insert('group_content')
      ->fields(['type', 'uuid', 'langcode']);
    foreach ($map_group_content as $record) {
      $gc_insert_query->values($record);
    }
    $gc_insert_query->execute();

    // build up array of nodes, source domain ids, and remapped group ids.
    // Use a static query for efficiency.
    $map_group_content_field_data = [];

    $results = $this->dbconn->query("SELECT
      gc.id,
      :gc_type as `type`,
      n.langcode,
      nfd.uid,
      mt.sourceid3,
      n.nid as entity_id,
      nfd.title as label,
      nfd.created,
      nfd.changed,
      :default_langcode as default_langcode
      FROM {node} n
      JOIN {group_content} gc ON gc.uuid = n.uuid
      JOIN {node_field_data} nfd ON nfd.nid = n.nid
      JOIN ${map_table} mt ON mt.destid1 = n.nid
      WHERE n.type = :type
    ", [
      ':gc_type' => $type_map['publication'],
      ':type' => $type,
      ':default_langcode' => 1,
    ]);
    foreach ($results as $row) {
      if (preg_match('/-/', $row->sourceid3)) {
        // Duplicate the row for N domain variants.
        foreach (explode('-', $row->sourceid3) as $domain_id) {
          if (empty($domain_id)) {
            continue;
          }

          $group_row = [];
          // Append to map array.
          $group_row['id'] = $row->id;
          $group_row['type'] = $row->type;
          $group_row['langcode'] = $row->langcode;
          $group_row['uid'] = $row->uid;
          $group_row['gid'] = \Drupal::service('dept_migrate.migrate_support')
            ->domainIdToGroupId($domain_id);
          $group_row['entity_id'] = $row->entity_id;
          $group_row['label'] = $row->label;
          $group_row['created'] = $row->created;
          $group_row['changed'] = $row->changed;
          $group_row['default_langcode'] = $row->default_langcode;
          // Create an index column to help de-dupe the final array.
          $group_row['idx'] = $this->createCollectionKey($group_row['id'], $group_row['gid']);

          if ($this->groupContainsNode($group_row['entity_id'], $group_row['gid'], $map_group_content_field_data) === FALSE) {
            $map_group_content_field_data[] = $group_row;
          }
        }
      }
      else {
        $map_item = [];
        $map_item['id'] = $row->id;
        $map_item['type'] = $row->type;
        $map_item['langcode'] = $row->langcode;
        $map_item['uid'] = $row->uid;
        $map_item['gid'] = \Drupal::service('dept_migrate.migrate_support')
          ->domainIdToGroupId($row->sourceid3);
        $map_item['entity_id'] = $row->entity_id;
        $map_item['label'] = $row->label;
        $map_item['created'] = $row->created;
        $map_item['changed'] = $row->changed;
        $map_item['default_langcode'] = $row->default_langcode;
        $map_item['idx'] = $this->createCollectionKey($map_item['id'], $map_item['gid']);

        if ($this->groupContainsNode($map_item['entity_id'], $map_item['gid'], $map_group_content_field_data) === FALSE) {
          $map_group_content_field_data[] = $map_item;
        }
      }
    }

    foreach ($map_group_content_field_data as $row) {
      if ($row['entity_id'] == 28574) {
        dump($row);
      }
    }

    exit();

    // Insert to group_content_field_data table.
    $gcfd_insert_query = $this->dbconn->insert('group_content_field_data')
      ->fields(['id', 'type', 'langcode', 'uid', 'gid', 'entity_id', 'label', 'created', 'changed', 'default_langcode']);
    foreach ($map_group_content_field_data as $record) {
      $gcfd_insert_query->values($record);
    }
    $gcfd_insert_query->execute();
  }

  /**
   * @param int $content_id
   *   The content id value.
   * @param int $group_id
   *   The group id value.
   * @param array $collection
   *   The collection we want to examine.
   *
   * @return bool
   *   Whether the collection already contains this key.
   */
  private function groupContainsNode(int $content_id, int $group_id, array &$collection) {
    $key = $this->createCollectionKey($content_id, $group_id);
    return in_array($key, $collection);
  }

  /**
   * @param int $content_id
   *   The numerical id of the content.
   * @param int $group_id
   *   The numerical id of the group.
   *
   * @return string
   *   The delimited, unique key for this collection entry.
   */
  private function createCollectionKey(int $content_id, int $group_id) {
    return $content_id . '/' . $group_id;
  }

}
