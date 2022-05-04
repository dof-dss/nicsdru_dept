<?php

namespace Drupal\dept_mdash\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\example\ExampleInterface;
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
   * @param \Drupal\Core\Database\Connection $connection
   *   The legacy database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, Connection $legacy_connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dbConn = $connection;
    $this->legacyConn = $legacy_connection;
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
      $container->get('dept_migrate.database_d7')
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
      $d9_rows = $this->dbConn->select('node')->condition('type', $bundle, '=')->countQuery()->execute()->fetchField();
      $d7_rows = $this->legacyConn->select('node')->condition('type', $bundle, '=')->countQuery()->execute()->fetchField();
      $diff = $d9_rows - $d7_rows;
      if ($diff > 0 ) {
        $diff_style = ['color: green'];
        $row_style =  ['font-weight: bold'];
      }
      elseif ($diff < 0) {
        $diff_style = ['color: red'];
        $row_style =  ['font-weight: bold'];
      }
      else {
        $diff_style = '';
        $row_style =  [''];
      }
      $rows[$bundle] = [
        'data' => [
        'bundle' => $bundle,
        'd9' => $d9_rows,
        'd7' => $d7_rows,
        'diff' => [
          'data' => $d9_rows - $d7_rows,
          'style' => $diff_style,
        ],
        ],
        'style' => $row_style,
      ];
    }

    $header = [
      'bundle' => [
        'data' => t('Bundle'),
        'style' => ['font-weight: bold; color: #1e293b; background-color: #94a3b8']
      ],
      'd9' => [
        'data' => t('Drupal 9'),
        'style' => ['font-weight: bold; color: #1e293b; background-color: #94a3b8']
      ],
      'd7' => [
        'data' => t('Drupal 7'),
        'style' => ['font-weight: bold; color: #1e293b; background-color: #94a3b8']
      ],
      'diff' => [
        'data' => t('Difference'),
        'style' => ['font-weight: bold; color: #1e293b; background-color: #94a3b8']
      ],
    ];

    $build['content'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
    return $build;
  }

  public function getCacheMaxAge() {
    return 0;
  }


}
