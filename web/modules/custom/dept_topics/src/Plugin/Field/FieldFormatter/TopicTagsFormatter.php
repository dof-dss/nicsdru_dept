<?php

namespace Drupal\dept_topics\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 *  'Topic tags' formatter for site topics display.
 *
 * @FieldFormatter(
 *   id = "dept_topics_topic_tags",
 *   label = @Translation("Topic tags"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class TopicTagsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $element = [];
    $parent_topics = [];
    $sub_topics = [];
    $topic_manager = \Drupal::service('topic.manager');
    $entities = $items->referencedEntities();

    foreach ($entities as $entity) {
      if ($entity->bundle() === 'topic') {
        $parent_topics[] = $entity;
      }
      elseif ($entity->bundle() === 'subtopic') {
        $sub_topics[] = $entity;
        $parents = $topic_manager->getParentNodes($entity);

        foreach ($parents as $parent) {
          if ($parent->type === 'topic') {
            $parent_topics[] = Node::load($parent->nid);
          }
        }
      }
    }

    // Reverse to display top level topics first.
    if (!empty($parent_topics)) {
      $parent_topics = array_reverse($parent_topics);
    }

    $nodes = array_merge(array_reverse($sub_topics), $parent_topics);

    foreach ($nodes as $node) {
      $element[$node->id()] = [
        '#type' => 'link',
        '#title' => $node->label(),
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $node->id()]),
        '#attributes' => [
          'aria-label' => $node->label() . ' topic',
        ],
        '#cache' => ['tags' => ['node:' . $node->id()]],
      ];
    }

    // Reverse the ordering of the topics to reflect the hierarchy
    // as seen in the order of Parent Topic > Topic.
    $element = array_reverse($element, TRUE);

    return $element;
  }

}
