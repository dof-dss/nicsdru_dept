<?php

namespace Drupal\dept_migrate\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * Command constructor.
   */
  public function __construct(Connection $database, Connection $d7_database, MigrateUuidLookupManager $lookup_manager, EntityTypeManagerInterface $etm, DepartmentManager $dept_manager) {
    parent::__construct();
    $this->dbConn = $database;
    $this->d7conn = $d7_database;
    $this->lookupManager = $lookup_manager;
    $this->entityTypeManager = $etm;
    $this->departmentManager = $dept_manager;
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
   * Update Topic content field entries
   *
   *    * @param string $domain_id
   *   The domain id to update.
   *
   * @command dept:update-topic-references
   * @aliases utr
   */
  public function updateTopicReferences($domain_id) {

    if (empty($domain_id)) {
      return;
    }

    $topic_content_sql ="
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
      // Fetch D7 topic content nodes.
      $topic_ref_nodes_d7 = $this->d7conn->query($topic_content_sql, [':nid' => $topic['d7nid']])->fetchAllKeyed(0, 2);
      $topic_ref_nodes_d9 = [];
      $delta = 0;

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
}
