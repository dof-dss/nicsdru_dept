<?php

namespace Drupal\dept_node;

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
   * @param int $node_id
   *   The node id for an item of content.
   * @param bool $include_subtopics
   *   Option to include or exclude subtopics in the final list.
   *
   * @return array
   *   The list of topics in [topic_id => topic_label] format.
   */
  public function getTopics(int $node_id, bool $include_subtopics = TRUE): array {
    $topics = [];

    if (empty($node_id)) {
      return $topics;
    }

    $node = $this->entityTypeManager->getStorage('node')->load($node_id);

    if ($node instanceof NodeInterface) {
      if ($node->hasField('field_site_topics')) {
        $node_topics = $node->get('field_site_topics')->referencedEntities();

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

      // Bitwise options allows natural sorting of text as well
      // as case-insensitivity to correctly handle inconsistent
      // capitalisation or title-case vs sentence-case style.
      asort($topics, SORT_NATURAL | SORT_FLAG_CASE);
    }

    return $topics;
  }

  /**
   * @param array $topics
   *   A [topic_id => $topic_name] array of topics to convert a render array.
   * @param bool $link
   *   Whether the items generated produce links to the topic pages.
   * @return array
   *   A valid render array.
   */
  public function render(array $topics, bool $link = FALSE): array {
    $items = [];
    foreach ($topics as $id => $name) {
      if ($link) {
        $items[] = Link::createFromRoute($name, 'entity.node.canonical', ['node' => $id]);
      }
      else {
        $items[] = $name;
      }
    }

    $render = [
      '#type' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $items,
    ];

    return $render;
  }

}
