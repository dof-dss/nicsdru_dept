<?php

namespace Drupal\dept_node;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dept_core\DepartmentManager;
use Drupal\node\NodeAccessControlHandler;
use Drupal\node\NodeGrantDatabaseStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends the core node access handler for Departmental sites.
 */
final class DeptNodeAccessControlHandler extends NodeAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    NodeGrantDatabaseStorageInterface $grant_storage,
    EntityTypeManagerInterface $entity_type_manager,
    protected DepartmentManager $departmentManager,
  ) {
    parent::__construct($entity_type, $grant_storage, $entity_type_manager);
  }

  /**
   * Entity handler factory used by EntityTypeManager.
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('node.grant_storage'),
      $container->get('entity_type.manager'),
      $container->get('department.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, ?AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {

    $node_type = $this->entityTypeManager->getStorage('node_type')->load($entity_bundle);
    $department_restrictions = $node_type->getThirdPartySetting('dept_node', 'department_restrictions', NULL);

    if (!empty($department_restrictions)) {
      // Filter as storage uses 0 to denote an unchecked department.
      $departments = array_filter($department_restrictions, 'is_string');
      $current_dept = $this->departmentManager->getCurrentDepartment();

      if (!in_array($current_dept->id(), $departments)) {
        return AccessResult::forbidden("Access to '" . $node_type->label() . "' is not allowed for this Department.")->cachePerPermissions();
      }
    }

    return parent::createAccess($entity_bundle, $account, $context, TRUE);
  }

}
