<?php

namespace Drupal\dept_topics;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorage;

/**
 * Provides methods for managing Sub/Topic referenced (child) content.
 */
class TopicManager {

  /**
   * Array of parent nodes.
   *
   * @var array
   */
  protected $parents = [];

  /**
   * Array of department topics.
   *
   * @var array
   */
  protected $deptTopics = [];

  /**
   * Array of target bundes.
   *
   * @var array
   */
  protected $targetBundles = [];

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConn;

  /**
   * The Entity Field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Node Storage instance.
   *
   * @var \Drupal\node\NodeStorage
   */
  protected $nodeStorage;

  /**
   * The Entity Display Repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a TopicManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The Entity Field Manager service.
   * @param \Drupal\Core\Entity\EntityDisplayRepository $entity_display_repository
   *   The Entity Display Repository service.
   *
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $connection,
    EntityFieldManagerInterface $entity_field_manager,
    EntityDisplayRepository $entity_display_repository,
    ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dbConn = $connection;
    $this->entityFieldManager = $entity_field_manager;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * Returns parent nodes for the given node ID.
   *
   * @param int $nid
   *   Node id to return the parent for.
   *
   * @return array|mixed
   *   Node ID indexed array comprising id, title and type.
   */
  public function getParentNodes($nid) {
    $parents = $this->dbConn->query("SELECT n.nid, nfd.title, nfd.type FROM node n
        LEFT JOIN node_field_data nfd
        ON nfd.nid = n.nid
        LEFT JOIN node__field_topic_content ftc
        ON ftc.entity_id = n.nid
        WHERE ftc.field_topic_content_target_id = :nid", [':nid' => $nid])->fetchAllAssoc('nid');

    if ($parents === NULL) {
      return $this->parents;
    }

    foreach ($parents as $parent) {
      $this->parents[$parent->nid] = $parent;
      $this->getParentNodes($parent->nid);
    }

    return $this->parents;
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
    $parent_topics = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'topic',
      'field_domain_source' => $department_id,
    ]);

    foreach ($parent_topics as $nid => $parent) {
      $this->deptTopics[$parent->id()] = $parent;
      $this->getChildTopics($parent);
    }

    return $this->deptTopics;
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
      $parent_nids = array_keys($this->getParentNodes($entity->id()));
      // @phpstan-ignore-next-line
      $site_topics = array_column($entity->get('field_site_topics')->getValue(), 'target_id');

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
          $topic_node->save();
        }
      }

      // Remove any topic content references.
      foreach ($site_topics_removed as $remove) {
        $topic_node = $this->nodeStorage->load($remove);

        if (empty($topic_node)) {
          continue;
        }

        $child_refs = $topic_node->get('field_topic_content');

        for ($i = 0; $i < $child_refs->count(); $i++) {
          // @phpstan-ignore-next-line
          if ($child_refs->get($i)->target_id == $entity->id()) {
            $child_refs->removeItem($i);
            $i--;
          }
        }

        $topic_node->save();
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
    $child_content = $topic->field_topic_content->referencedEntities();

    foreach ($child_content as $child) {
      if ($child->bundle() === 'subtopic') {
        $this->deptTopics[$child->id()] = $child;
        $this->getChildTopics($child);
      }
    }
  }

}
