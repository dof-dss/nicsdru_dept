<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Management page for nodes missing from topic content.
 */
final class BrokenTopicMappingController extends ControllerBase {

  /**
   * Displays problematic content.
   */
  public function __invoke(): array {
    $output = [];
    $topic_total = 0;
    $db = \Drupal::database();

    $department = \Drupal::service('department.manager')->getCurrentDepartment();

    $subtopics_query = $db->select('node__field_domain_source', 'ds');
    $subtopics_query->join('node_field_data', 'nfd', 'ds.entity_id = nfd.nid');
    $subtopics_query->fields('ds', ['entity_id'])
      ->fields('nfd', ['title'])
      ->condition('ds.bundle', 'subtopic')
      ->condition('ds.field_domain_source_target_id', $department->id());

    $subtopics = $subtopics_query->execute()->fetchAll();

    foreach ($subtopics as $subtopic) {

      // Topic nodes field_contents
      $topic_contents = $db->select('node__field_topic_content', 'tc')
        ->fields('tc', ['field_topic_content_target_id'])
        ->condition('entity_id', $subtopic->entity_id)
        ->execute()
        ->fetchCol();

      $site_topic_nodes = $db->select('node__field_site_topics', 'st')
        ->fields('st', ['entity_id'])
        ->condition('field_site_topics_target_id', $subtopic->entity_id)
        ->execute()
        ->fetchCol();

      $site_topic_nodes_query = $db->select('node__field_site_topics', 'st');
      $site_topic_nodes_query->join('node_field_data', 'nfd', 'st.entity_id = nfd.nid');
      $site_topic_nodes_query->fields('st', ['entity_id'])
        ->fields('nfd', ['title'])
        ->condition('st.field_site_topics_target_id', $subtopic->entity_id)
        ->condition('bundle', ['application', 'article', 'subtopic'], 'IN')
        ->condition('nfd.status', '1');

      $site_topic_nodes = $site_topic_nodes_query->execute()->fetchAllAssoc('entity_id');

      $missing = array_diff(array_keys($site_topic_nodes), $topic_contents);
      $rows = [];

      foreach ($missing as $missed) {
        $rows[$missed] = [
          ['data' => [
            '#type' => 'link',
            '#title' => $site_topic_nodes[$missed]->title,
            '#url' => Url::fromRoute('entity.node.canonical', ['node' => $missed]),
            ],
          ],
          ['data' => [
            '#type' => 'link',
            '#title' => $this->t('Add to topic content'),
            '#url' => Url::fromRoute('dept_topics.broken_topic_mapping.add_node_to_topic', ['nid' => $missed]),
          ],
          ],
        ];
      }

      if (!empty($rows)) {
        $topic_total++;
        $output[$subtopic->entity_id]['topic'] = [
          '#markup' => '<a href="' . Url::fromRoute('entity.node.canonical', ['node' => $subtopic->entity_id])->toString() . '"><h4>' . $subtopic->title . '</h4></a>',
        ];

        $output[$subtopic->entity_id]['missing'] = [
          '#type' => 'details',
          '#title' => 'Possible missing topic contents (' . count($rows) . ')',
          '#open' => FALSE,
        ];

        $output[$subtopic->entity_id]['missing']['table'] = [
          '#type' => 'table',
          '#header' => ['Title', 'Operations'],
          '#rows' => $rows,
        ];
      }
    }

    $build['intro'] = [
      '#markup' => '<h3>Possible Subtopics with content issues: ' . $topic_total . '</h3>',
    ];
    $build['content'] = $output;

    return $build;
  }

  public function addNodesToTopicContent(int $nid) {
    $node = Node::load($nid);
    $added_log = [];

    $site_topic_ids = array_column($node->get('field_site_topics')
      ->getValue(), 'target_id');

    $topic_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($site_topic_ids);

    foreach ($topic_nodes as $topic) {
      $topic_child_contents_ids = array_column($topic->get('field_topic_content')
        ->getValue(), 'target_id');

      if (!in_array($node->id(), $topic_child_contents_ids)) {
        $topic->get('field_topic_content')->appendItem([
          'target_id' => $node->id(),
        ]);
        $topic->setRevisionLogMessage('Added content: (' . $node->id() . ') ' . $node->label());
        $topic->save();
        $added_log[] = $topic->label();
      }
    }

    \Drupal::messenger()->addMessage($node->label() . ' added to the following topics: ' . implode(', ', $added_log));
    $this->redirect('dept_topics.broken_topic_mapping');
  }
}
