<?php

namespace Drupal\dept_example_group_entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a dept example group content entity type.
 */
interface DeptExampleGroupEntityInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the departmental example group content entity title.
   *
   * @return string
   *   Title of the departmental example group content entity.
   */
  public function getTitle();

  /**
   * Sets the departmental example group content entity title.
   *
   * @param string $title
   *   The departmental example group content entity title.
   *
   * @return \Drupal\dept_example_group_entity\DeptExampleGroupEntityInterface
   *   The called departmental example group content entity entity.
   */
  public function setTitle($title);

  /**
   * Gets the departmental example group content entity creation timestamp.
   *
   * @return int
   *   Creation timestamp of the departmental example group content entity.
   */
  public function getCreatedTime();

  /**
   * Sets the departmental example group content entity creation timestamp.
   *
   * @param int $timestamp
   *   The departmental example group content entity creation timestamp.
   *
   * @return \Drupal\dept_example_group_entity\DeptExampleGroupEntityInterface
   *   The called departmental example group content entity entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the departmental example group content entity status.
   *
   * @return bool
   *   TRUE if the departmental example group content entity is enabled,
   *   FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the departmental example group content entity status.
   *
   * @param bool $status
   *   TRUE to enable this departmental example group content entity,
   *   FALSE to disable.
   *
   * @return \Drupal\dept_example_group_entity\DeptExampleGroupEntityInterface
   *   The called departmental example group content entity entity.
   */
  public function setStatus($status);

}
