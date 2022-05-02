<?php

namespace Drupal\dept_dev\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\dept_core\DepartmentManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a drupal 7 node link block.
 *
 * Displays a list of links to each original Drupal 7 site that the
 * current node is associated with.
 *
 * @Block(
 *   id = "dept_dev_drupal_7_node_link",
 *   admin_label = @Translation("Drupal 7 node link"),
 *   category = @Translation("Development")
 * )
 */
class Drupal7NodeLinkBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The department.manager service.
   *
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected $departmentManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConn;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The Config Factory Service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Constructs a new Drupal7NodeLinkBlock instance.
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
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   The department.manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory Service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DepartmentManager $department_manager, Connection $connection, RouteMatchInterface $route_match, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->departmentManager = $department_manager;
    $this->dbConn = $connection;
    $this->routeMatch = $route_match;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('department.manager'),
      $container->get('database'),
      $container->get('current_route_match'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $links = [];

    $node_source_link = $this->configFactory->get('dept_dev.settings')->get('node_source_link');

    if ($node_source_link) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $this->routeMatch->getParameter('node');
      if ($node instanceof NodeInterface) {
        $mapping_table = 'migrate_map_node_' . $node->bundle();
        // Check mapping table exists for the current bundle.
        if ($this->dbConn->schema()->tableExists($mapping_table)) {
          // Lookup Destination node using current nid and extract D7
          // nid and group.
          $query = $this->dbConn->select($mapping_table, 'mt')
            ->condition('destid1', $node->id(), '=');
          $query->addField('mt', 'sourceid2', 'd7nid');
          $query->addField('mt', 'sourceid3', 'domains');

          $result = $query->execute();
          $node_migration_data = $result->fetch();

          // Iterate all domains and generate links.
          $domains = explode('-', $node_migration_data->domains);
          $domain_mappings = $this->configFactory->get('dept_dev.settings')->get('node_source_domains');


          foreach ($domains as $domain) {
            // Iterate all domains if the source domain for the node is 0  as
            // this denotes that it belongs to all sites.
            if ($domain == 0) {
              foreach ($domain_mappings as $domain) {
                $node_link = $domain . '/node/' . $node_migration_data->d7nid;

                $links[] = [
                  '#title' => $domain . ' : ' . $node->label(),
                  '#type' => 'link',
                  '#url' => Url::fromUri($node_link),
                ];
              }
              break;
            }
            else {
              $node_link = $domain_mappings[$domain] . '/node/' . $node_migration_data->d7nid;
              $links[] = [
                '#title' => $domain_mappings[$domain] . ' : ' . $node->label(),
                '#type' => 'link',
                '#url' => Url::fromUri($node_link),
              ];
            }
          }
        }
      }
    }

    $build['d7_links'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $links,
      '#cache' => [
        'contexts' => ['url'],
      ],
    ];

    return $build;
  }

}
