<?php

namespace Drupal\dept_migrate;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to support migrations.
 */
class MigrateSupport {

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
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to sync domain/groups for.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function syncDomainsToGroups(EntityInterface $entity) {
    if ($entity instanceof NodeInterface === FALSE) {
      return;
    }

    // Fetch the map/details of the D7 entity.
    if ($entity->isNew()) {
      $d7_entity = reset(\Drupal::service('dept_migrate.migrate_uuid_lookup_manager')->lookupBySourceNodeId($entity->d7_nid()));
    }
    else {
      $d7_entity = reset(\Drupal::service('dept_migrate.migrate_uuid_lookup_manager')->lookupByDestinationNodeIds([$entity->id()]));
    }

    // For each domain, assign it to the corresponding group entity.
    if (!empty($d7_entity['domains'])) {
      $all_groups = $this->entityTypeManager->getStorage('group')->loadByProperties(['type' => 'department_site']);

      // Compare current groups to current domains, if they differ then
      // update group content entity values.
      $d7_mapped_group_ids = [];
      $entity_group_ids = [];

      foreach (GroupContent::loadByEntity($entity) as $content_item) {
        $entity_group_ids[] = $content_item->getGroup()->id();
      }

      foreach ($d7_entity['domains'] as $domain_machine_name) {
        $d7_mapped_group_ids[] = $this->domainToGroupId($domain_machine_name);
      }

      if (empty(array_diff($d7_mapped_group_ids, $entity_group_ids))) {
        return;
      }

      $plugin_id = 'group_' . $entity->getEntityTypeId() . ':' . $entity->bundle();

      foreach ($all_groups as $group) {
        // Search for this entity in the group.
        $group_content_search = $group->getContent($plugin_id, ['entity_id' => $entity->id()]);

        if (!empty($group_content_search)) {
          $group_content = reset($group_content_search);
          // Remove entity from group.
          $group_content->delete();
        }

      }

      foreach ($d7_entity['domains'] as $domain_machine_name) {
        $group = Group::load($this->domainToGroupId($domain_machine_name));
        // Add entity to group.
        $group->addContent($entity, $plugin_id);
      }
    }
  }

  /**
   * @param string $domain_machine_name
   *   The machine name of the D7 domain.
   * @return int
   *   The group id (GID) of the group entity.
   */
  public function domainToGroupId(string $domain_machine_name) {
    $group_id = 0;

    switch ($domain_machine_name) {
      case 'newnigov':
      case 'del':
      case 'dcal':
      case 'doe':
        $group_id = 1;
        break;

      case 'dfp':
        $group_id = 2;
        break;

      case 'daera':
        $group_id = 3;
        break;

      case 'communities':
        $group_id = 4;
        break;

      case 'economy':
        $group_id = 5;
        break;

      case 'education':
        $group_id = 6;
        break;

      case 'execoffice':
        $group_id = 7;
        break;

      case 'health':
        $group_id = 8;
        break;

      case 'infrastructure':
        $group_id = 9;
        break;

      case 'justice':
        $group_id = 10;
        break;
    }

    return $group_id;
  }

}
