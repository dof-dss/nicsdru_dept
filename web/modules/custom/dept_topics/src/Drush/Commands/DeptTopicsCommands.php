<?php

namespace Drupal\dept_topics\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dept_core\DepartmentManager;
use Drupal\dept_topics\TopicManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for Topics.
 */
final class DeptTopicsCommands extends DrushCommands {

  /**
   * Constructs a DeptTopicsCommands object.
   */
  public function __construct(
    private readonly Connection $db,
    private readonly TopicManager $topicManager,
    private readonly DepartmentManager $departmentManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('topic.manager'),
      $container->get('department.manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Synchronises Topic content fields with Site Topics fields.
   */
  #[CLI\Command(name: 'topics:syncTopicContentSiteTopics', aliases: ['syncTopicContent'])]
  public function syncTopicContentSiteTopics() {

    $departments = $this->departmentManager->getAllDepartments();

    foreach ($departments as $department) {

      $this->output()->writeln(' ' . $department->label());

      $subtopics_ids = $this->db->select('node__field_domain_source', 'ds')
        ->fields('ds', ['entity_id'])
        ->condition('ds.bundle', 'subtopic')
        ->condition('ds.field_domain_source_target_id', $department->id())
        ->execute()->fetchCol();

      $subtopics = $this->entityTypeManager->getStorage('node')->loadMultiple($subtopics_ids);

      $progress_bar = new ProgressBar($this->output(), count($subtopics));
      $progress_bar->start();

      foreach ($subtopics as $subtopic) {

        $topic_contents = $this->db->select('node__field_topic_content', 'tc')
          ->fields('tc', ['field_topic_content_target_id'])
          ->condition('entity_id', $subtopic->id())
          ->execute()
          ->fetchCol();

        $site_topic_nodes_query = $this->db->select('node__field_site_topics', 'st');
        $site_topic_nodes_query->join('node_field_data', 'nfd', 'st.entity_id = nfd.nid');
        $site_topic_nodes_query->fields('st', ['entity_id'])
          ->fields('nfd', ['title'])
          ->condition('st.field_site_topics_target_id', $subtopic->id())
          ->condition('bundle', ['application', 'article', 'subtopic'], 'IN')
          ->condition('nfd.status', '1');

        $site_topic_nodes = $site_topic_nodes_query->execute()->fetchAllAssoc('entity_id');

        $missing = array_values(array_diff(array_keys($site_topic_nodes), $topic_contents));

        foreach ($missing as $missed) {
          $subtopic->get('field_topic_content')->appendItem([
            'target_id' => $missed,
          ]);
        }

        $message = 'Added missing topic content, ' . count($missing) . ' nodes.';
        $subtopic->setRevisionLogMessage($message);
        $subtopic->save();

        $progress_bar->advance();
      }

      $progress_bar->finish();

    }
  }

  /**
   * Removes any parent site_topics entries
   * i.e. if a subtopic and topic are present, remove the topic entry.
   */
  #[CLI\Command(name: 'topics:removeParentSiteTopics', aliases: ['removeParentSiteTopics'])]
  public function removeParentSiteTopics() {

  }

}
