<?php

namespace Drupal\dept_migrate\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_migrate\MigrateUtils;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Takes a D7 media id and finds the most appropriate
 * domain for it, based on the node content is it used on.
 *
 * @MigrateProcessPlugin(
 *   id = "d7_media_domain"
 * )
 */

class D7MediaDomain extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connDb;

  /**
   * The migration database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connMigrate;

  /**
   * The lookup manager service.
   *
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\dept_migrate\MigrateUuidLookupManager $lookup_manager
   *   Lookup manager service object.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, Connection $connection, MigrateUuidLookupManager $lookup_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connDb = $connection;
    $this->connMigrate = Database::getConnection('default', 'migrate');
    $this->lookupManager = $lookup_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('dept_migrate.migrate_uuid_lookup_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $d7_file_query = $this->connMigrate->query("SELECT
      fu.fid,
      fm.filename,
      d.domain_id,
      d.machine_name,
      n.nid,
      n.title,
      n.type,
      fu.count
      FROM {file_usage} fu
      JOIN {file_managed} fm ON fm.fid = fu.fid
      JOIN {node} n ON n.nid = fu.id
      JOIN {domain_access} da ON da.nid = n.nid
      JOIN {domain} d ON d.domain_id = da.gid
      WHERE fu.fid = :fid
      ORDER BY fu.fid, d.machine_name", [
        ':fid' => $value,
      ])->fetchAll();

    if (empty($d7_file_query)) {
      return 'nigov';
    }

    $depts = [];

    foreach ($d7_file_query as $row) {
      $depts[] = MigrateUtils::d7DomianToD9Domain($row->machine_name) ?? 'nigov';
    }

    return (is_array($depts)) ? array_unique($depts) : $depts;
  }

}
