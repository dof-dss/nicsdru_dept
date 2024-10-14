<?php

namespace Drupal\dept_core;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Department Entity.
 */
class DepartmentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Custom access which grants/denies permission to update Department entities
    // based on the users permissions and account 'Domain Access' field values.
    switch ($operation) {
      case 'view label':
      case 'view':
        return AccessResult::allowed();

      case 'update':
        return $this->userDepartmentAccess($entity, $account);

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer departments');

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * Returns the Access result based on an account permissions and Domain Access.
   *
   * @param \Drupal\Core\Entity\EntityInterface $department
   *   The Department entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   */
  private function userDepartmentAccess(EntityInterface $department, AccountInterface $account) {
    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->load($account->id());

    if ($user->hasPermission('administer department')) {
      return AccessResult::allowed();
    }

    $user_departments = array_column($user->get('field_domain_access')->getValue(), 'target_id');

    if (in_array($department->id(), $user_departments)) {
      return AccessResult::allowedIfHasPermission($account, 'update department');
    }

    return AccessResult::forbidden();
  }

}
