<?php

namespace Drupal\dept_ui\Entity;

use Drupal\node\Entity\Node as NodeBase;

/**
 * Node entity which integrates with Group module.
 */
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

  /**
   * Returns the groups the node is published to.
   *
   * @return array
   *   An array of groups. Group ID as key and title as value.
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
