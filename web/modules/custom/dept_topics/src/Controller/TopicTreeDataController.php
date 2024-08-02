<?php

namespace Drupal\dept_topics\Controller;

use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns dataset responses for topic tree field widget.
 */
class TopicTreeDataController {

  /**
   * Return all topics and subtopics for a department.
   *
   * @param string $department_id
   *   The department machine name.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON dataset adhering to the format outlined in https://www.jstree.com/docs/json/.
   */
  public function allDepartmentTopics($department_id) {
    return new JsonResponse($this->parentTopics($department_id));
  }

  /**
   * List of topics for a department.
   * @var array
   */
  protected $topics = [];

  /**
   * A helper function returning results.
   */
  public function parentTopics($department_id) {
    $root_topics = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'topic',
      'field_domain_access' => $department_id
    ]);

    foreach ($root_topics as $topic) {
      // See 'Alternative JSON' format at https://www.jstree.com/docs/json/
      $this->topics[] = [
        'id' => $topic->id(),
        'text' => $topic->label(),
        'parent' => '#',
      ];

      $this->subtopics($topic);
    }

    return $this->topics;
  }

  /**
   * Extracts child subtopics for a given topic/subtopic node.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   Parent topic to extract child content from.
   */
  public function subtopics(NodeInterface $parent) {
    $child_content = $parent->get('field_topic_content')->referencedEntities();

    foreach ($child_content as $child) {
      if ($child->bundle() === 'subtopic') {
        $this->topics[] = [
          'id' => $child->id(),
          'text' => $child->label(),
          'parent' => $parent->id(),
        ];
        $this->subtopics($child);
      }
    }
  }

}
