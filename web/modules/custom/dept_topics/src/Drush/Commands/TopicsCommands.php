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
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Update existing topics content to use the new topics system.
   */
  #[CLI\Command(name: 'topics:transform', aliases: ['tt'])]
  public function transform(): void {

    // Backup topic content data.
    foreach (self::DB_TOPIC_CONTENT_TABLES as $db_table) {
      $original_table = $db_table . "_original";
      $this->connection->query("DROP TABLE IF EXISTS {$original_table}");
      $this->connection->query("CREATE TABLE {$original_table} LIKE {$db_table}");
      $this->connection->query("INSERT INTO {$original_table} SELECT * FROM {$db_table}");
    }

    $node_storage = $this->entityTypeManager->getStorage('node');

    $operations = [];
    foreach (['topic', 'subtopic'] as $bundle) {
      $nids = $node_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $bundle)
        ->execute();

      foreach ($nids as $nid) {
        $operations[] = [[self::class, 'batchProcessTopic'], [$nid]];
      }
    }

    batch_set([
      'title' => 'Transforming topics content...',
      'operations' => $operations,
      'finished' => [self::class, 'batchFinished'],
    ]);

    // Disable custom cache processes while performing the batch to save memory.
    putenv("BYPASS_CACHE=TRUE");
    $_SERVER['BYPASS_CACHE'] = 'TRUE';
    $_ENV['BYPASS_CACHE'] = 'TRUE';
    drush_backend_batch_process();
  }

  /**
   * Processes a topic/subtopic's child content.
   *
   * @param int $nid
   *   The topic/subtopic node ID to process.
   * @param array $context
   *   The batch context.
   */
  public static function batchProcessTopic(int $nid, array &$context): void {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    /** @var \Drupal\dept_topics\TopicManager $topic_manager */
    $topic_manager = \Drupal::service('topic.manager');

    $topic = $node_storage->load($nid);
    if ($topic === NULL) {
      return;
    }

    $context['message'] = $topic->id() . ' : ' . $topic->label();

    $children = $topic->get('field_topic_content')->referencedEntities();
    $saved = FALSE;
    foreach ($children as $child) {
      $child = self::siteTopicsSanitise($child, $node_storage, $topic_manager, $saved);
      if ($child->get('moderation_state')->getString() === 'archived') {
        $topic_manager->removeChild($child, $topic);
      }
      elseif (!$saved) {
        // Process the child if it wasn't already done so by siteTopicsSanitise().
        $topic_manager->processChild($child);
      }
    }

    $context['results']['processed'] = ($context['results']['processed'] ?? 0) + 1;
  }

  /**
   * Batch 'finished' callback.
   *
   * @param bool $success
   *   Whether the batch completed without a fatal error.
   * @param array $results
   *   Results accumulated via $context['results'] in batchProcessTopic().
   */
  public static function batchFinished(bool $success, array $results): void {
    // Restore cache processes.
    putenv("BYPASS_CACHE");
    unset($_SERVER['BYPASS_CACHE'], $_ENV['BYPASS_CACHE']);

    if ($success) {
      \Drupal::messenger()->addStatus('###### Transform Finished ######');
      \Drupal::messenger()->addStatus('Processed ' . $results['processed'] . ' topics/subtopics.');
    }
    else {
      \Drupal::messenger()->addStatus('###### Transform Failed ######');
      \Drupal::messenger()->addError('The batch did not complete successfully.');
    }
  }

  /**
   * Removes any site topics that are a parent of the chosen topics.
   *
   * @param \Drupal\node\NodeInterface $child
   *   The child node to sanitise.
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   Drupal core node storage repository.
   * @param \Drupal\dept_topics\TopicManager $topic_manager
   *   The topic manager service.
   * @param bool $saved
   *   Set by reference to TRUE if this call saved the child.
   */
  protected static function siteTopicsSanitise(NodeInterface $child, EntityStorageInterface $node_storage, TopicManager $topic_manager, bool &$saved = FALSE): NodeInterface {
    $saved = FALSE;
    $updated_site_topics = FALSE;

    $topic_ids = array_column($child->get('field_site_topics')->getValue(), 'target_id');

    foreach ($topic_ids as $topic_id) {
      $topic_node = $node_storage->load($topic_id);
      $parents = array_keys($topic_manager->getParentNodes($topic_node));

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
      // Flag as saved to prevent duplicate processing of the child via processChild().
      $saved = TRUE;
    }

    return $child;
  }

}
