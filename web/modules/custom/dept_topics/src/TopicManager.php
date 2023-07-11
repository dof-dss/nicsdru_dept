<?php

namespace Drupal\dept_topics;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

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
   * The entity type manager.
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
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a TopicManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The Entity Field Manager service.
   *
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $connection,
    EntityFieldManagerInterface $entity_field_manager
    ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dbConn = $connection;
    $this->entityFieldManager = $entity_field_manager;
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
