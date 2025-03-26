<?php

declare(strict_types=1);

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dept_topics\OrphanManager;
use Drupal\dept_topics\TopicManager;
use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
/**
 * Entity event subscriber for processing topic and topic child entities.
 */
final class TopicsEntityEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a TopicsEntityCrudSubscriber object.
   */
  public function __construct(
    private readonly TopicManager $topicManager,
    private readonly OrphanManager $orphanManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Entity presave event handler.
   */
  public function onEntityPresave(EntityEvent $event): void {
    $entity = $event->getEntity();

    if ($entity->bundle() === 'topic' || $entity->bundle() === 'subopic') {
      $moderation_state = $entity->get('moderation_state')->getString();

      if ($moderation_state == 'published') {
        // Process orphaned.
      }
    }
  }

  /**
   * Entity insert event handler.
   */
  public function onEntityInsert(EntityEvent $event): void {
    $entity = $event->getEntity();

    if ($entity->bundle() === 'topic' || $entity->bundle() === 'subopic') {
      $moderation_state = $entity->get('moderation_state')->getString();

      if ($moderation_state == 'published') {
        // Process orphaned.
      }
    }
  }

  /**
   * Entity update event handler.
   */
  public function onEntityUpdate(EntityEvent $event): void {
    $entity = $event->getEntity();

    if ($entity->bundle() === 'topic' || $entity->bundle() === 'subopic') {
      $moderation_state = $entity->get('moderation_state')->getString();

      if ($moderation_state == 'published') {
        // Process orphaned.
      }
    }
  }

  /**
   * Entity delete event handler.
   */
  public function onEntityDelete(EntityEvent $event): void {
    $entity = $event->getEntity();

    if ($entity->bundle() === 'topic' || $entity->bundle() === 'subopic') {
      // Process orphaned.
    }
  }


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::PRESAVE => ['onEntityPresave'],
      EntityEventType::INSERT => ['onEntityInsert'],
      EntityEventType::UPDATE => ['onEntityUpdate'],
      EntityEventType::DELETE => ['onEntityDelete'],
    ];
  }

}
