<?php

namespace Drupal\dept_topics\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\dept_topics\TopicManager;
use Drupal\node\NodeInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Topics Drush commands.
 */
final class TopicsCommands extends DrushCommands {

  const DB_TOPIC_CONTENT_TABLES = [
    'node__field_topic_content',
    'node_revision__field_topic_content',
  ];

  /**
   * Constructor.
   */
  public function __construct(
    private readonly Token $token,
    private readonly TopicManager $topicManager,
    private readonly Connection $connection,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('token'),
      $container->get('topic.manager'),
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Update existing topics content to use the new topics system.
   */
  #[CLI\Command(name: 'topics:transform', aliases: ['tt'])]
  public function transform() {

    $log = [];

    foreach (self::DB_TOPIC_CONTENT_TABLES as $db_table) {
      $original_table = $db_table . "_original";
      $this->connection->query("DROP TABLE IF EXISTS {$original_table}");
      $this->connection->query("CREATE TABLE {$original_table} LIKE {$db_table}");
      $this->connection->query("INSERT INTO {$original_table} SELECT * FROM {$db_table}");
    }

    $node_storage = $this->entityTypeManager->getStorage('node');

    $topics = $node_storage->loadByProperties(
      ['type' => 'topic']
    );

    foreach ($topics as $topic) {
      $this->io()->writeln($topic->id() . ' : ' . $topic->label());
      $children = $topic->get('field_topic_content')->referencedEntities();
      foreach ($children as $child) {
        $this->io()->writeln('- ' . $child->label());

        $child = $this->siteTopicsSanitise($child, $node_storage);
        if ($child->get('moderation_state')->getString() != 'archived') {
          $this->topicManager->processChild($child);
        }
      }
    }

    $subtopics = $node_storage->loadByProperties(
      ['type' => 'subtopic']
    );

    foreach ($subtopics as $subtopic) {
      $this->io()->writeln($subtopic->id() . ' : ' . $subtopic->label());
      $children = $subtopic->get('field_topic_content')->referencedEntities();
      foreach ($children as $child) {
        $this->io()->writeln('- ' . $child->id() . ' : ' . $child->label());

        $child = $this->siteTopicsSanitise($child, $node_storage);

        if ($child->get('moderation_state')->getString() != 'archived') {
          $this->topicManager->processChild($child);
        }
      }
    }

    foreach ($log as $entry) {
      $this->io()->writeln('Topic: ' . $entry['topic'] . ' -- Child: ' . $entry['child'] . ' -- ' . $entry['action']);
    }
  }

  /**
   * Removes any site topics that are a parent of the chosen topics.
   *
   * @param \Drupal\node\NodeInterface $child
   *   The child node to sanitise.
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   Drupal core node storage repository.
   *
   */
  protected function siteTopicsSanitise(NodeInterface $child, EntityStorageInterface $node_storage) {
    $updated_site_topics = FALSE;

    $topic_ids = array_column($child->get('field_site_topics')->getValue(), 'target_id');

    foreach ($topic_ids as $topic_id) {
      $topic_node = $node_storage->load($topic_id);
      $parents = array_keys($this->topicManager->getParentNodes($topic_node));

      foreach ($parents as $parent) {
        if (($index = array_search($parent, $topic_ids)) !== FALSE) {
          $updated_site_topics = TRUE;
          unset($topic_ids[$index]);
        }
      }
    }

    if ($updated_site_topics) {
      $child->set('field_site_topics', $topic_ids);
      $child->setRevisionLogMessage('Removed parent site topic, only the lowest topic level should be selected.');
      $child->save();
    }

    return $child;
  }

}
