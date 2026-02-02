<?php

namespace Drupal\dept_topics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns dataset responses for topic tree field widget.
 */
final class TopicTreeDataController {

  /**
   * Node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $nodeStorage;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
  }

  /**
   * Container factory.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Return all topics and subtopics for a department.
   *
   * @param string $department
   *   The department machine name.
   */
  public function allDepartmentTopics(string $department): JsonResponse {
    return new JsonResponse($this->parentTopics($department));
  }

  /**
   * A helper function returning results.
   */
  public function parentTopics(string $department): array {
    $topics = [];

    $root_topics = $this->nodeStorage->loadByProperties([
      'type' => 'topic',
      // Keep your original field name; if this should be field_domain_source
      // or similar, adjust here.
      'field_domain_access' => $department,
    ]);

    foreach ($root_topics as $topic) {
      // If moderation_state doesn't exist on this bundle, skip safely.
      if ($topic->hasField('moderation_state')) {
        $mod_state = $topic->get('moderation_state')->getString();
        if ($mod_state === 'archived') {
          continue;
        }
      }

      // See 'Alternative JSON' format at https://www.jstree.com/docs/json/
      $topics[] = [
        'id' => $topic->id(),
        'text' => $topic->label(),
        'parent' => '#',
      ];

      $this->appendSubtopics($topic, $topics);
    }

    return $topics;
  }

  /**
   * Extracts child subtopics for a given topic/subtopic node.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   Parent topic to extract child content from.
   * @param array $topics
   *   Accumulator for jsTree rows.
   */
  public function appendSubtopics(NodeInterface $parent, array &$topics): void {
    $child_content = $parent->get('field_topic_content')->referencedEntities();

    foreach ($child_content as $child) {
      if ($child->bundle() !== 'subtopic') {
        continue;
      }

      if ($child->hasField('moderation_state')) {
        $mod_state = $child->get('moderation_state')->getString();
        if ($mod_state === 'archived') {
          continue;
        }
      }

      $topics[] = [
        'id' => $child->id(),
        'text' => $child->label(),
        'parent' => $parent->id(),
      ];

      $this->appendSubtopics($child, $topics);
    }
  }

}
