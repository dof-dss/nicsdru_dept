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
use Drupal\facets\Exception\Exception;
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
      Cache::invalidateTags(['topics_field:' . $dept_id]);
      Cache::invalidateTags([$dept_id . '_topics']);
    }

    if ($this->topicManager->isValidTopicChild($entity)) {
      if ($entity->get('moderation_state')->getString() !== 'archived') {
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

    if ($entity instanceof ContentEntityInterface && in_array($entity->bundle(), ['topic', 'subtopic'])) {
      $domain_source = $entity->get('field_domain_source')->getValue();
      $dept_id = $domain_source[0]['target_id'];
      Cache::invalidateTags(['topics_field:' . $dept_id]);
      Cache::invalidateTags([$dept_id . '_topics']);
    }

    if ($this->topicManager->isValidTopicChild($entity)) {
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
  }

  /**
   * Entity delete event handler.
   */
  public function onEntityDelete(EntityEvent $event): void {
    $entity = $event->getEntity();

    // Remove deleted child from topic references.
    if ($this->topicManager->isValidTopicChild($entity)) {
      $topics = $entity->get('field_site_topics')->referencedEntities();

      foreach ($topics as $topic) {
        $this->topicManager->removeChild($entity, $topic);
      }
    }

    // Prevent deletion of topics if it has any active child content.
    // Adding this in addition to the frontend warning to provide coverage
    // when using the CLI (drush) etc.
    if (in_array($entity->bundle(), ['topic', 'subtopic'])) {
      $children = $entity->get('field_topic_content')->referencedEntities();

      foreach ($children as $child) {
        if ($child->get('moderation_state')->getString() != 'archived') {
          throw new Exception(t('This @bundle (@id) cannot be deleted because it has active (published or draft) child content',
          [
            '@bundle' => $entity->bundle(),
            '@id' => $entity->id(),
          ])->render());
        }
      }
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
