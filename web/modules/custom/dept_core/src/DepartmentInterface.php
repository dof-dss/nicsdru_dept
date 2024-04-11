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
   * Department name.
   *
   * @return string
   *   The Department name.
   */
  public function name();

  /**
   * The Department Domain object .
   *
   * @return \Drupal\domain\Entity\Domain
   *   The Domain object.
   */
  public function domain(): \Drupal\domain\Entity\Domain;

  /**
   * Returns the weight among shortcuts with the same depth.
   *
   * @return int
   *   The shortcut weight.
   */
  public function getWeight(): int;

  /**
   * Sets the weight among shortcuts with the same depth.
   *
   * @param int $weight
   *   The shortcut weight.
   *
   * @return $this
   *   The called shortcut entity.
   */
  public function setWeight($weight): static;

}
