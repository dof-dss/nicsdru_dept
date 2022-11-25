<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\dept_migrate\MigrateUuidLookupManager $lookup_manager
   *   Lookup Manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              MigrateUuidLookupManager $lookup_manager,
                              LoggerChannelFactory $logger) {

    $this->entityTypeManager = $entity_type_manager;
    $this->lookupManager = $lookup_manager;
    $this->logger = $logger->get('dept_migrate');
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
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    $events = [];
    // NB: MigrateEvents is a migrate_plus namespace.
    $events[MigrateEvents::PREPARE_ROW] = ['onPrepareRow'];
    return $events;
  }

}
