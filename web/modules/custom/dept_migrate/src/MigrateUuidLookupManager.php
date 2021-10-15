<?php

namespace Drupal\dept_migrate;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
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

}
