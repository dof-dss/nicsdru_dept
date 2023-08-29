<?php

namespace Drupal\dept_topics\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
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
    $nodes = [];
    $topic_manager = \Drupal::service('topic.manager');
    $entities = $items->referencedEntities();

    foreach ($entities as $entity) {
      if ($entity->bundle() === 'topic') {
        $nodes[] = $entity;
      }
      elseif ($entity->bundle() === 'subtopic') {
        $nodes[] = $entity;
        $parents = $topic_manager->getParentNodes($entity);

        foreach ($parents as $parent) {
          if ($parent->type === 'topic') {
            $nodes[] = Node::load($parent->nid);
          }
        }
      }
    }

    foreach ($nodes as $node) {
      $element[$node->id()] = [
        '#type' => 'link',
        '#title' => $node->label(),
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $node->id()]),
        '#cache' => ['tags' => ['node:' . $node->id()]],
      ];
    }

    return $element;
  }

}
