<?php

namespace Drupal\dept_mdash\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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

    $mapping = [
      'group_content_type_ef01e89809ca7' => 'actions',
      'group_content_type_9dbed154ced4f' => 'application',
      'group_content_type_8f3c8c40c5ced' => 'article',
      'group_content_type_729499773bd55' => 'case_study',
      'group_content_type_fb2d5fb87aade' => 'consultation',
      'group_content_type_806d1de5fafe5' => 'contact',
      'group_content_type_85765319814ca' => 'easychart',
      'group_content_type_674e4ffa7ff39' => 'entity queue',
      'group_content_type_671a55a120b42' => 'gallery',
      'group_content_type_34099e0cf683b' => 'global_page',
      'group_content_type_4206bea64afae' => 'heritage_site',
      'group_content_type_6061d9dc53978' => 'infogram',
      'group_content_type_1b4b1ed9339c4' => 'landing_page',
      'department_site-group_node-link' => 'link',
      'department_site-group_node-news' => 'news',
      'department_site-group_node-page' => 'page',
      'group_content_type_d17c35c98baa3' => 'profile',
      'group_content_type_85d66e53e8361' => 'project',
      'group_content_type_ec8e415306531' => 'protected_area',
      'group_content_type_d91f8322473a4' => 'publication',
      'group_content_type_9741084175ea2' => 'subtopic',
      'department_site-group_node-topic' => 'topic',
      'department_site-group_node-ual' => 'ual',
      'department_site-group_membership' => 'user',
      'group_content_type_0b612c56d0b26' => 'webform',
    ];

    $header = [
      'type' => [
        'data' => $this->t('Type'),
        'class' => ['mdash-header'],
      ],
      'total' => [
        'data' => $this->t('total'),
        'class' => ['mdash-header'],
      ],
    ];

    $results = $this->dbConn->query('SELECT type, COUNT(type) AS total FROM group_content GROUP BY type')->fetchAll();
    $rows = [];
    foreach ($results as $result) {
      $rows[] = [
        $mapping[$result->type] ?? 'unknown',
        $result->total,
      ];
    }

    $build['content'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#suffix' => $this->t('Shows the number of department associations for the type of content.')
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
