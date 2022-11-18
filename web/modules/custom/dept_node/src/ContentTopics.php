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
   * Return a list of topics for the given node.
   *
   * @param int|NodeInterface $node
   *   The node id or node object for an item of content.
   * @param bool $include_subtopics
   *   Option to include or exclude subtopics in the final list.
   * @param bool $links
   *   Option to return the topic name as a link to the canonical page for that topic.
   *
   * @return array
   *   The list of topics in [topic_id => topic_label] format.
   */
  public function getTopics(int|NodeInterface $node, bool $include_subtopics = TRUE, bool $links = FALSE): array {
    $topics = [];

    if (empty($node)) {
      return $topics;
    }

    if (is_int($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }

    if ($node instanceof NodeInterface) {
      if ($node->hasField('field_site_topics')) {
        $node_topics = $node->get('field_site_topics')->referencedEntities();

        foreach ($node_topics as $topic) {
          $name = '';
          if ($links) {
            $name = Link::createFromRoute($topic->label(), 'entity.node.canonical', ['node' => $topic->id()])->toString();
          }
          else {
            $name = $topic->label();
          }

          $topics[$topic->id()] = $name;
        }
      }
      elseif ($node->hasField('field_parent_topic')) {
        $node_topics = $node->get('field_parent_topic')->referencedEntities();

        foreach ($node_topics as $topic) {
          $name = '';
          if ($links) {
            $name = Link::createFromRoute($topic->label(), 'entity.node.canonical', ['node' => $topic->id()])->toString();
          }
          else {
            $name = $topic->label();
          }

          $topics[$topic->id()] = $name;
        }
      }

      if ($include_subtopics && $node->hasField('field_site_subtopics')) {
        $node_subtopics = $node->get('field_site_subtopics')
          ->referencedEntities();

        foreach ($node_subtopics as $subtopic) {
          $name = '';
          if ($links) {
            $name = Link::createFromRoute($subtopic->label(), 'entity.node.canonical', ['node' => $subtopic->id()])->toString();
          }
          else {
            $name = $subtopic->label();
          }

          $topics[$subtopic->id()] = $name;
        }
      }
      elseif ($include_subtopics && $node->hasField('field_parent_subtopic')) {
        $node_subtopics = $node->get('field_parent_subtopic')
          ->referencedEntities();

        foreach ($node_subtopics as $subtopic) {
          $name = '';
          if ($links) {
            $name = Link::createFromRoute($subtopic->label(), 'entity.node.canonical', ['node' => $subtopic->id()])->toString();
          }
          else {
            $name = $subtopic->label();
          }

          $topics[$subtopic->id()] = $name;
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
   * Return a render array for the given topics.
   *
   * @param array $topics
   *   A [topic_id => $topic_name] array of topics to convert a render array.
   * @param bool $link
   *   Whether the items generated produce links to the topic pages.
   * @return array
   *   A valid render array.
   */
  public function renderList(array $topics, bool $link = TRUE): array {
    $items = [];
    $cache_tags = [];

    foreach ($topics as $id => $name) {
      if ($link) {
        $items[] = Link::createFromRoute($name, 'entity.node.canonical', ['node' => $id])->toRenderable();
      }
      else {
        $items[] = $name;
      }

      $cache_tags[] = 'node:' . $id;
    }

    $render = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $items,
      '#wrapper_attributes' => ['class' => 'container'],
      '#cache' => [
        '#tags' => $cache_tags,
      ],
    ];

    return $render;
  }

}
