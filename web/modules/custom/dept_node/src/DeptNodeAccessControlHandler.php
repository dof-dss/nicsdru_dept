<?php

namespace Drupal\dept_node;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
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

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = parent::access($entity, $operation, $account, TRUE);

    // TODO: temp fix.
    if (is_null($account)) {
      return $return_as_object ? $result : $result->isAllowed();
    }

    if ($entity instanceof NodeInterface && $entity->bundle() === 'publication') {
      $embargoed = $entity->get('field_embargoed')->getString();

      if ($embargoed) {
        switch ($operation) {
          case "view":
          case "update":
          case "delete":
          case "view scheduled transition":
          case "View all revisions":
            return $this->publicationViewUpdateDelete($entity, $operation, $account);

          default:
            $result = parent::access($entity, $operation, $account, TRUE);
        }
      }
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   *  Handle access control for various operation states.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check access for.
   * @param string $operation
   *   The operation to perform (view, update, delete)
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account requesting the operation permission.
   */
  protected function publicationViewUpdateDelete(NodeInterface $node, string $operation, ?AccountInterface $account = NULL) {
    $user = User::load($account->id());
    // Map access operation to permission action.
    $action = ($operation == 'update') ? 'edit' : $operation;

    switch ($operation) {
      case "view":
      case "update":
      case "delete":

        if ($operation === 'view' && $node->isPublished()) {
          $access = new AccessResultAllowed();
          break;
        }

        if ($user->hasPermission($action . ' any embargoed publication')) {
          $access = new AccessResultAllowed();
        }
        elseif ($user->hasPermission($action . ' own embargoed publication') && $node->getOwnerId() === $user->id()) {
          $access = new AccessResultAllowed();
        }
        else {
          $access = new AccessResultForbidden();
        }
        break;

      case "view scheduled transition":
      case "View all revisions":
        if ($user->hasPermission('update any embargoed publication')) {
          $access = new AccessResultAllowed();
        }
        elseif ($user->hasPermission('update own embargoed publication') && $node->getOwnerId() === $user->id()) {
          $access = new AccessResultAllowed();
        }
        else {
          $access = new AccessResultForbidden();
        }
        break;

      default:
        $access = parent::access($node, $operation, $account, TRUE);
    }

    /* @phpstan-ignore-next-line */
    $access->cachePerUser()->cachePerPermissions()->addCacheTags(
      ['node:' . $node->id()]
    );

    return $access;
  }

}
