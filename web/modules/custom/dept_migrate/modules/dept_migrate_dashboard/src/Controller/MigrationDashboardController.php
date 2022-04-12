<?php

namespace Drupal\dept_migrate_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dept_core\DepartmentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Migrations dashboard routes.
 */
class MigrationDashboardController extends ControllerBase {


  /**
   * The DepartmentManager service.
   *
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected $departmentManager;

  /**
   * Migrations dashboard constructor.
   *
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   The DepartmentManager service.
   */
  public function __construct(DepartmentManager $department_manager) {
    $this->departmentManager = $department_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('department.manager')
    );
  }


  /**
   * Builds the response.
   */
  public function build() {

    $departments = $this->departmentManager->getAllDepartments();

    /** @var \Drupal\dept_core\Department $department **/
    foreach ($departments as $department) {

      $build['department_' . $department->id()] = [
        'fieldset' => [
          '#type' => 'fieldset',
          '#title' => $department->name(),
        ],
      ];

    }

    return $build;
  }

}
