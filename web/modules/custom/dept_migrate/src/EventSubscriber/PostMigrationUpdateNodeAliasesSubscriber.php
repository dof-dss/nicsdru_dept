<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Post migration subscriber updating path aliases with dept suffixes to the correct department.
 */
class PostMigrationUpdateNodeAliasesSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Logger\LoggerChannel definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   */
  public function __construct(LoggerChannelFactory $logger, Connection $connection) {
    $this->logger = $logger->get('dept_migrate');
    $this->dbconn = $connection;
  }

  /**
   * Get subscribed events.
   *
   * @inheritdoc
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_IMPORT][] = ['onMigratePostImport'];
    return $events;
  }

  /**
   * Handle post import migration event.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
    $event_id = $event->getMigration()->getBaseId();

    if (strpos($event_id, 'node_') === 0) {
      $this->logger->notice("Update amended path aliases to the correct department");

      // Define extracted variables or Drupal Check will moan.
      $database = '';
      $host = '';
      $password = '';
      $username = '';
      extract(Database::getConnectionInfo('default')['default'], EXTR_OVERWRITE);
      $pdo = new \PDO("mysql:host=$host;dbname=$database", $username, $password);

      // Execute Stored Procedure to suffix the correct department.
      $query = $pdo->query('call UPDATE_PATH_ALIAS_DEPARTMENT_SUFFIX()');

      // Handle duplicate path aliases.
      $aliases = $this->dbconn->query("SELECT alias AS distinct_values_count FROM path_alias GROUP BY alias HAVING COUNT(alias) > 1 AND COUNT(DISTINCT path) > 1;")
        ->fetchCol();

      foreach ($aliases as $alias) {
        $query = $this->dbconn->select('path_alias', 'pa')
          ->fields('pa', ['id', 'path', 'alias'])
          ->fields('fd', ['status'])
          ->condition('pa.alias', $alias);
        // Join by extracting the nid from the path.
        $query->join('node_field_data', 'fd', 'SUBSTRING(pa.path, 7, 100) = fd.nid');
        // Order by status, so we can give any published content alias priority.
        $query->orderBy('fd.status', 'DESC');

        $results = $query->execute();
        $index = 0;

        // Update each alias, incrementing the end for each.
        foreach ($results as $result) {
          $new_alias = $this->createAlias($result->alias, $index);
          $this->dbconn->update('path_alias')
            ->fields(['alias' => $new_alias])
            ->condition('id', $result->id, '=')
            ->execute();
        }
      }
    }
  }

  /**
   * Create a unique path alias for a node.
   *
   * @param string $alias
   *   The alias to make unique.
   * @param int $index
   *   The numeric index to append to the end of the alias.
   *
   * @return string
   *   The unique alias.
   */
  private function createAlias($alias, &$index) {
    $new_alias = ($index === 0) ? $alias : $alias . '-' . $index;

    $exists = $this->dbconn->select('path_alias', 'pa')
      ->condition('alias', $new_alias)
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($exists > 0) {
      $index++;
      $this->createAlias($alias, $index);
    }

    return $new_alias;
  }

}
