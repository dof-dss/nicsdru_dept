<?php

namespace Drupal\dept_topics;

use Drupal\book\BookManagerInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Provides methods for managing Topic/Subtopic referenced (child) content.
 */
final class TopicManager {

  const int MAX_TRAVERSAL_DEPTH = 20;

  /**
   * Constructs a TopicManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityFieldManager $entityFieldManager
   *   The Entity Field Manager service.
   * @param \Drupal\Core\Entity\EntityDisplayRepository $entityDisplayRepository
   *   The Entity Display Repository service.
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The Book manager service.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The Book manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The Book manager service.
   * @param array $targetBundles
   *   Array of target bundles.
   *
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $connection,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityDisplayRepository $entityDisplayRepository,
    protected BookManagerInterface $bookManager,
    protected ModerationInformationInterface $moderationInformation,
    protected CacheBackendInterface $cache,
    protected array $targetBundles = [],
  ) {}

  /**
   * Returns parent nodes for the given node ID.
   *
   * @param \Drupal\node\NodeInterface|int $child_node
   *   Node or Node ID to return the parents for.
   *
   * @param array $parents
   *   Array of existing parent nodes.
   * @param int $depth
   *   The current traversal depth.
   *
   * @return array|mixed
   *   Node ID indexed array comprising id, title and type.
   */
  public function getParentNodes($child_node, &$parents = [], int $depth = 0) {

    if ($depth >= self::MAX_TRAVERSAL_DEPTH) {
      return $parents;
    }

    if ($child_node instanceof NodeInterface) {
      $nid = $child_node->id();
    }
    else {
      $nid = $child_node;
    }

    $child_nodes = $this->connection->query("SELECT n.nid, nfd.title, nfd.type FROM node n
        LEFT JOIN node_field_data nfd
        ON nfd.nid = n.nid
        LEFT JOIN node__field_topic_content ftc
        ON ftc.entity_id = n.nid
        WHERE ftc.field_topic_content_target_id = :nid", [':nid' => $nid])
      ->fetchAllAssoc('nid');

    foreach ($child_nodes as $parent) {
      // Skip already processed visited parents to avoid repeated queries.
      if (isset($parents[$parent->nid])) {
        continue;
      }
      $parents[$parent->nid] = $parent;
      $this->getParentNodes($parent->nid, $parents, $depth + 1);
    }

    return $parents;
  }

  /**
   * Returns a list of bundles that can be referenced from a topic or subtopic.
   *
   * @return array|mixed
   *   Array of bundle ID's.
   */
  public function getTopicChildNodeTypes() {
    if (empty($this->targetBundles)) {
      $bundle_fields = $this->entityFieldManager->getFieldDefinitions('node', 'topic');
      $field_definition = $bundle_fields['field_topic_content'];
      $this->targetBundles = $field_definition->getSetting('handler_settings')['target_bundles'];
    }

    return $this->targetBundles;
  }

  /**
   * Return true or false if the provided type is enabled as a topic child content option.
   *
   * @param mixed $type
   *   A node entity or bundle name.
   *
   * @return bool
   *   True if a topic child content type.
   */
  public function isValidTopicChild(mixed $type) {
    if ($type instanceof NodeInterface) {
      return in_array($type->bundle(), $this->getTopicChildNodeTypes());
    }

    return in_array($type, $this->getTopicChildNodeTypes());
  }

  /**
   * Returns a list of topics and subtopics for a department.
   *
   * @param string $department_id
   *   The department machine name.
   *
   * @return array
   *   Array of Topic/Subtopic nodes, indexed by node ID.
   */
  public function getTopicsForDepartment(string $department_id) {
    $dept_topics = $this->cache->get('dept_topics_' . $department_id);
    $node_storage = $this->entityTypeManager->getStorage('node');

    if (!empty($dept_topics)) {
      return $dept_topics->data;
    }

    $parent_nids = $node_storage->getQuery()
      ->condition('type', 'topic')
      ->condition('field_domain_source', $department_id)
      ->accessCheck(FALSE)
      ->execute();

    $subtopic_nids = $this->collectChildContentIds($parent_nids);
    $dept_topics = $node_storage->loadMultiple($parent_nids + $subtopic_nids);

    // This cache will be cleared by TopicsEntityEventSubscriber when a topic is added, updated or deleted.
    $this->cache->set('dept_topics_' . $department_id, $dept_topics, Cache::PERMANENT, [$department_id . '_topics']);

    return $dept_topics;
  }

  /**
   * Collects descendant child content node IDs below the given topic IDs.
   *
   * Traverses field_topic_content one level at a time with a direct query
   * against the field storage table, classifying each child by bundle in
   * SQL. Only subtopics are recursed into. This avoids loading every
   * referenced child entity (including non-subtopic content such as
   * articles) just to check its bundle, which is what a recursive
   * referencedEntities() walk would otherwise do.
   *
   * @param array $root_nids
   *   Topic/subtopic node IDs to collect child content below.
   * @param bool $subtopics_only
   *   TRUE to only collect subtopic descendants, FALSE to also collect
   *   non-subtopic content directly referenced by the given topics or any
   *   of their subtopic descendants.
   *
   * @return array
   *   Child content node IDs, keyed by node ID.
   */
  private function collectChildContentIds(array $root_nids, bool $subtopics_only = TRUE): array {
    $collected = [];
    $current_level = $root_nids;
    $depth = 0;

    while (!empty($current_level) && $depth < self::MAX_TRAVERSAL_DEPTH) {
      $query = $this->connection->select('node__field_topic_content', 'ftc');
      $query->join('node_field_data', 'nfd', 'nfd.nid = ftc.field_topic_content_target_id');
      $query->addField('ftc', 'field_topic_content_target_id', 'nid');
      $query->addField('nfd', 'type', 'bundle');
      $query->condition('ftc.entity_id', $current_level, 'IN');
      $query->distinct();

      $next_level = [];

      foreach ($query->execute() as $row) {
        $nid = (int) $row->nid;

        if ($row->bundle === 'subtopic') {
          if (!isset($collected[$nid])) {
            $next_level[] = $nid;
          }
          $collected[$nid] = $nid;
        }
        elseif (!$subtopics_only) {
          $collected[$nid] = $nid;
        }
      }

      $current_level = $next_level;
      $depth++;
    }

    return $collected;
  }

  /**
   * Returns an array of child nodes below a parent topic node.
   *
   * @param \Drupal\node\NodeInterface $topic
   *   The topic node.
   * @param bool $subtopics_only
   *   TRUE to only return the subtopics below the topic, FALSE to also
   *   return non-subtopic child content.
   *
   * @return array
   *   Child nodes below the given topic, indexed by node ID.
   */
  public function getTopicChildren(NodeInterface $topic, bool $subtopics_only = TRUE) {
    $child_nids = $this->collectChildContentIds([$topic->id()], $subtopics_only);
    return $child_nids ? $this->entityTypeManager->getStorage('node')->loadMultiple($child_nids) : [];
  }

  /**
   * Returns the maximum assignable topics permitted for the given node bundle.
   *
   * @param string|ContentEntityInterface $type
   *   A node type ID or content entity.
   * @return int
   *   The maximum amount.
   */
  public static function maximumTopicsForType(string|ContentEntityInterface $type) {
    if (empty($type)) {
      throw new \Exception('$type must not be empty');
    }

    if ($type instanceof ContentEntityInterface) {
      $type = $type->bundle();
    }

    return match($type) {
      'subtopic' => 1,
      default =>  3,
    };
  }

  /**
   * Adds or removes a child node from a topic node child content list.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $child
   *   The child node to add to a topic.
   */
  public function processChild(ContentEntityInterface $child) {
    if (!$this->isValidTopicChild($child)) {
      return;
    }

    // Fetch the chosen site topics for this child revision. This is the
    // desired state: the child should end up referenced only by these
    // topics, regardless of what it was referenced by in the past.
    $topic_nids = array_column($child->get('field_site_topics')->getValue(), 'target_id');

    $existing_nids = $this->fetchTopicsReferencingChild($child);

    // Compare the child's selected topics to our list of topic_content nids.
    $topics_added_ids = array_unique(array_diff($topic_nids, $existing_nids));
    $topics_removed_ids = array_unique(array_diff($existing_nids, $topic_nids));

    foreach ($topics_added_ids as $topic_id) {
      $topic = $this->entityTypeManager->getStorage('node')->load($topic_id);

      if (!empty($topic)) {
        $this->addChild($child, $topic);
      }
    }

    foreach ($topics_removed_ids as $topic_id) {
      $topic = $this->entityTypeManager->getStorage('node')->load($topic_id);

      if (!empty($topic)) {
        $this->removeChild($child, $topic);
      }
    }
  }

  /**
   * Adds a child node to the topic contents field of a topic.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $child
   *   The child to add to the topic.
   * @param \Drupal\Core\Entity\ContentEntityInterface $topic
   *   The topic the child will be added to.
   */
  public function addChild(ContentEntityInterface $child, ContentEntityInterface $topic) {
    // Reading the last delta and inserting the next one must not interleave
    // with another request adding a child to the same topic, otherwise both
    // compute the same delta and the second insert fails on the primary key.
    $transaction = $this->connection->startTransaction();
    try {
      $this->lockTopic($topic);

      // Published topic node contents.
      $children = $this->connection->select('node__field_topic_content', 'tc')
        ->fields('tc', ['delta', 'field_topic_content_target_id'])
        ->condition('entity_id', $topic->id())
        ->orderBy('delta', 'ASC')
        ->execute()
        ->fetchAllKeyed(1, 0);

      if (!array_key_exists($child->id(), $children)) {
        $delta = (empty($children)) ? 0 : end($children) + 1;
        $this->addChildDatabaseEntry($child, $topic, 'node__field_topic_content', $topic->getRevisionId(), $delta);
      }

      $topic_revisions = $this->getRevisionIds($topic);

      // Topic Revisions.
      foreach ($topic_revisions as $revision_id) {
        $revision_children = $this->connection->select('node_revision__field_topic_content', 'rtc')
          ->fields('rtc', ['revision_id', 'delta', 'field_topic_content_target_id'])
          ->condition('entity_id', $topic->id())
          ->condition('revision_id', $revision_id)
          ->orderBy('delta', 'ASC')
          ->execute()
          ->fetchAllKeyed(2, 1);

        if (!array_key_exists($child->id(), $revision_children)) {
          $delta = (empty($revision_children)) ? 0 : end($revision_children) + 1;
          $this->addChildDatabaseEntry($child, $topic, 'node_revision__field_topic_content', $revision_id, $delta);
        }
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }

    // Commit the transaction (or release the savepoint when nested).
    unset($transaction);

    $this->clearCache($child, $topic);
  }

  /**
   * Takes an exclusive row lock on the topic for the current transaction.
   *
   * Locks the topic's row in the node base table with SELECT ... FOR UPDATE.
   * That row always exists, so the lock works even for a topic with no
   * children yet, where locking the topic content rows would lock nothing
   * under READ COMMITTED. A concurrent request taking the same lock blocks
   * until the holding transaction commits or rolls back.
   *
   * Must be called inside a transaction.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $topic
   *   The topic node to lock.
   */
  protected function lockTopic(ContentEntityInterface $topic): void {
    $this->connection->select('node', 'n')
      ->fields('n', ['nid'])
      ->condition('nid', $topic->id())
      ->forUpdate()
      ->execute();
  }

  /**
   * Returns a list of topics/subtopic that reference the given child node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $child
   *   The child node to fetch the references for.
   *
   * @return array
   *   List of entity ID's.
   */
  protected function fetchTopicsReferencingChild(ContentEntityInterface $child) {
    $existing_topics = $this->connection->select('node__field_topic_content', 'tc')
      ->fields('tc', ['entity_id'])
      ->condition('field_topic_content_target_id', $child->id())
      ->distinct()
      ->execute()
      ->fetchCol();

    $existing_topics_revisions = $this->connection->select('node_revision__field_topic_content', 'tc')
      ->fields('tc', ['entity_id'])
      ->condition('field_topic_content_target_id', $child->id())
      ->distinct()
      ->execute()
      ->fetchCol();

    // Create a list of all topic nids (active and revisions) for this child from the results of both the topic_contents tables.
    return array_unique(array_merge($existing_topics, $existing_topics_revisions));
  }

  /**
   * Removes all topic contents reference records for the given child.
   *
   * Uses both the topics currently selected on the child and any topics that
   * still reference it, so a topic deselected in the same save as the archive
   * does not keep a stale reference.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $child
   *   The child to archive.
   */
  public function archiveChild(ContentEntityInterface $child) {
    $selected_nids = array_column($child->get('field_site_topics')->getValue(), 'target_id');
    $topic_nids = array_unique(array_merge($selected_nids, $this->fetchTopicsReferencingChild($child)));

    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($topic_nids) as $topic) {
      $this->removeChild($child, $topic);
    }
  }

  /**
   * Remove a child node from the topic contents field of a topic.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $child
   *   The child to remove.
   * @param \Drupal\Core\Entity\ContentEntityInterface $topic
   *   The topic the child is removed from.
   */
  public function removeChild(ContentEntityInterface $child, ContentEntityInterface $topic) {
    $this->connection->delete('node__field_topic_content')
      ->condition('field_topic_content_target_id', $child->id())
      ->condition('entity_id', $topic->id())
      ->execute();

    $this->connection->delete('node_revision__field_topic_content')
      ->condition('field_topic_content_target_id', $child->id())
      ->condition('entity_id', $topic->id())
      ->execute();

    $this->clearCache($child, $topic);
  }

  /**
   * Stores the display order of child content nids of a topic.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $topic
   *   The topic node whose child order is being updated.
   * @param array $ordered_child_nids
   *   The child node IDs, in their new display order.
   */
  public function reorderChildren(ContentEntityInterface $topic, array $ordered_child_nids): void {
    $ordered_child_nids = array_values($ordered_child_nids);

    // Fetch the current child content order to see if we need to update.
    $current_nids = $this->connection->select('node__field_topic_content', 'tc')
      ->fields('tc', ['field_topic_content_target_id'])
      ->condition('entity_id', $topic->id())
      ->orderBy('delta', 'ASC')
      ->execute()
      ->fetchCol();

    // Type safe comparison of the current vs provided child content order.
    if (array_map('strval', $current_nids) === array_map('strval', $ordered_child_nids)) {
      return;
    }

    // Replace the rows atomically so a failure cannot leave the topic with
    // no (or partial) child content.
    $transaction = $this->connection->startTransaction();
    try {
      // Serialise with addChild() so a child added mid-reorder is not lost.
      $this->lockTopic($topic);

      // Default (current) revision table.
      $this->connection->delete('node__field_topic_content')
        ->condition('entity_id', $topic->id())
        ->execute();

      // Published topic node contents.
      foreach ($ordered_child_nids as $delta => $child_nid) {
        $this->connection->insert('node__field_topic_content')
          ->fields([
            'bundle' => $topic->bundle(),
            'deleted' => 0,
            'entity_id' => $topic->id(),
            'revision_id' => $topic->getRevisionId(),
            'langcode' => 'en',
            'delta' => $delta,
            'field_topic_content_target_id' => $child_nid,
          ])
          ->execute();
      }

      $topic_revisions = $this->getRevisionIds($topic);

      // Topic Revisions.
      foreach ($topic_revisions as $revision_id) {
        $this->connection->delete('node_revision__field_topic_content')
          ->condition('entity_id', $topic->id())
          ->condition('revision_id', $revision_id)
          ->execute();

        foreach ($ordered_child_nids as $delta => $child_nid) {
          $this->connection->insert('node_revision__field_topic_content')
            ->fields([
              'bundle' => $topic->bundle(),
              'deleted' => 0,
              'entity_id' => $topic->id(),
              'revision_id' => $revision_id,
              'langcode' => 'en',
              'delta' => $delta,
              'field_topic_content_target_id' => $child_nid,
            ])
            ->execute();
        }
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }

    // Commit the transaction.
    unset($transaction);

    $this->clearCache(NULL, $topic);
  }

  /**
   * Determines if a given topic has child nodes that are
   * in 'published', 'draft' or 'needs review' states.
   *
   * @param \Drupal\node\NodeInterface|\Drupal\Core\Entity\ContentEntityInterface $topic
   *   The topic node to check for active children.
   *
   * @return bool
   *   True if there are artive children, false otherwise.
   */
  public function topicHasActiveChildren(NodeInterface|ContentEntityInterface $topic): bool {
    $children = $topic->get('field_topic_content')->referencedEntities();
    $node_storage = $this->entityTypeManager->getStorage('node');

    foreach ($children as $child) {
      $latest_revision_id = $node_storage->getLatestRevisionId($child->id());
      $latest_revision = $node_storage->loadRevision($latest_revision_id);

      if ($latest_revision->get('moderation_state')->getString() !== 'archived') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns the number of nodes referencing a topic, excluding topic child types.
   *
   * Archived nodes are not counted. A node without a moderation state has
   * never been through the workflow, so cannot have been archived.
   *
   * @param \Drupal\node\NodeInterface|\Drupal\Core\Entity\ContentEntityInterface $topic
   *   The topic or subtopic node.
   *
   * @return int
   *   The number of non-archived nodes referencing the given topic in
   *   field_site_topics.
   *
   * @see \Drupal\dept_topics\TopicManager::getTopicChildNodeTypes()
   */
  public function countReferencingNodes(NodeInterface|ContentEntityInterface $topic): int {
    $topic_child_types = array_keys($this->getTopicChildNodeTypes());

    // The moderation state cannot be used in a node entity query, so join to
    // the moderation state of the default revision of each node.
    $query = $this->connection->select('node__field_site_topics', 'topics');
    $query->join('node_field_data', 'node', 'node.nid = topics.entity_id AND node.vid = topics.revision_id');
    $query->leftJoin(
      'content_moderation_state_field_data',
      'moderation',
      'moderation.content_entity_type_id = :entity_type AND moderation.content_entity_id = node.nid AND moderation.content_entity_revision_id = node.vid AND moderation.langcode = node.langcode',
      [':entity_type' => 'node'],
    );
    $query->condition('topics.field_site_topics_target_id', $topic->id());
    $query->condition('topics.deleted', 0);

    if (!empty($topic_child_types)) {
      $query->condition('node.type', $topic_child_types, 'NOT IN');
    }

    $archived = $query->orConditionGroup()
      ->isNull('moderation.moderation_state')
      ->condition('moderation.moderation_state', 'archived', '<>');
    $query->condition($archived);

    $query->addExpression('COUNT(DISTINCT node.nid)', 'total');

    return (int) $query->execute()->fetchField();
  }

  /**
   * Inserts an entity reference value for a given child and topic.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $child
   *   The child node (target) to add.
   * @param \Drupal\Core\Entity\ContentEntityInterface $topic
   *   The topic node to add the entity reference to.
   * @param string $table
   *   The entity reference database table.
   * @param string|int $revision_id
   *   The revision ID to inert the reference for.
   * @param int $delta
   *   The position in the entity reference list.
   */
  protected function addChildDatabaseEntry(ContentEntityInterface $child, ContentEntityInterface $topic, string $table, string|int $revision_id, int $delta = 0) {
    $this->connection->insert($table)
      ->fields([
        'bundle' => $topic->bundle(),
        'deleted' => 0,
        'entity_id' => $topic->id(),
        'revision_id' => $revision_id,
        'langcode' => 'en',
        'delta' => $delta,
        'field_topic_content_target_id' => $child->id(),
      ])
      ->execute();
  }

  /**
   *
   * Clear the cache for given child and topic nodes.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $child
   *   The child node to clear cache, or NULL if the change isn't specific
   *   to a single child (e.g. reordering).
   * @param \Drupal\Core\Entity\ContentEntityInterface $topic
   *   The topic node to clear cache.
   */
  protected function clearCache(?ContentEntityInterface $child, ContentEntityInterface $topic) {
    $tags = ['node:' . $topic->id()];

    if ($child !== NULL && !empty($child->id())) {
      $tags[] = 'node:' . $child->id();
    }

    // We need to reset the cache for new child content to display on cached topics.
    // It is not enough to just invalidate the topic node tag.
    $this->entityTypeManager->getStorage('node')->resetCache([$topic->id()]);
    Cache::invalidateTags($tags);
  }

  /**
   * Gets all revision IDs for the given topic node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $topic
   *   The topic node.
   *
   * @return int[]
   *   The revision IDs, in ascending order.
   */
  protected function getRevisionIds(ContentEntityInterface $topic): array {
    $revision_ids = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition('nid', $topic->id())
      ->sort('vid')
      ->execute();

    return array_map('intval', array_keys($revision_ids));
  }

}
