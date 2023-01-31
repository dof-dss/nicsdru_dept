<?php

namespace Drupal\dept_topics;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\node\NodeInterface;

/**
 * A utility class to provide easy access to a node's topics/subtopics.
 * Intended to reduce code duplication between backend and frontend
 * preprocessing.
 */
class ContentTopics {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor for ContentTopic class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Return a list of topics for the given node.
   *
   * @param int|NodeInterface $node
   *   The node id or node object for an item of content.
   * @param bool $include_subtopics
   *   Option to include or exclude subtopics in the final list.
   *
   * @return array
   *   The list of topics in [topic_id => topic_label] format.
   */
  public function getTopics(int|NodeInterface $node, bool $include_subtopics = TRUE): array {
    $topics = [];

    if (empty($node)) {
      return $topics;
    }

    if (is_int($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }

    if ($node instanceof NodeInterface === FALSE) {
      return $topics;
    }

    if ($node->hasField('field_site_topics')) {
      $node_topics = $node->get('field_site_topics')->referencedEntities();
      foreach ($node_topics as $topic) {
        $topics[$topic->id()] = $topic->label();
      }
    }
    elseif ($node->hasField('field_parent_topic')) {
      $node_topics = $node->get('field_parent_topic')->referencedEntities();
      foreach ($node_topics as $topic) {
        $topics[$topic->id()] = $topic->label();
      }
    }

    if ($include_subtopics && $node->hasField('field_site_subtopics')) {
      $node_subtopics = $node->get('field_site_subtopics')
        ->referencedEntities();
      foreach ($node_subtopics as $subtopic) {
        $topics[$subtopic->id()] = $subtopic->label();
      }
    }
    elseif ($include_subtopics && $node->hasField('field_parent_subtopic')) {
      $node_subtopics = $node->get('field_parent_subtopic')
        ->referencedEntities();

      foreach ($node_subtopics as $subtopic) {
        $topics[$subtopic->id()] = $subtopic->label();
      }
    }

    // Bitwise options allows natural sorting of text as well
    // as case-insensitivity to correctly handle inconsistent
    // capitalisation or title-case vs sentence-case style.
    asort($topics, SORT_NATURAL | SORT_FLAG_CASE);

    return $topics;
  }

  /**
   * @param int|\Drupal\node\NodeInterface $node
   *   The subtopic node (nid or full object)
   *
   * @return array
   *   A render array of links elements.
   */
  public function getSubtopicContent(int|NodeInterface $node): array {
    $content = [];

    if (empty($node)) {
      return $content;
    }

    if (is_int($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }

    if ($node instanceof NodeInterface && $node->bundle() === 'subtopic') {
      // Fetch articles tagged with this subtopic, as well as subtopics
      // referencing it from the parent subtopic field.
      $subtopic_content_sql = "SELECT
        st_nfd.nid,
        st_nfd.type,
        st_nfd.title
        FROM {node_field_data} st_nfd
        JOIN {node__field_parent_subtopic} nfps ON st_nfd.nid = nfps.entity_id
        WHERE st_nfd.type = 'subtopic' AND nfps.field_parent_subtopic_target_id = :subtopic_id
        AND st_nfd.status = 1
      UNION
        SELECT
        ar_nfd.nid,
        ar_nfd.type,
        ar_nfd.title
        FROM {node_field_data} ar_nfd
        JOIN {node__field_site_subtopics} nfss ON ar_nfd.nid = nfss.entity_id
        WHERE ar_nfd.type = 'article' AND nfss.field_site_subtopics_target_id = :subtopic_id
        AND ar_nfd.status = 1
      ORDER BY title ASC";

      $subtopic_content = \Drupal::database()
        ->query($subtopic_content_sql, [':subtopic_id' => $node->id()])
        ->fetchAll();

      foreach ($subtopic_content as $row) {
        $content[] = Link::createFromRoute($row->title, 'entity.node.canonical', ['node' => $row->nid])->toString();
      }
    }

    return $content;
  }

}
