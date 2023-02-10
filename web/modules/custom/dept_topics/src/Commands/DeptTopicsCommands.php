<?php

namespace Drupal\dept_topics\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dept_migrate\MigrateSupport;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for dept_topics module.
 */
class DeptTopicsCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The entity type manager service object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $etManager;

  /**
   * The migration lookup manager service object.
   *
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected MigrateUuidLookupManager $lookupManager;

  /**
   * The migration support service object.
   *
   * @var \Drupal\dept_migrate\MigrateSupport
   */
  protected MigrateSupport $migrateSupport;

  /**
   * D7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $d7DbConn;

  /**
   * DB object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $dbconn;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $et_manager, MigrateUuidLookupManager $lookup_manager, MigrateSupport $migrate_support, Connection $dbconn) {
    parent::__construct();
    $this->etManager = $et_manager;
    $this->lookupManager = $lookup_manager;
    $this->migrateSupport = $migrate_support;
    $this->dbconn = $dbconn;
    $this->d7DbConn = Database::getConnection('default', 'drupal7db');
  }

  /**
   * Bulk generate any required topic and subtopic entity queues
   * based on known stored values in the D7 database.
   *
   * @command dept_topics:syncTopicSubtopicOrder
   * @aliases dept_topics:ststo
   */
  public function generateTopicAndSubtopicQueues() {
    $this->io()->writeln("Syncing topics");
    $this->syncTopics();

    $this->io()->writeln("Syncing topics");
    $this->syncSubtopics();

    $this->io()->success("Done!");
  }

  /**
   * Barebones function to return a dept id based on D7 domain id.
   *
   * @param int $d7_id
   *   The D7 domain id.
   *
   * @return string
   *   The D9 dept id.
   */
  private function domainD7ToD9(int $d7_id) {
    return match ($d7_id) {
      1, 3, 10, 11 => 'nigov',
      2 => 'daera',
      4 => 'economy',
      5 => 'execoffice',
      6 => 'education',
      7 => 'finance',
      8 => 'health',
      9 => 'infrastructure',
      12 => 'justice',
      13 => 'communities',
    };
  }

  /**
   * Builds the top-level queue of items listed under /topics.
   */
  public function syncTopics() {
    $d7topicsByDept = $this->getD7TopicsByDept();

    if (empty($d7topicsByDept)) {
      return;
    }

    foreach ($d7topicsByDept as $d7_domain_id => $topics) {
      foreach ($topics as $topic_data) {
        $d9_dept_id = $this->domainD7ToD9($d7_domain_id);
        $d9_topic_lookup = reset($this->lookupManager->lookupBySourceNodeId([$topic_data['nid']]));

        $row = [
          'view_name' => 'content_stacks',
          'view_display' => 'dept_topics',
          'args' => '["' . $d9_dept_id . '"]',
          'entity_id' => $d9_topic_lookup['nid'],
          'weight' => $topic_data['weight'],
        ];
        $this->dbconn->insert('draggableviews_structure')
          ->fields($row)->execute();

        $this->io()->writeln("Inserted row for Topic " . $topic_data['title']);
      }
    }
  }

  /**
   * Re-builds the subtopic entity queues and fills them
   * with content references in the weight they had in the
   * D7 table(s).
   */
  public function syncSubtopics() {
    // Load all our D7 subtopic queues (stored in draggableviews table).
    $d7Subtopics = $this->getD7Subtopics();

    if (empty($d7Subtopics)) {
      $this->io()->warning("No D7 subtopic queues found");
      return;
    }

    foreach ($d7Subtopics as $d7_topic_id => $d7_subtopic_data) {
      $topic_lookup = reset($this->lookupManager->lookupBySourceNodeId([$d7_topic_id]));
      $d9_topic_id = $topic_lookup['nid'];
      $d9_topic_title = $topic_lookup['title'];

      foreach ($d7_subtopic_data as $d7_subtopic_info) {
        $d7_subtopic_id = $d7_subtopic_info['nid'];
        $d7_subtopic_weight = $d7_subtopic_info['weight'];

        $this->io()->writeln($d9_topic_title . " - processing subtopic d7 id " . $d7_subtopic_id);

        if (empty($d7_subtopic_id)) {
          $this->io()->writeln("Couldn't find the D7 subtopic id for " . $d7_subtopic_id);
          continue;
        }

        // Lookup the D9 subtopic node id.
        $lookup_data = reset($this->lookupManager->lookupBySourceNodeId([$d7_subtopic_id]));
        $d9_subtopic_id = $lookup_data['nid'] ?? 0;

        if (empty($d9_subtopic_id)) {
          $this->io()
            ->writeln("No D9 subtopic node found for D7 id " . $d7_subtopic_id);
          continue;
        }

        // Is there a draggable views entry?
        $existing_dv_data = \Drupal::database()
          ->query("SELECT * FROM {draggableviews_structure} WHERE view_name = :display AND args = :args", [
            ':display' => 'topic_subtopics',
            ':args' => '["' . $d9_topic_id . '"]'
          ])->fetchAll();

        if (empty($existing_dv_data)) {
          // Pull in D7 draggableviews data and insert the required rows.
          $this->io()->writeln("Adding record for " . $d9_subtopic_id);

          $row = [
            'view_name' => 'content_stacks',
            'view_display' => 'topic_subtopics',
            'args' => '["' . $d9_topic_id . '"]',
            'entity_id' => $d9_subtopic_id,
            'weight' => $d7_subtopic_weight,
          ];
          $this->dbconn->insert('draggableviews_structure')
            ->fields($row)->execute();

          // Per-subtopic, insert values for any articles referenced by them.
          $this->syncSubtopicArticles($d7_subtopic_id, $d9_subtopic_id);
        }
        else {
          $this->io()
            ->writeln("Existing data in draggableviews_structure table for subtopic id " . $d9_subtopic_id);
        }
      }
    }

    $this->io()->success("Finished");
  }

  /**
   * Function to map D7 draggableviews data for a subtopic
   * against the D9 equivalent content.
   *
   * @param int $d7_subtopic_id
   *   The Drupal 7 subtopic id value.
   * @param int $d9_subtopic_id
   *   The Drupal 9 subtopic id value.
   */
  protected function syncSubtopicArticles(int $d7_subtopic_id, int $d9_subtopic_id) {
    $subtopic_articles = $this->getD7SubtopicArticles($d7_subtopic_id);
    if (!empty($subtopic_articles)) {
      $subtopic_articles = reset($subtopic_articles);
      foreach ($subtopic_articles as $row) {
        $article_lookup = $this->lookupManager->lookupBySourceNodeId([$row['nid']]);
        $article_d9_nid = reset($article_lookup)['nid'];

        $row = [
          'view_name' => 'content_stacks',
          'view_display' => 'subtopic_articles',
          'args' => '["' . $d9_subtopic_id . '"]',
          'entity_id' => $article_d9_nid,
          'weight' => $row['weight'],
        ];

        $this->dbconn->insert('draggableviews_structure')
          ->fields($row)->execute();
      }
    }
  }

  /**
   * Drupal 7 topics by department id.
   *
   * @return array
   *   The topics grouped by department id.
   */
  protected function getD7TopicsByDept() {
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

    $result = $this->d7DbConn->query($sql)->fetchAll();

    // Tidy up and reformat the results array.
    $queue = [];
    foreach ($result as $row_id => $row_object) {
      $queue[$row_object->gid][] = [
        'nid' => $row_object->nid,
        'title' => $row_object->title,
        'weight' => $row_object->weight,
        'd7_domain_name' => $row_object->machine_name,
      ];
    }

    return $queue;
  }

  /**
   * Fetch an array of Drupal 7 subtopics.
   *
   * @return array
   *   The D7 subtopics keyed by the parent topic id.
   */
  protected function getD7Subtopics() {
    $subtopics = [];

    $topics_to_query = $this->d7DbConn->query("SELECT nid,trim(title) as trim_title from {node} where type='topic' order by trim(title)")->fetchAll();
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

      $result = $this->d7DbConn->query($sql)->fetchAll();

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

  /**
   * Class to fetch weighted, article content for a given D7 subtopic.
   *
   * @param int $d7_subtopic_id
   *   The D7 subtopic id.
   * @return array
   *   D7 articles for the subtopic specified.
   */
  protected function getD7SubtopicArticles(int $d7_subtopic_id) {
    $articles = [];

    $sql = "SELECT
    ds.view_name, ds.view_display, ds.entity_id, ds.weight, ds.parent, n.nid, n.type, n.title
    FROM {draggableviews_structure} ds
    JOIN {node} n on n.nid = ds.entity_id
    WHERE args LIKE '%" . $d7_subtopic_id . '\",\"' . $d7_subtopic_id . "%' AND
    n.type = 'article'
    ORDER by view_name, view_display, weight";

    $result = $this->d7DbConn->query($sql)->fetchAll();

    // Tidy up and reformat the results array.
    foreach ($result as $row_object) {
      $articles[$d7_subtopic_id][] = [
        'nid' => $row_object->nid,
        'title' => $row_object->title,
        'weight' => $row_object->weight,
      ];
    }

    return $articles;
  }

}
