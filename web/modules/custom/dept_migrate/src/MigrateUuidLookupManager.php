<?php

namespace Drupal\dept_migrate;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to help build a map between D7 legacy content
 * and migrated D9 import, based on D7 UUID or node id lookups.
 */
class MigrateUuidLookupManager {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7conn;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructs a new instance of this object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dbconn = Database::getConnection('default', 'default');
    $this->d7conn = Database::getConnection('default', 'migrate');
  }

  /**
   * @param array $uuids
   *   One or more source UUIDs.
   *
   * @return array
   *   Associative array of node metdata, keyed by source UUID.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function lookupBySourceUuId(array $uuids) {
    $map = [];

    $d7results = $this->d7conn->query("SELECT * FROM {node} WHERE uuid IN (:uuids[])", [':uuids[]' => $uuids]);
    foreach ($d7results as $row) {
      $map[$row->uuid] = [
        'd7nid' => $row->nid,
        'd7type' => $row->type,
        'd7title' => $row->title,
      ];
    }

    // Match up to D9 nodes using uuid as key from migrate table.
    foreach ($map as $d7uuid => $node) {
      $table = 'migrate_map_node_' . $node['d7type'];
      if ($this->dbconn->schema()->tableExists($table) === FALSE) {
        // Skip the rest if this table doesn't exist.
        continue;
      }

      $migrate_map = $this->dbconn->query("SELECT * from ${table} WHERE sourceid1 = :uuid", [':uuid' => $d7uuid]);

      foreach ($migrate_map as $row) {
        $node = $this->entityTypeManager->getStorage('node')->load($row->destid1);
        $map[$d7uuid]['nid'] = $node->id();
        $map[$d7uuid]['uuid'] = $node->uuid();
        $map[$d7uuid]['title'] = $node->label();
        $map[$d7uuid]['type'] = $node->bundle();
      }
    }

    return $map;
  }

  /**
   * @param array $nids
   *   One or more source node ids.
   *
   * @return array
   *   Associative array of node metdata, keyed by source node id
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function lookupBySourceNodeId(array $nids) {
    $map = [];

    $d7results = $this->d7conn->query("SELECT * FROM {node} WHERE nid IN (:ids[])", [':ids[]' => $nids]);
    foreach ($d7results as $row) {
      $map[$row->nid] = [
        'd7uuid' => $row->uuid,
        'd7type' => $row->type,
        'd7title' => $row->title,
      ];
    }

    // Match up to D9 nodes using uuid as key from migrate table.
    foreach ($map as $d7nid => $node) {
      $table = 'migrate_map_node_' . $node['d7type'];
      if ($this->dbconn->schema()->tableExists($table) === FALSE) {
        // Skip the rest if this table doesn't exist.
        continue;
      }

      $migrate_map = $this->dbconn->query("SELECT * from ${table} WHERE sourceid1 = :uuid", [':uuid' => $node['d7uuid']]);

      foreach ($migrate_map as $row) {
        $node = $this->entityTypeManager->getStorage('node')->load($row->destid1);
        $map[$d7nid]['nid'] = $node->id();
        $map[$d7nid]['uuid'] = $node->uuid();
        $map[$d7nid]['title'] = $node->label();
        $map[$d7nid]['type'] = $node->bundle();
      }
    }

    return $map;
  }

  /**
   * @param array $uids
   *   One or more source user ids.
   *
   * @return array
   *   Associative array of user metdata, keyed by source user id
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function lookupBySourceUserId(array $uids) {
    $map = [];

    $d7results = $this->d7conn->query("SELECT * FROM {users} WHERE uid IN (:ids[])", [':ids[]' => $uids]);
    foreach ($d7results as $row) {
      $map[$row->uid] = [
        'd7uuid' => $row->uuid,
        'd7name' => $row->name,
        'd7mail' => $row->mail,
      ];
    }

    // Match up to D9 users using uuid as key from migrate table.
    foreach ($map as $d7uid => $user_data) {
      $table = 'migrate_map_users';
      if ($this->dbconn->schema()->tableExists($table) === FALSE) {
        // Skip the rest if this table doesn't exist.
        continue;
      }

      $migrate_map = $this->dbconn->query("SELECT * from ${table} WHERE sourceid1 = :uuid", [':uuid' => $user_data['d7uuid']]);

      foreach ($migrate_map as $row) {
        // UID 1 might give some odd results, so look it up by username instead.
        $lookup_uid = $row->destid1 ?? 1;
        $user = $this->entityTypeManager->getStorage('user')
          ->load($lookup_uid);

        if ($user instanceof UserInterface) {
          $map[$d7uid]['uid'] = $user->id();
          $map[$d7uid]['uuid'] = $user->uuid();
          $map[$d7uid]['name'] = $user->getAccountName();
          $map[$d7uid]['mail'] = $user->getEmail();
        }
      }
    }

    return $map;
  }

  /**
   * @param array $fids
   *   One or more source file ids.
   *
   * @return array
   *   Associative array of file metadata, keyed by source file id
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function lookupBySourceFileId(array $fids) {
    $map = [];

    $d7results = $this->d7conn->query("SELECT * FROM {file_managed} WHERE fid IN (:ids[])", [':ids[]' => $fids]);
    foreach ($d7results as $row) {
      $map[$row->fid] = [
        'd7uuid' => $row->uuid,
        'd7type' => $row->type,
        'd7filename' => $row->filename,
      ];
    }

    // Match up to D9 files using uuid as key from migrate table.
    foreach ($map as $d7fid => $file) {
      $table = 'migrate_map_d7_file';
      $d9_entity = 'file';

      // Switch table if there's a specified type for this file.
      switch ($file['d7type']) {
        case 'image':
          $table .= '_media_image';
          $d9_entity = 'media';
          break;

        case 'video':
        case 'remote_video':
          $table .= '_media_video';
          $d9_entity = 'media';
          break;

      }

      if ($this->dbconn->schema()->tableExists($table) === FALSE) {
        // Skip the rest if this table doesn't exist.
        continue;
      }

      $migrate_map = $this->dbconn->query("SELECT * from ${table} WHERE sourceid1 = :uuid", [':uuid' => $file['d7uuid']]);

      foreach ($migrate_map as $row) {
        if (empty($row->destid1)) {
          continue;
        }

        $file = $this->entityTypeManager->getStorage($d9_entity)->load($row->destid1);

        if ($file instanceof EntityInterface) {
          $map[$d7fid]['id'] = $file->id();
          $map[$d7fid]['uuid'] = $file->uuid();
          $map[$d7fid]['filename'] = $file->label();
          $map[$d7fid]['type'] = $file->bundle();
        }
      }
    }

    return $map;
  }

  /**
   * @param array $nids
   *   One or more destination node ids.
   * @return array
   *   Associative array of node metdata, keyed by destination node id
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function lookupByDestinationNodeIds(array $nids) {
    $map = [];

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    foreach ($nodes as $index => $node) {
      if ($node instanceof NodeInterface === FALSE) {
        continue;
      }

      $map[$node->id()] = [
        'nid' => $node->id(),
        'title' => $node->label(),
        'type' => $node->bundle(),
        'uuid' => $node->uuid(),
        'd7uuid' => '',
        'd7nid' => '',
        'd7type' => '',
        'd7title' => '',
      ];

      // Look up the D7 details.
      $table = 'migrate_map_node_' . $node->bundle();
      if ($this->dbconn->schema()->tableExists($table) === FALSE) {
        // Skip the rest if this table doesn't exist.
        continue;
      }

      $migrate_map = $this->dbconn->query("SELECT * from ${table} WHERE destid1 = :nid", [':nid' => $node->id()]);

      foreach ($migrate_map as $row) {
        $d7results = $this->d7conn->query("SELECT * FROM {node} WHERE uuid = :uuid", [':uuid' => $row->sourceid1]);

        foreach ($d7results as $d7node) {
          $map[$node->id()]['d7nid'] = $d7node->nid;
          $map[$node->id()]['d7uuid'] = $d7node->uuid;
          $map[$node->id()]['d7title'] = $d7node->title;
          $map[$node->id()]['d7type'] = $d7node->type;
        }
      }
    }

    return $map;
  }

  /**
   * @param array $uuids
   *   One or more destination node uuids.
   * @return array
   *   Associative array of node metdata, keyed by destination node id
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function lookupByDestinationUuid(array $uuids) {
    $map = [];

    foreach ($uuids as $uuid) {
      $node = $this->entityTypeManager->getStorage('node')->loadByProperties(['uuid' => $uuid]);
      $node = reset($node);

      if ($node instanceof NodeInterface === FALSE) {
        continue;
      }

      $map[$node->id()] = [
        'nid' => $node->id(),
        'title' => $node->label(),
        'type' => $node->bundle(),
      ];

      // Look up the D7 details.
      $table = 'migrate_map_node_' . $node->bundle();
      if ($this->dbconn->schema()->tableExists($table) === FALSE) {
        // Skip the rest if this table doesn't exist.
        continue;
      }

      $migrate_map = $this->dbconn->query("SELECT * from ${table} WHERE destid1 = :nid", [':nid' => $node->id()]);

      foreach ($migrate_map as $row) {
        $d7results = $this->d7conn->query("SELECT * FROM {node} WHERE uuid = :uuid", [':uuid' => $row->sourceid1]);

        foreach ($d7results as $d7node) {
          $map[$node->id()]['d7nid'] = $d7node->nid;
          $map[$node->id()]['d7uuid'] = $d7node->uuid;
          $map[$node->id()]['d7title'] = $d7node->title;
          $map[$node->id()]['d7type'] = $d7node->type;
        }
      }
    }

    return $map;
  }

  /**
   * Gets a list of migrated content + metadata.
   *
   * @param array $criteria
   *   Key/value query criteria, eg: ['type'] = 'news'.
   * @param int $num_per_page
   *   Number of items per page of results.
   * @param int $offset
   *   The row offset to begin fetching results from.
   *
   * @return array
   *   Array of content keyed by 'total' and 'rows'.
   */
  public function getMigrationContent(array $criteria, int $num_per_page, int $offset) {

    // TODO: TEMP -- Fix migration - bundle naming
    $criteria['type'] = 'news';

    $query = $this->dbconn->select('migrate_map_node_' . $criteria['type'], 'mm');
    $query->addField('mm', 'sourceid1', 'd7uuid');
    $query->addField('mm', 'destid1', 'd9nid');
    $query->addField('n', 'uuid', 'd9uuid');
    $query->innerJoin('node', 'n', 'mm.destid1 = n.nid');

    $d9_data = $query->execute()->fetchAllAssoc('d7uuid');

    $query = $this->d7conn->select('node', 'n');
    $query->fields('n', ['nid', 'title', 'type', 'uuid']);
    $query->condition('uuid', array_keys($d9_data), 'IN');

    $d7_data = $query->execute()->fetchAllAssoc('uuid');

    $migrated_data = [];

    foreach ($d7_data as $d7_uuid => $d7_item) {
      $mig_content['rows'][]  = [
        'type' => $d7_item->type,
        'title' => $d7_item->title,
        'nid' => $d9_data[$d7_uuid]->d9nid,
        'uuid' => $d9_data[$d7_uuid]->d9uuid,
        'd7nid' => $d7_item->nid,
        'd7uuid' => $d7_uuid,
        'd7title' => $d7_item->title,
        'd7type' => $d7_item->type,
      ];
    }

    return $mig_content;

    /**
     * =======================================================
     */


//    $query = $this->dbconn->select('node_field_data', 'nfd');
//    $query->fields('nfd', ['nid', 'title', 'type']);
//    $query->fields('n', ['uuid']);
//    $query->innerJoin('node', 'n', 'nfd.nid = n.nid');
//
//    // Process any supplied criteria.
//    if (!empty($criteria)) {
//      foreach ($criteria as $key => $value) {
//        switch ($key) {
//          case 'type':
//            $query->condition('n.type', $value, '=');
//            break;
//
//          case 'title':
//            $query->condition('nfd.title', "%${value}%", 'LIKE');
//            break;
//
//          case 'nid':
//            $query->condition('nfd.nid', $value, '=');
//            break;
//
//          case 'uuid':
//            $query->condition('n.uuid', $value, '=');
//            break;
//
//        }
//      }
//    }
//
//    $query->execute()->fetchAll();
//
//    $mig_content = [
//      'total' => count($query->execute()->fetchAll()),
//    ];
//
//    $query->range($offset, $num_per_page);
//    $result = $query->execute()->fetchAllAssoc('nid');
//
//    // Expand metadata with D7 migration data.
//    $d7_data = $this->lookupByDestinationNodeIds(array_keys($result));
//
//    foreach ($result as $record) {
//      $mig_content['rows'][] = [
//        'type' => $record->type,
//        'title' => $record->title,
//        'nid' => $record->nid,
//        'uuid' => $record->uuid,
//        'd7nid' => $d7_data[$record->nid]['d7nid'],
//        'd7uuid' => $d7_data[$record->nid]['d7uuid'],
//        'd7title' => $d7_data[$record->nid]['d7title'],
//        'd7type' => $d7_data[$record->nid]['d7type'],
//      ];
//    }

//    return $mig_content;
  }

  /**
   * Gets a list of migrated content + metadata.
   *
   * @param array $criteria
   *   Key/value query criteria, eg: ['type'] = 'news'.
   * @param int $num_per_page
   *   Number of items per page of results.
   * @param int $offset
   *   The row offset to begin fetching results from.
   *
   * @return array
   *   Array of content keyed by 'total' and 'rows'.
   */
  public function getMigratedContent(array $criteria, int $num_per_page, int $offset) {

//    $mig_map_tables = $this->dbconn->schema()->findTables('migrate_map_node%');
//
//    if (!empty($criteria['type']) && !in_array('migrate_map_node_' . $criteria['type'])) {
//      return;
//    }
//
//    $query = $this->dbconn->select('migrate_map_node_' . $criteria['type'], 'mm');
//    $query->addField('mm', 'sourceid1', 'd7uuid');
//    $query->addField('mm', 'destid1', 'd9nid');
//
//    $d9_data = $query->execute()->fetchAllAssoc('d7uuid');
////    $query->fields('nfd', ['nid', 'title', 'type']);
////    $query->fields('n', ['uuid']);
////    $query->innerJoin('node', 'n', 'nfd.nid = n.nid');
//
//
//
//    if (in_array('migrate_map_node_' . $criteria['type'])) {
//
//
//      SELECT sourceid1 as d7uuid, destid1 as nid FROM migrate_map_node_[TYPE]
//INNER JOIN node
//ON destid1 = node.nid
//
//
//    }

//}
  }
}
