<?php

namespace Drupal\dept_etgrm\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * Drush commands for interacting with ETGRM.
 */
class EtgrmCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * MyModuleCommands constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct();
    $this->configFactory = $config_factory;
  }

  /**
   * Remove all relationships.
   *
   * @command etgrm:removeAll
   * @aliases etgrm:ra
   */
  public function removeAllCommand() {
    // Fetch the recorded timestamp from the import process. All
    // created group content entities use this as their created date.
    // This makes it easy for us to remove any imported rows while leaving
    // other manually created entries in place.
    $ts = $this->configFactory->get('dept_etgrm.data')->get('processed_ts');

    if (!empty($ts) || (int) $ts > 0) {
      $dbConn = Database::getConnection('default', 'default');

      $this->io()->title("Removing imported Group Content entities");

      $this->io()->write("Removing rows from group_content table");
      $dbConn->query("DELETE gc FROM {group_content} AS gc INNER JOIN {group_content_field_data} AS gfd ON gc.id = gfd.id WHERE gfd.created = :ts", [
        ':ts' => $ts,
      ]);
      $this->io()->writeln(" ✅");

      $this->io()->write("Removing rows from group_content_field_data table");
      $dbConn->query("DELETE gfd FROM {group_content_field_data} as gfd WHERE gfd.created = :ts", [
        ':ts' => $ts,
      ]);
      $this->io()->writeln(" ✅");

      $this->io()->success('Finished');
    }

  }

  /**
   * Create all relationships.
   *
   * @command etgrm:createAll
   * @aliases etgrm:ca
   */
  public function all() {
    $database = '';
    $host = '';
    $password = '';
    $username = '';

    extract(Database::getConnectionInfo('default')['default'], EXTR_OVERWRITE);

    $dbConn = Database::getConnection('default', 'default');
    $conf = $this->configFactory->getEditable('dept_etgrm.data');

    if ($dbConn->schema()->tableExists('group_relationships')) {
      $results = $dbConn->select('group_relationships')->countQuery()->execute()->fetchField();

      if (!empty($results) || (int) $results > 0) {
        $this->io()->note("Removing existing group content entities");
        $process = Drush::drush(Drush::aliasManager()->getSelf(), 'etgrm:removeAll', [], []);
        $process->start();
      }
    }

    // Timestamp for entity and import processed date.
    $ts = time();

    // Using PDO as Drupal's db driver doesn't provide an option to bind
    // parameters to prepared statements.
    $pdo = new \PDO("mysql:host=$host;dbname=$database", $username, $password);

    $this->io()->title("Creating group content for migrated nodes.");

    $this->io()->write("Building node to group relationships");
    $query = $pdo->prepare('call CREATE_GROUP_RELATIONSHIPS(?)');
    $query->bindParam(1, $database);
    $query->execute();
    $this->io()->writeln(" ✅");

    $this->io()->write("Expanding zero based domains to all groups");
    $query = $pdo->query('call PROCESS_GROUP_ZERO_RELATIONSHIPS()');
    $this->io()->writeln(" ✅");

    $this->io()->write("Creating Group Content data (this may take a while)");
    $query = $pdo->prepare('call PROCESS_GROUP_RELATIONSHIPS(?)');
    $query->bindParam(1, $ts);
    $query->execute();
    $this->io()->writeln(" ✅");

    $this->io()->write("Updating Node Access data for Domain");
    $query = $pdo->query('call UPDATE_NODE_ACCESS()');
    $this->io()->writeln(" ✅");

    $this->io()->write("Tidying up any stale previous timestamped imports");
    $query = $pdo->query('call DELETE_STALE_IMPORTS(1000)');
    $this->io()->writeln(" ✅");

    $conf->set('processed_ts', $ts);
    $conf->save();

    $this->io()->success("Finished");
  }

}
