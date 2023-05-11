<?php

namespace Drupal\dept_migrate_files\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\file\FileInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Post migration subscriber to correct managed file import properties.
 */
class PostMigrationTopicReferenceAssignment implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;


  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              LoggerChannelFactory $logger,
                              Connection $connection) {

    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('dept_migrate');
    $this->dbconn = $connection;
  }

  /**
   * Get subscribed events.
   *
   * @inheritdoc
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_IMPORT][] = ['onMigratePostImport'];
    return $events;
  }

  /**
   * Handle post import migration event.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
    $event_id = $event->getMigration()->getBaseId();

    if ($event_id === 'node_subtopic') {

      $subtopics =  $this->dbconn->query("
        SELECT pt.entity_id AS subtopic, pt.field_parent_topic_target_id AS topic, n.vid AS revision FROM node__field_parent_topic pt
        LEFT JOIN node n
        ON pt.entity_id = n.nid
        LEFT JOIN node__field_topic_content tc
        ON pt.field_parent_topic_target_id = tc.entity_id AND pt.entity_id = tc.field_topic_content_target_id
        WHERE tc.entity_id IS NULL"
      )->fetchAll();

      foreach ($subtopics as $subtopic) {
        $delta = $this->dbconn->query("
          SELECT MAX(delta) + 1
          FROM node__field_topic_content
          WHERE entity_id = " . $subtopic->topic
        )->fetchField();

        $delta = $delta ?? 0;

        $this->dbconn->insert('node__field_topic_content')->fields([
          'bundle' => 'Topic',
          'deleted' => 0,
          'entity_id' => $subtopic->topic,
          'revision_id' => $subtopic->revision,
          'langcode' => 'en',
          'delta' => $delta,
          'field_topic_content_target_id' => $subtopic->subtopic,
        ])
        ->execute();
      }
    }
  }

}
