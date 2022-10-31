<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Pre migration subscriber to clean up domain access entries.
 */
class PreDomainAccessCleanUp implements EventSubscriberInterface {

  /**
   * Drupal\Core\Logger\LoggerChannel definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Drupal 7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db7conn;

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
    $this->db7conn = $connection;
  }

  /**
   * Get subscribed events.
   *
   * @inheritdoc
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PRE_IMPORT][] = ['onMigratePreImport'];
    return $events;
  }

  /**
   * Handle pre import migration event.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePreImport(MigrateImportEvent $event) {
    $event_id = $event->getMigration()->getBaseId();

    if (strpos($event_id, 'node_') === 0) {
      // Delete nigov da rows for news items of type 'press release'.
      $this->db7conn->query("DELETE domain_access FROM domain_access INNER JOIN field_data_field_news_type ON domain_access.nid = field_data_field_news_type.entity_id WHERE field_data_field_news_type.field_news_type_value = 'pressrelease' AND domain_access.gid = 1");

      // Delete nigov da rows for publication and secure publications.
      $this->db7conn->query("DELETE domain_access FROM domain_access INNER JOIN node ON domain_access.nid = node.nid WHERE domain_access.gid = 1 AND node.type = 'publication' OR node.type = 'secure_publication' ");

      // Delete nigov da rows for consultations.
      $this->db7conn->query("DELETE domain_access FROM domain_access INNER JOIN node ON domain_access.nid = node.nid WHERE domain_access.gid = 1 AND node.type = 'consultation' ");

      // Delete rows for old domains.
      $this->db7conn->query("DELETE domain_access FROM domain_access WHERE domain_access.gid IN (3,10,11)");
    }
  }

}
