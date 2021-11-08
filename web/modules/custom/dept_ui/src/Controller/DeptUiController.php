<?php

namespace Drupal\dept_ui\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for User Interface routes.
 */
class DeptUiController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
