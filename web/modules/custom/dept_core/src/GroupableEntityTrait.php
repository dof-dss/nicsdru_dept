<?php

namespace Drupal\dept_core;

/**
 * Provides methods to help integrate an entity into the Group module.
 */
trait GroupableEntityTrait {

  /**
   * {@inheritdoc}
   */
  public function groupBundle() {
    /** @var \Drupal\group\Plugin\GroupContentEnablerManager $group_content_enabler */
    $group_content_enabler = \Drupal::service('plugin.manager.group_content_enabler');
    $plugins = $group_content_enabler->getInstalled();

    foreach ($plugins as $plugin) {
      if ($this->bundle() === $plugin->getDerivativeId()) {
        return $plugin->getPluginId();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGroups() {
    $all_groups = \Drupal::service('entity_type.manager')->getStorage('group')->loadMultiple();
    $entity_groups = [];

    foreach ($all_groups as $group) {
      if ($this->groupBundle() != NULL) {
        if ($group->getContentByEntityId($this->groupBundle(), $this->id())) {
          $entity_groups[$group->id()] = $group->label();
        }
      }
    }

    return $entity_groups;
  }

}
