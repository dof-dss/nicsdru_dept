<?php

declare(strict_types=1);

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dept_topics\TopicManager;
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
    private readonly BookManagerInterface $bookManager,
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

    // Update the order of child content on entity save.
    // TODO: Only update if the child order has changed.
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
      throw new Exception(t("This @bundle '%title' cannot be deleted until all child pages have been reallocated to a different topic, archived or deleted.",
        [
          '@bundle' => $entity->bundle(),
          '%title' => $entity->label(),
        ])->render());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::INSERT => ['onEntityInsertOrUpdate'],
      EntityEventType::UPDATE => ['onEntityInsertOrUpdate'],
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
