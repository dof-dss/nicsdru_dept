<?php

namespace Drupal\dept_topics;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\book\BookManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Provides methods for managing Sub/Topic referenced (child) content.
 */
final class TopicManager {
  const int MAX_TRAVERSAL_DEPTH = 20;

  /**
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

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
   * @param array $targetBundles
   *   Array of target bundles.
   * @param array $deptTopics
   *   Array of department topics used around this class.
   *
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $connection,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityDisplayRepository $entityDisplayRepository,
    protected BookManagerInterface $bookManager,
    protected array $targetBundles = [],
    protected array $deptTopics = [],
  ) {
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
  }

  /**
   * Returns parent nodes for the given node ID.
   *
   * @param \Drupal\node\NodeInterface|int $node
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
  public function getParentNodes($node, &$parents = [], int $depth = 0) {

    if ($depth >= self::MAX_TRAVERSAL_DEPTH) {
      return $parents;
    }

    if ($node instanceof NodeInterface) {
      $nid = $node->id();
    }
    else {
      $nid = $node;
    }

    $nodes = $this->connection->query("SELECT n.nid, nfd.title, nfd.type FROM node n
        LEFT JOIN node_field_data nfd
        ON nfd.nid = n.nid
        LEFT JOIN node__field_topic_content ftc
        ON ftc.entity_id = n.nid
        WHERE ftc.field_topic_content_target_id = :nid", [':nid' => $nid])
      ->fetchAllAssoc('nid');

    if ($nodes === NULL) {
      return $parents;
    }

    foreach ($nodes as $node) {
      $parents[$node->nid] = $node;
      $this->getParentNodes($node->nid, $parents, ++$depth);
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
    // TODO: replace with injected property.
    $dept_topics = \Drupal::cache()->get('dept_topics_' . $department_id);

    if (!empty($dept_topics)) {
      return $dept_topics->data;
    }
    else {
      $parent_topics = $this->entityTypeManager->getStorage('node')
        ->loadByProperties([
          'type' => 'topic',
          'field_domain_source' => $department_id,
        ]);

      foreach ($parent_topics as $nid => $parent) {
        $this->deptTopics[$parent->id()] = $parent;
        $this->getChildTopics($parent);
      }

      // TODO: replace with injected property.
      \Drupal::cache()
        ->set('dept_topics_' . $department_id, $this->deptTopics, Cache::PERMANENT, [$department_id . '_topics']);

      return $this->deptTopics;
    }
  }

  /**
   * Update the topics property with a list of child nodes.
   *
   * @param \Drupal\node\NodeInterface $topic
   *   The topic/subtopic node to extract child subtopics from.
   * @param int $depth
   *   The current traversal depth.
   *
   */
  private function getChildTopics(NodeInterface $topic, int $depth = 0) {
    if ($depth >= self::MAX_TRAVERSAL_DEPTH) {
      return;
    }

    $child_content = $topic->get('field_topic_content')->referencedEntities();

    foreach ($child_content as $child) {
      if ($child->bundle() === 'subtopic') {
        $this->deptTopics[$child->id()] = $child;
        $this->getChildTopics($child, ++$depth);
      }
    }
  }

  /**
   * Public service function to return an array of topic ids
   * from a parent topic node.
   *
   * More or less a convenience wrapper around the private
   * function getChildTopics().
   *
   * @param \Drupal\node\NodeInterface $topic
   *   The topic node.
   *
   * @return array
   *   Structured array with the hierarchy of topic ids below
   *   the parent topic passed in as an input parameter.
   */
  public function getTopicChildren(NodeInterface $topic) {
    $this->getChildTopics($topic);
    return $this->deptTopics;
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
   * Adds a child node to a topic node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $child
   *   The child node to add to a topic.
   */
  public function processChild(ContentEntityInterface $child) {
    if (!$this->isValidTopicChild($child)) {
      return;
    }

    $topic_nids = $child->get('field_site_topics')->getValue();
    $topic_nids = array_column($topic_nids, 'target_id');

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

    $existing_nids = array_unique(array_merge($existing_topics, $existing_topics_revisions));

    $topics_added_ids = array_diff($topic_nids, $existing_nids);
    $topics_removed_ids = array_diff($existing_nids, $topic_nids);

    foreach ($topics_added_ids as $topic_id) {
      $topic = $this->entityTypeManager->getStorage('node')->load($topic_id);
      $this->addChild($child, $topic);
    }

    foreach ($topics_removed_ids as $topic_id) {
      $topic = $this->entityTypeManager->getStorage('node')->load($topic_id);
      $this->removeChild($child, $topic);
    }
  }

  /**
   * Removes all topic contents reference records for the given child.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $child
   *   The child to archive.
   */
  public function archiveChild(ContentEntityInterface $child) {
    $topics = $child->get('field_site_topics')->referencedEntities();

    foreach ($topics as $topic) {
      $this->removeChild($child, $topic);
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

    $topic_revisions = $this->entityTypeManager->getStorage('node')->revisionIds($topic);

    // Topic Revisions.
    foreach ($topic_revisions as $revision_id) {
      $revision_children = $this->connection->select('node_revision__field_topic_content', 'rtc')
        ->fields('rtc', ['revision_id', 'delta', 'field_topic_content_target_id'])
        ->condition('entity_id', $topic->id())
        ->condition('revision_id', $revision_id)
        ->orderBy('delta', 'ASC')
        ->execute()
        ->fetchAll();

      if (!array_key_exists($child->id(), $revision_children)) {
        $delta = (empty($revision_children)) ? 0 : end($revision_children)->delta + 1;
        $this->addChildDatabaseEntry($child, $topic, 'node_revision__field_topic_content', $revision_id, $delta);
      }
    }

    $this->clearCache($child, $topic);
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
   * Inserts an entity reference value for a given child and topic
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
   * @param \Drupal\Core\Entity\ContentEntityInterface $child
   *   The child node to clear cache.
   * @param \Drupal\Core\Entity\ContentEntityInterface $topic
   *   The topic node to clear cache.
   */
  protected function clearCache(ContentEntityInterface $child, ContentEntityInterface $topic) {
    $tags = ['node:' . $topic->id()];

    if (empty($child->id())) {
      $tags[] = 'node:' . $child->id();
    }

    // We need to reset the cache for new child content to display on cached topics.
    // It is not enough to just invalidate the topic node tag.
    $this->entityTypeManager->getStorage('node')->resetCache([$topic->id()]);
    Cache::invalidateTags($tags);
  }

}
