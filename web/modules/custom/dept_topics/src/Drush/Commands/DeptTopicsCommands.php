<?php

namespace Drupal\dept_topics\Drush\Commands;

use BlueM\Tree;
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
   * List of topics for a department.
   * @var array
   */
  protected $topics = [];

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
  #[CLI\Argument(name: 'department', description: 'Department id.')]
  public function syncTopicContentSiteTopics(string $department) {
    $this->output()->writeln(' ' . $department);

    $subtopics_ids = $this->db->select('node__field_domain_source', 'ds')
      ->fields('ds', ['entity_id'])
      ->condition('ds.bundle', 'subtopic')
      ->condition('ds.field_domain_source_target_id', $department)
      ->execute()->fetchCol();

    $subtopics = $this->entityTypeManager->getStorage('node')
      ->loadMultiple($subtopics_ids);

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

      $site_topic_nodes = $site_topic_nodes_query->execute()
        ->fetchAllAssoc('entity_id');

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

  /**
   * Removes any parent site_topics entries
   * i.e. if a subtopic and topic are present, remove the topic entry.
   */
  #[CLI\Command(name: 'topics:removeParentSiteTopics', aliases: ['removeParentSiteTopics'])]
  #[CLI\Argument(name: 'department', description: 'Department id.')]
  public function removeParentSiteTopics($department) {
    $data = [];
    $topic_data = $this->topicManager->getTopicsTree($department);
    $tree = new Tree($topic_data, ['rootId' => 0, 'id' => 'nid', 'title' => 'label']);
    $topics = $this->entityTypeManager->getStorage('node')->loadMultiple(array_column($topic_data, 'nid'));

    // Loop each topic and subtopic.
    foreach ($topics as $topic) {
      $topic_contents_nodes = $topic->get('field_topic_content')->referencedEntities();

      // Loop each topics child content and...
      foreach ($topic_contents_nodes as $topic_contents_node) {
        $site_topics_ids = array_column($topic_contents_node->get('field_site_topics')->getValue(), 'target_id');

        // If the child has multiple site topics then...
        if (count($site_topics_ids) > 1) {

          // Loop each site topic and...
          foreach ($site_topics_ids as $site_topic_id) {

            // If the site topic has a tree entry then.
            if (in_array($site_topic_id, array_column($topic_data, 'nid'))) {
              // Fetch the node from the tree and...
              $site_topic_leaf = $tree->getNodeById($site_topic_id);
              // Fetch its ancestor nodes.
              $ancestors = $site_topic_leaf->getAncestors();

              // If the site topic has ancestors then...
              if (!empty($ancestors)) {
                $ancestors_ids = [];

                // Loop each ancestor and...
                foreach ($ancestors as $ancestor) {
                  // If the ancestor id exists in the site topics array then...
                  if (in_array($ancestor->get('id'), $site_topics_ids)) {
                    // Delete any field entries and revisions.
                    $this->db->delete('node__field_site_topics')
                      ->condition('entity_id', $topic_contents_node->id())
                      ->condition('field_site_topics_target_id', $ancestor->get('id'))
                      ->execute();
                    $this->db->delete('node_revision__field_site_topics')
                      ->condition('entity_id', $topic_contents_node->id())
                      ->condition('field_site_topics_target_id', $ancestor->get('id'))
                      ->execute();

                    $data[$topic_contents_node->id()] = [$site_topic_id, $ancestor->get('id')];
                  }
                }
              }
            }
          }
        }
      }
    }
  }

}
