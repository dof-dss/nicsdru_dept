<?php

namespace Drupal\dept_migrate_group_users\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dept_migrate\MigrateSupport;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class UserEntityMigrateSubscriber.
 */
class UserEntityMigrateSubscriber implements EventSubscriberInterface {

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
    if ($event->getMigration()->id() === 'users') {
      $user_id = $event->getDestinationIdValues()[0];
      $user = $this->entityTypeManager->getStorage('user')->load($user_id);

      if (!empty($user)) {
        $this->migrateSupport->syncDomainsToGroups($user);
      }
      else {
        $this->logger->error("Couldn't load user for destination id ${user_id}");
      }
    }
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
