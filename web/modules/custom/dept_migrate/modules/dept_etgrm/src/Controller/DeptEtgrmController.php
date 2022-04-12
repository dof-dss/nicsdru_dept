<?php

namespace Drupal\dept_etgrm\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Departmental Entity To Group Relationship Manager routes.
 */
class DeptEtgrmController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('Entity To Group Relationship Manager'),
    ];

    return $build;
  }

}
