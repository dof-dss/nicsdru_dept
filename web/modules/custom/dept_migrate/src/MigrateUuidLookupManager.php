<?php

namespace Drupal\dept_migrate;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerInterface;
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
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * Constructs a new instance of this object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactory $logger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('dept_migrate');
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
    if (empty($nids)) {
      return [];
    }

    $map = [];

    $d7results = $this->d7conn->query("
        SELECT n.*, GROUP_CONCAT(d.machine_name) AS domains
        FROM {node} n
        LEFT JOIN {domain_access} da ON da.nid = n.nid
        INNER JOIN {domain} d ON d.domain_id = da.gid
        WHERE n.nid IN (:ids[])", [':ids[]' => $nids]);

    foreach ($d7results as $row) {
      $map[$row->nid] = [
        'd7uuid' => $row->uuid,
        'd7type' => $row->type,
        'd7title' => $row->title,
      ];

      // Add any domain ids, if they're present.
      if (!empty($row->domains)) {
        $map[$row->nid]['domains'] = explode(',', $row->domains);
      }
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
        if (empty($row->destid1)) {
          $this->logger->error('No destination match for D7 node id ' . $row->sourceid2);
          continue;
        }

        $node = $this->entityTypeManager->getStorage('node')->load($row->destid1);

        if (!$node instanceof NodeInterface) {
          $this->logger->error('No node found with id ' . $row->destid1);
          continue;
        }

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
    if (empty($uids)) {
      return [];
    }

    $map = [];

    $d7results = $this->d7conn->query("
        SELECT u.*, GROUP_CONCAT(d.machine_name) AS domains
        FROM {users} u
        LEFT JOIN {domain_editor} de ON de.uid = u.uid
        INNER JOIN {domain} d ON d.domain_id = de.domain_id
        WHERE u.uid IN (:ids[])
        GROUP BY u.uid", [':ids[]' => $uids]);

    foreach ($d7results as $row) {
      $map[$row->uid] = [
        'd7uuid' => $row->uuid,
        'd7name' => $row->name,
        'd7mail' => $row->mail,
      ];

      // Add any domain ids, if they're present.
      if (!empty($row->domains)) {
        $map[$row->uid]['domains'] = explode(',', $row->domains);
      }
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
   * @param array $uuids
   *   One or more source file uuids.
   *
   * @return array
   *   Associative array of file metadata, keyed by source file id.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function lookupBySourceFileUuid(array $uuids) {
    if (empty($uuids)) {
      return [];
    }

    $map = [];

    $d7results = $this->d7conn->query("SELECT * FROM {file_managed} WHERE uuid IN (:uuids[])", [':uuids[]' => $uuids]);
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
   * @param array $fids
   *   One or more source file file ids.
   *
   * @return array
   *   Associative array of file metadata, keyed by source file id.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function lookupBySourceFileId(array $fids) {
    if (empty($fids)) {
      return [];
    }

    $map = [];

    $d7results = $this->d7conn->query("SELECT * FROM {file_managed} WHERE fid IN (:ids[])", [':ids[]' => $fids]);
    foreach ($d7results as $row) {
      $map[$row->fid] = [
        'd7uuid' => $row->uuid,
        'd7type' => $row->type,
        'd7filename' => $row->filename,
        'd7uri' => $row->uri,
      ];
    }

    // Match up to D9 files using uuid as key from migrate table.
    foreach ($map as $d7fid => $file) {
      $table = 'migrate_map_d7_file';

      if (preg_match('/^private/', $file['d7uri'])) {
        $table = 'migrate_map_d7_file_private';
      }

      $d9_entity = 'file';

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
   * @param array $fids
   *   One or more D7 file ids.
   *
   * @return array
   *   Associative array of media metadata, keyed by source file id.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function lookupMediaBySourceFileId(array $fids) {
    if (empty($fids)) {
      return [];
    }

    $map = [];

    $d7results = $this->d7conn->query("SELECT * FROM {file_managed} WHERE fid IN (:ids[])", [':ids[]' => $fids]);
    foreach ($d7results as $row) {
      $map[$row->fid] = [
        'd7uuid' => $row->uuid,
        'd7type' => $row->type,
        'd7filename' => $row->filename,
        'd7uri' => $row->uri,
      ];
    }

    // Match up to D9 media entity using D7 file uuid as key from migrate table.
    foreach ($map as $d7fid => $file) {
      // Switch table if there's a specified type for this file.
      switch ($file['d7type']) {
        case 'document':
        case 'undefined':
          if (preg_match('/^private/', $file['d7uri'])) {
            $table = 'migrate_map_d7_file_private';
          }
          else {
            $table = 'migrate_map_d7_file';
          }
          break;

        case 'image':
          $table = 'migrate_map_d7_file_media_image';
          break;

        case 'video':
        case 'remote_video':
          $table = 'migrate_map_d7_file_media_video';
          break;
      }

      if ($this->dbconn->schema()->tableExists($table) === FALSE) {
        // Skip the rest if this table doesn't exist.
        $this->logger->error('Could not find table ' . $table);
        continue;
      }

      $migrate_map = $this->dbconn->query("SELECT * from ${table} WHERE sourceid1 = :uuid", [':uuid' => $file['d7uuid']]);

      foreach ($migrate_map as $row) {
        if (empty($row->destid1)) {
          $this->logger->error('destid1 property of media lookup was null and failed.');
          continue;
        }

        $media_entity = NULL;
        $media_entities = $this->entityTypeManager->getStorage('media')->loadByProperties([
          'field_media_file' => $row->destid1,
        ]);
        $media_entity = is_array($media_entities) ? array_pop($media_entities) : NULL;

        // If null, try loading by secure media file property.
        if (empty($media_entity)) {
          $media_entities = $this->entityTypeManager->getStorage('media')->loadByProperties([
            'field_media_file_1' => $row->destid1,
          ]);

          $media_entity = is_array($media_entities) ? array_pop($media_entities) : NULL;
        }

        if ($media_entity instanceof EntityInterface) {
          $map[$d7fid]['id'] = $media_entity->id();
          $map[$d7fid]['uuid'] = $media_entity->uuid();
          $map[$d7fid]['filename'] = $media_entity->label();
          $map[$d7fid]['type'] = $media_entity->bundle();
        }
        else {
          $this->logger->error('No match found for media entity with file property value of ' . $row->destid1);
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
    if (empty($nids)) {
      return [];
    }

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
        $d7results = $this->d7conn->query("
            SELECT n.*, GROUP_CONCAT(d.machine_name) AS domains
            FROM {node} n
            LEFT JOIN {domain_access} da ON da.nid = n.nid
            INNER JOIN {domain} d ON d.domain_id = da.gid
            WHERE n.uuid = :uuid", [':uuid' => $row->sourceid1]);

        foreach ($d7results as $d7node) {
          $map[$node->id()]['d7nid'] = $d7node->nid;
          $map[$node->id()]['d7uuid'] = $d7node->uuid;
          $map[$node->id()]['d7title'] = $d7node->title;
          $map[$node->id()]['d7type'] = $d7node->type;

          // Add any domain ids, if they're present.
          if (!empty($d7node->domains)) {
            $map[$node->id()]['domains'] = explode(',', $d7node->domains);
          }
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
    if (empty($uuids)) {
      return [];
    }

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
        $d7results = $this->d7conn->query("
            SELECT n.*, GROUP_CONCAT(d.machine_name) AS domains
            FROM {node} n
            LEFT JOIN {domain_access} da ON da.nid = n.nid
            INNER JOIN {domain} d ON d.domain_id = da.gid
            WHERE n.uuid = :uuid", [':uuid' => $row->sourceid1]);

        foreach ($d7results as $d7node) {
          $map[$node->id()]['d7nid'] = $d7node->nid;
          $map[$node->id()]['d7uuid'] = $d7node->uuid;
          $map[$node->id()]['d7title'] = $d7node->title;
          $map[$node->id()]['d7type'] = $d7node->type;

          // Add any domain ids, if they're present.
          if (!empty($d7row->domains)) {
            $map[$node->id()]['domains'] = explode(',', $d7row->domains);
          }
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
    // If we don't have a 'type' fetch all the migrate_map_node tables.
    if (empty($criteria['type'])) {
      $mig_map_tables = $this->dbconn->schema()->findTables('migrate_map_node_%');
    }
    else {
      // As some content types are merged we need to check that we are querying
      // all original content types (see Secure/Publications for example).
      $map_file_path = \Drupal::service('extension.list.module')->getPath('dept_migrate') . '/d7_content_type_map.yml';
      $map_file = Yaml::decode(file_get_contents($map_file_path));

      if (is_array($map_file[$criteria['type']])) {
        foreach ($map_file[$criteria['type']] as $entry) {
          $mig_map_tables[] = 'migrate_map_node_' . $entry;
        }
      }
      else {
        $mig_map_tables[] = 'migrate_map_node_' . $map_file[$criteria['type']];
      }

      // If the number of mapped tables for the type doesn't match the database,
      // warn the user they are missing some migrations.
      $mig_table_count = 0;
      foreach ($mig_map_tables as $table) {
        $mig_table_count += $this->dbconn->schema()->tableExists($table);
      }

      if ($mig_table_count < count($mig_map_tables)) {
        \Drupal::messenger()->addMessage(t("Unable to process due to missing migration map tables. Check the database for: @tables", [
          '@tables' => implode(', ', $mig_map_tables)
        ]), MessengerInterface::TYPE_ERROR);
        return [];
      }
    }

    $d9_data = [];

    foreach ($mig_map_tables as $mig_map_table) {
      $query = $this->dbconn->select($mig_map_table, 'mm');
      $query->addField('mm', 'sourceid1', 'd7uuid');
      $query->addField('mm', 'destid1', 'd9nid');
      $query->addField('n', 'uuid', 'd9uuid');
      $query->addField('n', 'type', 'd9type');
      $query->addField('nfd', 'title', 'd9title');
      $query->innerJoin('node', 'n', 'mm.destid1 = n.nid');
      $query->innerJoin('node_field_data', 'nfd', 'n.nid = nfd.nid');

      $d9_results[] = $query->execute()->fetchAllAssoc('d7uuid');
    }

    $d9_data = array_merge([], ...$d9_results);

    $mig_content = [
      'total' => count($d9_data),
    ];

    // Fetch Drupal 7 node data for the corresponding UUID imported into
    // Drupal 9.
    $query = $this->d7conn->select('node', 'n');
    $query->fields('n', ['nid', 'title', 'type', 'uuid']);
    $query->condition('uuid', array_keys($d9_data), 'IN');
    $query->range($offset, $num_per_page);

    $d7_data = $query->execute()->fetchAllAssoc('uuid');

    foreach ($d7_data as $d7_uuid => $d7_item) {
      $mig_content['rows'][] = [
        'type' => $d9_data[$d7_uuid]->d9type,
        'title' => $d9_data[$d7_uuid]->d9title,
        'nid' => $d9_data[$d7_uuid]->d9nid,
        'uuid' => $d9_data[$d7_uuid]->d9uuid,
        'd7nid' => $d7_item->nid,
        'd7uuid' => $d7_uuid,
        'd7title' => $d7_item->title,
        'd7type' => $d7_item->type,
      ];
    }

    return $mig_content;

  }

}
