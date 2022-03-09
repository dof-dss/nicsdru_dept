<?php

namespace Drupal\dept_migrate_group_files\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\dept_migrate\MigrateSupport;
use Drupal\media\MediaInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class MediaEntityMigrateSubscriber.
 */
class MediaEntityMigrateSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\dept_migrate\MigrateSupport
   */
  protected $migrateSupport;

  /**
   * Drupal\Core\Logger\LoggerChannelFactory definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\dept_migrate\MigrateSupport $migrate_support
   *   Migrate support service.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              MigrateSupport $migrate_support,
                              LoggerChannelFactory $logger) {

    $this->entityTypeManager = $entity_type_manager;
    $this->migrateSupport = $migrate_support;
    $this->logger = $logger->get('dept_migrate');
  }

  /**
   * Callback for post-row-save event.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The migrate post row save event.
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) {
    if (preg_match('/^d7_file_media_/', $event->getMigration()->id())) {
      // If there isn't a group relation entity for this media entity, add one.
      $entity_id = $event->getDestinationIdValues()[0];
      $media = $this->entityTypeManager->getStorage('media')->load($entity_id);

      if ($media instanceof MediaInterface) {
        $this->migrateSupport->addMediaToDefaultGroup($media);
      }
      else {
        $this->logger->error("Couldn't load media entity for destination id ${entity_id}");
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
