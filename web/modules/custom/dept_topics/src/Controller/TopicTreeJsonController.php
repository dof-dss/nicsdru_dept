<?php

namespace Drupal\dept_topics\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for Topic tree routes.
 */
class TopicTreeJsonController {

  public function parents($department) {
    return new JsonResponse($this->parentTopics($department));
  }

  protected $topics = [];

  /**
   * A helper function returning results.
   */
  public function parentTopics($department) {
    $root_topics = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'topic',
      'field_domain_access' => $department
    ]);

    foreach ($root_topics as $topic) {

      $this->topics[] = [
        'id' => $topic->id(),
        'text' => $topic->label(),
        'parent' => '#',
      ];

      $subtopics = $this->subtopics($topic);
    }

    return $this->topics;
  }

  public function subtopics($parent) {
    $child_content = $parent->field_topic_content->referencedEntities();

    foreach ($child_content as $child) {
      if ($child->bundle() === 'subtopic') {
        $this->topics[] = ['id' => $child->id(), 'text' => $child->label(), 'parent' => $parent->id()];
        $this->subtopics($child);
      }
    }
  }

}
