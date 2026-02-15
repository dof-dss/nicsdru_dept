<?php

namespace Drupal\dept_topics;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\book\BookManagerInterface;
use Drupal\node\NodeInterface;
use function PHPUnit\Framework\isInstanceOf;

/**
 * Provides methods for managing Sub/Topic referenced (child) content.
 */
final class TopicManager {

  public const int MAX_TRAVERSAL_DEPTH = 20;

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
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The Entity Field Manager service.
   * @param \Drupal\Core\Entity\EntityDisplayRepository $entityDisplayRepository
   *   The Entity Display Repository service.
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The Book manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache backend for dept topics.
   * @param array $targetBundles
   *   Array of target bundles.
   * @param array $deptTopics
   *   Array of department topics used around this class.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $connection,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityDisplayRepository $entityDisplayRepository,
    protected BookManagerInterface $bookManager,
    protected CacheBackendInterface $cache,
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
   * @param array $parents
   *   Array of existing parent nodes.
   * @param int $depth
   *   The current traversal depth.
   *
   * @return array
   *   Node ID indexed array comprising id, title and type.
   */
  public function getParentNodes($node, &$parents = [], int $depth = 0): array {
    if ($depth >= self::MAX_TRAVERSAL_DEPTH) {
      return $parents;
    }

    $nid = $node instanceof NodeInterface ? $node->id() : (int) $node;

    $nodes = $this->connection->query(
      "SELECT n.nid, nfd.title, nfd.type
       FROM node n
       LEFT JOIN node_field_data nfd ON nfd.nid = n.nid
       LEFT JOIN node__field_topic_content ftc ON ftc.entity_id = n.nid
       WHERE ftc.field_topic_content_target_id = :nid",
      [':nid' => $nid]
    )->fetchAllAssoc('nid');

    if (empty($nodes)) {
      return $parents;
    }

    foreach ($nodes as $row) {
      $parents[$row->nid] = $row;
      $this->getParentNodes((int) $row->nid, $parents, $depth + 1);
    }

    return $parents;
  }

  /**
   * Returns a list of bundles that can be referenced from a topic or subtopic.
   */
  public function getTopicChildNodeTypes(): array {
    if (empty($this->targetBundles)) {
      $bundle_fields = $this->entityFieldManager->getFieldDefinitions('node', 'topic');
      $field_definition = $bundle_fields['field_topic_content'];
      $this->targetBundles = $field_definition->getSetting('handler_settings')['target_bundles'] ?? [];
    }

    return $this->targetBundles;
  }

  /**
   * Return true if the provided type is enabled as a topic child content option.
   */
  public function isValidTopicChild(mixed $type): bool {
    if ($type instanceof EntityInterface) {
      return in_array($type->bundle(), $this->getTopicChildNodeTypes(), TRUE);
    }

    return in_array((string) $type, $this->getTopicChildNodeTypes(), TRUE);
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
  public function getTopicsForDepartment(string $department_id): array {
    $cid = 'dept_topics_' . $department_id;

    if ($cache_item = $this->cache->get($cid)) {
      return $cache_item->data;
    }

    $parent_topics = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'topic',
      'field_domain_source' => $department_id,
    ]);

    foreach ($parent_topics as $parent) {
      $this->deptTopics[$parent->id()] = $parent;
      $this->getChildTopics($parent);
    }

    $this->cache->set(
      $cid,
      $this->deptTopics,
      Cache::PERMANENT,
      [$department_id . '_topics']
    );

    return $this->deptTopics;
  }

  /**
   * Add/remove an entity to topic child content lists based on Site Topic field.
   */
  public function updateChildDisplayOnTopics(EntityInterface $entity): void {
    if (!$entity->hasField('field_site_topics') || !$this->isValidTopicChild($entity)) {
      return;
    }

    // If an entity is a child entry to a book, don't update.
    if ($book_data = $this->bookManager->loadBookLink($entity->id())) {
      $is_book = ($book_data['bid'] ?? NULL) === $entity->id();
      if (($book_data['pid'] ?? NULL) !== $entity->id() && $is_book === FALSE) {
        return;
      }
    }

    $parent_nids = array_keys($this->getParentNodes($entity->id()));
    $site_topics = array_column($entity->get('field_site_topics')->getValue(), 'target_id');

    $site_topics_removed = array_diff($parent_nids, $site_topics);
    $site_topics_new = array_diff($site_topics, $parent_nids);

    foreach ($site_topics_new as $new) {
      $topic_node = $this->nodeStorage->load($new);
      if (!$topic_node) {
        continue;
      }

      $child_refs = $topic_node->get('field_topic_content');
      $ref_exists = FALSE;

      foreach ($child_refs as $ref) {
        if ((int) $ref->target_id === (int) $entity->id()) {
          $ref_exists = TRUE;
          break;
        }
      }

      if (!$ref_exists) {
        $topic_node->get('field_topic_content')->appendItem(['target_id' => $entity->id()]);
        $topic_node->setRevisionLogMessage('Added child: (' . $entity->id() . ') ' . $entity->label());
        $topic_node->save();
      }
    }

    foreach ($site_topics_removed as $remove) {
      $topic_node = $this->nodeStorage->load($remove);
      if (!$topic_node) {
        continue;
      }

      $child_refs = $topic_node->get('field_topic_content');
      $child_removed = FALSE;

      for ($i = 0; $i < $child_refs->count(); $i++) {
        if ((int) $child_refs->get($i)->target_id === (int) $entity->id()) {
          $child_refs->removeItem($i);
          $child_removed = TRUE;
          $i--;
        }
      }

      if ($child_removed) {
        $topic_node->setRevisionLogMessage('Removed child: (' . $entity->id() . ') ' . $entity->label());
        $topic_node->save();
      }
    }
  }

  /**
   * Remove all topic child references for the given entity.
   */
  public function removeChildDisplayFromTopics(EntityInterface $entity): void {
    if (!$entity->hasField('field_site_topics') || !$this->isValidTopicChild($entity)) {
      return;
    }

    $parent_nids = array_keys($this->getParentNodes($entity->id()));

    foreach ($parent_nids as $parent) {
      $topic_node = $this->nodeStorage->load($parent);
      if (!$topic_node) {
        continue;
      }

      $child_refs = $topic_node->get('field_topic_content');
      for ($i = 0; $i < $child_refs->count(); $i++) {
        if ((int) $child_refs->get($i)->target_id === (int) $entity->id()) {
          $child_refs->removeItem($i);
          $i--;
        }
      }

      $topic_node->setRevisionLogMessage('Removed child: (' . $entity->id() . ') ' . $entity->label());
      $topic_node->save();
    }
  }

  /**
   * Update the topics property with a list of child nodes.
   */
  private function getChildTopics(NodeInterface $topic, int $depth = 0): void {
    if ($depth >= self::MAX_TRAVERSAL_DEPTH) {
      return;
    }

    foreach ($topic->get('field_topic_content')->referencedEntities() as $child) {
      if ($child->bundle() === 'subtopic') {
        $this->deptTopics[$child->id()] = $child;
        $this->getChildTopics($child, $depth + 1);
      }
    }
  }

  /**
   * Public service function to return an array of topic ids from a parent topic.
   */
  public function getTopicChildren(NodeInterface $topic): array {
    $this->getChildTopics($topic);
    return $this->deptTopics;
  }

  /**
   * Returns the maximum assignable topics permitted for the given node bundle.
   */
  public static function maximumTopicsForType(string|ContentEntityInterface $type): int {
    if (empty($type)) {
      throw new \InvalidArgumentException('$type must not be empty');
    }

    if ($type instanceof ContentEntityInterface) {
      $type = $type->bundle();
    }

    return match ($type) {
      'subtopic' => 1,
      default => 3,
    };
  }

}
