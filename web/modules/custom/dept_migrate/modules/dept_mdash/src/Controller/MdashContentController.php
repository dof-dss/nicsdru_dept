<?php

namespace Drupal\dept_mdash\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Migration Dashboard routes.
 */
class MdashContentController extends ControllerBase {

  /**
   * The block manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager service.
   */
  public function __construct(BlockManagerInterface $block_manager) {
    $this->blockManager = $block_manager;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
    );
  }

  /**
   * Builds the response.
   */
  public function build() {
    $plugin_block = $this->blockManager->createInstance('dept_mdash_content_summary', []);
    $content_summary_block = $plugin_block->build();

    $plugin_block = $this->blockManager->createInstance('dept_mdash_error_summary', []);
    $error_summary_block = $plugin_block->build();

    $plugin_block = $this->blockManager->createInstance('dept_mdash_relationship_summary', []);
    $relationship_summary_block = $plugin_block->build();

    return [
      '#theme' => 'mdash_dashboard',
      '#content_summary' => $content_summary_block,
      '#error_summary' => $error_summary_block,
      '#relationship_summary' => $relationship_summary_block,
      '#attached' => [
        'library' => [
          'dept_mdash/dashboard',
        ],
      ],
    ];

  }

}
