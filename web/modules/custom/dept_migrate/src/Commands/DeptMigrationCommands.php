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

    $tables = [
      'body',
      'field_additional_info',
      'field_summary',
    ];

    $dbConn = Database::getConnection('default', 'default');
    $lookupMan = \Drupal::service('dept_migrate.migrate_uuid_lookup_manager');

    $this->io()->title("Updating all internal links");

    foreach ($tables as $table) {
      $query = $dbConn->select('node__' . $table, 't');
      $query->addField('t', 'entity_id', 'nid');
      $query->addField('t', $table . '_value', 'value');
      $query->condition($table . '_value', 'node\/[0-9]+', 'REGEXP');

      $results = $query->execute()->fetchAll();


      foreach ($results as $result) {
          $updated_value = preg_replace_callback(
            '/node\/(\d+)/m',
            function ($matches) use ($lookupMan) {
              $d9_lookup = $lookupMan->lookupBySourceNodeId([$matches[1]]);

              if (!empty($d9_lookup['nid'])) {
                return 'node/' . $d9_lookup['nid'];
              }
            },
            $result->value
          );
        }
      }

    $this->io()->success("Finished");
  }

}
