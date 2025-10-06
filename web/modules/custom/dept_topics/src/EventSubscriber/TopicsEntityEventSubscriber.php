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
   * Entity presave event handler.
   */
  public function onEntityPresave(EntityEvent $event): void {
    $entity = $event->getEntity();

    if (!$this->topicManager->isValidTopicChild($entity)) {
      return;
    }




  }

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
        $this->topicManager->addChildToTopic($child, $topic);
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

    $original = $child->original;

    if (!$child->get('moderation_state')->getString() == 'published') {
    }

    $existing_topics = array_column($original->get('field_site_topics')->getValue(), 'target_id');
    $updated_topics = array_column($child->get('field_site_topics')->getValue(), 'target_id');

    $topics_added_ids = array_diff($updated_topics, $existing_topics);
    $topics_removed_ids = array_diff($existing_topics, $updated_topics);

    if (!empty($topics_removed_ids) || !empty($topics_added_ids)) {
      $node_store = $topic = $this->entityTypeManager->getStorage('node');
    }

    foreach ($topics_added_ids as $topic_id) {
      $topic = $node_store->load($topic_id);
      $this->topicManager->addChildToTopic($child, $topic);
    }

    foreach ($topics_removed_ids as $topic_id) {
      $topic = $node_store->load($topic_id);
      $this->topicManager->removeChildFromTopic($child, $topic);
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
      $this->topicManager->removeChildFromTopic($child, $topic);
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
