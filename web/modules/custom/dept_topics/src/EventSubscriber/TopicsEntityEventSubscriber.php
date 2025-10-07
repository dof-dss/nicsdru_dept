<?php

declare(strict_types=1);

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dept_topics\OrphanManager;
use Drupal\dept_topics\TopicContentAction;
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
    private readonly BookManagerInterface $bookManager,
  ) {}

  /**
   * Entity insert event handler.
   */
  public function onEntityInsert(EntityEvent $event): void {
    /* @var ContentEntityInterface $child */
    $child = $event->getEntity();
    if (!$this->topicManager->isValidTopicChild($child)) {
      return;
    }

    if ($child->get('moderation_state')->getString() == 'published') {
      $topics = $child->get('field_site_topics')->referencedEntities();

      foreach ($topics as $topic) {
        $this->topicManager->addChild($child, $topic);
      }
    }
  }

  /**
   * Entity update event handler.
   */
  public function onEntityUpdate(EntityEvent $event): void {
    $child = $event->getEntity();

    if (!$this->topicManager->isValidTopicChild($child)) {
      return;
    }

    if ($child->get('moderation_state')->getString() == 'published') {
      $this->topicManager->processChild($child);
    }

    if ($child->get('moderation_state')->getString() == 'archived') {
      $this->topicManager->archiveChild($child);
    }
  }

  /**
   * Entity delete event handler.
   */
  public function onEntityDelete(EntityEvent $event): void {
    $child = $event->getEntity();
    if (!$this->topicManager->isValidTopicChild($child)) {
      return;
    }

    $topics = $child->get('field_site_topics')->referencedEntities();

    foreach ($topics as $topic) {
      $this->topicManager->removeChild($child, $topic);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::INSERT => ['onEntityInsert'],
      EntityEventType::UPDATE => ['onEntityUpdate'],
      EntityEventType::DELETE => ['onEntityDelete'],
    ];
  }

}
