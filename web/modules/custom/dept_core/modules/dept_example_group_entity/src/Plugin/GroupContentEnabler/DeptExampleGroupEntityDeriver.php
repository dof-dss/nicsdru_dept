<?php

namespace Drupal\dept_example_group_entity\Plugin\GroupContentEnabler;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Deriver for Dept example group entities.
 */
class DeptExampleGroupEntityDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives['dept_example_group_entity'] = [
      'entity_bundle' => 'dept_example_group_entity',
      'label' => t('Group entity (Dept example Group entity)'),
      'description' => t('Add dept example group entity content'),
    ] + $base_plugin_definition;

    return $this->derivatives;

  }

}
