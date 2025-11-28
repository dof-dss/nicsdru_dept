<?php

declare(strict_types=1);

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\dept_topics\TopicManager;
use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Entity event subscriber for processing topic child entities.
 */
final class TopicsChildEntityEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a TopicsChildEntityEventSubscriber object.
   */
  public function __construct(
    private readonly TopicManager $topicManager,
  ) {}

  /**
   * Entity insert event handler.
   */
  public function onEntityInsert(EntityEvent $event): void {
    /* @var ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->topicManager->isValidTopicChild($entity)) {
      return;
    }

    if ($entity->get('moderation_state')->getString() !== 'archived') {
      $topics = $entity->get('field_site_topics')->referencedEntities();
      foreach ($topics as $topic) {
        $this->topicManager->addChild($entity, $topic);
      }
    }
  }

  /**
   * Entity update event handler.
   */
  public function onEntityUpdate(EntityEvent $event): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->topicManager->isValidTopicChild($entity)) {
      return;
    }

    $moderation_state = $entity->get('moderation_state')->getString();

    switch ($moderation_state) {
      case 'archived':
        $this->topicManager->archiveChild($entity);
        break;

      default:
        $this->topicManager->processChild($entity);
        break;
    }
  }

  /**
   * Entity delete event handler.
   */
  public function onEntityDelete(EntityEvent $event): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->topicManager->isValidTopicChild($entity)) {
      return;
    }

    // Remove deleted child from topic references.
    $topics = $entity->get('field_site_topics')->referencedEntities();

    foreach ($topics as $topic) {
      $this->topicManager->removeChild($entity, $topic);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::INSERT => ['onEntityInsert'],
      EntityEventType::UPDATE => ['onEntityUpdate'],
      EntityEventType::DELETE => ['onEntityDelete', 100],
    ];
  }

}
