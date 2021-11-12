<?php

namespace Drupal\dept_node\Entity;

use Drupal\dept_core\GroupContentEntityInterface;
use Drupal\node\Entity\Node as NodeBase;

/**
 * Node entity which integrates with Group module.
 */
class Node extends NodeBase implements GroupContentEntityInterface{

  /**
   * {@inheritdoc}
   */
  public function groupBundle() {
    return 'group_node:' . $this->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getGroups() {
    $all_groups = $this->entityTypeManager()->getStorage('group')->loadMultiple();
    $node_groups = [];

    foreach ($all_groups as $group) {
      if ($group->getContentByEntityId($this->groupBundle(), $this->id())) {
        $node_groups[$group->id()] = $group->label();
      }
    }

    return $node_groups;
  }

}
