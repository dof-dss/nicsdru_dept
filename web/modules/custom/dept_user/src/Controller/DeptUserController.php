<?php

namespace Drupal\dept_user\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for departmental users routes.
 */
class DeptUserController extends ControllerBase {

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
