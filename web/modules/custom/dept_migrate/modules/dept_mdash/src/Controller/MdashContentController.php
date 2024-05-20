<?php

namespace Drupal\dept_mdash\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Migration Dashboard routes.
 */
class MdashContentController extends ControllerBase {

  /**
   * The block manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConn;

  /**
   * Drupal 7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7conn;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory service.
   */
  public function __construct(BlockManagerInterface $block_manager, Connection $connection, DateFormatterInterface $date_formatter, ConfigFactoryInterface $config_factory) {
    $this->blockManager = $block_manager;
    $this->dbConn = $connection;
    $this->dateFormatter = $date_formatter;
    $this->configFactory = $config_factory;

    $this->d7conn = Database::getConnection('default', 'migrate');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('config.factory'),
    );
  }

  /**
   * Builds the page for overview.
   */
  public function pageOverview() {
    $plugin_block = $this->blockManager->createInstance('dept_mdash_content_summary', []);
    $content_summary_block = $plugin_block->build();

    $plugin_block = $this->blockManager->createInstance('dept_mdash_error_summary', []);
    $error_summary_block = $plugin_block->build();

    $plugin_block = $this->blockManager->createInstance('dept_mdash_domain_access', []);
    $domain_access_block = $plugin_block->build();

    $plugin_block = $this->blockManager->createInstance('dept_mdash_log_viewer', []);
    $log_viewer_block = $plugin_block->build();

    return [
      '#theme' => 'mdash_dashboard',
      '#content_summary' => $content_summary_block,
      '#error_summary' => $error_summary_block,
      '#domain_access' => $domain_access_block,
      '#last_migration' => $this->lastMigation(),
      '#null_destination_nodes' => $this->nullDestinationNodes(),
      '#log_output' => $log_viewer_block,
      '#attached' => [
        'library' => [
          'dept_mdash/dashboard',
        ],
      ],
    ];
  }

  /**
   * Builds the page for recent revisions.
   */
  public function pageRecentRevisions() {
    $build = [];

    $domains = $this->d7conn->select('domain', 'd')
      ->fields('d', ['sitename'])
      ->execute()
      ->fetchAllAssoc('sitename');

    foreach ($domains as $domain => $val) {
      $rows = [];

      $results = $this->d7conn->query("SELECT n.nid, nh.vid, n.type, nh.from_state, nh.state, FROM_UNIXTIME(nh.stamp) as datetime, n.title FROM workbench_moderation_node_history nh
        LEFT JOIN node n
        ON nh.nid = n.nid
        LEFT JOIN domain_access da
        ON n.nid = da.nid
        LEFT JOIN domain d
        ON da.gid = d.domain_id
        WHERE nh.stamp > n.changed
        AND nh.stamp > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 4 WEEK))
        AND (nh.state = 'draft' OR nh.state = 'needs_review')
        AND d.sitename = '" . $domain . "'")
        ->fetchAll();

      if (count($results) < 1) {
        continue;
      }

      foreach ($results as $result) {
        $rows[] = [
          $result->nid,
          $result->vid,
          $result->type,
          $result->from_state,
          $result->state,
          $result->datetime,
          $result->title,
        ];
      }

      $build[$domain]['data'] = [
        '#type' => 'details',
        '#title' => $domain,
      ];

      $build[$domain]['data']['table'] = [
        '#type' => 'table',
        '#header' => [
          'nid',
          'revision id',
          'bundle',
          'from state',
          'to state',
          'datetime',
          'title'
        ],
        '#rows' => $rows,
      ];
    }

    return $build;
  }

  /**
   * Builds the page for bad content links.
   */
  public function pageBadLinks() {
    $build = [];

    if (!$this->dbConn->schema()->tableExists('dept_migrate_invalid_links')) {
      return $build;
    }

    $query = $this->dbConn->select('dept_migrate_invalid_links', 'il')
      ->fields('il', ['entity_id', 'bad_link', 'field']);

    $query->join('node__field_domain_source', 'ds', 'il.entity_id = ds.entity_id');
    $query->addField('ds', 'field_domain_source_target_id', 'department');
    $query->orderBy('department');

    $results = $query->execute()->fetchAll();
    $department_links = [];

    foreach ($results as $result) {
      $department_links[$result->department][] = [
        'nid' => $result->entity_id,
        'bad_link' => $result->bad_link,
        'field' => $result->field,
      ];
    }

    foreach ($department_links as $department => $links) {
      $rows = [];

      foreach ($links as $link) {
        $rows[] = [
          'nid' => Link::createFromRoute($link['nid'], 'entity.node.canonical', ['node' => $link['nid']], ['absolute' => TRUE]),
          'bad_link' => $link['bad_link'],
          'field' => $link['field'],
        ];
      }

      $build[$department] = [
        '#type' => 'details',
        '#title' => $department . ' (' . count($links) . ')',
      ];

      $build[$department]['table'] = [
        '#type' => 'table',
        '#header' => [
          'nid',
          'Bad link',
          'Field',
        ],
        '#rows' => $rows,
      ];
    }

    return $build;
  }

  /**
   * Returns the date for when migration was last run.
   *
   * @return string
   *   Date of last migration import or an empty string.
   */
  public function lastMigation() {
    $migration_timestamps = \Drupal::keyValue('migrate_last_imported');

    $mig_ts = $migration_timestamps->getAll();

    $last_import_ts = max(array_values($mig_ts));

    if ($last_import_ts !== NULL) {
      return $this->dateFormatter->format((int) ($last_import_ts / 1000), 'custom', 'd M Y');
    }

    return '';
  }

  /**
   * Returns the total number of migrations with no node ID for the D9 site.
   *
   * @return string
   *   Total number of null node ids.
   */
  public function nullDestinationNodes() {

    $migration_table = [
      'node_application',
      'node_article',
      'node_consultation',
      'node_contact',
      'node_gallery',
      'node_heritage_site',
      'node_link',
      'node_news',
      'node_page',
      'node_profile',
      'node_protected_area',
      'node_publication',
      'node_subtopic',
      'node_topic',
      'node_ual',
    ];

    $total = 0;

    foreach ($migration_table as $table) {
      $total += $this->dbConn->select("migrate_map_$table")
        ->isNull('destid1')
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    return $total;
  }

}
