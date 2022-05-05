<?php

namespace Drupal\dept_mdash\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to display migration errors.
 *
 * @Block(
 *   id = "dept_mdash_error_summary",
 *   admin_label = @Translation("Mdash: Error Summary"),
 *   category = @Translation("mdash")
 * )
 */
class MdashErrorSummaryBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $msg_tables = [
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

    $header = [
      'data' => [
        'id' => t('ID'),
        'message' => t('Message'),
      ],
      'class' => ['mdash-highlight']
    ];

    foreach ($msg_tables as $table) {
      $results = $this->dbConn->query("SELECT * FROM migrate_message_$table")->fetchAll();
      $has_messages = count($results) > 0;

      $rows[$table] = [
        [
          'data' => Markup::create((($has_messages) ? '&#9940; ' : '&#9989; ') . $table),
          'colspan' => 2,
          'class' => ['mdash-header']
        ]
      ];

      if (count($results) > 0) {
        $rows[$table . '_header'] = $header;

        foreach ($results as $result) {
          $rows[$result->msgid] = [$result->msgid, $result->message];
        }
      }
    }

    $build['content'] = [
      '#type' => 'table',
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
