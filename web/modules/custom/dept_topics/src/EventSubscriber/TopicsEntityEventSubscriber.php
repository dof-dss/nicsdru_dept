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
 * Entity event subscriber for processing topic entities.
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
  public function onEntityInsertOrUpdate(EntityEvent $event): void {
    /* @var ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->isTopic($entity)) {
      return;
    }

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

  /**
   * Determine if an entity is a valid Topic type based on bundle ID.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   True if valid topic bundle, otherwise false.
   */
  protected function isTopic(ContentEntityInterface $entity): bool {
    return in_array($entity->bundle(), ['topic', 'subtopic']);
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

}
