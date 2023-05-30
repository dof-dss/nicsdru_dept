<?php

namespace Drupal\dept_topics;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines a service provider for the Departmental sites: topics module.
 */
class TopicManager  {

  protected $parents = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConn;

  /**
   * Constructs a TopicManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $connection
    ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dbConn = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentNodes ($nid) {

    $parents = $this->dbConn->query("SELECT n.nid, nfd.title, nfd.type FROM node n
        LEFT JOIN node_field_data nfd
        ON nfd.nid = n.nid
        LEFT JOIN node__field_topic_content ftc
        ON ftc.entity_id = n.nid
        WHERE ftc.field_topic_content_target_id = :nid", [':nid' => $nid])->fetchAllAssoc('nid');

    if ($parents === NULL) {
      return $this->parents;
    }

    foreach ($parents as $parent) {
      $this->parents[$parent->nid] = $parent;
      $this->getParentNodes($parent->nid);
    }

    return $this->parents;
  }

}
