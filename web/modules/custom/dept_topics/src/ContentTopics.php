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
   *   The topic node (nid or full object)
   *
   * @return array
   *   Array of subtopics as [$subtopic_id => $subtopic_label].
   */
  public function getSubtopicsForTopic(int|NodeInterface $node): array {
    $subtopics = [];

    if (is_int($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }

    if ($node->bundle() != 'topic') {
      return $subtopics;
    }

    // Find the entity ids and weights of the draggable view display.
    $query = \Drupal::database()->select('draggableviews_structure', 'ds')
      ->fields('ds', ['entity_id'])
      ->condition('view_name', 'content_stacks')
      ->condition('view_display', 'topic_subtopics')
      ->condition('args', '["' . $node->id() . '"]')
      ->orderBy('weight', 'ASC');

    $ids = $query->execute()->fetchAllAssoc('entity_id');
    $subtopic_nodes = \Drupal::entityTypeManager()
      ->getStorage('node')->loadMultiple(array_keys($ids));

    foreach ($subtopic_nodes as $node) {
      $subtopics[$node->id()] = [
        'title' => $node->label(),
        'summary' => $node->field_summary->value,
      ];
    }

    return $subtopics;
  }

}
