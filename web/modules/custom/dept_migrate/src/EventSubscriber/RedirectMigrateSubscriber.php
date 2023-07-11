<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate_plus\Event\MigrateEvents as MigratePlusEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class RedirectMigrateSubscriber.
 */
class RedirectMigrateSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

  /**
   * Drupal\Core\Logger\LoggerChannelFactory definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;


  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7dbconn;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\dept_migrate\MigrateUuidLookupManager $lookup_manager
   *   Lookup Manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Core\Database\Connection $d7_connection
   *   Database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              MigrateUuidLookupManager $lookup_manager,
                              LoggerChannelFactory $logger,
                              Connection $connection,
                              Connection $d7_connection) {
    $this->entityTypeManager = $entity_type_manager;
    $this->lookupManager = $lookup_manager;
    $this->logger = $logger->get('dept_migrate');
    $this->dbconn = $connection;
    $this->d7dbconn = $d7_connection;
  }

  /**
   * Callback for preparing the migration row.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The event object to prepare.
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    if ($event->getMigration()->id() === 'redirects') {
      $source = $event->getRow()->getSourceProperty('source');
      $destination = $event->getRow()->getSourceProperty('redirect');

      // Source: if it's an internal node path update the node id to the D9 equivalent.
      $source_matches = [];
      preg_match('|node/(\d+)|', $source, $source_matches);

      if (!empty($source_matches[1])) {
        $d9_info = $this->lookupManager->lookupBySourceNodeId([$source_matches[1]]);
        $d9_nid = reset($d9_info)['nid'] ?? 0;

        if (!empty($d9_nid)) {
          $d9_source = preg_replace('|node/(\d+)|', 'node/' . $d9_nid, $source);
          $event->getRow()->setSourceProperty('source', $d9_source);
        }
      }

      // Destination: if it's an internal node path update the node id to the D9 equivalent.
      $dest_matches = [];
      preg_match('|node/(\d+)|', $destination, $dest_matches);

      if (!empty($dest_matches[1])) {
        $d9_info = $this->lookupManager->lookupBySourceNodeId([$dest_matches[1]]);
        $d9_nid = reset($d9_info)['nid'] ?? 0;

        if (!empty($d9_nid)) {
          $d9_dest = preg_replace('|node/(\d+)|', 'node/' . $d9_nid, $destination);
          $event->getRow()->setSourceProperty('redirect', $d9_dest);
        }
      }
    }
  }

  /**
   * Handle post import migration event.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
    $event_id = $event->getMigration()->getBaseId();

    if ($event_id === 'node_page' || $event_id === 'node_subtopic') {
      $this->createRedirectsByType(substr($event_id, 5));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    $events = [];
    // NB: MigrateEvents is a migrate_plus namespace.
    $events[MigratePlusEvents::PREPARE_ROW] = ['onPrepareRow'];
    $events[MigrateEvents::POST_IMPORT] = ['onMigratePostImport'];
    return $events;
  }

  /**
   * Create redirect when a bundle has a different alias pattern from d7.
   *
   * @param string $bundle
   *   Bundle type to process.
   */
  protected function createRedirectsByType(string $bundle) {
    // Fetch the D7 path aliases for the bundle.
    $nids = $this->d7dbconn->query("SELECT n.nid, a.alias FROM {node} n LEFT JOIN {url_alias} a ON CONCAT('node/', n.nid) = a.source WHERE n.type = :type",
      [':type' => $bundle])
      ->fetchAllKeyed();

    foreach ($nids as $nid => $alias) {
      $redirect_exists = $this->entityTypeManager->getStorage('redirect')->loadByProperties(['redirect_source' => $alias]);

      if (!$redirect_exists) {
        $d9_info = $this->lookupManager->lookupBySourceNodeId([$nid]);

        if (is_array($d9_info)) {
          $d9_info = current($d9_info);

          if (is_array($d9_info) && array_key_exists('nid', $d9_info)) {
            $redirect = Redirect::create([
              'redirect_source' => $alias,
              'redirect_redirect' => 'internal:/node/' . $d9_info['nid'],
              'language' => 'und',
              'status_code' => '301',
            ]);
            $redirect->save();
          }
        }
      }

    }
  }

}
