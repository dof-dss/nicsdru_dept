<?php

namespace Drupal\dept_etgrm;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dept_core\DepartmentManager;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;

/**
 * EntityToGroupRelationshipManagerService service.
 */
class EntityToGroupRelationshipManagerService {

  /**
   * The type of database operation to perform.
   *
   * @var string
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

  /**
   * Set the database operation to create relationships.
   *
   * @return $this
   */
  public function create() {
    $this->action = 'create';
    return $this;
  }

  /**
   * Set the database operation to remove relationships.
   *
   * @return $this
   */
  public function remove() {
    $this->action = 'remove';
    return $this;
  }

  /**
   * Create or remove all relationships.
   */
  public function all() {
    if ($this->action === 'remove') {
      $this->dbConn->truncate('group_content')->execute();
      $this->dbConn->truncate('group_content_field_data')->execute();
    }
    else {
      // Get a list of content types used by the departmental group
      // Iterate each group, lookup the mapping table, for each row
      // create a relationship based on the data from sourceid3 and destid1
      $departments = $this->entityTypeManager->getStorage('group_type')->load('department_site');

      foreach ($departments->getInstalledContentPlugins() as $plugin) {
        if ($plugin->getEntityTypeId() === 'node') {
          $bundle = $plugin->getEntityBundle();
          $counter = 0;

          $migration_table = 'migrate_map_node_' . $bundle;

          if ($this->dbConn->schema()->tableExists($migration_table)) {
            $query = $this->dbConn->select($migration_table, 'mt');
            $query->addField('mt', 'sourceid3', 'domains');
            $query->addField('mt', 'destid1', 'nid');
            $result = $query->execute();

            foreach ($result->fetchAll() as $row) {

              $node = $this->entityTypeManager->getStorage('node')->load($row->nid);

              if (empty($node)) {
                continue;
              }
              $relationships = GroupContent::loadByEntity($node);
              $groups = [];

              foreach ($relationships as $relation) {
                $groups[] = $relation->getGroup()->id();
              }

              foreach (explode('-', $row->domains) as $domain) {
                if (!in_array($domain, $groups)) {
                  $group = Group::load($domain);

                  if (empty($group)) {
                    continue;
                  }
                  $group->addContent($node, 'group_node:' . $node->bundle());
                  $counter++;
                }
              }
            }
          }
        }

        return $counter;
      }
    }
  }

}
