<?php

namespace Drupal\dept_migrate\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dept_core\DepartmentManager;
use Drupal\dept_migrate\MigrateUtils;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\node\NodeInterface;
use Drush\Commands\DrushCommands;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Symfony\Component\Console\Helper\Table;

/**
 * Drush commands processing Departmental migrations.
 */
class DeptMigrationCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

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
  public function updateInternalLinks($department_id) {

    if (empty($department_id)) {
      $this->logger->warning("You must provide a department id");
      return;
    }

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
      $query->join('node__field_domain_source', 'ds', 't.entity_id = ds.entity_id');
      $query->addField('t', 'entity_id', 'nid');
      $query->addField('t', $field . '_value', 'value');
      $query->condition('ds.field_domain_source_target_id', $department_id);
      $query->condition('t.' . $field . '_value', 'node\/[0-9]+', 'REGEXP');

      $results = $query->execute()->fetchAll();

      foreach ($results as $result) {
        // Update all node links.
        $updated_value = preg_replace_callback(
          '/(<a href="\/node\/)(\d+)/m',
          function ($matches) use ($result, $field) {
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
              else {
                // Log the broken link to the DB and leave it untouched in the field.
                $d7_source_nid = $this->lookupManager->lookupByDestinationNodeIds([$result->nid]);
                $entity_id = $d7_source_nid[$result->nid]['d7nid'];

                if (!empty($entity_id)) {
                  $this->dbConn->insert('dept_migrate_invalid_links')
                    ->fields([
                      'entity_id' => $entity_id,
                      'bad_link' => $matches[0] . '">',
                      'field' => $field
                    ])
                    ->execute();
                }
              }

              return $matches[0];
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

    $exclusions = getenv('MIGRATE_IGNORE_SITES') ?? '';

    if (!empty($exclusions)) {
      $exclusions = explode(',', $exclusions);
    }

    foreach ($depts as $dept) {
      if (in_array($dept->id(), $exclusions)) {
        $this->io()->writeln('Skipping ' . $dept->label() . ' as it was found in MIGRATE_IGNORE_SITES');
        continue;
      }

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

      if ($dept->id() === 'nigov') {
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
        WHERE d.machine_name = :site_id AND n.status = 1
        ORDER BY
            fn_flag.uid IS NOT NULL DESC,
            hp_flag.uid IS NOT NULL DESC,
            fdfpd.field_published_date_value DESC
        LIMIT 10", [':site_id' => MigrateUtils::d9DomainToD7Domain($dept->id())]
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
   * Determines if a node is a page within a book content type.
   *
   * @param int $nid
   *   The node ID to check.
   * @return bool
   *   True if the node is a book page, otherwise false.
   */
  protected function isBookPage($nid) {
    $book_nids = \Drupal::cache()->get('book_page_nids');

    if (empty($book_nids)) {
      $book_nids = \Drupal::database()->query("SELECT book.nid FROM book WHERE book.depth > 1")->fetchAllAssoc('nid');
      \Drupal::cache()->set('book_page_nids', $book_nids, strtotime('+1 hour', time()));
    }
    else {
      $book_nids = $book_nids->data;
    }

    return array_key_exists($nid, $book_nids);
  }

  /**
   * Creates next audit due dates for content flagged in D7 for auditing.
   *
   *    * @param string $domain
   *   The D9 domain (machine name) to update.
   *
   * @command dept:update-audit-date
   * @aliases audit
   */
  public function createAuditDueDate(string $domain) {
    if (empty($domain)) {
      $this->logger->warning("You must provide a domain id");
      return;
    }

    // Transform dept name to d7 domain id.
    $d7_domain = MigrateUtils::d9DomainToD7Domain($domain);
    $domain_id = $this->d7conn->query("SELECT domain_id FROM domain WHERE machine_name = :domain", [':domain' => $d7_domain])->fetchField();

    // First we query all nodes for the given domain that have an audit flag set.
    // From this we take the node changed timestamp, add 6 months to it and format for the audit_field.
    $results = $this->d7conn->query("SELECT n.nid AS d7_nid, DATE_FORMAT(DATE_ADD(from_unixtime(n.changed), INTERVAL 6 MONTH), '%Y-%m-%d') AS audit_due FROM flag_counts fc LEFT JOIN node n ON fc.entity_id = n.nid LEFT JOIN domain_access da ON da.nid = fc.entity_id WHERE fc.fid = 3 AND n.status = 1 AND da.gid = " . $domain_id)
      ->fetchAllAssoc('d7_nid', \PDO::FETCH_ASSOC);

    foreach ($results as $d7nid => $audit) {
      $update_fields = [];
      // Fetch the D9 nid and the published revision id for each node.
      $node_data = $this->lookupManager->lookupBySourceNodeId([$d7nid]);

      if (empty($node_data[$d7nid]['nid'])) {
        $this->logger->warning($this->t("No local node was found for nid: @nid", ['@nid' => $d7nid]));
        continue;
      }

      $revision_id = $this->dbConn->query("SELECT n.vid AS revision_id FROM node n LEFT JOIN node_field_data nfd ON n.nid = nfd.nid WHERE nfd.status = 1 AND n.nid = " . $node_data[$d7nid]['nid'])->fetchField();

      if (empty($revision_id)) {
        continue;
      }

      $update_fields = [
        'bundle' => $node_data[$d7nid]['type'],
        'entity_id' => $node_data[$d7nid]['nid'],
        'revision_id' => $revision_id,
        'langcode' => 'en',
        'delta' => 0,
        'field_next_audit_due_value' => $audit['audit_due'],
      ];

      // Insert a new audit due date in the node and node revision tables.
      $this->dbConn->insert('node__field_next_audit_due')->fields($update_fields)->execute();
      $this->dbConn->insert('node_revision__field_next_audit_due')->fields($update_fields)->execute();
    }
  }

  /**
   * Removes Audit Due dates.
   *
   *    * @param string $domain
   *   The D9 domain (machine name) to remove audit dates.
   *
   * @command dept:remove-audit-date
   * @aliases audit-remove
   */
  public function removeAuditDueDate(string $domain) {
    if (empty($domain)) {
      $this->logger->warning("You must provide a domain id");
      return;
    }

    // Remove audit due dates for given domain.
    $this->dbConn->query("DELETE ad FROM node__field_next_audit_due ad
      LEFT JOIN node__field_domain_access ds
      ON ad.entity_id = ds.entity_id
      WHERE ds.field_domain_access_target_id ='" . $domain . "'");

    // Remove revision audit due dates for given domain.
    $this->dbConn->query("DELETE ad FROM node_revision__field_next_audit_due ad
      LEFT JOIN node__field_domain_access ds
      ON ad.entity_id = ds.entity_id
      WHERE ds.field_domain_access_target_id ='" . $domain . "'");

  }

  /**
   * Fix migrated usernames.
   *
   * @see DEPT-973
   * @command dept:fix-usernames
   * @aliases fix-usernames
   */
  public function fixUsernames() {
    $query = $this->dbConn->select('users_field_data', 'ud');
    $query->join('migrate_map_users', 'mmu', 'ud.uid = mmu.destid1');

    $results = $query->fields('ud', ['uid'])
      ->fields('mmu', ['sourceid1'])
      ->condition('ud.name', '^[0-9]+$', 'REGEXP')
      ->execute()->fetchAll();

    $rows = [];

    foreach ($results as &$result) {
      $query = $this->d7conn->select('users', 'u')
        ->fields('u', ['name'])
        ->condition('uuid', $result->sourceid1, '=');
      $rows[] = [$result->uid, $query->execute()->fetchField(), $result->sourceid1];
    }

    $table = new Table($this->output());
    $table->setHeaders(['UID', 'Username', 'Hash'])
      ->setRows($rows);

    $table->render();
  }

  /**
   * Add Stored procedures to database.
   *
   * @command dept:create-stored-procedures
   * @aliases create-sprocs
   */
  public function createSprocs() {
    // Define extracted variables or Drupal Check will moan.
    $database = '';
    $host = '';
    $password = '';
    $username = '';
    extract(Database::getConnectionInfo('default')['default'], EXTR_OVERWRITE);

    $module_handler = \Drupal::service('module_handler');
    $module_path = \Drupal::service('file_system')->realpath($module_handler->getModule('dept_migrate')->getPath());

    $pdo = new \PDO("mysql:host=$host;dbname=$database", $username, $password);

    $pdo->exec('DROP PROCEDURE IF EXISTS UPDATE_PATH_ALIAS_DEPARTMENT_SUFFIX');
    $result = $pdo->exec(file_get_contents($module_path . '/inc/update_path_alias_department_suffix.sproc'));

    if ($result === FALSE) {
      $this->logger->warning("Unable to add stored procedure to database");
    }
    else {
      $this->logger->notice("Stored procedure added database");
    }
  }

  /**
   *  Removes entries from the migration logging tables (dept_migrate_).
   *
   * @param string $table
   *   Table name to purge.
   *
   * @command dept:purge-migration-logs
   * @aliases purge-mig-logs
   */
  public function purgeMigrationLogging(string $table) {

    if (empty($table) || !in_array($table, [
        'dept_migrate_audit',
        'dept_migrate_invalid_links',
        'dept_redirects_results'
      ])) {
      $this->logger->warning('Invalid table name');
      return;
    }

    $this->logger->notice('Purging ' . $table . ' table');
    $this->dbConn->truncate($table)->execute();
  }

}
