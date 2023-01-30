<?php

namespace Drupal\dept_mdash\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
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
   * Builds the response.
   */
  public function build() {
    $plugin_block = $this->blockManager->createInstance('dept_mdash_content_summary', []);
    $content_summary_block = $plugin_block->build();

    $plugin_block = $this->blockManager->createInstance('dept_mdash_error_summary', []);
    $error_summary_block = $plugin_block->build();

    $plugin_block = $this->blockManager->createInstance('dept_mdash_domain_access', []);
    $domain_access_block = $plugin_block->build();

    $plugin_block = $this->blockManager->createInstance('dept_mdash_log_viewer', []);
    $log_viewer_block = $plugin_block->build();

    ksm($content_summary_block, $domain_access_block);

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
      'node_actions',
      'node_application',
      'node_article',
      'node_consultation',
      'node_contact',
      'node_easychart',
      'node_gallery',
      'node_heritage_site',
      'node_infogram',
      'node_landing_page',
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
