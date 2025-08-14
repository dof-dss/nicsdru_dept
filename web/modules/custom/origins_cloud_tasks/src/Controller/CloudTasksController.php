<?php

declare(strict_types=1);

namespace Drupal\origins_cloud_tasks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Exception;
use Google\Cloud\Tasks\V2\Client\CloudTasksClient;

/**
 * Returns responses for Origins cloud tasks routes.
 */
final class CloudTasksController extends ControllerBase {

  /**
   * Display current Cloud Tasks in the Queue.
   */
  public function displayTasks(): array {

    putenv('GOOGLE_APPLICATION_CREDENTIALS=/app/google_application_credentials.json');

    $project_id = getenv('PLATFORM_APPLICATION_NAME');
    $location = 'europe-west2-a';
    $queue_id = $project_id . '-origins-cloud-tasks';

    $client = new CloudTasksClient();

    $build = [];

    try {
      $queue_name = $client->queueName($project_id, $location, $queue_id);

      $tasks = $client->listTasks($queue_name);

      foreach ($tasks as $task) {
        $build[$task->id()] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $task->id(),
        ];
      }

    } catch (Exception $e) {
      $build[] = [
        '#markup' => 'Error: ' . $e->getMessage()
      ];
    } finally {
      $client->close();
    }

    return $build;
  }

}
