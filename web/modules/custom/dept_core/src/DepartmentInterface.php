<?php

namespace Drupal\dept_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a department entity type.
 */
interface DepartmentInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {


  /**
   * Returns the weight among shortcuts with the same depth.
   *
   * @return int
   *   The shortcut weight.
   */
  public function getWeight();

  /**
   * Sets the weight among shortcuts with the same depth.
   *
   * @param int $weight
   *   The shortcut weight.
   *
   * @return $this
   *   The called shortcut entity.
   */
  public function setWeight($weight);


}
