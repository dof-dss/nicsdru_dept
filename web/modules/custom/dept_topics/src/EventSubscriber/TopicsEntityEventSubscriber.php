<?php

declare(strict_types=1);

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
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
    /* @var ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if ($entity instanceof ContentEntityInterface && in_array($entity->bundle(), ['topic', 'subtopic'])) {
      // Resolves an issue that prevented the 'Topics' field from including a
      // newly created topic when adding child content via the moderation sidebar.
      $domain_source = $entity->get('field_domain_source')->getValue();
      $dept_id = $domain_source[0]['target_id'];
      Cache::invalidateTags([$dept_id . '_topics']);
    }

    if ($this->topicManager->isValidTopicChild($entity)) {
      if ($entity->get('moderation_state')->getString() == 'published') {
        $topics = $entity->get('field_site_topics')->referencedEntities();

        foreach ($topics as $topic) {
          $this->topicManager->addChild($entity, $topic);
        }
      }
    }
  }

  /**
   * Entity update event handler.
   */
  public function onEntityUpdate(EntityEvent $event): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if ($this->topicManager->isValidTopicChild($entity)) {
      if ($entity->get('moderation_state')->getString() == 'published') {
        $this->topicManager->processChild($entity);
      }

      if ($entity->get('moderation_state')->getString() == 'archived') {
        $this->topicManager->archiveChild($entity);
      }
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
