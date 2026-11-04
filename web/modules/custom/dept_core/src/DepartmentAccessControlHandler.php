<?php

namespace Drupal\dept_core;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access control handler for Department Entity.
 */
final class DepartmentAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a DepartmentAccessControlHandler object.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
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
   * Returns the access result based on an account permissions and Domain Access.
   */
  private function userDepartmentAccess(EntityInterface $department, AccountInterface $account): AccessResult {
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($account->id());

    if (!$user) {
      return AccessResult::forbidden();
    }

    if ($user->hasPermission('administer department')) {
      return AccessResult::allowed();
    }

    $user_departments = array_column($user->get('field_domain_access')->getValue(), 'target_id');

    if (in_array($department->id(), $user_departments, TRUE)) {
      return AccessResult::allowedIfHasPermission($account, 'update department');
    }

    return AccessResult::forbidden();
  }

}
