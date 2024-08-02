<?php

namespace Drupal\dept_migrate_audit;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Batch service for processing Migration Audit data.
 */
class MigrationAuditBatchService {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The D7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $d7Database;

  /**
   * Creates an Audit Batch Service instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Drupal database connection.
   * @param \Drupal\Core\Database\Connection $d7_database
   *   Drupal 7/migration database connection.
   */
  public function __construct(Connection $database, Connection $d7_database) {
    $this->database = $database;
    $this->d7Database = $d7_database;
  }

  /**
   * Creates a batch process to process audit data.
   */
  public function setupBatch(): void {
    $this->database->truncate('dept_migrate_audit')->execute();

    $types = [
      'application',
      'article',
      'collection',
      'consultation',
      'contact',
      'gallery',
      'heritage_site',
      'link',
      'news',
      'page',
      'protected_area',
      'publication',
      'secure_publication',
      'subtopic',
      'topic',
      'ual',
    ];

    // Initialize batch builder.
    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle($this->t('Migration Audit'))
      ->setInitMessage($this->t('Processing audit data'))
      ->setProgressMessage($this->t('@current items out of @total.'))
      ->setErrorMessage($this->t('Error processing audit data.'))
      ->setFinishCallback([$this, 'processAuditDataFinished']);

    foreach ($types as $type) {
      $results = $this->d7Database->select('node', 'n')
        ->fields('n', ['uuid', 'type'])
        ->condition('type', $type)
        ->execute()
        ->fetchAll();

      if ($results) {
        $batch_builder->addOperation([$this, 'processAuditData'], [$type, $results]);
      }
    }

    // Set the batch.
    batch_set($batch_builder->toArray());
  }

  /**
   * Batch process.
   *
   * @param string $id
   *   ID of the batch process.
   * @param array $data
   *   Dataset to process.
   * @param array $context
   *   Batch context object.
   */
  public function processAuditData($id, $data, &$context) {

    $now = \Drupal::time()->getCurrentTime();

    $query = $this->database
      ->insert('dept_migrate_audit')
      ->fields(['uuid', 'type', 'last_import']);
    foreach ($data as $index => $row) {
      $query->values([$row->uuid, $row->type, $now]);
    }

    $query->execute();

    $context['results'][] = $id;

    // Optional message displayed under the progressbar.
    $context['message'] = $this->t('Processing audit data for @type',
      ['@type' => $id]
    );

  }

  /**
   * Callback for finished batch.
   *
   * @param bool $success
   *   Indicate that the batch API tasks were all completed successfully.
   * @param array $results
   *   An array of all the results that were updated in update_do_one().
   * @param array $operations
   *   A list of all the operations that had not been completed by the batch API.
   */
  public function processAuditDataFinished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();

    if ($success) {
      $messenger->addMessage($this->t('@count results processed.', ['@count' => count($results)]));
    }
    else {
      $error_operation = reset($operations);
      $messenger->addMessage(
        $this->t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }

}
