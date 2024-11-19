<?php

namespace Drupal\dept_migrate\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dept_core\DepartmentManager;
use Drupal\dept_migrate\LookupHelper;
use Drupal\dept_migrate\MigrateUtils;
use Drush\Commands\DrushCommands;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Drush commands processing Departmental migrations.
 */
class DeptTopicMigrationCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  use StringTranslationTrait;
  use SiteAliasManagerAwareTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConn;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7conn;

  /**
   * Migration Lookup Manager.
   *
   * @var \Drupal\dept_migrate\LookupHelper
   */
  protected $lookupHelper;

  /**
   * EntityTypeManager service object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Department manager service object.
   *
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected $departmentManager;

  /**
   * List of self referencing Subtopic nodes.
   *
   * @var array
   */
  private $selfReferencedTopicsCount = [];

  /**
   * Holds total number of subtopics updated.
   *
   * @var int
   */
  private $subtopicUpdateCount = 0;


  /**
   * The Drupal 7 GID for the domain.
   *
   * @var int|string
   */
  private $domainGid;

  /**
   * Command constructor.
   */
  public function __construct(Connection $database, Connection $d7_database, LookupHelper $lookup_helper, EntityTypeManagerInterface $etm, DepartmentManager $dept_manager) {
    parent::__construct();
    $this->dbConn = $database;
    $this->d7conn = $d7_database;
    $this->lookupHelper = $lookup_helper;
    $this->entityTypeManager = $etm;
    $this->departmentManager = $dept_manager;
  }

  /**
   * Create Topic child contents.
   *
   * @command dept:topics-child-contents
   * @aliases tcc
   */
  public function topicChildContents($domain_id) {
    $topic_update_count = 0;

    if (empty($domain_id)) {
      return;
    }

    $d7_domain = MigrateUtils::d9DomainToD7Domain($domain_id);
    $this->domainGid = $this->d7conn->query("SELECT domain_id FROM domain WHERE machine_name = '" . $d7_domain . "'")->fetchField(0);

    if (empty($this->domainGid)) {
      $this->io()->error("Domain not found.");
      return;
    }

    $this->io()->writeln("Creating topic contents for $domain_id.");

    // Remove existing topic content reference links for the given department.
    $this->dbConn->query("DELETE tc FROM node__field_topic_content tc
      LEFT JOIN node__field_domain_source ds
      ON tc.entity_id = ds.entity_id
      WHERE ds.field_domain_source_target_id = '" . $domain_id . "'
      AND tc.bundle = 'topic'"
    )->execute();

    // Remove existing subtopic content reference links for the given department.
    $this->dbConn->query("DELETE tc FROM node__field_topic_content tc
      LEFT JOIN node__field_domain_source ds
      ON tc.entity_id = ds.entity_id
      WHERE ds.field_domain_source_target_id = '" . $domain_id . "'
      AND tc.bundle = 'subtopic'"
    )->execute();

    $domain_topic_ids = $this->dbConn->query("
        SELECT ds.entity_id AS nid FROM node__field_domain_source ds
        INNER JOIN node_field_data nfd
        ON ds.entity_id = nfd.nid
        WHERE ds.bundle = 'topic'
        AND ds.field_domain_source_target_id = '$domain_id'"
    )->fetchCol(0);

    $domain_topics = $this->entityTypeManager->getStorage('node')->loadMultiple(array_values($domain_topic_ids));

    ProgressBar::setFormatDefinition('custom', "%bar% %current%/%max% -- %message%");
    $progress_bar = $this->io()->createProgressBar(count($domain_topics));
    $progress_bar->setFormat('custom');
    $progress_bar->setMessage('Updating Topic contents for ' . $domain_id);
    $progress_bar->start();

    foreach ($domain_topics as $domain_topic) {
      $progress_bar->setMessage('Updating ' . $domain_topic->label());
      $child_contents = $this->subtopicsByTopic($domain_topic->id());

      if ($child_contents) {
        $delta = 0;
        $topic_update_count++;

        /* @var $child_content \Drupal\dept_migrate\LookupEntry */
        foreach ($child_contents as $child_content) {

          $child_content_exists = $this->dbConn->query("SELECT
          * FROM {node__field_topic_content}
          WHERE entity_id = :entity_id
          AND revision_id = :rev_id
          AND field_topic_content_target_id = :target_id", [
            ':entity_id' => $domain_topic->id(),
            ':rev_id' => $domain_topic->getRevisionId(),
            ':target_id' => $child_content->id(),
          ])->fetchAll();

          if (empty($child_content_exists)) {
            $this->dbConn->insert('node__field_topic_content')
              ->fields([
                'bundle' => 'topic',
                'deleted' => 0,
                'entity_id' => $domain_topic->id(),
                'revision_id' => $domain_topic->getRevisionId(),
                'langcode' => 'en',
                'delta' => $delta++,
                'field_topic_content_target_id' => $child_content->id(),
              ])
              ->execute();

            if ($child_content->type() === 'subtopic') {
              $this->subtopicChildContents($child_content->id());
            }
          }
        }
      }

      $progress_bar->advance();
    }

    // @phpstan-ignore-next-line
    $process = $this->processManager()->drush($this->siteAliasManager()->getSelf(), 'cache:rebuild', []);
    $process->mustRun();

    $progress_bar->setMessage('Finished');
    $progress_bar->finish();
    $this->io()->writeln('');

    $this->io()->writeln('Topics updated: ' . $topic_update_count);
    $this->io()->writeln('Subtopics updated: ' . $this->subtopicUpdateCount);

    if (count($this->selfReferencedTopicsCount) > 0) {
      $selfRefList = implode(",", $this->selfReferencedTopicsCount);
      $this->logger->warning("Self referencing topics: " . $selfRefList);
      $this->io()->caution("Rubber chickens awarded: " . count($this->selfReferencedTopicsCount) . " 🐔");
    }

  }

  /**
   * Preview content of a topic.
   *
   * @command dept:preview-topics-child-contents
   * @aliases ptcc
   */
  public function previewTopicChildContents($topic_id) {
    print_r($this->subtopicsByTopic($topic_id));
  }

  /**
   * Create Subtopic child contents.
   *
   * @param string $subtopic_id
   *   A Drupal 10 topic id.
   */
  private function subtopicChildContents($subtopic_id) {
    $child_items = $this->subtopicsByTopicWithArticles($subtopic_id);

    if (empty($child_items)) {
      return;
    }

    $this->subtopicUpdateCount++;

    $subtopic = $this->entityTypeManager->getStorage('node')->load($subtopic_id);

    foreach ($child_items as $child_item) {

      if (empty($child_item->id())) {
        $this->io()->warning("No D10 node for the D7 node id: " . $child_item->d7Id());
        continue;
      }

      // If the child node is a reference to the parent, ignore it.
      if ($child_item->id() == $subtopic_id) {
        $this->selfReferencedTopicsCount[] = $subtopic_id;
        continue;
      }

      // If the node hasn't been migrated, move along.
      if (empty($child_item->id())) {
        continue;
      }

      // Don't add child pages of books to the topic contents, only the book entry.
      if ($this->isBookPage($child_item->id())) {
        continue;
      }

      $this->createSubtopicContentRefLink($subtopic, $child_item->id());

      // If the child node is a subtopic then process it for child content.
      if ($child_item->type() === 'subtopic') {
        $this->subtopicChildContents($child_item->id());
      }
    }
  }

  /**
   * Returns child content for top level topics.
   *
   * @param string $topic_id
   *   A Drupal 10 topic id.
   *
   * @return array
   *   Array of LookupEntry items.
   */
  private function subtopicsByTopic($topic_id) {
    $results = [];
    // Lookup the D7 nid for the given D10 topic id.
    $topic_id_d7 = $this->lookupHelper->destination()->id($topic_id)->d7Id();

    if (empty($topic_id)) {
      $this->io()->warning("D7 nid not found for D10 nid " . $topic_id);
      return $results;
    }

    $sql = "SELECT node.nid AS nid, node.title AS node_title, node.created AS node_created, COALESCE(draggableviews_structure.weight, 2147483647) AS draggableviews_structure_weight_coalesce
            FROM node
            LEFT JOIN flagging flagging_node ON node.nid = flagging_node.entity_id AND (flagging_node.fid = '5')
            LEFT JOIN domain_access domain_access ON node.nid = domain_access.nid AND (domain_access.realm = 'domain_id')
            LEFT JOIN field_data_field_parent_subtopic field_data_field_parent_subtopic ON node.nid = field_data_field_parent_subtopic.entity_id AND (field_data_field_parent_subtopic.entity_type = 'node' AND field_data_field_parent_subtopic.deleted = '0')
            LEFT JOIN field_data_field_parent_topic field_data_field_parent_topic ON node.nid = field_data_field_parent_topic.entity_id AND (field_data_field_parent_topic.entity_type = 'node' AND field_data_field_parent_topic.deleted = '0')
            LEFT JOIN field_data_field_site_topics field_data_field_site_topics ON node.nid = field_data_field_site_topics.entity_id AND (field_data_field_site_topics.entity_type = 'node' AND field_data_field_site_topics.deleted = '0')
            LEFT JOIN draggableviews_structure draggableviews_structure ON node.nid = draggableviews_structure.entity_id AND draggableviews_structure.view_name = 'draggable_subtopics' AND draggableviews_structure.view_display = 'panel_pane_1' AND draggableviews_structure.args = :nid_args
            WHERE (( (field_data_field_parent_topic.field_parent_topic_target_id = :nid ) OR (field_data_field_site_topics.field_site_topics_target_id = :nid ) )AND(( (node.status = '1') AND (node.type IN  ('subtopic')) AND (((domain_access.realm = 'domain_id' AND domain_access.gid = :gid) OR (domain_access.realm = 'domain_site' AND domain_access.gid = 0))) AND (field_data_field_parent_subtopic.field_parent_subtopic_target_id IS NULL ) AND (flagging_node.uid IS NULL ) )))
            ORDER BY draggableviews_structure_weight_coalesce ASC, node_created DESC";

    $nodes = $this->d7conn->query($sql, [
      ':nid_args' => '["' . $topic_id_d7 . '","' . $topic_id_d7 . '"]',
      ':nid' => $topic_id_d7,
      ':gid' => $this->domainGid,
    ])->fetchAll();

    foreach ($nodes as $node) {
      $node = $this->lookupHelper->source()->id($node->nid);

      if ($node) {
        $results[] = $node;
      }
    }

    return $results;
  }

  /**
   * Returns child content for subtopics.
   *
   * @param string $subtopic_id
   *   A Drupal 10 subtopic id.
   *
   * @return array
   *   Array of LookupEntry items.
   */
  private function subtopicsByTopicWithArticles($subtopic_id) {
    $results = [];
    // Lookup the D7 nid for the given D10 topic id.
    $subtopic_d7_id = $this->lookupHelper->destination()->id($subtopic_id)->d7Id();

    if (empty($subtopic_d7_id)) {
      $this->io()->warning("D7 nid not found for D10 nid " . $subtopic_id);
      return $results;
    }

    $sql = "SELECT node.nid AS nid, node.title AS node_title, node.created AS node_created, COALESCE(draggableviews_structure.weight, 2147483647) AS draggableviews_structure_weight_coalesce
            FROM node
            LEFT JOIN flagging flagging_node ON node.nid = flagging_node.entity_id AND (flagging_node.fid = '5')
            LEFT JOIN domain_access domain_access ON node.nid = domain_access.nid AND (domain_access.realm = 'domain_id')
            LEFT JOIN field_data_field_site_subtopics field_data_field_site_subtopics ON node.nid = field_data_field_site_subtopics.entity_id AND (field_data_field_site_subtopics.entity_type = 'node' AND field_data_field_site_subtopics.deleted = '0')
            LEFT JOIN field_data_field_parent_subtopic field_data_field_parent_subtopic ON node.nid = field_data_field_parent_subtopic.entity_id AND (field_data_field_parent_subtopic.entity_type = 'node' AND field_data_field_parent_subtopic.deleted = '0')
            LEFT JOIN draggableviews_structure draggableviews_structure ON node.nid = draggableviews_structure.entity_id AND draggableviews_structure.view_name = 'draggable_subtopics' AND draggableviews_structure.view_display = 'panel_pane_2' AND draggableviews_structure.args = :nid_args
            WHERE (( (field_data_field_site_subtopics.field_site_subtopics_target_id = :nid ) OR (field_data_field_parent_subtopic.field_parent_subtopic_target_id = :nid ) )AND(( (node.status = '1') AND (node.type IN  ('application', 'article', 'project', 'subtopic')) AND (((domain_access.realm = 'domain_id' AND domain_access.gid = :gid) OR (domain_access.realm = 'domain_site' AND domain_access.gid = 0))) AND (flagging_node.uid IS NULL ) )))
            ORDER BY draggableviews_structure_weight_coalesce ASC, node_created DESC";

    $nodes = $this->d7conn->query($sql, [
      ':nid_args' => '["' . $subtopic_d7_id . '","' . $subtopic_d7_id . '"]',
      ':nid' => $subtopic_d7_id,
      ':gid' => $this->domainGid,
    ])->fetchAll();

    foreach ($nodes as $node) {
      $node = $this->lookupHelper->source()->id($node->nid);

      if ($node) {
        $results[] = $node;
      }
    }

    return $results;
  }

  /**
   * Creates an entity reference link in the topic contents field
   *
   * @param \Drupal\node\Entity\Node $parent_node
   *   Drupal 10 node to which we add the entity reference.
   * @param int $child_nid
   *   Drupal 10 entity id.
   */
  private function createSubtopicContentRefLink($parent_node, $child_nid) {
    // Fetch the max delta number, so we can add our new entity reference
    // to the bottom of the list.
    $delta = $this->dbConn->query("SELECT MAX(delta) FROM node__field_topic_content WHERE entity_id = :parent_nid", [
      ':parent_nid' => $parent_node->id()
    ])->fetchField(0);

    // If we don't have a delta, set to -1 as we will increment in the insert
    // call and the delta is zero indexed.
    $delta = $delta ?? -1;

    $existing_topic_data = $this->dbConn->query("SELECT
        nftc.*
        FROM {node__field_topic_content} nftc
        WHERE nftc.entity_id = :entity_id
        AND nftc.revision_id = :rev_id
        AND nftc.field_topic_content_target_id = :target_id", [
          ':entity_id' => $parent_node->id(),
          ':rev_id' => $parent_node->getRevisionId(),
          ':target_id' => $child_nid,
        ]
    )->fetchAll();

    if (empty($existing_topic_data)) {
      $this->dbConn->insert('node__field_topic_content')
        ->fields([
          'bundle' => $parent_node->bundle(),
          'deleted' => 0,
          'entity_id' => $parent_node->id(),
          'revision_id' => $parent_node->getRevisionId(),
          'langcode' => 'en',
          'delta' => ++$delta,
          'field_topic_content_target_id' => $child_nid,
        ])
        ->execute();
    }
  }

  /**
   * Determines if a node is a page within a book content type.
   *
   * @param int $node_id
   *   The Drupal 10 node ID to check.
   * @return bool
   *   True if the node is a book page, otherwise false.
   */
  protected function isBookPage($node_id) {
    $book_nids = \Drupal::cache()->get('book_page_nids');

    if (empty($book_nids)) {
      $book_nids = $this->dbConn->query("SELECT book.nid FROM book WHERE book.depth > 1")->fetchAllAssoc('nid');
      \Drupal::cache()->set('book_page_nids', $book_nids, strtotime('+1 hour', time()));
    }
    else {
      $book_nids = $book_nids->data;
    }

    return array_key_exists($node_id, $book_nids);
  }

}
