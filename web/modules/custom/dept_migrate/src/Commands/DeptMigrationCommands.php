<?php

namespace Drupal\dept_migrate\Commands;

use Drupal\Core\Database\Database;
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
  public function all() {

    $dbConn = Database::getConnection('default', 'default');

    $this->io()->title("Updating all internal links");


    $this->io()->success("Finished");
  }

}
