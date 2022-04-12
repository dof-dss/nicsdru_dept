<?php

namespace Drupal\dept_migrate_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * Migrations dashboard constructor.
   *
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   The DepartmentManager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(DepartmentManager $department_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->departmentManager = $department_manager;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('department.manager'),
      $container->get('entity_type.manager')
    );
  }


  /**
   * Builds the response.
   */
  public function build() {

    $departments = $this->departmentManager->getAllDepartments();
    $group_type = $this->entityTypeManager->getStorage('group_type')->load('department_site');
    $node_bundles = [];
    $table_header = ['Bundle', 'Drupal 9', 'Drupal 7'];

    foreach ($group_type->getInstalledContentPlugins() as $plugin) {
      if ($plugin->getEntityTypeId() === 'node') {
        $node_bundles[] = $plugin->getEntityBundle();
      }
    }


    /** @var \Drupal\dept_core\Department $department **/
    foreach ($departments as $department) {
      $rows = [];

      $build['department_' . $department->id()]['wrapper'] = [
          '#type' => 'details',
          '#title' => $department->name(),
          '#open' => TRUE,
      ];

      foreach ($node_bundles as $bundle) {
        $rows[] = [$bundle, 10, 10];
      }

      $build['department_' . $department->id()]['wrapper']['nodes'] = [
        '#type' => 'table',
        '#header' => $table_header,
        '#rows' => $rows,
      ];
    }

    return $build;
  }

}
