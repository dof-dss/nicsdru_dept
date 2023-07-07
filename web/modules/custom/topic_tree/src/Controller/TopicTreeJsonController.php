<?php

namespace Drupal\topic_tree\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for Topic tree routes.
 */
class TopicTreeJsonController {

  public function parents() {
    return new JsonResponse($this->parentTopics());
  }

  protected $topics;

  /**
   * A helper function returning results.
   */
  public function parentTopics() {

    $current_dept = 'finance';

    $root_topics = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'topic',
      'field_domain_access' => $current_dept
    ]);

    $topics = [];

    foreach ($root_topics as $topic) {

      $this->topics[] = [
        'id' => $topic->id(),
        'text' => $topic->label(),
        'parent' => '#',
      ];

      $subtopics = $this->subtopics($topic->id());

    }

    return $this->topics;
  }

  public function subtopics($parent) {

    for ($i=1; $i < 3; $i++) {
      $this->topics[] = ['id' => $parent . '-' . $i, 'text' => 'child ' . $i, 'parent' => $parent];
    }

  }

}
