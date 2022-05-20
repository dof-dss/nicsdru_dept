<?php

namespace Drupal\dept_migrate\Commands;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands processing Departmental migrations.
 */
class DeptMigrationCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * Updates all internal /node/XXXX links from their D7 to the D9 nid.
   *
   * @command dept:updatelinks
   * @aliases dept:uplnks
   */
  public function updateInternalLinks() {

    $fields = [
      'body',
      'field_additional_info',
      'field_summary',
    ];

    $dbConn = Database::getConnection('default', 'default');
    $lookupMan = \Drupal::service('dept_migrate.migrate_uuid_lookup_manager');

    foreach ($fields as $field) {
      $table = 'node__' . $field;
      $query = $dbConn->select($table, 't');
      $query->addField('t', 'entity_id', 'nid');
      $query->addField('t', $field . '_value', 'value');
      $query->condition($field . '_value', 'node\/[0-9]+', 'REGEXP');

      $results = $query->execute()->fetchAll();


      foreach ($results as $result) {
        $updated_value = preg_replace_callback(
          '/(<a href="\/node\/)(\d+)/m',
          function ($matches) use ($lookupMan, $dbConn) {
            $d9_lookup = $lookupMan->lookupBySourceNodeId([$matches[2]]);

            if (!empty($d9_lookup)) {
              $node_data = current($d9_lookup);

              if (!empty($node_data['nid'])) {
                $d9_nid = $node_data['nid'];
                $d9_uuid = $dbConn->query('SELECT uuid FROM {node} WHERE nid = :nid', [':nid' => $d9_nid])->fetchField();

                return '<a data-entity-substitution="canonical" data-entity-type="node" data-entity-uuid="' . $d9_uuid . '" href="/node/' . $node_data['nid'];
              }
            }
          },
          $result->value
        );

        $dbConn->update($table)
          ->fields([$field . '_value' => $updated_value])
          ->condition('entity_id', $result->nid, '=')
          ->execute();
      }

    }
    $this->io()->success("Finished");
  }
}
