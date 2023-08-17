<?php

namespace Drupal\dept_topics\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

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
    $nodes = [];
    $topic_manager = \Drupal::service('topic.manager');

    $entities = $items->referencedEntities();

    foreach ($entities as $entity) {
      $nodes[$entity->id()] = (object) [
        'nid' => $entity->id(),
        'title' => $entity->label(),
        'type' => $entity->bundle(),
      ];
      $parents = $topic_manager->getParentNodes($entity);

      $parents = array_filter($parents, function ($parent) {
        return ($parent->type == 'topic');
      });

      $nodes = $nodes + $parents;
    }

    foreach ($nodes as $node) {
      $element[$node->nid] = [
        '#type' => 'link',
        '#title' => $node->title,
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $node->nid]),
      ];
    }

    return $element;
  }

}
