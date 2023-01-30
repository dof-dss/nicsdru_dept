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
 *   id = "dept_mdash_domain_access",
 *   admin_label = @Translation("Mdash: Domain Access Summary"),
 *   category = @Translation("mdash")
 * )
 */
class MdashDomainAccessBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, Connection $legacy_connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dbConn = $connection;
    $this->legacyConn = $legacy_connection;
    $this->dateFormatter = $date_formatter;
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

    foreach ($bundles as $bundle) {
      $d9_data = $this->dbConn->query("SELECT n.nid AS 'd9nid', na.gid, mm.sourceid2 AS 'd7nid' FROM node AS n
INNER JOIN node_access AS na
ON n.nid = na.nid
INNER JOIN migrate_map_node_" . $bundle . " AS mm
ON n.nid = mm.destid1
WHERE n.type = '" . $bundle . "'")->fetchAll();

      $d7_data = $this->legacyConn->query("SELECT n.nid, da.gid FROM node AS n
INNER JOIN domain_access AS da
ON n.nid = da.nid
WHERE n.type = '" . $bundle . "'")->fetchAll();

      $diff = count($d9_data) - count($d7_data);
      $diff_style = '';
      $row_class = [''];

      if ($diff > 0) {
        $diff_style = ['color: green'];
        $row_class = ['mdash-highlight'];
      }
      elseif ($diff < 0) {
        $diff_style = ['color: red'];
        $row_class = ['mdash-highlight'];
      }

      $rows[$bundle] = [
        'data' => [
          'bundle' => ($bundle === 'publication') ? $bundle . "*" : $bundle,
          'd9' => count($d9_data),
          'd7' => count($d7_data),
          'diff' => [
            'data' => $diff,
            'style' => $diff_style,
          ],
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
