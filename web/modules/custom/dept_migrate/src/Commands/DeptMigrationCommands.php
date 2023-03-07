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
  public function __construct(Connection $database, MigrateUuidLookupManager $lookup_manager, EntityTypeManagerInterface $etm, DepartmentManager $dept_manager) {
    parent::__construct();
    $this->dbConn = $database;
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
        continue;
      }

      $fcl_nid = reset($query);
      $fcl_node = $node_storage->load($fcl_nid);

      if ($fcl_node instanceof NodeInterface === FALSE) {
        continue;
      }

      // TODO: Find the nodes for the content featured for this dept in D7.
      $d7_featured_nodes = [];

      // TODO: Find them in D9 and add them as the values for the FCL items.
      $d9_featured_nodes = [];
      $fcl_node->field_featured_content->setValue($d9_featured_nodes);

      // TODO: Save the FCL node.
      $fcl_node->save();

    }

    $this->io()->success("Finished");
  }

}
