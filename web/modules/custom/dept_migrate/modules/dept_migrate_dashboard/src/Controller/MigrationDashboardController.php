<?php

namespace Drupal\dept_migrate_dashboard\Controller;

use _PHPStan_ae8980142\React\Socket\ConnectionInterface;
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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConn;

  /**
   * Migrations dashboard constructor.
   *
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   The DepartmentManager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(DepartmentManager $department_manager, EntityTypeManagerInterface $entity_type_manager, ConnectionInterface $connection) {
    $this->departmentManager = $department_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->dbConn = $connection;
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
      $container->get('entity_type.manager'),
      $container->get('database'),
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

        // TODO: Query the D9 'group_content' table to count the relationships.
        // $this->dbConn->select("")
        // TODO: Query the D7 'domain_access' table to count the relationships.

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
