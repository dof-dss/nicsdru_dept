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
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The Book manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
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
    protected ModerationInformationInterface $moderationInformation,
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
    $dept_topics = $this->cache->get('dept_topics_' . $department_id);

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

      // TODO: Check if this cache is properly cleared anywhere.
      $this->cache->set('dept_topics_' . $department_id, $this->deptTopics, Cache::PERMANENT, [$department_id . '_topics']);

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

}
