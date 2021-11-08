<?php

namespace Drupal\dept_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "dept_ui_example",
 *   admin_label = @Translation("Example"),
 *   category = @Translation("User Interface")
 * )
 */
class ExampleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}
