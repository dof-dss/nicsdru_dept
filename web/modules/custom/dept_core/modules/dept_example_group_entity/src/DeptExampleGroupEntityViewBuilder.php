<?php

namespace Drupal\dept_example_group_entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Provides a view controller for a dept example group content entity type.
 */
class DeptExampleGroupEntityViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode) {
    $build = parent::getBuildDefaults($entity, $view_mode);
    // The departmental example group content entity has no entity template.
    unset($build['#theme']);
    return $build;
  }

}
