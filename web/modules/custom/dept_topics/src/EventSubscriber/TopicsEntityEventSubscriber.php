<?php

declare(strict_types=1);

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dept_topics\OrphanManager;
use Drupal\dept_topics\TopicContentAction;
use Drupal\dept_topics\TopicManager;
use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\node\NodeInterface;
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
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::INSERT => ['onEntityInsert', 10], ['purgeTopicCaches', 0],
      EntityEventType::UPDATE => ['onEntityUpdate', 10], ['purgeTopicCaches', 0],
      EntityEventType::DELETE => ['onEntityDelete', 10], ['purgeTopicCaches', 0],
    ];
  }

  /**
   * Entity insert event handler.
   */
  public function onEntityInsert(EntityEvent $event): void {
    $entity = $event->getEntity();

    // Only process node entities.
    if (!$entity instanceof NodeInterface) {
      return;
    }

    // PROCESS TOPIC/SUBTOPIC.
    if ($entity->bundle() === 'topic' || $entity->bundle() === 'subtopic') {
      $moderation_state = $entity->get('moderation_state')->getString();

      // For new published topics we must iterate each child node adding the
      // topic to the field_site_topics field and then save each node.
      if ($moderation_state === 'published') {
        $child_nids = array_column($entity->get('field_topic_content')
          ->getValue(), 'target_id');
        $child_nodes = $this->entityTypeManager->getStorage('node')
          ->loadMultiple($child_nids);

        foreach ($child_nodes as $child) {
          $child->get('field_site_topics')->appendItem([
            'target_id' => $entity->id()
          ]);
          $child->setRevisionLogMessage('Added site topic: (' . $entity->id() . ') ' . $entity->label());
          $child->save();
        }

        // Cleanup any orphaned entries for child nodes added to this topic.
        $this->orphanManager->processTopicContents($child_nids);

      }
    }

    // PROCESS CHILD TYPES TO TOPICS.
    if (in_array($entity->bundle(), $this->topicManager->getTopicChildNodeTypes())) {
      $moderation_state = $entity->get('moderation_state')->getString();

      // When a topic child content node is created we load each topic selected
      // in the field_site_topics field and add the node to that topic's
      // field_child_content field.
      if ($moderation_state === 'published') {
        $site_topic_ids = array_column($entity->get('field_site_topics')
          ->getValue(), 'target_id');

        foreach ($site_topic_ids as $site_topic_nid) {
          $topic = $this->entityTypeManager->getStorage('node')->load($site_topic_nid);
          if (!empty($topic)) {
            $topic->get('field_topic_content')->appendItem([
              'target_id' => $entity->id(),
            ]);

            $topic->setRevisionLogMessage('Added content: (' . $entity->id() . ') ' . $entity->label());
            $topic->save();
          }
        }
      }
    }
  }

  /**
   * Entity update event handler.
   */
  public function onEntityUpdate(EntityEvent $event): void {
    $entity = $event->getEntity();

    // Only process node entities.
    if (!$entity instanceof NodeInterface) {
      return;
    }

    // PROCESS TOPIC/SUBTOPIC.
    if ($entity->bundle() === 'topic' || $entity->bundle() === 'subtopic') {

      // We handle new topic child content with the onEntityInsert function.
      if ($entity->isNew()) {
        return;
      }

      $moderation_state = $entity->get('moderation_state')->getString();

      // When a topic is updated we must process any child content that has been
      // added or removed to update their field_site_topics field and process
      // any orphaned status.
      if ($moderation_state === 'published') {
        $topic_content_children = array_column($entity->get('field_topic_content')->getValue(), 'target_id');
        $nodes_referencing_topic = $this->topicManager->getNodesReferencingTopic($entity);

        $added = array_diff($topic_content_children, $nodes_referencing_topic);
        $removed = array_diff($nodes_referencing_topic, $topic_content_children);

        // ADDED CHILD ENTRIES.
        //
        // Add this topic to the field_site_topics field of each child
        // content node and remove any orphaned status.
        foreach ($added as $child_nid) {
          $child = $this->entityTypeManager->getStorage('node')->load($child_nid);
          if (!empty($child)) {
            $child_topic_tags = array_column($child->get('field_site_topics')
              ->getValue(), 'target_id');

            if (!in_array($entity->id(), $child_topic_tags)) {
              $child->get('field_site_topics')->appendItem([
                'target_id' => $entity->id(),
              ]);

              $child->setRevisionLogMessage('Added to topic: (' . $entity->id() . ') ' . $entity->label());
              $child->save();

              $this->orphanManager->removeOrphan($child);
            }
          }
        }

        // REMOVED CHILD ENTRIES.
        //
        // Remove this topic from the field_site_topics field of each removed
        // child content node and orphan the node if it has no site topics.
        foreach ($removed as $child_nid) {

          // Do not remove site topics from the node if it is a book page.
          if ($this->bookManager->loadBookLink($child_nid) === TRUE) {
            continue;
          }

          $child = $this->entityTypeManager->getStorage('node')->load($child_nid);
          if (!empty($child)) {
            $child_topic_tags = $child->get('field_site_topics');

            for ($i = 0; $i < $child_topic_tags->count(); $i++) {
              // @phpstan-ignore-next-line
              if ($child_topic_tags->get($i)->target_id == $entity->id()) {
                $child_topic_tags->removeItem($i);
                $i--;
              }
            }
            $child->setRevisionLogMessage('Removed from topic: (' . $entity->id() . ') ' . $entity->label());
            $child->save();

            if ($child_topic_tags->count() == 0) {
              $this->orphanManager->addOrphan($child, $entity);
            }
          }
        }
      }
      // We don't want the 'PROCESS CHILD TYPES' to process any subtopics (a child type)
      // from this point on.
      return;
    }

    // PROCESS CHILD TYPES TO TOPICS.
    if (in_array($entity->bundle(), $this->topicManager->getTopicChildNodeTypes())) {
      $moderation_state = $entity->get('moderation_state')->getString();

      // When a topic is updated we must process any child content that has been
      // added or removed to update their field_site_topics field and process
      // any orphaned status.
      if ($moderation_state === 'published') {
        $site_topic_ids = array_column($entity->get('field_site_topics')
          ->getValue(), 'target_id');
        $parent_ids = array_keys($this->topicManager->getParentNodes($entity));

        $added = array_diff($site_topic_ids, $parent_ids);
        $removed = array_diff($parent_ids, $site_topic_ids);

        // ADDED TOPIC ENTRIES.
        //
        // Add this node to the field_topic_contents field of each new site
        // topic entry.
        foreach ($added as $site_topic_nid) {
          $topic = $this->entityTypeManager->getStorage('node')->load($site_topic_nid);
          if (!empty($topic)) {
            $topic_child_contents_ids = array_column($topic->get('field_topic_content')
              ->getValue(), 'target_id');

            if (!in_array($site_topic_nid, $topic_child_contents_ids)) {
              $topic->get('field_topic_content')->appendItem([
                'target_id' => $entity->id(),
              ]);
              $topic->setRevisionLogMessage('Added content: (' . $entity->id() . ') ' . $entity->label());
              $topic->save();
            }
          }
        }

        // REMOVED TOPIC ENTRIES.
        //
        // Remove this node to the field_topic_contents field of each removed
        // site topic entry.
        foreach ($removed as $site_topic_nid) {
          $topic = $this->entityTypeManager->getStorage('node')->load($site_topic_nid);
          if (!empty($topic)) {
            $topic_child_contents = $topic->get('field_topic_content');

            for ($i = 0; $i < $topic_child_contents->count(); $i++) {
              // @phpstan-ignore-next-line
              if ($topic_child_contents->get($i)->target_id == $entity->id()) {
                $topic_child_contents->removeItem($i);
                $topic->setRevisionLogMessage('Removed content: (' . $entity->id() . ') ' . $entity->label());
                $topic->save();
                break;
              }
            }
          }
        }
      }
    }

  }

  /**
   * Handles deletion of topic and topic child content nodes.
   */
  public function onEntityDelete(EntityEvent $event): void {
    $entity = $event->getEntity();

    // Only process node entities.
    if (!$entity instanceof NodeInterface) {
      return;
    }

    // PROCESS TOPIC/SUBTOPIC
    //
    // If we delete a topic we must iterate each child node and remove the topic
    // from field_site_topics and save the node. If the child node has no
    // entries in the site topics field we will record it as orphaned.
    if ($entity->bundle() === 'topic' || $entity->bundle() === 'subtopic') {
      $child_nids = array_column($entity->get('field_topic_content')->getValue(), 'target_id');
      $child_nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($child_nids);

      foreach ($child_nodes as $child_node) {
        $child_topics = $child_node->get('field_site_topics');

        // Remove the topic entry from field_site_topics.
        for ($i = 0; $i < $child_topics->count(); $i++) {
          // @phpstan-ignore-next-line
          if ($child_topics->get($i)->target_id == $entity->id()) {
            $child_topics->removeItem($i);
            $i--;
          }
        }
        $child_node->setRevisionLogMessage('Topic/subtopic deleted: (' . $entity->id() . ') ' . $entity->label());
        $child_node->save();

        // If the child doesn't have any topics assigned, orphan it.
        if ($child_topics->count() == 0) {
          $this->orphanManager->addOrphan($child_node, $entity);
        }
      }

      // Cleanup any orphan content entry for this topic.
      $this->orphanManager->removeOrphan($entity);
    }

    // PROCESS TOPIC CHILD CONTENT NODES
    //
    // If we delete a topic child node type we need to remove the child node
    // entry in the field_topic_content field of its parent topics/subtopics
    // and save those topics.
    if (in_array($entity->bundle(), $this->topicManager->getTopicChildNodeTypes())) {
      $parents = $this->topicManager->getParentNodes($entity->id());

      // Iterate this nodes parents and remove any references to it.
      foreach ($parents as $parent => $data) {
        $topic_node = $this->entityTypeManager->getStorage('node')->load($parent);

        // Flag to prevent us creating a revision for topics that don't
        // reference this content.
        $has_child_entry = FALSE;

        $child_refs = $topic_node->get('field_topic_content');

        for ($i = 0; $i < $child_refs->count(); $i++) {
          // @phpstan-ignore-next-line
          if ($child_refs->get($i)->target_id == $entity->id()) {
            $child_refs->removeItem($i);
            $has_child_entry = TRUE;
            $i--;
          }
        }

        // Save the topic with the updated child content reference list.
        if ($has_child_entry) {
          $topic_node->setRevisionLogMessage('Removed child: (' . $entity->id() . ') ' . $entity->label());
          $topic_node->save();
        }
      }

      // Cleanup any orphan content entry for this topic child content type.
      $this->orphanManager->removeOrphan($entity);
    }
  }

  /**
   * Clears caches for topic child contents and topic hierarchy.
   *
   * @param \Drupal\entity_events\Event\EntityEvent $event
   *   The entity event object.
   */
  public function purgeTopicCaches(EntityEvent $event) {
    $entity = $event->getEntity();

    // Only process node entities.
    if (!$entity instanceof NodeInterface) {
      return;
    }

    if ($entity->bundle() === 'topic' || $entity->bundle() === 'subtopic') {
      // Clear the node cache for all the child nodes belonging to a topic.
      $cache_tags = array_column($entity->get('field_topic_content')->getValue(), 'target_id');

      // Prepend the core 'node:' cache tag to each child nid.
      array_walk($cache_tags, function (&$value, $key) {
        $value = 'node:' . $value;
      });

      // Clear the topic hierarchy cache entries for the department.
      // See: topicManager->getTopicsForDepartment().
      $domain_source = $entity->get('field_domain_source')->getValue();
      $department_id = $domain_source[0]['target_id'];
      $cache_tags[] = $department_id . '_topics';

      Cache::invalidateTags($cache_tags);
    }
  }

}
