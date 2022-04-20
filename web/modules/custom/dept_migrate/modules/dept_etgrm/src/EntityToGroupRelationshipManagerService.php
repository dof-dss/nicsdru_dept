<?php

namespace Drupal\dept_etgrm;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;

/**
 * EntityToGroupRelationshipManagerService service.
 */
class EntityToGroupRelationshipManagerService {

  use DependencySerializationTrait;

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
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * An array containing arrays of nids and group ids to process.
   *
   * @var array
   */
  public $batch;

  /**
   * Current batch index.
   *
   * @var int
   */
  protected $batchIndex;



  /**
   * Constructs an EntityToGroupRelationshipManagerService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection, Messenger $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dbConn = $connection;
    $this->messenger = $messenger;
  }

  /**
   * Maps Drupal 7 domain ID's to Drupal 9 group ID's.
   *
   * @param int $domain_id
   *  A domain id.
   * @return int
   *  Corresponding group id, 0 for retired site and -1 for not found.
   */
  public static function domainIDtoGroupId(int $domain_id) {
    $map = [
      1 => 1,   // nigov.site
      2 => 3,   // daera.site
      3 => 0,   // del.vm
      4 => 5,   // economy.site
      5 => 7,   // execoffice.site
      6 => 6,   // education.site
      7 => 2,   // finance.site
      8 => 8,   // health.site
      9 => 9,   // infrastructure.site
      10 => 0,  // dcal.vm
      11 => 0,  // doe.vm
      12 => 10, // justice.site
      13 => 4,  // communities.site
    ];

    if (array_key_exists($domain_id, $map)) {
      return $map[$domain_id];
    }

    return -1;
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

  public function single($nid, $group_ids = []) {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    if ($node) {
      // Load the node group relationships.
      $relationships = GroupContent::loadByEntity($node);

      foreach ($relationships as $relation) {
        $node_group_ids[] = $relation->getGroup()->id();
      }

      foreach ($group_ids as $gid) {
        if (!array_key_exists($gid, $node_group_ids)) {
          $group = Group::load($gid);
          $group->addContent($node, 'group_node:' . $node->bundle());
        }
      }
    }
  }

  public function process($items) {
    $counter = 0;
    // TODO: Replace hardcoded with lookup against dept type groups.
    $groups = Group::loadMultiple(range(1, 10));

    foreach ($items as $nid => $group_ids) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);

      $relationships = GroupContent::loadByEntity($node);
      $node_groups = [];

      foreach ($relationships as $relation) {
        $node_groups[] = $relation->getGroup()->id();
      }

      foreach ($group_ids as $group_id) {
        if ($group_ids < 1) {
          continue;
        }
        else if (!in_array($group_id, $node_groups)) {
          $groups[$group_id]->addContent($node, 'group_node:' . $node->bundle());
          $counter++;
        }
      }
    }
    return $counter;
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

    $departments =  $this->entityTypeManager->getStorage('group_type')->load('department_site');

    foreach ($departments->getInstalledContentPlugins() as $plugin) {
      if ($plugin->getEntityTypeId() === 'node') {
        $bundle = $plugin->getEntityBundle();
        $migration_table = 'migrate_map_node_' . $bundle;

        if ($this->dbConn->schema()->tableExists($migration_table)) {
          $query = $this->dbConn->select($migration_table, 'mt');
          $query->addField('mt', 'sourceid3', 'domains');
          $query->addField('mt', 'destid1', 'nid');
          $result = $query->execute();

          $rows = $result->fetchAllAssoc('nid');

          // Create an array indexed by nid with a value array of the correct
          // group ids.
          foreach ($rows as $row) {
            $items[$row->nid] = array_map(fn($domain) => self::domainIDtoGroupId($domain[0]), explode('-', $row->domains));
          }

        }
        }

      }



      // Get a list of content types used by the departmental group
      // Iterate each group, lookup the mapping table, for each row
      // create a relationship based on the data from sourceid3 and destid1
      $departments = $this->entityTypeManager->getStorage('group_type')->load('department_site');
      $groups = Group::loadMultiple(range(1, 10));

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

            $rows = $result->fetchAllAssoc('nid');

            $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple(array_keys($rows));

            foreach ($nodes as $node) {
              $relationships = GroupContent::loadByEntity($node);
              $node_groups = [];

              foreach ($relationships as $relation) {
                $node_groups[] = $relation->getGroup()->id();
              }

              foreach (explode('-', $rows[$node->id()]->domains) as $domain) {
                $domain_group = self::domainIDtoGroupId($domain);

                if ($domain_group < 1) {
                  continue;
                }

                if (!in_array($domain_group, $node_groups)) {
                  $groups[$domain_group]->addContent($node, 'group_node:' . $node->bundle());
                  $counter++;
                }
              }
            }
          }
        }
      }
      return $counter;
    }
  }

  public function addToBatch($bundle) {

    $migration_table = 'migrate_map_node_' . $bundle;

    if (\Drupal::database()->schema()->tableExists($migration_table)) {
      $query = \Drupal::database()->select($migration_table, 'mt');
      $query->addField('mt', 'sourceid3', 'domains');
      $query->addField('mt', 'destid1', 'nid');
      $result = $query->execute();

      $rows = $result->fetchAllAssoc('nid');

      // Create an array indexed by nid with a value array of the correct
      // group ids.
      foreach ($rows as $row) {
        $items[$row->nid] = array_map(fn($domain) => self::domainIDtoGroupId($domain[0]), explode('-', $row->domains));
      }

      if (is_array($this->batch)) {
        $this->batch = array_merge($this->batch, array_chunk($items, 500, true));
      } else {
        $this->batch = array_chunk($items, 500, true);
      }

      return count($rows);
    }
  }

}
