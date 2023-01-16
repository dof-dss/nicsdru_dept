<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Post migration subscriber for handling flag assignments.
 */
class PostMigrationFlagSubscriber implements EventSubscriberInterface {

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
   * Drupal 7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db7conn;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etManager;

  /**
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   * @param \Drupal\Core\Database\Connection $d9_connection
   *   Database connection.
   * @param \Drupal\Core\Database\Connection $d7_connection
   *   Database connection for D7 data source.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service object.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   Flag service object.
   */
  public function __construct(LoggerChannelFactory $logger,
                              Connection $d9_connection,
                              Connection $d7_connection,
                              EntityTypeManagerInterface $entity_type_manager,
                              FlagServiceInterface $flag_service) {

    $this->logger = $logger->get('dept_migrate');
    $this->dbconn = $d9_connection;
    $this->db7conn = $d7_connection;
    $this->etManager = $entity_type_manager;
    $this->flagService = $flag_service;
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

    if (preg_match('/node_(topic|subtopic$)/', $event_id)) {
      $this->logger->notice("Processing flag content data for " . $event_id);
      $type = str_replace('node_', '', $event_id);

      $sql = "SELECT
         n.nid,
         n.title,
         n.type,
         flag.name
         FROM node n
         JOIN flagging f ON f.entity_id = n.nid AND f.entity_type = 'node'
         JOIN flag ON flag.fid = f.fid
         WHERE flag.name = 'hide_listing' AND n.type = :type";

      $d7_hidden_nodes = $this->db7conn->query($sql, [':type' => $type])->fetchAll();

      foreach ($d7_hidden_nodes as $row) {
        $d9_nid_lookup = reset(\Drupal::service('dept_migrate.migrate_uuid_lookup_manager')
          ->lookupBySourceNodeId([$row->nid]));
        $d9_nid = $d9_nid_lookup['nid'];

        // Flag this D9 node as hide in listings.
        $flag = $this->flagService->getFlagById('hide_listing');

        if ($flag instanceof FlagInterface) {
          $node = $this->etManager->getStorage('node')->load($d9_nid);
          $flagging = $this->flagService->flag($flag, $node);
        }
      }
    }
  }

}
