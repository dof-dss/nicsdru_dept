<?php

namespace Drupal\dept_example_group_entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Provides a view controller for a departmental example group content entity entity type.
 */
class DeptExampleGroupEntityViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode) {
    $build = parent::getBuildDefaults($entity, $view_mode);
    // The departmental example group content entity has no entity template itself.
    unset($build['#theme']);
    return $build;
  }

}
