<?php

declare(strict_types=1);

namespace Drupal\origins_cloud_tasks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\origins_cloud_tasks\CloudTasksManager;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Returns responses for Origins cloud tasks routes.
 */
final class CloudTasksController extends ControllerBase {

  use AutowireTrait;

  /**
   * Constructs a Cloud Tasks manager object.
   */
  public function __construct(
    #[Autowire(service: 'origins_cloud_tasks.manager')]
    protected CloudTasksManager $taskManager,
  ) {}

  public function displayTasks() {
    $build = [];
    $database = \Drupal::database();
//    $task_manager = \Drupal::service('origins_cloud_tasks.manager');
//
//    /* @var \Google\ApiCore\PagedListResponse $tasks */
//    $tasks = $task_manager->getTasks();
//    $scheduled_transitions_tasks = [];
//
//    for ($tasks as $task) {
//      if (str_starts_with($task['name'] , 'scheduled_transition)--')) {
//        $scheduled_transitions_tasks
//      }
//    }


    $results = $database->select('scheduled_transition', 'st')
      ->fields('st', ['id', 'transition_on'])
      ->condition('is_processed', 0)
      ->condition('moderation_state', ['archived', 'published'], 'IN')
      ->execute()->fetchAllAssoc('id');

    ksm($results);

    return $build;
  }

//  /**
//   * Display current Cloud Tasks in the Queue.
//   */
//  public function displayTasks(): array {
//    $build = [];
//
//    try {
//      $tasks = $this->taskManager->getTasks();
//
//      $rows = [];
//      foreach ($tasks as $task) {
//
//        $rows[] =
//          [
//            'name' => $task->getName(),
//            'schedule' => $task->getScheduleTime()->toDateTime()->format('d/m/Y H:i:s'),
//            'url' => $task->getHttpRequest()->getUrl(),
//          ];
//      }
//
//      $rows = $this->moduleHandler()->invokeAll('origins_cloud_tasks_list_rows', [$rows]);
//
//      $build['tasks'] = [
//        '#type' => 'table',
//        '#header' => [
//          'name' => $this->t('name'),
//          'schedule' => $this->t('schedule'),
//          'url' => $this->t('url'),
//        ],
//        '#rows' => $rows,
//        '#empty' => $this->t('No tasks found.'),
//      ];
//    }
//    catch (\Exception $ex) {
//      $build = [
//        '#type' => 'html_tag',
//        '#tag' => 'p',
//        '#value' => 'Error: ' . $ex->getMessage(),
//      ];
//    }
//    finally {
//      return $build;
//    }
//
//  }

}
