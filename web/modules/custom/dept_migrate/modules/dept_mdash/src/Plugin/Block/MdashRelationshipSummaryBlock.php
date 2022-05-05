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
 *   id = "dept_mdash_relationship_summary",
 *   admin_label = @Translation("Mdash: Relationship Summary"),
 *   category = @Translation("mdash")
 * )
 */
class MdashRelationshipSummaryBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConn;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dbConn = $connection;
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

    $header = [
      'type' => $this->t('Type'),
      'total' => $this->t('Total'),
    ];

    $results = $this->dbConn->query('SELECT type, COUNT(type) AS total FROM group_content GROUP BY type')->fetchAll();

    foreach ($results as $result) {
      $rows[] = [
        $result->type,
        $result->total,
      ];
    }

    $build['content'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
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
