<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Post migration subscriber for tidying up any known, awkward body values.
 */
class PostMigrationBodyTIdySubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Logger\LoggerChannel definition.
   *
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
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   */
  public function __construct(LoggerChannelFactory $logger, Connection $connection) {
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

    if (strpos($event_id, 'node_') === 0) {
      $this->logger->notice("Removing unwanted data-entity-uuid references from node body table");

      // Regex: removes the entirety of the data-entity-uuid key+value from the
      // stored markup because it interferes with entity token replacement
      // when rendering the node resulting in WSOD.
      $this->dbconn->query("UPDATE node__body SET body_value = REGEXP_REPLACE(body_value, 'data-entity-type=\"node\" (data-entity-uuid=\".+)\" ', '') WHERE body_value LIKE '%data-entity-type=\"node\" data-entity-uuid=%'");
    }
  }

}
