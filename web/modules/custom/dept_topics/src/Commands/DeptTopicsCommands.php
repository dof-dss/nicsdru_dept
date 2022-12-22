<?php

namespace Drupal\dept_topics\Commands;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\entityqueue\Entity\EntityQueue;
use Drupal\entityqueue\Entity\EntitySubqueue;
use Drupal\entityqueue\EntityQueueInterface;
use Drupal\entityqueue\EntityQueueRepositoryInterface;
use Drupal\entityqueue\EntitySubqueueInterface;
use Drupal\node\NodeInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for dept_topics module.
 */
class DeptTopicsCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The entity queue repo service object.
   *
   * @var \Drupal\entityqueue\EntityQueueRepositoryInterface
   */
  protected $eqRepo;

  /**
   * The entity type manager service object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etManager;

  /**
   * The migration lookup manager service object.
   *
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

  /**
   * D7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7DbConn;

  /**
   * Class constructor.
   */
  public function __construct(EntityQueueRepositoryInterface $eq_repo, EntityTypeManagerInterface $et_manager, MigrateUuidLookupManager $lookup_manager) {
    parent::__construct();
    $this->eqRepo = $eq_repo;
    $this->etManager = $et_manager;
    $this->lookupManager = $lookup_manager;
  }

  /**
   * Bulk generate any required topic and subtopic entity queues
   * based on known stored values in the D7 database.
   *
   * @command dept_topics:generateEntityQueues
   * @aliases dept_topics:geq
   */
  public function generateTopicAndSubtopicQueues() {
    // Verifies and rebuilds the list at /topics, per dept.
    $this->regenerateTopicsQueue();
    // Sets up the queues for each subtopic.
    $this->regenerateSubtopicQueues();
  }

  /**
   * Builds the top-level queue of items listed under /topics.
   */
  public function regenerateTopicsQueue() {
    $d7topicsByDept = $this->getD7TopicsByDept();

    if (!empty($d7topicsByDept)) {
      foreach ($d7topicsByDept as $d7_domain_id => $queue_item) {
        $dept_topics_queue_id = $this->d7DomainIdToEntityQueueId($d7_domain_id);
        // Check if we have the entityqueue for dept topics, create one if not.
        // Clear out + fill the entity queue for each dept.
        $this->emptyEntityQueue($dept_topics_queue_id);
        // Re-fill the queue with contents.
        $this->insertIntoEntityQueue($dept_topics_queue_id, $queue_item);
      }

      $this->io()->success("Finished");
    }
    else {
      $this->io()->warning("No D7 topic queue could be loaded");
    }
  }

  /**
   * Re-builds the subtopic entity queues and fills them
   * with content references in the weight they had in the
   * D7 table(s).
   */
  public function regenerateSubtopicQueues() {
    // Load all our D7 subtopic queues (stored in draggableviews table).
    $d7Subtopics = $this->getD7Subtopics();

    if (!empty($d7Subtopics)) {
      foreach ($d7Subtopics as $topic_id => $subtopic_queue) {
        $d9_topic_nid = reset($this->lookupManager->lookupBySourceNodeId([$topic_id]))['nid'];
        $topic_queue_id = 'topic_' . $d9_topic_nid;

        // Attempt to load the entity queue, if it's empty - create it.
        $eq = $this->etManager->getStorage('entity_queue')->load($topic_queue_id);

        if (!$eq instanceof EntityQueueInterface) {
          $topic_node = $this->etManager->getStorage('node')->load($d9_topic_nid);
          EntityQueue::create([
            'id' => $topic_queue_id,
            'label' => 'Topic: ' . $topic_node->label(),
            'handler' => 'multiple',
            'entity_settings' => [
              'target_type' => 'node',
              'handler' => 'default:node',
              'handler_settings' => [
                'target_bundles' => ['article', 'subtopic'],
                'sort' => ['field' => '_none', 'direction' => 'ASC'],
                'auto_create' => FALSE,
                'auto_create_bundle' => 'article',
              ],
              'queue_settings' => [
                'min_size' => 0,
                'max_size' => 0,
                'act_as_queue' => FALSE,
                'reverse' => FALSE,
              ],
            ],
          ])->save();

          $this->io()->writeln("Created queue " . $topic_queue_id . ", for Topic: " . $topic_node->label());
        }

        foreach ($subtopic_queue as $subqueue_item) {
          $d9subtopic_lookup = reset($this->lookupManager->lookupBySourceNodeId([$subqueue_item['nid']]));
          $d9subtopic_nid = $d9subtopic_lookup['nid'] ?? 0;

          if (empty($d9subtopic_nid)) {
            $this->io()->warning("Could not create subtopic subqueue for " . $subqueue_item['title'] . " as no D9 equivalent node could be loaded");
            continue;
          }

          $subtopic_queue_id = 'subtopic_' . $d9subtopic_nid;

          /** @var \Drupal\entityqueue\EntitySubqueueInterface $subqueue */
          $subqueue = $this->etManager->getStorage('entity_subqueue')->load($subtopic_queue_id);

          // Is there a subqueue for the subtopic? If not, create one.
          if (!$subqueue instanceof EntitySubqueueInterface) {
            $subqueue = EntitySubqueue::create([
              'queue' => $topic_queue_id,
              'name' => $subtopic_queue_id,
              'title' => $subqueue_item['title'],
            ])->save();
            $this->io()->writeln("Created subtopic queue " . $subtopic_queue_id . " for " . $subqueue_item['title']);
          }

          if (!$subqueue instanceof EntitySubqueueInterface) {
            $this->io()->warning("Unable to empty+fill subtopic queue " . $subtopic_queue_id . " as subqueue id reference did not implement EntitySubqueueInterface");
            continue;
          }

          $this->emptyEntityQueue($subtopic_queue_id);
          $this->fillSubtopicQueue($subqueue);
        }
      }

      $this->io()->success("Finished");
    }
    else {
      $this->io()->warning("No D7 subtopic queues found");
    }
  }

  /**
   * Fills up a subtopic queue with D7 content references.
   */
  protected function fillSubtopicQueue(EntitySubqueueInterface $subqueue) {
    $subtopic_d9_nid = str_replace('subtopic_', '', $subqueue->id());

    $articles = [];
    // Find the content this subtopic includes.
    $d7DbConn = Database::getConnection('default', 'drupal7db');
    $sql = "SELECT
      ds.view_name, ds.view_display, ds.entity_id, ds.weight, ds.parent, n.nid, n.type, n.title
      FROM {draggableviews_structure} ds
      JOIN {node} n on n.nid = ds.entity_id
      WHERE args LIKE '%" . $subtopic_d9_nid . '\",\"' . $subtopic_d9_nid . "%' AND
      n.type = 'article'
      ORDER by view_name, view_display, weight";

    $result = $d7DbConn->query($sql)->fetchAll();

    // Tidy up and reformat the results array.
    $article_nids = [];
    foreach ($result as $row_object) {
      $d7nid = $row_object->nid;
      $lookup = reset($this->lookupManager->lookupBySourceNodeId([$d7nid]));
      $d9nid = $lookup['nid'] ?? 0;

      if (!empty($d9nid)) {
        $article_nids[] = ['target_id' => $d9nid];
      }
    }

    if (!empty($article_nids)) {
      $subqueue->set('items', $article_nids);
      $subqueue->save();

      $this->io()->writeln("Added " . count($article_nids) . " articles to subqueue " . $subqueue->id());
    }
    else {
      $this->io()->writeln("No content found to add to subqueue " . $subqueue->id());
    }
  }

  /**
   * Empties out existing items in an entity queue.
   */
  protected function emptyEntityQueue($entity_queue_id) {
    /** @var \Drupal\entityqueue\EntitySubqueueInterface $eq */
    $eq = $this->etManager->getStorage('entity_subqueue')->load($entity_queue_id);
    $eq->set('items', []);
    $eq->save();
  }

  /**
   * Adds known queue data into an entity queue.
   *
   * @param string $entity_queue_id
   *   The mechine name of the entity queue.
   * @param array $queue_data
   *   The data to add.
   */
  protected function insertIntoEntityQueue(string $entity_queue_id, array $queue_data) {
    $eq = $this->etManager->getStorage('entity_subqueue')->load($entity_queue_id);

    if ($eq instanceof EntitySubqueueInterface) {
      foreach ($queue_data as $item) {
        $lookup_data = reset($this->lookupManager->lookupBySourceNodeId([$item['nid']]));
        $d9nid = $lookup_data['nid'] ?? 0;

        if (!empty($d9nid)) {
          $node = $this->etManager->getStorage('node')->load($d9nid);
          $eq->addItem($node);
        }
      }

      $eq->save();
      $this->io()->writeln("Updated entity queue " . $entity_queue_id);
    }
  }

  /**
   * Drupal 7 topics by department id.
   *
   * @return array
   *   The topics grouped by department id.
   */
  protected function getD7TopicsByDept() {
    $d7DbConn = Database::getConnection('default', 'drupal7db');

    $sql = "SELECT
      da.gid,
      d.machine_name,
      n.nid,
      n.type,
      n.title,
      ds.view_name,
      ds.view_display,
      ds.args,
      ds.weight
      FROM {node} n
      JOIN {draggableviews_structure} ds ON ds.entity_id  = n.nid
      JOIN {domain_access} da ON da.nid = n.nid
      JOIN {domain} d ON d.domain_id = da.gid
      WHERE ds.view_name = 'topics'
      ORDER BY da.gid, ds.weight";

    $result = $d7DbConn->query($sql)->fetchAll();

    // Tidy up and reformat the results array.
    $queue = [];
    foreach ($result as $row_id => $row_object) {
      $queue[$row_object->gid][] = [
        'nid' => $row_object->nid,
        'title' => $row_object->title,
        'weight' => $row_object->weight,
      ];
    }

    return $queue;
  }

  /**
   * Function to map Drupal 7 domain id to a defined
   * D9 entity queue id.
   *
   * @param int $d7_domain_id
   *   The Drupal 7 domain id.
   *
   * @return string
   *   The Drupal 9 entity queue id.
   */
  protected function d7DomainIdToEntityQueueId(int $d7_domain_id) {
    $entity_queue_id_prefix = 'topics_dept_';
    $suffix = '';

    switch ($d7_domain_id) {
      case 1:
        $suffix = 'executive_office';
        break;

      case 2:
        $suffix = 'daera';
        break;

      case 4:
        $suffix = 'economy';
        break;

      case 5:
        $suffix = 'nigov';
        break;

      case 6:
        $suffix = 'education';
        break;

      case 7:
        $suffix = 'finance';
        break;

      case 8:
        $suffix = 'health';
        break;

      case 9:
        $suffix = 'infrastructure';
        break;

      case 12:
        $suffix = 'justice';
        break;

      case 13:
        $suffix = 'communities';
        break;
    }

    return $entity_queue_id_prefix . $suffix;
  }

  /**
   * Fetch an array of Drupal 7 subtopics.
   *
   * @return array
   *   The D7 subtopics keyed by the parent topic id.
   */
  protected function getD7Subtopics() {
    $d7DbConn = Database::getConnection('default', 'drupal7db');

    $subtopics = [];

    $topics_to_query = $d7DbConn->query("SELECT nid,trim(title) as trim_title from {node} where type='topic' order by trim(title)")->fetchAll();
    foreach ($topics_to_query as $topic) {
      $topic_id = $topic->nid;
      $topic_title = $topic->trim_title;

      $sql = "SELECT
      ds.view_name, ds.view_display, ds.entity_id, ds.weight, ds.parent, n.nid, n.type, n.title
      FROM {draggableviews_structure} ds
      JOIN {node} n on n.nid = ds.entity_id
      WHERE args LIKE '%" . $topic_id . '\",\"' . $topic_id . "%' AND
      n.type = 'subtopic'
      ORDER by view_name, view_display, weight";

      $result = $d7DbConn->query($sql)->fetchAll();

      // Tidy up and reformat the results array.
      foreach ($result as $row_object) {
        $subtopics[$topic_id][] = [
          'nid' => $row_object->nid,
          'title' => $row_object->title,
          'weight' => $row_object->weight,
        ];
      }
    }

    return $subtopics;
  }

}
