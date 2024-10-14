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

    switch ($operation){
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

  private function userDepartmentAccess(EntityInterface $department, AccountInterface $account) {
    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->load($account->id());
    $user_departments = array_column($user->get('field_domain_access')
      ->getValue(), 'target_id');

    if ($user->hasPermission('administer department')) {
      return AccessResult::allowed();
    }

    if (in_array($department->id(), $user_departments)) {
      return AccessResult::allowedIfHasPermission($account, 'update department');
    }

    return AccessResult::forbidden();
  }

}
