<?php

namespace Drupal\dept_topics;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
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
   *
   * @return array|mixed
   *   Node ID indexed array comprising id, title and type.
   */
  public function getParentNodes($node, &$parents = []) {
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
      $this->getParentNodes($node->nid, $parents);
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
   * Add and remove an entity to topic child content lists based on the Site Topic field values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to use as a child reference.
   */
  public function updateChildDisplayOnTopics(EntityInterface $entity) {
    // @phpstan-ignore-next-line
    if ($entity->hasField('field_site_topics') && $this->isValidTopicChild($entity)) {
      // If an entity is a child entry to a book, don't update the
      // 'topic child contents' field to the topics in its site_topics field.
      if ($book_data = $this->bookManager->loadBookLink($entity->id())) {
        // Is this node the actual book node.
        $is_book = $book_data['bid'] === $entity->id();

        if (($book_data['pid'] !== $entity->id()) && $is_book === FALSE) {
          return;
        }
      }

      $parent_nids = array_keys($this->getParentNodes($entity->id()));
      // @phpstan-ignore-next-line
      $site_topics = array_column($entity->get('field_site_topics')
        ->getValue(), 'target_id');

      $site_topics_removed = array_diff($parent_nids, $site_topics);
      $site_topics_new = array_diff($site_topics, $parent_nids);

      // Add topic content references.
      foreach ($site_topics_new as $new) {
        $topic_node = $this->nodeStorage->load($new);

        if (empty($topic_node)) {
          continue;
        }

        $child_refs = $topic_node->get('field_topic_content');
        $ref_exists = FALSE;

        // Check if an entry exists to prevent duplicates.
        foreach ($child_refs as $ref) {
          // @phpstan-ignore-next-line
          if ($ref->target_id == $entity->id()) {
            $ref_exists = TRUE;
          }
        }

        if (!$ref_exists) {
          $topic_node->get('field_topic_content')->appendItem([
            'target_id' => $entity->id()
          ]);
          $topic_node->setRevisionLogMessage('Added child: (' . $entity->id() . ') ' . $entity->label());
          $topic_node->save();
        }
      }

      // Remove any topic content references.
      foreach ($site_topics_removed as $remove) {
        $topic_node = $this->nodeStorage->load($remove);
        $child_removed = FALSE;

        if (empty($topic_node)) {
          continue;
        }

        $child_refs = $topic_node->get('field_topic_content');

        for ($i = 0; $i < $child_refs->count(); $i++) {
          // @phpstan-ignore-next-line
          if ($child_refs->get($i)->target_id == $entity->id()) {
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
  }

  /**
   * Remove all topic child references for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to remove all references for.
   */
  public function removeChildDisplayFromTopics(EntityInterface $entity) {
    // @phpstan-ignore-next-line
    if ($entity->hasField('field_site_topics') && $this->isValidTopicChild($entity)) {
      $parent_nids = array_keys($this->getParentNodes($entity->id()));

      foreach ($parent_nids as $parent) {
        $topic_node = $this->nodeStorage->load($parent);
        $child_refs = $topic_node->get('field_topic_content');

        for ($i = 0; $i < $child_refs->count(); $i++) {
          // @phpstan-ignore-next-line
          if ($child_refs->get($i)->target_id == $entity->id()) {
            $child_refs->removeItem($i);
            $i--;
          }
        }

        $topic_node->setRevisionLogMessage('Removed child: (' . $entity->id() . ') ' . $entity->label());
        $topic_node->save();
      }
    }
  }

  /**
   * Update the topics property with a list of child nodes.
   *
   * @param \Drupal\node\NodeInterface $topic
   *   The topic/subtopic node to extract child subtopics from.
   */
  private function getChildTopics(NodeInterface $topic) {
    $child_content = $topic->get('field_topic_content')->referencedEntities();

    foreach ($child_content as $child) {
      if ($child->bundle() === 'subtopic') {
        $this->deptTopics[$child->id()] = $child;
        $this->getChildTopics($child);
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

}
