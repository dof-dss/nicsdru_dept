<?php

namespace Drupal\dept_migrate_flags\EventSubscriber;

use Drupal\Core\Database\Connection;
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
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Core\Database\Connection $d7_connection
   *   D7 database connection.
   */
  public function __construct( Connection $connection, Connection $d7_connection) {
    $this->dbconn = $connection;
    $this->d7dbconn = $d7_connection;
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
   * @param \Drupal\migrate\EventMigrateImportEvent\ $event
   *   The pre migration import event.
   */
  public function onPreImport(MigrateImportEvent $event) {
    $flag_migrations = [
      'flagging_hide_on_topic_subtopic_pages'
    ];

//    if (in_array($event->getMigration()->getBaseId(), $flag_migrations)) {
    $results = $this->d7dbconn->query("SELECT CASE WHEN fid = 4 THEN 'hide_listing' WHEN fid = 5 THEN 'hide_on_topic_subtopic_pages' WHEN fid = 6 THEN 'display_on_rss_feeds' END AS flag_id, entity_id, last_updated FROM {flag_counts} WHERE fid IN (4,5,6)");

      foreach ($results as $result) {
        $this->dbconn->query("INSERT IGNORE INTO {flag_counts} (flag_id, entity_type, entity_id, count, last_updated) VALUES ('$result->flag_id', 'node', '$result->entity_id', '1', '$result->last_updated')")->execute();
      }
    }
//  }

}
