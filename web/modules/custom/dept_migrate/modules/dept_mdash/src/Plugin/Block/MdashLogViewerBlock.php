<?php

namespace Drupal\dept_mdash\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to show migration log output.
 *
 * @Block(
 *   id = "dept_mdash_log_viewer",
 *   admin_label = @Translation("Mdash: Log Viewer"),
 *   category = @Translation("mdash")
 * )
 */
class MdashLogViewerBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var string
   * The contents of the log file.
   */
  protected $logContents;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, string $log_contents) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logContents = $log_contents;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('dept_migrate.last_migrate_output')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $log_contents = file_get_contents($this->logContents);

    if (!empty($log_contents)) {
      $build['content'] = [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $log_contents,
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
