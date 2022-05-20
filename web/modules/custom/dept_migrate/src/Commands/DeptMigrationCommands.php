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

    $this->io()->title("Updating all internal links");

    foreach ($tables as $table) {

      $query = $dbConn->select('node__' . $table, 't');
      $query->addField('t', 'entity_id', 'id');
      $query->addField('t', $table . '_value', 'value');
      $query->condition($table . '_value', 'node\/[0-9]+', 'REGEXP');
      $results = $query->execute()->fetchAll();

      foreach ($results as $record) {
        $this->io()->writeln($record->id);
      }
    }

    $this->io()->success("Finished");
  }

}
