<?php

namespace Drupal\dept_migrate\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dept_core\DepartmentManager;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\node\NodeInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands processing Departmental migrations.
 */
class DeptMigrationCommands extends DrushCommands {

  use StringTranslationTrait;

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
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

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
   * Drupal\Core\Logger\LoggerChannel definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * List of self referencing Subtopic nodes.
   *
   * @var array
   */
  private $selfReferencedTopicsCount = [];

  /**
   * List of missing Subtopic child nodes.
   *
   * @var array
   */
  private $missingTopicsContentCount = [];

  /**
   * Command constructor.
   */
  public function __construct(Connection $database, Connection $d7_database, MigrateUuidLookupManager $lookup_manager, EntityTypeManagerInterface $etm, DepartmentManager $dept_manager, LoggerChannel $logger) {
    parent::__construct();
    $this->dbConn = $database;
    $this->d7conn = $d7_database;
    $this->lookupManager = $lookup_manager;
    $this->entityTypeManager = $etm;
    $this->departmentManager = $dept_manager;
    $this->logger = $logger;
  }

  /**
   * Updates all internal /node/XXXX links from their D7 to the D9 nid.
   *
   * @command dept:updatelinks
   * @aliases uplnks
   */
  public function updateInternalLinks() {

    $fields = [
      'body',
      'field_additional_info',
      'field_summary',
    ];

    $this->io()->title("Updating and converting content links");

    foreach ($fields as $field) {

      $this->io()->write("Updating links within $field field");
      // Select all 'node/XXXX' links from the current field table.
      $table = 'node__' . $field;
      $query = $this->dbConn->select($table, 't');
      $query->addField('t', 'entity_id', 'nid');
      $query->addField('t', $field . '_value', 'value');
      $query->condition($field . '_value', 'node\/[0-9]+', 'REGEXP');

      $results = $query->execute()->fetchAll();

      foreach ($results as $result) {
        // Update all node links.
        $updated_value = preg_replace_callback(
          '/(<a href="\/node\/)(\d+)/m',
          function ($matches) {
            // Fetch the new D9 nid for the D7 nid.
            $d9_lookup = $this->lookupManager->lookupBySourceNodeId([$matches[2]]);

            if (!empty($d9_lookup)) {
              $node_data = current($d9_lookup);

              if (!empty($node_data['nid'])) {
                $d9_nid = $node_data['nid'];
                $d9_uuid = $this->dbConn->query('SELECT uuid FROM {node} WHERE nid = :nid', [':nid' => $d9_nid])->fetchField();
                // Replace the '<a href="/nodeXXX' markup with LinkIt markup.
                return '<a data-entity-substitution="canonical" data-entity-type="node" data-entity-uuid="' . $d9_uuid . '" href="/node/' . $node_data['nid'];
              }
            }
          },
          $result->value
        );

        // Update the field value with our new links.
        $this->dbConn->update($table)
          ->fields([$field . '_value' => $updated_value])
          ->condition('entity_id', $result->nid, '=')
          ->execute();
      }

      $this->io()->writeln(" ✅");
    }
    $this->io()->success("Finished");
  }

  /**
   * Syncs the featured content shown on the homepage from D7 to D9.
   *
   * @command dept:sync-homepage-content
   * @aliases shc
   */
  public function syncHomepageFeaturedContent() {
    $this->io()->title("Synchronising homepage featured content from D7 to D9");
    $node_storage = $this->entityTypeManager->getStorage('node');

    // For each dept, find the FCL for it.
    $depts = $this->departmentManager->getAllDepartments();

    foreach ($depts as $dept) {
      $this->io()->writeln('Processing ' . $dept->label());

      // NB: accessCheck = FALSE because otherwise we can't load the node object.
      $query = $node_storage->getQuery()
        ->condition('type', 'featured_content_list')
        ->condition('status', 1)
        ->condition('field_fcl_type', 'homepage_news')
        ->condition('field_domain_source', $dept->id())
        ->range(0, 1)
        ->accessCheck(FALSE)
        ->execute();

      if (empty($query)) {
        // Create a new FCL node for this dept if we have none already.
        $fcl_node = $node_storage->create([
          'title' => $dept->label() . ' homepage featured content',
          'type' => 'featured_content_list',
          'status' => 1,
          'field_domain_source' => ['target_id' => $dept->id()],
          'field_domain_access' => ['target_id' => $dept->id()],
          'field_fcl_type' => 'homepage_news',
        ]);
      }
      else {
        $fcl_nid = reset($query);
        $fcl_node = $node_storage->load($fcl_nid);
      }

      if ($fcl_node instanceof NodeInterface === FALSE) {
        continue;
      }

      // Find the nodes for the content featured for this dept in D7.
      $d7_featured_nodes = [];

      if ($dept->id() === 'nigov' || $dept->id() === 'executiveoffice') {
        $d7_query = $this->d7conn->query("SELECT n.nid
          FROM {node} n
          JOIN {field_data_eq_node} fdeqn ON fdeqn.eq_node_target_id = n.nid AND fdeqn.bundle = 'niexec_homepage_news'
          JOIN {entityqueue_subqueue} eqs ON fdeqn.entity_id = eqs.subqueue_id AND eqs.name = 'niexec_homepage_news'
          LEFT JOIN {domain_access} da ON n.nid = da.nid
          WHERE n.status = 1
          AND n.type = 'news'
          AND da.gid <= 1
          ORDER BY fdeqn.delta ASC"
        );

        if (empty($d7_query)) {
          continue;
        }

        $d9_featured_nodes = [];

        foreach ($d7_query as $row) {
          $d9_lookup = $this->lookupManager->lookupBySourceNodeId([$row->nid]);

          if (empty($d9_lookup)) {
            $this->io()->warning('No lookup result found for D7 node ' . $row->nid . ' - ' . $row->title);
            continue;
          }

          $d9_lookup = reset($d9_lookup);
          if (!empty($d9_lookup['nid'])) {
            $d9_featured_nodes[] = $d9_lookup['nid'];
          }
        }
      }
      else {
        $d7_query = $this->d7conn->query("SELECT DISTINCT
            n.nid,
            n.type,
            n.title,
            d.sitename
        FROM {node} n
        JOIN {domain_access} da ON da.nid = n.nid
        JOIN {domain} d ON d.domain_id = da.gid
        LEFT JOIN {flagging} fn_flag ON fn_flag.entity_id = n.nid AND fn_flag.fid = 1
        LEFT JOIN {flagging} hp_flag ON hp_flag.entity_id = n.nid AND hp_flag.fid = 2
        JOIN {flag_counts} fc on fc.entity_id = n.nid
        JOIN {field_data_field_published_date} fdfpd ON fdfpd.entity_id = n.nid
        WHERE d.sitename = :site_id AND n.status = 1
        ORDER BY
            fn_flag.uid IS NOT NULL DESC,
            hp_flag.uid IS NOT NULL DESC,
            fdfpd.field_published_date_value DESC
        LIMIT 10", [':site_id' => $dept->id()]
        );

        if (empty($d7_query)) {
          continue;
        }

        // Find them in D9 and add them as the values for the FCL items.
        $d9_featured_nodes = [];

        foreach ($d7_query as $row) {
          $d9_lookup = $this->lookupManager->lookupBySourceNodeId([$row->nid]);

          if (empty($d9_lookup)) {
            $this->io()->warning('No lookup result found for D7 node ' . $row->nid . ' - ' . $row->title);
            continue;
          }

          $d9_lookup = reset($d9_lookup);
          if (!empty($d9_lookup['nid'])) {
            $d9_featured_nodes[] = $d9_lookup['nid'];
          }
        }
      }

      if (!empty($d9_featured_nodes)) {
        $fcl_node->field_featured_content->setValue($d9_featured_nodes);
        $fcl_node->save();
      }
      else {
        $this->io()->warning("No featured content found for " . $dept->label());
      }
    }

    $this->io()->success("Finished");
  }

  /**
   * Insert content field entries for Topic (top level) nodes.
   *
   *    * @param string $domain_id
   *   The domain id to update.
   *
   * @command dept:topic-child-content
   * @aliases tcc
   */
  public function createTopicChildContent($domain_id) {

    if (empty($domain_id)) {
      return;
    }

    $topic_content_sql = "
      WITH content_stack_cte (nid, type, title, weight) AS (
        SELECT
          n.nid,
          n.type,
          n.title,
          ds.weight
        FROM draggableviews_structure ds
        JOIN node n ON n.nid = ds.entity_id
        WHERE ds.view_name = 'draggable_subtopics'
        AND ds.view_display = 'panel_pane_1'
        AND ds.args LIKE '[\":nid\",\":nid\"]'
        AND n.status = 1
        UNION
        SELECT
          st_n.nid,
          st_n.type,
          st_n.title,
          99 as weight
        FROM node st_n
        JOIN field_data_field_parent_topic nfps ON st_n.nid = nfps.entity_id
        LEFT OUTER JOIN field_data_field_parent_subtopic nfpst ON st_n.nid = nfpst.entity_id
        WHERE st_n.type = 'subtopic'
        AND nfps.field_parent_topic_target_id = :nid
        AND st_n.status = 1
        AND nfpst.entity_id IS NULL
        UNION
        SELECT
          ar_n.nid,
          ar_n.type,
          ar_n.title,
          99 as weight
        FROM node ar_n
        JOIN field_data_field_site_subtopics nfss ON ar_n.nid = nfss.entity_id
        WHERE ar_n.type = 'article' AND nfss.field_site_subtopics_target_id = :nid
        AND ar_n.status = 1
      )
      SELECT DISTINCT nid, type, title FROM content_stack_cte
      ORDER BY weight, title";

    $domain_topics_d9 = $this->dbConn->query("
        SELECT ds.entity_id, nfd.vid FROM node__field_domain_source ds
        INNER JOIN node_field_data nfd
        ON ds.entity_id = nfd.nid
        WHERE ds.bundle = 'topic'
        AND ds.field_domain_source_target_id = '$domain_id'"
    )->fetchAllAssoc('entity_id');

    $domain_topics_d7 = $this->lookupManager->lookupByDestinationNodeIds(array_keys($domain_topics_d9));

    foreach ($domain_topics_d7 as $topic_id => $topic) {
      // Fetch the nid, type and title of D7 topic child content nodes.
      $topic_ref_nodes_d7 = $this->d7conn->query(
        $topic_content_sql, [
          ':nid' => $topic['d7nid']
        ])->fetchAllKeyed(0, 2);
      $delta = 0;

      // Loop through each child node, lookup the D9 counterpart node and
      // insert into the topic content entity references for the topic.
      foreach ($topic_ref_nodes_d7 as $d7_node_id => $title) {
        $node = $this->lookupManager->lookupBySourceNodeId([$d7_node_id]);

        $this->dbConn->insert('node__field_topic_content')
          ->fields([
            'bundle' => 'Topic',
            'deleted' => 0,
            'entity_id' => $topic['nid'],
            'revision_id' => $topic['vid'],
            'langcode' => 'en',
            'delta' => $delta++,
            'field_topic_content_target_id' => $node[$d7_node_id]['nid'],
          ])
          ->execute();
      }
    }
  }

  /**
   * Debug D7 child nodes for the given nid.
   *
   *    * @param string $nid
   *   The node id to display child nodes.
   *    * @param string $version
   *   The version of the site the nid comes from (d9 or d7).
   *
   * @command dept:debug-subtopic-child-content
   * @aliases dscc
   */
  public function displaySubtopicChildContent($nid, $version = 'd9') {
    if ($version === 'd9') {
      $d7_nid = $this->lookupManager->lookupByDestinationNodeIds([$nid]);
      $node_id = $d7_nid[$nid]['d7nid'];
    }
    else {
      $node_id = $nid;
    }
    print "Child nodes for $version node " . $nid . " ";
    print_r($this->fetchD7NodeContents($node_id));
  }

  /**
   * Insert content field entries for Topic (top level) nodes.
   *
   *    * @param string $domain_id
   *   The domain id to update.
   *
   * @command dept:subtopic-child-content
   * @aliases scc
   */
  public function createSubtopicContentReferences($domain_id) {
    $domain_topics_d9 = $this->dbConn->query("
    SELECT ds.entity_id FROM node__field_domain_source ds
    INNER JOIN node_field_data nfd
    ON ds.entity_id = nfd.nid
    WHERE ds.bundle = 'topic'
    AND ds.field_domain_source_target_id = '$domain_id'"
    )->fetchCol();

    $topic_nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($domain_topics_d9);

    // Loop Topics and get node ids.
    foreach ($topic_nodes as $topic_node) {
      $topic_content_references = $topic_node->get('field_topic_content');

      foreach ($topic_content_references as $reference) {
        $topic_nid = $reference->get('entity')->getTargetIdentifier();
        $topic_data = $this->lookupManager->lookupByDestinationNodeIds([$topic_nid]);
        $this->processSubtopicChildContent($topic_data[$topic_nid]['d7nid']);
      }
    }

    if (count($this->selfReferencedTopicsCount) > 0) {
      $selfRefList = implode(",", $this->selfReferencedTopicsCount);
      $this->io()->caution("Rubber chickens awarded: " . count($this->selfReferencedTopicsCount) . " 🐔");
      $this->logger->warning("Self referencing topics: " . $selfRefList);
    }

    if (count($this->missingTopicsContentCount) > 0) {
      $missingContentList = implode(",", $this->missingTopicsContentCount);
      $this->io()->caution("Missing topic content nodes: " . count($this->missingTopicsContentCount) . " 🙈 ");
      $this->logger->warning("Missing subtopic content nodes: " . $missingContentList);
    }

    $this->io()->success("Subtopic content entity references completed");
  }

  /**
   * Fetch subtopic child nodes and add as a reference to the parent.
   *
   * @param int $nid
   *   Drupal 7 parent node ID.
   */
  private function processSubtopicChildContent($nid) {
    $child_nodes = $this->fetchSubtopicChildContent($nid);
    // Fetch the D9/D7 data for the parent node.
    $parent_data = $this->lookupManager->lookupbySourceNodeId([$nid]);
    $parent_node = $this->entityTypeManager->getStorage('node')->load($parent_data[$nid]['nid']);

    foreach ($child_nodes as $child_node) {
      $child_data = $this->lookupManager->lookupBySourceNodeId([$child_node->nid]);

      // If the child node is a reference to the parent, ignore it.
      if ($child_node->nid == $nid) {
        $this->selfReferencedTopicsCount[] = $nid;
        continue;
      }

      // If we don't have a D9 nid it might be that the node in question hasn't
      // been migrated so skip adding it.
      if (!array_key_exists('nid', $child_data[$child_node->nid]) || is_null($child_data[$child_node->nid]['nid'])) {
        $this->missingTopicsContentCount[] = $child_node->nid;
      }
      else {
        $this->createSubtopicContentRefLink($parent_node, $child_data[$child_node->nid]['nid']);
      }

      // If the child node is a subtopic then process it for child content.
      if ($child_node->type === 'subtopic') {
        $this->processSubtopicChildContent($child_node->nid);
      }
    }
  }

  /**
   * Fetch the child nodes of the given Drupal 7 subtopic.
   *
   * @param int $nid
   *   Subtopic node id.
   * @return array
   *   Array elements containing node id, type and title.
   */
  private function fetchSubtopicChildContent($nid) {
    // Select nodes from the draggable view, with associated parent term or
    // subtopic term.
    $subcontent_sql = "WITH content_stack_cte (nid, type, title, weight) AS (
    SELECT
        n.nid,
        n.type,
        n.title,
        ds.weight
    FROM draggableviews_structure ds
    JOIN node n ON n.nid = ds.entity_id
    WHERE ds.view_name = 'draggable_subtopics'
      AND ds.view_display = 'panel_pane_2'
      AND ds.args = :nidargs
      AND n.status = 1
     UNION
         SELECT
        st_n.nid,
        st_n.type,
        st_n.title,
        99 as weight
    FROM node st_n
    JOIN field_data_field_parent_topic nfps ON st_n.nid = nfps.entity_id
    LEFT OUTER JOIN field_data_field_parent_subtopic nfpst ON st_n.nid = nfpst.entity_id
    WHERE st_n.type = 'subtopic'
    AND nfps.field_parent_topic_target_id = :nid
    AND st_n.status = 1
    AND nfpst.entity_id IS NULL
    UNION
    SELECT
        ar_n.nid,
        ar_n.type,
        ar_n.title,
        99 as weight
    FROM node ar_n
    JOIN field_data_field_site_subtopics nfss ON ar_n.nid = nfss.entity_id
    WHERE ar_n.type = 'article' AND nfss.field_site_subtopics_target_id = :nid
      AND ar_n.status = 1

)
SELECT DISTINCT nid, type, title FROM content_stack_cte
ORDER BY weight, title";

    $nodes = $this->d7conn->query($subcontent_sql, [
      ':nidargs' => '["' . $nid . '","' . $nid . '"]',
      ':nid' => $nid
    ])->fetchAll();

    return $nodes;
  }

  /**
   * Creates an entity reference link in the topic contents field
   *
   * @param \Drupal\node\Entity\Node $parent_node
   *   Node to which we add the entity reference.
   * @param int $child_nid
   *   Entity reference node id.
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
