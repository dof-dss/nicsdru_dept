<?php

declare(strict_types=1);

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\book\BookManagerInterface;
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

    if ($entity->bundle() === 'topic' || $entity->bundle() === 'subtopic') {
      $moderation_state = $entity->get('moderation_state')->getString();

      if ($moderation_state === 'published' || $moderation_state === 'archived') {

        // Remove any orphaned content that is assigned to a new published topic.
        if ($entity->isNew() && $moderation_state === 'published') {
          $topic_content = array_column($entity->get('field_topic_content')->getValue(), 'target_id');
          $this->orphanManager->processTopicContents($topic_content);
          return;
        }

        // Add or remove site topic tags to nodes that are added or removed from topic child contents.
        // @phpstan-ignore-next-line
        $original = array_column($entity->original->get('field_topic_content')->getValue(), 'target_id');
        // @phpstan-ignore-next-line
        $updated = array_column($entity->get('field_topic_content')->getValue(), 'target_id');

        $removed = array_diff($original, $updated);
        $added = array_diff($updated, $original);

        if ($removed) {
          foreach ($removed as $nid) {

            // Do not remove site topics from the node if it is a book page.
            if ($this->bookManager->loadBookLink($nid) === TRUE) {
              continue;
            }

            $child_node = $this->entityTypeManager->getStorage('node')->load($nid);

            if (!empty($child_node)) {
              $child_topics = $child_node->get('field_site_topics');

              for ($i = 0; $i < $child_topics->count(); $i++) {
                // @phpstan-ignore-next-line
                if ($child_topics->get($i)->target_id == $entity->id()) {
                  $child_topics->removeItem($i);
                  $i--;
                }
              }
              $child_node->save();

              if ($child_topics->count() == 0) {
                $this->orphanManager->addOrphan($child_node, $entity);
              }
            }
          }
        }

        if ($added) {
          foreach ($added as $nid) {
            $child_node = $this->entityTypeManager->getStorage('node')->load($nid);
            if (!empty($child_node)) {
              $child_topic_tags = array_column($child_node->get('field_site_topics')->getValue(), 'target_id');

              if (!in_array($entity->id(), $child_topic_tags)) {
                $child_node->get('field_site_topics')->appendItem([
                  'target_id' => $entity->id()
                ]);
                $child_node->save();
                $this->orphanManager->removeOrphan($child_node);
              }
            }
          }
        }
      }
    }

  }

  /**
   * Entity insert event handler.
   */
  public function onEntityInsert(EntityEvent $event): void {
  }

  /**
   * Entity update event handler.
   */
  public function onEntityUpdate(EntityEvent $event): void {
  }

  /**
   * Entity delete event handler.
   */
  public function onEntityDelete(EntityEvent $event): void {
    $entity = $event->getEntity();

    // Cleanup in orphan data for this node.
    if (in_array($entity->bundle(), $this->topicManager->getTopicChildNodeTypes())) {
      $this->orphanManager->removeOrphan($entity);
    }

    if ($entity->bundle() === 'topic' || $entity->bundle() === 'subtopic') {
      // Process orphaned.
      $child_contents = array_column($entity->get('field_topic_content')->getValue(), 'target_id');

      foreach ($child_contents as $child_id) {
        $child_node = $this->entityTypeManager->getStorage('node')->load($child_id);
        $child_topics = $child_node->get('field_site_topics');

        for ($i = 0; $i < $child_topics->count(); $i++) {
          // @phpstan-ignore-next-line
          if ($child_topics->get($i)->target_id == $entity->id()) {
            $child_topics->removeItem($i);
            $i--;
          }
        }
        $child_node->save();

        if ($child_topics->count() == 0) {
          $this->orphanManager->addOrphan($child_node, $entity);
        }
      }

      // Cleanup in orphan data for this node.
      $this->orphanManager->removeOrphan($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [];
//    return [
//      EntityEventType::PRESAVE => ['onEntityPresave'],
//      EntityEventType::INSERT => ['onEntityInsert'],
//      EntityEventType::UPDATE => ['onEntityUpdate'],
//      EntityEventType::DELETE => ['onEntityDelete'],
//    ];
  }

}
