<?php

namespace Drupal\dept_migrate_group_nodes\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dept_migrate\MigrateSupport;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class NodeEntityMigrateSubscriber.
 */
class NodeEntityMigrateSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\dept_migrate\MigrateSupport
   */
  protected $migrateSupport;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\dept_migrate\MigrateSupport $migrate_support
   *   Migrate support service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MigrateSupport $migrate_support) {
    $this->entityTypeManager = $entity_type_manager;
    $this->migrateSupport = $migrate_support;
  }

  /**
   * Callback for post-row-save event.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The migrate post row save event.
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) {
    // Check migration id, otherwise it'll trigger for anything!
    $map = $event->getRow()->getIdMap();
    $node_id = $map['destid1'];

    $node = $this->entityTypeManager->getStorage('node')->load($node_id);
    $this->migrateSupport->syncDomainsToGroups($node);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    $events = [];
    $events[MigrateEvents::POST_ROW_SAVE] = ['onPostRowSave'];
    return $events;
  }

}
