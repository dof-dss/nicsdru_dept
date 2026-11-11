<?php

namespace Drupal\dept_topics\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\dept_topics\TopicManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns dataset responses for topic tree field widget.
 */
final class TopicTreeDataController implements ContainerInjectionInterface {

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(private readonly Connection $database) {
  }

  /**
   * Container factory.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
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
   *
   * Uses direct queries rather than entity loads, as hydrating every child
   * content node of every topic is very slow for large departments.
   *
   * @param string $department
   *   The department machine name.
   *
   * @return array
   *   jsTree 'Alternative JSON' rows, each with id, text and parent keys.
   */
  public function parentTopics(string $department): array {
    $root_query = $this->database->select('node_field_data', 'n');
    $root_query->join('node__field_domain_source', 'da', 'da.entity_id = n.nid AND da.revision_id = n.vid');
    $root_query->leftJoin(
      'content_moderation_state_field_data',
      'cms',
      'cms.content_entity_type_id = :entity_type AND cms.content_entity_id = n.nid AND cms.content_entity_revision_id = n.vid AND cms.langcode = n.langcode',
      [':entity_type' => 'node'],
    );
    $root_query->fields('n', ['nid', 'title']);
    $root_query->condition('n.type', 'topic');
    $root_query->condition('n.default_langcode', 1);
    $root_query->condition('da.deleted', 0);
    $root_query->condition('da.field_domain_source_target_id', $department);
    $root_query->condition($this->notArchived($root_query));
    $root_query->orderBy('n.nid');
    $roots = $root_query->execute()->fetchAllKeyed();

    if (empty($roots)) {
      return [];
    }

    $children = $this->subtopicsByParent();

    $topics = [];
    foreach ($roots as $nid => $title) {
      $topics[] = [
        'id' => (string) $nid,
        'text' => $title,
        'parent' => '#',
      ];

      $this->appendSubtopics((int) $nid, $children, $topics);
    }

    return $topics;
  }

  /**
   * Loads all non-archived subtopics, grouped by their parent and ordered.
   *
   * @return array
   *   Subtopic rows (nid, title) keyed by parent node ID, in field delta order.
   */
  protected function subtopicsByParent(): array {
    $query = $this->database->select('node__field_topic_content', 'tc');
    $query->join('node_field_data', 'n', 'n.nid = tc.field_topic_content_target_id');
    $query->leftJoin(
      'content_moderation_state_field_data',
      'cms',
      'cms.content_entity_type_id = :entity_type AND cms.content_entity_id = n.nid AND cms.content_entity_revision_id = n.vid AND cms.langcode = n.langcode',
      [':entity_type' => 'node'],
    );
    $query->fields('tc', ['entity_id']);
    $query->fields('n', ['nid', 'title']);
    $query->condition('tc.deleted', 0);
    $query->condition('n.type', 'subtopic');
    $query->condition('n.default_langcode', 1);
    $query->condition($this->notArchived($query));
    $query->orderBy('tc.entity_id');
    $query->orderBy('tc.delta');

    $children = [];
    foreach ($query->execute() as $row) {
      $children[$row->entity_id][] = ['id' => (string) $row->nid, 'text' => $row->title];
    }

    return $children;
  }

  /**
   * Builds a condition excluding nodes with an archived moderation state.
   *
   * A node without a moderation state has never been through the workflow, so
   * cannot be archived.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   Query that joins the moderation state table with the alias 'cms'.
   *
   * @return \Drupal\Core\Database\Query\ConditionInterface
   *   The condition group.
   */
  protected function notArchived(SelectInterface $query): ConditionInterface {
    return $query->orConditionGroup()
      ->isNull('cms.moderation_state')
      ->condition('cms.moderation_state', 'archived', '<>');
  }

  /**
   * Appends the child subtopics of a topic/subtopic, depth first.
   *
   * @param int $parent_id
   *   Parent topic/subtopic node ID.
   * @param array $children
   *   Subtopics grouped by parent node ID.
   * @param array $topics
   *   Accumulator for jsTree rows.
   * @param int $depth
   *   The current traversal depth.
   */
  protected function appendSubtopics(int $parent_id, array $children, array &$topics, int $depth = 0): void {
    if ($depth >= TopicManager::MAX_TRAVERSAL_DEPTH) {
      return;
    }

    foreach ($children[$parent_id] ?? [] as $child) {
      $topics[] = [
        'id' => $child['id'],
        'text' => $child['text'],
        'parent' => (string) $parent_id,
      ];

      $this->appendSubtopics((int) $child['id'], $children, $topics, $depth + 1);
    }
  }

}
