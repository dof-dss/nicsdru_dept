<?php

namespace Drupal\dept_node;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dept_core\DepartmentManager;
use Drupal\node\NodeAccessControlHandler;
use Drupal\node\NodeGrantDatabaseStorageInterface;

/**
 * Extends the core node access handler for Departmental sites.
 */
class DeptNodeAccessControlHandler extends NodeAccessControlHandler {

  /**
   *  The Department Manager service.
   *
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected DepartmentManager $departmentManager;

  /**
   * Constructs a NodeAccessControlHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\node\NodeGrantDatabaseStorageInterface $grant_storage
   *   The node grant storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, NodeGrantDatabaseStorageInterface $grant_storage, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $grant_storage, $entity_type_manager);

    $this->departmentManager = \Drupal::service('department.manager');
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
