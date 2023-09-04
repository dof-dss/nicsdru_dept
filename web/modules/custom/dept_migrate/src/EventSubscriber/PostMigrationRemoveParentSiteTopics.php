<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Post migration subscriber for removing parent site topic entries.
 */
class PostMigrationRemoveParentSiteTopics implements EventSubscriberInterface {

  /**
   * LoggerChannel definition.
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
    $events[MigrateEvents::PRE_ROW_SAVE][] = ['onMigratePreRowSave'];
    $events[MigrateEvents::PRE_ROW_SAVE][] = ['onMigratePreRowSave'];
    return $events;
  }

  /**
   * Handle post import migration event.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePreRowSave(MigratePreRowSaveEvent $event) {
  }

  public function onMigratePostRowSave(MigratePostRowSaveEvent $event) {
    var_dump($event->getDestinationIdValues());
  }

}
