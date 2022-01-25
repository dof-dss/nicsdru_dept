<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Post migration event handler for Topic and Subtopic bundles.
 */
class PostMigrationSubTopics implements EventSubscriberInterface {

  /**
   * Drupal\Core\Logger\LoggerChannelFactory definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * Lookup manager.
   *
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

  /**
   * Stores the entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * PostMigrationSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   * @param \Drupal\dept_migrate\MigrateUuidLookupManager $lookup_manager
   *   Lookup_manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              LoggerChannelFactory $logger,
                              MigrateUuidLookupManager $lookup_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('dept_migrate');
    $this->lookupManager = $lookup_manager;
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

    if ($event_id === 'node_topic' || $event_id === 'node_subtopic') {

      $dbconn_default = Database::getConnection('default', 'default');

      $query = $dbconn_default->select('node__field_parent_topic', 'pt');
      $query->fields('pt', ['field_parent_topic_target_id']);
      $d7nids = $query->distinct()->execute()->fetchCol('field_parent_topic_target_id');

      $d9data = $this->lookupManager->lookupBySourceNodeId($d7nids);

      foreach ($d9data as $d7nid => $data) {
        $num_updated = $dbconn_default->update('node__field_parent_topic')
          ->fields(['field_parent_topic_target_id' => $data['nid'],])
          ->condition('field_parent_topic_target_id', $d7nid, '=')
          ->execute();
        $this->logger->notice("Updated $num_updated entries for $d7nid");
      }

    }
  }

}
