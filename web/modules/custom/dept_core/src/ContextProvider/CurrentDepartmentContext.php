<?php

namespace Drupal\dept_core\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dept_core\DepartmentManager;

/**
 * Sets the current Department as a context.
 */
class CurrentDepartmentContext implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * The Department Manager.
   *
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected DepartmentManager $departmentManager;

  /**
   * Context cqonstructor.
   *
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   The Department Manager.
   */
  public function __construct(DepartmentManager $department_manager) {
    $this->departmentManager = $department_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $current_dept = $this->departmentManager->getCurrentDepartment();

    if ($current_dept) {
      $context = EntityContext::fromEntity($current_dept, $this->t('Current department'));
    }
    else {
      $context = EntityContext::fromEntityTypeId('department', $this->t('Current department'));
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['url.site']);
    $context->addCacheableDependency($cacheability);

    $result = [
      'department' => $context,
    ];

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return $this->getRuntimeContexts([]);
  }

}
