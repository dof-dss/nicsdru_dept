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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends the core node access handler for Departmental sites.
 */
class DeptNodeAccessControlHandler extends NodeAccessControlHandler {

  /**
   * Builds the permission used to delete revisions without deleting nodes.
   */
  public static function revisionDeletePermission(string $bundle): string {
    return "delete $bundle revisions without node delete access";
  }

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
    // Entity handlers may be subclassed by tests or downstream projects.
    // @phpstan-ignore-next-line
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

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $node, $operation, AccountInterface $account) {
    assert($node instanceof NodeInterface);

    if ($operation === 'delete revision' && $account->hasPermission(static::revisionDeletePermission($node->bundle()))) {
      // A default revision is the live node, regardless of whether a newer
      // pending revision exists. It must only be removable through node delete.
      $result = $node->isDefaultRevision() ? AccessResult::forbidden() : AccessResult::allowed();
      return $result->cachePerPermissions()->addCacheableDependency($node);
    }

    return parent::checkAccess($node, $operation, $account);
  }

}
