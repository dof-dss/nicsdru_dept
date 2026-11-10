<?php

declare(strict_types=1);

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\dept_topics\TopicManager;
use Drupal\dept_topics\UiMessages;
use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\facets\Exception\Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Entity event subscriber for processing topic entities.
 */
final class TopicsEntityEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a TopicsEntityEventSubscriber object.
   */
  public function __construct(
    private readonly TopicManager $topicManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Entity insert and update event handler.
   */
  public function onEntityInsertOrUpdate(EntityEvent $event): void {
    /* @var ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->isTopic($entity)) {
      return;
    }

    // Save child order to all Topic revisions (only if the order has changed).
    $ordered_nids = array_column($entity->get('field_topic_content')->getValue(), 'target_id');
    $this->topicManager->reorderChildren($entity, $ordered_nids);

    // Resolves an issue that prevented the 'Topics' field from including a
    // newly created topic when adding child content via the moderation sidebar.
    $domain_source = $entity->get('field_domain_source')->getValue();
    $dept_id = $domain_source[0]['target_id'];
    Cache::invalidateTags(['topics_field:' . $dept_id]);
    Cache::invalidateTags([$dept_id . '_topics']);
  }

  /**
   * Entity update event handler.
   */
  public function onEntityUpdate(EntityEvent $event): void {
    /* @var ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->isTopic($entity) || !$entity->isDefaultRevision()) {
      return;
    }

    // Clear child node caches when a topic with a thin banner image is
    // published, as children inherit the banner.
    $original = $entity->original ?? NULL;
    if (!$entity->get('field_banner_image_thin')->isEmpty() && $entity->isPublished()
      && (!$original || !$original->isPublished())
    ) {
      $children = $this->topicManager->getTopicChildren($entity);
      $cache_tags = array_map(static fn ($nid): string => 'node:' . $nid, array_keys($children));
      Cache::invalidateTags($cache_tags);
    }
  }

  /**
   * Entity delete event handler.
   */
  public function onEntityDelete(EntityEvent $event): void {
    /* @var ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->isTopic($entity)) {
      return;
    }

    // Prevent deletion of topics if it has any active child content.
    // Adding this in addition to the frontend warning to provide coverage
    // when using the CLI (drush) etc.
    if ($this->topicManager->topicHasActiveChildren($entity)) {
      throw new Exception(UiMessages::deleteBlockedActiveChildren($entity->bundle(), $entity->label())->render());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::INSERT => ['onEntityInsertOrUpdate'],
      EntityEventType::UPDATE => [['onEntityInsertOrUpdate'], ['onEntityUpdate']],
      EntityEventType::DELETE => ['onEntityDelete', 100],
    ];
  }

  /**
   * Determine if an entity is a valid Topic type based on bundle ID.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   True if valid topic bundle, otherwise false.
   */
  protected function isTopic(EntityInterface $entity): bool {
    return $entity instanceof ContentEntityInterface && in_array($entity->bundle(), ['topic', 'subtopic']);
  }

}
