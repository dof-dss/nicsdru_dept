<?php

namespace Drupal\dept_node;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dept_core\DepartmentManager;
use Drupal\node\NodeAccessControlHandler;
use Drupal\node\NodeGrantDatabaseStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

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

  public function access(EntityInterface $entity, $operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {

    // TODO: temp fix.
    if (is_null($account)) {
      return parent::access($entity, $operation, $account, TRUE)->cachePerPermissions();
    }

    $result = parent::access($entity, $operation, $account, TRUE)->cachePerPermissions();

    if ($entity instanceof NodeInterface && $entity->bundle() === 'publication') {
      $embargoed = $entity->get('field_embargoed')->getString();

      if ($embargoed) {
        switch ($operation) {
          case "view label":
            return $this->publicationViewLabel($entity, $operation, $account);
          case "view":
          case "update":
          case "delete":
            return $this->publicationViewUpdateDelete($entity, $operation, $account);
        }
      }
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  protected function publicationViewUpdateDelete(NodeInterface $node, $operation, ?AccountInterface $account = NULL) {
    $id = $account->id();
    $user = User::load($account->id());
    if ($user->hasRole('stats_supervisor') || $user->hasRole('administrator')) {
      $result = parent::access($node, $operation, $account, TRUE)->cachePerPermissions();
    } elseif ($user->hasRole('stats_author')) {
      if ($user->id() === $node->getOwnerId()) {
        $result = AccessResult::allowed();
      } else {
        $result = AccessResult::forbidden();
      }
    }

    return $result;
  }

  protected function publicationViewLabel(NodeInterface $node, $operation, ?AccountInterface $account = NULL) {
    if ($node->isPublished()) {
      return AccessResult::allowed();
    } elseif ($account->isAuthenticated()) {
      return AccessResult::allowed();
    } else {
      return AccessResult::forbidden();
    }
  }
}
