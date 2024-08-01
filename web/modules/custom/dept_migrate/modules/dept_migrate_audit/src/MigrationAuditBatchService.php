<?php

namespace Drupal\dept_migrate_audit;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Batch service for processing Migration Audit data..
 */
class MigrationAuditBatchService {

  use StringTranslationTrait;
  use DependencySerializationTrait;


  /**
   * The D7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7Database;

  /**
   * Creates a CountryRepository instance.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(Connection $d7_database) {
    $this->d7Database = $d7_database;
  }

  public function setupBatch() {

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
    $batch_builder->setTitle($this->t('Checking redirects'))
      ->setInitMessage($this->t('Redirect check is starting...'))
      ->setProgressMessage($this->t('@current items out of @total.'))
      ->setErrorMessage($this->t('Redirect check has encountered an error.'))
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

  public function processAuditData($id, $data, &$context) {

    $now = \Drupal::time()->getCurrentTime();

    $query = \Drupal::database()
      ->insert('dept_migrate_audit')
      ->fields(['uuid', 'type', 'last_import']);
    foreach ($data as $index => $row) {
      $query->values([$row->uuid, $row->type, $now]);
    }

    $query->execute();

    $context['results'][] = $id;

    // Optional message displayed under the progressbar.
    $context['message'] = $this->t('Processing audit data for @type' ,
      ['@type' => $id]
    );

  }

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
