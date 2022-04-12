<?php

namespace Drupal\dept_etgrm;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * EntityToGroupRelationshipManagerService service.
 */
class EntityToGroupRelationshipManagerService {

  /**
   * The type of database operation to perform.
   */
  protected $action;

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
   * Constructs an EntityToGroupRelationshipManagerService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dbConn = $connection;
  }

  public function create() {
    $this->action = 'create';
    return $this;
  }

  public function remove() {
    $this->action = 'remove';
    return $this;
  }

  public function all() {
    if ($this->action === 'remove') {
      $this->dbConn->truncate(['group_content'])->execute();
      $this->dbConn->truncate(['group_content_field_data'])->execute();
    }
    else {

    }
  }


}
