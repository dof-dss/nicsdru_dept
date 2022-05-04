<?php

namespace Drupal\dept_mdash\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Migration Dashboard routes.
 */
class MdashContentController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Database\Connection $legacy_connection
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection, Connection $legacy_connection) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dbConn = $connection;
    $this->legacyConn = $legacy_connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('dept_migrate.database_d7'),
    );
  }

  /**
   * Builds the response.
   */
  public function build() {

    $block_manager = \Drupal::service('plugin.manager.block');
    $config = [];

    $plugin_block = $block_manager->createInstance('dept_mdash_content_summary', $config);
    $content_status_block = $plugin_block->build();

    $plugin_block = $block_manager->createInstance('dept_mdash_error_summary', $config);
    $error_status_block = $plugin_block->build();

    return [
      '#theme' => 'mdash_dashboard',
      '#content_status' => $content_status_block,
      '#error_status' => $error_status_block,
      '#attached' => [
        'library' => [
          'dept_mdash/dashboard',
        ],
      ],
    ];

  }

}
