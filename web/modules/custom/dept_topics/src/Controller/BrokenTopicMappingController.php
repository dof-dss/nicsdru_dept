<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Returns responses for Departmental sites: topics routes.
 */
final class BrokenTopicMappingController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): array {
    $output = [];
    $topic_total = 0;
    $db = \Drupal::database();

    $subtopics_query = $db->select('node__field_domain_source', 'ds');
    $subtopics_query->join('node_field_data', 'nfd', 'ds.entity_id = nfd.nid');
    $subtopics_query->fields('ds', ['entity_id'])
      ->fields('nfd', ['title'])
      ->condition('ds.bundle', 'subtopic')
      ->condition('ds.field_domain_source_target_id', 'finance');

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
            '#url' => Url::fromRoute('entity.node.canonical', ['node' => $missed])
            ]
          ]
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
          '#rows' => $rows
        ];
      }
    }

    $build['intro'] = [
      '#markup' => '<h3>Possible Subtopics with content issues: ' . $topic_total . '</h3>'
    ];
    $build['content'] = $output;

    return $build;
  }

}
