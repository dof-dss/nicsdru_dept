<?php

namespace Drupal\dept_migrate_flags\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event handler for Flag migrations.
 */
class MigrationFlagCountsSubscriber implements EventSubscriberInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;

  /**
   * D7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7dbconn;

  /**
   * Departmental Migration lookup manager.
   *
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Core\Database\Connection $d7_connection
   *   D7 database connection.
   * @param \Drupal\dept_migrate\MigrateUuidLookupManager $lookup_manager
   *   The lookup manager.
   */
  public function __construct(Connection $connection, Connection $d7_connection, MigrateUuidLookupManager $lookup_manager) {
    $this->dbconn = $connection;
    $this->d7dbconn = $d7_connection;
    $this->lookupManager = $lookup_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    $events[MigrateEvents::PRE_IMPORT] = ['onPreImport'];
    return $events;
  }

  /**
   * Callback for pre import.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The pre migration import event.
   */
  public function onPreImport(MigrateImportEvent $event) {

    // Limit the flag count data creation to migrations that use the
    // flagging_source plugin.
    if ($event->getMigration()->getSourcePlugin()->getPluginId() === 'flagging_source') {
      $results = $this->d7dbconn->query("SELECT CASE WHEN fid = 4 THEN 'hide_listing' WHEN fid = 5 THEN 'hide_on_topic_subtopic_pages' WHEN fid = 6 THEN 'display_on_rss_feeds' END AS flag_id, entity_id, last_updated FROM {flag_counts} WHERE fid IN (4,5,6)");

      foreach ($results as $result) {
        $nids = $this->lookupManager->lookupBySourceNodeId([$result->entity_id]);

        $d9_nid = $nids[$result->entity_id]['nid'];
        $this->dbconn->query("INSERT IGNORE INTO {flag_counts} (flag_id, entity_type, entity_id, count, last_updated) VALUES ('$result->flag_id', 'node', '$d9_nid', '0', '$result->last_updated')")->execute();
      }
    }
  }

}
