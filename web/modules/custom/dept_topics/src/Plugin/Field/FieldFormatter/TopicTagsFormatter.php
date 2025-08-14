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
    $current_user = \Drupal::currentUser();

    foreach ($entities as $entity) {
      if ($entity->bundle() === 'topic') {
        if ($current_user->isAnonymous()) {
          if ($entity->isPublished()) {
            $parent_topics[] = $entity;
          }
        }
        else {
          $parent_topics[] = $entity;
        }
      }
      elseif ($entity->bundle() === 'subtopic') {
        $sub_topics[] = $entity;
        $parents = $topic_manager->getParentNodes($entity);
        $parent_nodes = Node::loadMultiple(array_keys($parents));

        foreach ($parent_nodes as $parent) {
          if ($current_user->isAnonymous()) {
            if ($parent->isPublished()) {
              $parent_topics[] = $parent;
            }
          }
          else {
            $parent_topics[] = $parent;
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
        '#url' => $node->toUrl(),
        '#attributes' => [
          'aria-label' => $node->label() . ' topic',
        ],
        '#cache' =>
          [
            'tags' => ['node:' . $node->id()],
            'contexts' => ['user.roles:anonymous'],
          ],
      ];
    }

    // Reverse the ordering of the topics to reflect the hierarchy
    // as seen in the order of Parent Topic > Topic.
    $element = array_reverse($element, TRUE);

    return $element;
  }

}
