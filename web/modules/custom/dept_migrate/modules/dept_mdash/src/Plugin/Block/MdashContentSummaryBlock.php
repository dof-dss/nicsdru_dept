<?php

namespace Drupal\dept_mdash\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a migrated content summary block.
 *
 * @Block(
 *   id = "dept_mdash_content_summary",
 *   admin_label = @Translation("Mdash: Content Summary"),
 *   category = @Translation("mdash")
 * )
 */
class MdashContentSummaryBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConn;

  /**
   * The legacy database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $legacyConn;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Migration import timestamps.
   *
   * @var array
   */
  protected array $migrationTimestamps;

  /**
   * Constructs a new MdashcontentsummaryBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Database\Connection $legacy_connection
   *   The legacy database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter service.
   * @param array $migration_import_timestamps
   *   Array of last import timestamps for each migration.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, Connection $legacy_connection, DateFormatterInterface $date_formatter, array $migration_import_timestamps) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dbConn = $connection;
    $this->legacyConn = $legacy_connection;
    $this->dateFormatter = $date_formatter;
    $this->migrationTimestamps = $migration_import_timestamps;
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
      $container->get('dept_migrate.database_d7'),
      $container->get('date.formatter'),
      $container->get('keyvalue')->get('migrate_last_imported')->getAll(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $bundles = [
      'actions',
      'application',
      'article',
      'consultation',
      'contact',
      'easychart',
      'gallery',
      'heritage_site',
      'infogram',
      'landing_page',
      'link',
      'news',
      'page',
      'profile',
      'protected_area',
      'publication',
      'subtopic',
      'topic',
      'ual',
    ];

    $migration_timestamps = \Drupal::keyValue('migrate_last_imported');

    ksm($migration_timestamps->getAll());

    foreach ($bundles as $bundle) {
      $d9_rows = $this->dbConn->select('node')->condition('type', $bundle, '=')->countQuery()->execute()->fetchField();
      $d7_rows = $this->legacyConn->select('node')->condition('type', $bundle, '=')->countQuery()->execute()->fetchField();
      $diff = $d9_rows - $d7_rows;
      if ($diff > 0) {
        $diff_style = ['color: green'];
        $row_class = ['mdash-highlight'];
      }
      elseif ($diff < 0) {
        $diff_style = ['color: red'];
        $row_class = ['mdash-highlight'];
      }
      else {
        $diff_style = '';
        $row_class = [''];
      }

      // Retrieve and format the last imported date.
      $imported = $this->migrationTimestamps['node_' . $bundle];
      $last_imported = $this->dateFormatter->format((int) ($imported / 1000), 'custom', 'Y-m-d H:i:s');

      $rows[$bundle] = [
        'data' => [
          'bundle' => ($bundle === 'publication') ? $bundle . "*" : $bundle,
          'd9' => $d9_rows,
          'd7' => $d7_rows,
          'diff' => [
            'data' => $d9_rows - $d7_rows,
            'style' => $diff_style,
          ],
          'imported' => $last_imported,
        ],
        'class' => $row_class,
      ];
    }

    $header = [
      'bundle' => [
        'data' => $this->t('Bundle'),
        'class' => ['mdash-header'],
      ],
      'd9' => [
        'data' => $this->t('Drupal 9 total'),
        'class' => ['mdash-header'],
      ],
      'd7' => [
        'data' => $this->t('Drupal 7 total'),
        'class' => ['mdash-header'],
      ],
      'diff' => [
        'data' => $this->t('Difference'),
        'class' => ['mdash-header'],
      ],
      'imported' => [
        'data' => $this->t('Last imported'),
        'class' => ['mdash-header'],
      ],
    ];

    $build['content'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#suffix' => "* publication contains both 'publication' and 'secure publication' nodes from the Drupal 7 site.
         <br>A positive or negative difference is not an unequivocal indication that data has not been migrated and should be verified by checking the 2 data sources.",
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
