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
final class Drupal7NodeLinkBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly Connection $dbConn,
    private readonly RouteMatchInterface $routeMatch,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly DepartmentManager $departmentManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('current_route_match'),
      $container->get('config.factory'),
      $container->get('department.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $links = [];

    $node_source_link = (bool) $this->configFactory
      ->get('dept_dev.settings')
      ->get('node_source_link');

    if ($node_source_link) {
      $node = $this->routeMatch->getParameter('node');

      if ($node instanceof NodeInterface) {
        $mapping_table = 'migrate_map_node_' . $node->bundle();

        if ($this->dbConn->schema()->tableExists($mapping_table)) {
          $query = $this->dbConn->select($mapping_table, 'mt')
            ->condition('destid1', $node->id(), '=');
          $query->addField('mt', 'sourceid2', 'd7nid');

          $node_migration_data = $query->execute()->fetchObject();

          if ($node_migration_data && isset($node_migration_data->d7nid)) {
            $department = $this->departmentManager->getCurrentDepartment();

            if ($department) {
              $node_link = 'https://' . $department->hostname(TRUE) . '/node/' . $node_migration_data->d7nid;

              $links[] = [
                '#title' => $node->label(),
                '#type' => 'link',
                '#url' => Url::fromUri($node_link),
              ];
            }
          }
        }
      }
    }

    return [
      'd7_links' => [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => $links,
        '#cache' => [
          'contexts' => ['url'],
        ],
      ],
    ];
  }

}
