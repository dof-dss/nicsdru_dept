<?php

namespace Drupal\dept_ui\Entity;

use Drupal\node\Entity\Node as NodeBase;

class Node extends NodeBase {

  /**
   * Gets the Group bundle of the entity.
   *
   * @return string
   *   The Group bundle of the entity.
   */
  public function groupBundle() {
    return 'group_node:' . $this->bundle();
  }

}
